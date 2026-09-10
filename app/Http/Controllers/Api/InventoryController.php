<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Inventory::with('currentHolder:id,first_name,last_name');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('current_status', $request->status);
        }

        $sortBy = $request->get('sortBy', 'name');
        $sortOrder = $request->get('sortOrder', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('perPage', 10);
        $inventory = $query->paginate($perPage);

        return response()->json($inventory);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:zimmet,rental',
            'unit' => 'nullable|string|max:50',
            'unit_price' => 'nullable|numeric|min:0',
            'serial_number' => 'nullable|string|max:100',
            'nfc_uid' => 'nullable|string|max:64',
            'daily_rate' => 'nullable|numeric|min:0',
            'purchase_cost' => 'nullable|numeric|min:0',
            'current_status' => 'nullable|in:available,in_use,maintenance,damaged,lost',
            'current_holder_id' => 'nullable|exists:personnel,id',
            'notes' => 'nullable|string',
        ]);

        $validated['current_status'] = $validated['current_status'] ?? 'available';

        $inventory = Inventory::create($validated);

        return response()->json($inventory, 201);
    }

    public function show(Inventory $inventory): JsonResponse
    {
        $inventory->load('currentHolder');

        // Proje kullanım geçmişi - hasar bilgileriyle birlikte
        $usageHistory = $inventory->projectDayUsages()
            ->with([
                'projectDay.project.customer',
                'assignedToPersonnel.personnel:id,first_name,last_name',
            ])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($usage) {
                return [
                    'id' => $usage->id,
                    'project_id' => $usage->projectDay->project->id,
                    'project_name' => $usage->projectDay->project->name,
                    'customer_name' => $usage->projectDay->project->customer->name ?? 'Bilinmiyor',
                    'date' => $usage->projectDay->date,
                    'assigned_to' => $usage->assignedToPersonnel?->personnel ? [
                        'id' => $usage->assignedToPersonnel->personnel->id,
                        'name' => $usage->assignedToPersonnel->personnel->first_name . ' ' . $usage->assignedToPersonnel->personnel->last_name,
                    ] : null,
                    'delivered_at' => $usage->delivered_at,
                    'returned_at' => $usage->returned_at,
                    'return_status' => $usage->return_status,
                    'damage_photo' => $usage->damage_photo,
                    'damage_description' => $usage->damage_description,
                ];
            });

        // Proje bazlı özet
        $projectSummary = $usageHistory->groupBy('project_id')
            ->map(function ($usages, $projectId) {
                $first = $usages->first();
                $dates = $usages->pluck('date')->sort();
                $damagedCount = $usages->where('return_status', 'damaged')->count();

                return [
                    'project_id' => $projectId,
                    'project_name' => $first['project_name'],
                    'customer_name' => $first['customer_name'],
                    'start_date' => $dates->first(),
                    'end_date' => $dates->last(),
                    'total_days' => $usages->count(),
                    'damaged_count' => $damagedCount,
                ];
            })
            ->values();

        // Hasar geçmişi - sadece hasarlı olanlar
        $damageHistory = $usageHistory->where('return_status', 'damaged')->values();

        // Zimmet geçmişi - şu anki holder ve önceki zimmetler
        $assignments = collect();
        if ($inventory->current_holder_id) {
            $assignments->push([
                'id' => 0,
                'personnel' => $inventory->currentHolder,
                'assigned_at' => $inventory->updated_at,
                'returned_at' => null,
            ]);
        }

        return response()->json([
            'id' => $inventory->id,
            'name' => $inventory->name,
            'type' => $inventory->type,
            'unit' => $inventory->unit,
            'unit_price' => $inventory->unit_price,
            'serial_number' => $inventory->serial_number,
            'daily_rate' => $inventory->daily_rate,
            'purchase_cost' => $inventory->purchase_cost,
            'current_status' => $inventory->current_status,
            'current_holder' => $inventory->currentHolder,
            'notes' => $inventory->notes,
            'assignments' => $assignments,
            'project_summary' => $projectSummary,
            'usage_history' => $usageHistory,
            'damage_history' => $damageHistory,
            'stats' => [
                'total_usages' => $usageHistory->count(),
                'total_damage_count' => $damageHistory->count(),
                'total_projects' => $projectSummary->count(),
            ],
        ]);
    }

    public function update(Request $request, Inventory $inventory): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:zimmet,rental',
            'unit' => 'nullable|string|max:50',
            'unit_price' => 'nullable|numeric|min:0',
            'serial_number' => 'nullable|string|max:100',
            'nfc_uid' => 'nullable|string|max:64',
            'daily_rate' => 'nullable|numeric|min:0',
            'purchase_cost' => 'nullable|numeric|min:0',
            'current_status' => 'required|in:available,in_use,maintenance,damaged,lost',
            'current_holder_id' => 'nullable|exists:personnel,id',
            'notes' => 'nullable|string',
        ]);

        $inventory->update($validated);

        return response()->json($inventory);
    }

    public function destroy(Inventory $inventory): JsonResponse
    {
        // Aktif kullanımda mı kontrol et
        if ($inventory->current_status === 'in_use') {
            return response()->json([
                'message' => 'Bu envanter su anda kullanimda. Once iade alinmalidir.'
            ], 422);
        }

        $inventory->delete();
        return response()->json(['message' => 'Envanter silindi']);
    }

    public function all(Request $request): JsonResponse
    {
        $query = Inventory::where('current_status', 'available');

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $inventory = $query->orderBy('name')
            ->get(['id', 'name', 'type', 'serial_number', 'daily_rate']);

        return response()->json($inventory);
    }

    /**
     * Zimmet ata
     */
    public function assignToPersonnel(Request $request, Inventory $inventory): JsonResponse
    {
        $validated = $request->validate([
            'personnel_id' => 'required|exists:personnel,id',
        ]);

        if (!$inventory->isAvailable()) {
            return response()->json([
                'message' => 'Bu envanter musait degil.'
            ], 422);
        }

        $inventory->update([
            'current_holder_id' => $validated['personnel_id'],
            'current_status' => 'in_use',
        ]);

        return response()->json($inventory->load('currentHolder'));
    }

    /**
     * Zimmet al
     */
    public function returnFromPersonnel(Inventory $inventory): JsonResponse
    {
        if ($inventory->current_status !== 'in_use') {
            return response()->json([
                'message' => 'Bu envanter zaten iade edilmis.'
            ], 422);
        }

        $inventory->update([
            'current_holder_id' => null,
            'current_status' => 'available',
        ]);

        return response()->json($inventory);
    }
}
