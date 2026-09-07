<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectDay;
use App\Models\ProjectDayPersonnel;
use App\Models\ProjectDayInventory;
use App\Models\Personnel;
use App\Models\Inventory;
use App\Models\ProjectExpense;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProjectDayController extends Controller
{
    /**
     * Günün detaylarını getir
     */
    public function show(ProjectDay $projectDay): JsonResponse
    {
        $projectDay->load([
            'project:id,name,customer_id,status',
            'project.customer:id,name',
            'supervisor:id,first_name,last_name',
            'personnelAssignments.personnel:id,first_name,last_name,group_id,default_wage,phone',
            'personnelAssignments.personnel.group:id,name,commission_type,commission_value',
            'inventoryAssignments.inventory:id,name,type,serial_number,daily_rate',
            'expenses',
        ]);

        return response()->json($projectDay);
    }

    /**
     * Günü güncelle (supervisor, notlar vs.)
     */
    public function update(Request $request, ProjectDay $projectDay): JsonResponse
    {
        $validated = $request->validate([
            'supervisor_id' => 'nullable|exists:personnel,id',
            'notes' => 'nullable|string',
        ]);

        $projectDay->update($validated);

        return response()->json($projectDay->load('supervisor:id,first_name,last_name'));
    }

    /**
     * Güne personel ata
     */
    public function assignPersonnel(Request $request, ProjectDay $projectDay): JsonResponse
    {
        $validated = $request->validate([
            'personnel_id' => 'required|exists:personnel,id',
            'daily_wage' => 'nullable|numeric|min:0',
            'zone' => 'nullable|string|max:100',
        ]);

        // Zaten atanmış mı kontrol et
        if ($projectDay->personnelAssignments()->where('personnel_id', $validated['personnel_id'])->exists()) {
            return response()->json([
                'message' => 'Bu personel zaten bu gune atanmis.'
            ], 422);
        }

        // Varsayılan ücreti al
        if (!isset($validated['daily_wage'])) {
            $personnel = Personnel::find($validated['personnel_id']);
            $validated['daily_wage'] = $personnel->default_wage;
        }

        $assignment = $projectDay->personnelAssignments()->create($validated);
        $assignment->load('personnel:id,first_name,last_name,group_id,default_wage', 'personnel.group:id,name');

        return response()->json($assignment, 201);
    }

    /**
     * Personel atamasını kaldır
     */
    public function removePersonnel(ProjectDay $projectDay, ProjectDayPersonnel $assignment): JsonResponse
    {
        // Check-in yapılmış mı?
        if ($assignment->check_in_time) {
            return response()->json([
                'message' => 'Check-in yapilmis personel atamalari kaldirilamaz.'
            ], 422);
        }

        $assignment->delete();
        return response()->json(['message' => 'Atama kaldirildi']);
    }

    /**
     * Personel atamasını güncelle
     */
    public function updatePersonnelAssignment(Request $request, ProjectDay $projectDay, ProjectDayPersonnel $assignment): JsonResponse
    {
        $validated = $request->validate([
            'daily_wage' => 'nullable|numeric|min:0',
            'zone' => 'nullable|string|max:100',
            'check_in_time' => 'nullable|date',
            'check_out_time' => 'nullable|date',
            'payment_status' => 'nullable|in:pending,partial,paid',
            'payment_method' => 'nullable|in:cash,bank,mixed',
            'payment_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $assignment->update($validated);

        return response()->json($assignment->load('personnel:id,first_name,last_name'));
    }

    /**
     * Güne envanter ata
     */
    public function assignInventory(Request $request, ProjectDay $projectDay): JsonResponse
    {
        $validated = $request->validate([
            'inventory_id' => 'required|exists:inventory,id',
            'quantity' => 'nullable|integer|min:1',
        ]);

        $validated['quantity'] = $validated['quantity'] ?? 1;

        // Envanter müsait mi kontrol et
        $inventory = Inventory::find($validated['inventory_id']);
        if ($inventory->current_status !== 'available') {
            return response()->json([
                'message' => 'Bu envanter su anda musait degil.'
            ], 422);
        }

        // Zaten atanmış mı kontrol et
        if ($projectDay->inventoryAssignments()->where('inventory_id', $validated['inventory_id'])->exists()) {
            return response()->json([
                'message' => 'Bu envanter zaten bu gune atanmis.'
            ], 422);
        }

        $assignment = $projectDay->inventoryAssignments()->create($validated);
        $assignment->load('inventory:id,name,type,serial_number,daily_rate');

        return response()->json($assignment, 201);
    }

    /**
     * Envanter atamasını kaldır
     */
    public function removeInventory(ProjectDay $projectDay, ProjectDayInventory $assignment): JsonResponse
    {
        // Teslim edilmiş mi?
        if ($assignment->delivered_at) {
            return response()->json([
                'message' => 'Teslim edilmis envanter atamalari kaldirilamaz.'
            ], 422);
        }

        $assignment->delete();
        return response()->json(['message' => 'Atama kaldirildi']);
    }

    /**
     * Envanteri teslim et
     */
    public function deliverInventory(ProjectDay $projectDay, ProjectDayInventory $assignment): JsonResponse
    {
        // Zaten teslim edilmiş mi?
        if ($assignment->delivered_at) {
            return response()->json([
                'message' => 'Bu envanter zaten teslim edilmis.'
            ], 422);
        }

        $assignment->update([
            'delivered_at' => now(),
            'delivered_by' => auth()->id(),
        ]);

        $assignment->load('inventory:id,name,type,serial_number,daily_rate');

        return response()->json($assignment);
    }

    /**
     * Toplu personel ataması
     */
    public function bulkAssignPersonnel(Request $request, ProjectDay $projectDay): JsonResponse
    {
        $validated = $request->validate([
            'personnel_ids' => 'required|array',
            'personnel_ids.*' => 'exists:personnel,id',
            'zone' => 'nullable|string|max:100',
        ]);

        $created = [];
        $skipped = [];

        foreach ($validated['personnel_ids'] as $personnelId) {
            // Zaten atanmış mı kontrol et
            if ($projectDay->personnelAssignments()->where('personnel_id', $personnelId)->exists()) {
                $skipped[] = $personnelId;
                continue;
            }

            $personnel = Personnel::find($personnelId);
            $assignment = $projectDay->personnelAssignments()->create([
                'personnel_id' => $personnelId,
                'daily_wage' => $personnel->default_wage,
                'zone' => $validated['zone'] ?? null,
            ]);
            $created[] = $assignment->id;
        }

        return response()->json([
            'message' => count($created) . ' personel atandi, ' . count($skipped) . ' zaten atanmis.',
            'created_count' => count($created),
            'skipped_count' => count($skipped),
        ]);
    }

    /**
     * Önceki günden kopyala
     */
    public function copyFromPreviousDay(ProjectDay $projectDay): JsonResponse
    {
        // Önceki günü bul
        $previousDay = ProjectDay::where('project_id', $projectDay->project_id)
            ->where('date', '<', $projectDay->date)
            ->orderBy('date', 'desc')
            ->first();

        if (!$previousDay) {
            return response()->json([
                'message' => 'Onceki gun bulunamadi.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $copiedPersonnel = 0;
            $copiedInventory = 0;

            // Personel atamalarını kopyala
            foreach ($previousDay->personnelAssignments as $pa) {
                if (!$projectDay->personnelAssignments()->where('personnel_id', $pa->personnel_id)->exists()) {
                    $projectDay->personnelAssignments()->create([
                        'personnel_id' => $pa->personnel_id,
                        'daily_wage' => $pa->daily_wage,
                        'zone' => $pa->zone,
                    ]);
                    $copiedPersonnel++;
                }
            }

            // Envanter atamalarını kopyala
            foreach ($previousDay->inventoryAssignments as $ia) {
                if (!$projectDay->inventoryAssignments()->where('inventory_id', $ia->inventory_id)->exists()) {
                    $projectDay->inventoryAssignments()->create([
                        'inventory_id' => $ia->inventory_id,
                        'quantity' => $ia->quantity,
                    ]);
                    $copiedInventory++;
                }
            }

            // Supervisor'ı kopyala
            if ($previousDay->supervisor_id && !$projectDay->supervisor_id) {
                $projectDay->update(['supervisor_id' => $previousDay->supervisor_id]);
            }

            DB::commit();

            return response()->json([
                'message' => "Onceki gunden kopyalandi: {$copiedPersonnel} personel, {$copiedInventory} envanter",
                'copied_personnel' => $copiedPersonnel,
                'copied_inventory' => $copiedInventory,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Kopyalama basarisiz: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Ekstra masraf ekle
     */
    public function addExpense(Request $request, ProjectDay $projectDay): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'category' => 'nullable|string|in:food,transport,material,accommodation,other',
            'receipt_photo' => 'nullable|string',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['status'] = 'pending'; // Onay bekliyor

        $expense = $projectDay->expenses()->create($validated);

        return response()->json($expense, 201);
    }

    /**
     * Masraf sil (sadece onaylanmamış masraflar silinebilir)
     */
    public function deleteExpense(ProjectDay $projectDay, ProjectExpense $expense): JsonResponse
    {
        // Giderin bu güne ait olup olmadığını kontrol et
        if ($expense->project_day_id !== $projectDay->id) {
            return response()->json(['message' => 'Bu gider bu güne ait değil.'], 403);
        }

        // Sadece onaylanmamış giderler silinebilir
        if ($expense->status !== 'pending') {
            return response()->json(['message' => 'Onaylanmış veya reddedilmiş giderler silinemez.'], 422);
        }

        $expense->delete();

        return response()->json(['message' => 'Gider silindi.']);
    }
}
