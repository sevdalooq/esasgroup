<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryRental;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Kiralık envanter kayıtları: ne zaman, ne kadar kiralandı, ne zaman iade edilmeli, edildi mi */
class InventoryRentalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = InventoryRental::with(['project:id,name,customer_id,start_date,end_date', 'project.customer:id,name', 'projectDay:id,date', 'createdBy:id,name', 'returnedBy:id,name'])
            ->orderByRaw('returned_at IS NULL DESC')
            ->orderBy('due_date')
            ->latest('id');

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        switch ($request->get('status')) {
            case 'open':
                $query->whereNull('returned_at');
                break;
            case 'overdue':
                $query->whereNull('returned_at')->whereDate('due_date', '<', today());
                break;
            case 'returned':
                $query->whereNotNull('returned_at');
                break;
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('item_name', 'like', "%{$s}%")->orWhere('supplier', 'like', "%{$s}%"));
        }

        $items = $query->limit(500)->get();

        return response()->json([
            'data' => $items,
            'summary' => [
                'open' => InventoryRental::open()->count(),
                'overdue' => InventoryRental::open()->whereDate('due_date', '<', today())->count(),
                'open_quantity' => (int) InventoryRental::open()->sum('quantity'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);
        $data['created_by'] = $request->user()->id;
        $data['total_cost'] = $data['total_cost'] ?? $this->computeTotal($data);

        $rental = InventoryRental::create($data);

        return response()->json($rental->load(['project:id,name', 'projectDay:id,date', 'createdBy:id,name']), 201);
    }

    public function update(Request $request, InventoryRental $rental): JsonResponse
    {
        $data = $this->validateData($request, true);
        if (array_key_exists('daily_cost', $data) || array_key_exists('quantity', $data) || array_key_exists('due_date', $data)) {
            $merged = array_merge($rental->only(['quantity', 'daily_cost', 'rented_at', 'due_date', 'total_cost']), $data);
            $data['total_cost'] = $data['total_cost'] ?? $this->computeTotal($merged);
        }
        $rental->update($data);

        return response()->json($rental->fresh(['project:id,name', 'projectDay:id,date', 'createdBy:id,name', 'returnedBy:id,name']));
    }

    /** İade edildi olarak işaretle */
    public function markReturned(Request $request, InventoryRental $rental): JsonResponse
    {
        $data = $request->validate([
            'returned_at' => 'nullable|date',
            'return_notes' => 'nullable|string|max:2000',
        ]);

        $rental->update([
            'returned_at' => $data['returned_at'] ?? now(),
            'return_notes' => $data['return_notes'] ?? null,
            'returned_by' => $request->user()->id,
        ]);

        return response()->json($rental->fresh(['project:id,name', 'projectDay:id,date', 'createdBy:id,name', 'returnedBy:id,name']));
    }

    /** İadeyi geri al */
    public function reopen(InventoryRental $rental): JsonResponse
    {
        $rental->update(['returned_at' => null, 'returned_by' => null, 'return_notes' => null]);

        return response()->json($rental->fresh(['project:id,name', 'projectDay:id,date']));
    }

    public function destroy(InventoryRental $rental): JsonResponse
    {
        $rental->delete();

        return response()->json(['message' => 'Kiralama kaydı silindi']);
    }

    private function validateData(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes|' : '';

        return $request->validate([
            'project_id' => $req.'required|exists:projects,id',
            'project_day_id' => 'nullable|exists:project_days,id',
            'item_name' => $req.'required|string|max:255',
            'quantity' => $req.'required|integer|min:1',
            'supplier' => 'nullable|string|max:255',
            'supplier_phone' => 'nullable|string|max:50',
            'rented_at' => $req.'required|date',
            'due_date' => 'nullable|date|after_or_equal:rented_at',
            'daily_cost' => 'nullable|numeric|min:0',
            'total_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);
    }

    private function computeTotal(array $data): ?float
    {
        if (empty($data['daily_cost'])) {
            return $data['total_cost'] ?? null;
        }
        $days = 1;
        if (!empty($data['rented_at']) && !empty($data['due_date'])) {
            $days = max(1, \Carbon\Carbon::parse($data['rented_at'])->diffInDays(\Carbon\Carbon::parse($data['due_date'])) + 1);
        }

        return round((float) $data['daily_cost'] * (int) ($data['quantity'] ?? 1) * $days, 2);
    }
}
