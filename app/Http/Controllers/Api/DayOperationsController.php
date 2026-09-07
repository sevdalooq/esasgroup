<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectDay;
use App\Models\ProjectDayPersonnel;
use App\Models\ProjectDayInventory;
use App\Models\ZoneOption;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\DayOperationService;

class DayOperationsController extends Controller
{
    public function __construct(private readonly DayOperationService $dayOps)
    {
    }

    /**
     * Gün başlatma için gerekli verileri getir
     */
    public function getStartDayData(ProjectDay $projectDay): JsonResponse
    {
        $projectDay->load([
            'project:id,name,status',
            'personnelAssignments.personnel:id,first_name,last_name,phone,group_id,default_wage',
            'personnelAssignments.personnel.group:id,name,commission_type,commission_value',
            'personnelAssignments.assignedInventory.inventory:id,name,type,serial_number,daily_rate',
            'inventoryAssignments.inventory:id,name,type,serial_number,daily_rate',
        ]);

        // Teslim edilmemiş zimmetler (sadece zimmet türündekiler)
        $undeliveredInventory = $projectDay->inventoryAssignments()
            ->whereNull('delivered_at')
            ->whereHas('inventory', fn($q) => $q->where('type', 'zimmet'))
            ->with('inventory:id,name,type,serial_number,daily_rate')
            ->get();

        // Zaten teslim edilmiş zimmetler (personel bazında)
        $deliveredInventory = $projectDay->inventoryAssignments()
            ->whereNotNull('delivered_at')
            ->whereNotNull('assigned_to_personnel_id')
            ->whereHas('inventory', fn($q) => $q->where('type', 'zimmet'))
            ->with(['inventory:id,name,type,serial_number,daily_rate', 'assignedToPersonnel.personnel:id,first_name,last_name'])
            ->get();

        // Bölge önerileri
        $zoneSuggestions = ZoneOption::getSuggestions($projectDay->project_id);

        return response()->json([
            'day' => $projectDay,
            'undelivered_inventory' => $undeliveredInventory,
            'delivered_inventory' => $deliveredInventory,
            'zone_suggestions' => $zoneSuggestions,
        ]);
    }

    /**
     * Personel check-in işlemi (wizard adım 2-3)
     */
    public function checkInPersonnel(Request $request, ProjectDay $projectDay, ProjectDayPersonnel $assignment): JsonResponse
    {
        $validated = $request->validate([
            'zone' => 'nullable|string|max:100',
            'is_checked' => 'required|boolean',
            'inventory_ids' => 'nullable|array',
            'inventory_ids.*' => 'exists:project_day_inventory,id',
        ]);

        DB::beginTransaction();
        try {
            // Personeli güncelle
            $assignment->update([
                'check_in_time' => now(),
                'zone' => $validated['zone'] ?? $assignment->zone,
                'is_checked' => $validated['is_checked'],
            ]);

            // Bölge kaydet
            if (!empty($validated['zone'])) {
                ZoneOption::addOrUpdate($projectDay->project_id, $validated['zone']);
            }

            // Seçilen zimmetleri personele ata
            if (!empty($validated['inventory_ids'])) {
                ProjectDayInventory::whereIn('id', $validated['inventory_ids'])
                    ->where('project_day_id', $projectDay->id)
                    ->update([
                        'assigned_to_personnel_id' => $assignment->id,
                        'delivered_at' => now(),
                        'delivered_by' => auth()->id(),
                    ]);
            }

            DB::commit();

            $assignment->load([
                'personnel:id,first_name,last_name,phone,group_id',
                'personnel.group:id,name',
                'assignedInventory.inventory:id,name,type,serial_number',
            ]);

            return response()->json($assignment);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Check-in basarisiz: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Günü başlat (tüm check-in'ler tamamlandıktan sonra)
     */
    public function startDay(Request $request, ProjectDay $projectDay): JsonResponse
    {
        $validated = $request->validate([
            'start_photo' => 'required|string', // Base64 veya dosya yolu
        ]);

        $photoPath = $this->dayOps->savePhoto($validated['start_photo'], 'day-photos/start');
        $this->dayOps->start($projectDay, $photoPath);

        $projectDay->load([
            'personnelAssignments.personnel:id,first_name,last_name',
            'inventoryAssignments.inventory:id,name,type',
        ]);

        return response()->json([
            'message' => 'Gün başlatıldı',
            'day' => $projectDay,
        ]);
    }

    /**
     * Gün bitirme için gerekli verileri getir
     */
    public function getEndDayData(ProjectDay $projectDay): JsonResponse
    {
        $projectDay->load([
            'project:id,name,status',
            'personnelAssignments.personnel:id,first_name,last_name,phone,group_id,default_wage',
            'personnelAssignments.personnel.group:id,name',
            'personnelAssignments.assignedInventory.inventory:id,name,type,serial_number',
            'inventoryAssignments.inventory:id,name,type,serial_number,daily_rate',
            'inventoryAssignments.assignedToPersonnel.personnel:id,first_name,last_name',
        ]);

        return response()->json([
            'day' => $projectDay,
        ]);
    }

    /**
     * Personel check-out işlemi (çıkış, mesai, zimmet iade, ödeme)
     */
    public function checkOutPersonnel(Request $request, ProjectDay $projectDay, ProjectDayPersonnel $assignment): JsonResponse
    {
        $validated = $request->validate([
            'check_out_time' => 'nullable|date',
            'overtime_hours' => 'nullable|numeric|min:0|max:12',
            'overtime_rate' => 'nullable|numeric|min:0',
            'payment_status' => 'required|in:pending,partial,paid',
            'payment_method' => 'nullable|in:cash,bank,mixed',
            'payment_amount' => 'nullable|numeric|min:0',
            'inventory_returns' => 'nullable|array',
            'inventory_returns.*.id' => 'required|exists:project_day_inventory,id',
            'inventory_returns.*.return_status' => 'required|in:returned,damaged',
            'inventory_returns.*.damage_photo' => 'nullable|string',
            'inventory_returns.*.damage_description' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Mesai hesapla
            $overtimeHours = $validated['overtime_hours'] ?? 0;
            $overtimeRate = $validated['overtime_rate'] ?? $assignment->suggested_overtime_rate;
            $totalEarnings = (float) $assignment->daily_wage + ($overtimeHours * $overtimeRate);

            // Personeli güncelle
            $assignment->update([
                'check_out_time' => $validated['check_out_time'] ?? now(),
                'overtime_hours' => $overtimeHours,
                'overtime_rate' => $overtimeRate,
                'total_earnings' => $totalEarnings,
                'payment_status' => $validated['payment_status'],
                'payment_method' => $validated['payment_method'],
                'payment_amount' => $validated['payment_amount'] ?? 0,
            ]);

            // Zimmet iadelerini işle
            if (!empty($validated['inventory_returns'])) {
                foreach ($validated['inventory_returns'] as $return) {
                    $inventory = ProjectDayInventory::find($return['id']);

                    $updateData = [
                        'returned_at' => now(),
                        'returned_by' => auth()->id(),
                        'return_status' => $return['return_status'],
                    ];

                    if ($return['return_status'] === 'damaged') {
                        if (!empty($return['damage_photo'])) {
                            $updateData['damage_photo'] = $this->savePhoto($return['damage_photo'], 'damage-photos');
                        }
                        $updateData['damage_description'] = $return['damage_description'] ?? null;
                    }

                    $inventory->update($updateData);
                }
            }

            DB::commit();

            $assignment->load([
                'personnel:id,first_name,last_name',
                'assignedInventory.inventory:id,name,type',
            ]);

            return response()->json($assignment);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Check-out basarisiz: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Günü bitir (tüm check-out'lar tamamlandıktan sonra)
     */
    public function endDay(Request $request, ProjectDay $projectDay): JsonResponse
    {
        $validated = $request->validate([
            'end_photo' => 'required|string',
        ]);

        try {
            $photoPath = $this->dayOps->savePhoto($validated['end_photo'], 'day-photos/end');
            $result = $this->dayOps->end($projectDay, $photoPath);

            return response()->json([
                'message' => 'Gün kapatıldı',
                'day' => $result['day'],
                'summary' => $result['summary'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gün kapatma başarısız: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Bölge önerileri getir
     */
    public function getZoneSuggestions(Request $request): JsonResponse
    {
        $projectId = $request->get('project_id');
        $search = $request->get('q');

        $suggestions = ZoneOption::getSuggestions($projectId, $search);

        return response()->json($suggestions);
    }

    /**
     * Fotoğraf kaydet (base64 veya dosya) – DayOperationService'e devredildi
     */
    private function savePhoto(string $photo, string $folder): string
    {
        return $this->dayOps->savePhoto($photo, $folder) ?? $photo;
    }

    /**
     * Fotoğraf yükle (form-data)
     */
    public function uploadPhoto(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'photo' => 'required|image|max:5120', // Max 5MB
            'folder' => 'nullable|string',
        ]);

        $folder = $validated['folder'] ?? 'uploads';
        $path = $request->file('photo')->store($folder, 'public');

        return response()->json([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    /**
     * Zimmet hasarı bildir
     */
    public function reportDamage(Request $request, ProjectDayInventory $inventory): JsonResponse
    {
        $validated = $request->validate([
            'damage_photo' => 'required|string',
            'damage_description' => 'required|string|max:500',
        ]);

        $photoPath = $this->savePhoto($validated['damage_photo'], 'damage-photos');

        $inventory->update([
            'return_status' => 'damaged',
            'damage_photo' => $photoPath,
            'damage_description' => $validated['damage_description'],
            'returned_at' => now(),
            'returned_by' => auth()->id(),
        ]);

        return response()->json($inventory);
    }
}
