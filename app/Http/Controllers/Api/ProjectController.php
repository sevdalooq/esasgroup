<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectDay;
use App\Services\AccountingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Project::with(['customer:id,name', 'days']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $sortBy = $request->get('sortBy', 'start_date');
        $sortOrder = $request->get('sortOrder', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('perPage', 10);
        $projects = $query->paginate($perPage);

        // Her proje için özet bilgileri ekle
        $projects->getCollection()->transform(function ($project) {
            $project->total_days = $project->days->count();
            $project->personnel_count = $project->days->sum(function ($day) {
                return $day->personnelAssignments()->count();
            });
            return $project;
        });

        return response()->json($projects);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'account_id' => 'nullable|exists:accounts,id',
            'name' => 'required|string|max:255',
            'offer_number' => 'nullable|string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'offer_price' => 'nullable|numeric|min:0',
            'delivery_type' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'venue_address' => 'nullable|string|max:255',
            'venue_lat' => 'nullable|numeric|between:-90,90',
            'venue_lng' => 'nullable|numeric|between:-180,180',
        ]);

        DB::beginTransaction();
        try {
            // Teklif numarası otomatik oluştur
            if (empty($validated['offer_number'])) {
                $validated['offer_number'] = Project::generateOfferNumber();
            }

            // Proje her zaman taslak olarak başlar
            $validated['status'] = 'draft';

            $project = Project::create($validated);

            // Başlangıç ve bitiş tarihi arasındaki günleri oluştur
            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);

            while ($startDate->lte($endDate)) {
                $project->days()->create([
                    'date' => $startDate->format('Y-m-d'),
                    'status' => 'pending',
                ]);
                $startDate->addDay();
            }

            DB::commit();
            return response()->json($project->load(['customer', 'days']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Proje olusturulamadi: ' . $e->getMessage()], 500);
        }
    }

    public function show(Project $project): JsonResponse
    {
        $project->load([
            'customer',
            'days' => function ($query) {
                $query->orderBy('date');
            },
            'days.supervisor:id,name,phone',
            'days.personnelAssignments.personnel:id,first_name,last_name,group_id,default_wage',
            'days.personnelAssignments.personnel.group:id,name',
            'days.inventoryAssignments.inventory:id,name,type,serial_number,daily_rate',
            'days.expenses',
        ]);

        // Özet bilgiler
        $project->summary = [
            'total_days' => $project->days->count(),
            'total_personnel_assignments' => $project->days->sum(fn($d) => $d->personnelAssignments->count()),
            'total_inventory_assignments' => $project->days->sum(fn($d) => $d->inventoryAssignments->count()),
            'total_expenses' => $project->days->sum(fn($d) => $d->expenses->sum('amount')),
            'estimated_personnel_cost' => $project->days->sum(fn($d) => $d->personnelAssignments->sum('daily_wage')),
        ];

        return response()->json($project);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        // Muhasebeleştirilmiş projeler düzenlenemez
        if ($project->isFinalized()) {
            return response()->json([
                'message' => 'Muhasebelestirilmis projeler duzenlenemez.'
            ], 422);
        }

        // Temel kurallar - her durumda güncellenebilir
        $rules = [
            'customer_id' => 'required|exists:customers,id',
            'account_id' => 'nullable|exists:accounts,id',
            'name' => 'required|string|max:255',
            'offer_number' => 'nullable|string|max:50',
            'delivery_type' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'venue_address' => 'nullable|string|max:255',
            'venue_lat' => 'nullable|numeric|between:-90,90',
            'venue_lng' => 'nullable|numeric|between:-180,180',
            'offer_price' => 'nullable|numeric|min:0',
        ];

        // Sadece draft ve pending durumlarında tarih değişikliği yapılabilir
        if (in_array($project->status, ['draft', 'pending'])) {
            $rules['start_date'] = 'required|date';
            $rules['end_date'] = 'required|date|after_or_equal:start_date';
        }

        $validated = $request->validate($rules);

        DB::beginTransaction();
        try {
            // Tarih değişikliği varsa günleri yeniden oluştur
            if (isset($validated['start_date']) && isset($validated['end_date'])) {
                $oldStart = $project->start_date;
                $oldEnd = $project->end_date;
                $newStart = Carbon::parse($validated['start_date']);
                $newEnd = Carbon::parse($validated['end_date']);

                if (!$oldStart->eq($newStart) || !$oldEnd->eq($newEnd)) {
                    // Atanmamış günleri sil, yeni günleri ekle
                    $project->days()->whereDoesntHave('personnelAssignments')
                        ->whereDoesntHave('inventoryAssignments')
                        ->delete();

                    // Eksik günleri ekle
                    $existingDates = $project->days()->pluck('date')->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))->toArray();

                    $currentDate = $newStart->copy();
                    while ($currentDate->lte($newEnd)) {
                        $dateStr = $currentDate->format('Y-m-d');
                        if (!in_array($dateStr, $existingDates)) {
                            $project->days()->create([
                                'date' => $dateStr,
                                'status' => 'pending',
                            ]);
                        }
                        $currentDate->addDay();
                    }
                }
            }

            $project->update($validated);
            DB::commit();

            return response()->json($project->load(['customer', 'days']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Guncelleme basarisiz: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Project $project): JsonResponse
    {
        // Sadece draft durumundaki projeler silinebilir
        if ($project->status !== 'draft') {
            return response()->json([
                'message' => 'Sadece taslak durumundaki projeler silinebilir.'
            ], 422);
        }

        $project->delete();
        return response()->json(['message' => 'Proje silindi']);
    }

    /**
     * Proje durumunu değiştir
     *
     * Durum Akışı:
     * - draft (Taslak) -> pending, cancelled
     * - pending (Onay Bekliyor) -> draft, approved (sadece yönetici), cancelled (sadece yönetici)
     * - approved (Onaylandı) -> active, cancelled (sadece yönetici)
     * - active (Aktif) -> completed (sadece yönetici), cancelled (sadece yönetici)
     * - completed (Tamamlandı) -> iptal edilemez
     * - cancelled (İptal) -> iptal edilemez
     */
    public function updateStatus(Request $request, Project $project): JsonResponse
    {
        // Muhasebeleştirilmiş projelerin durumu değiştirilemez
        if ($project->isFinalized()) {
            return response()->json([
                'message' => 'Muhasebeleştirilmiş projelerin durumu değiştirilemez.'
            ], 422);
        }

        $validated = $request->validate([
            'status' => 'required|in:draft,pending,approved,active,completed,cancelled',
        ]);

        $newStatus = $validated['status'];
        $user = $request->user();

        // Yönetici yetkisi gerektiren durumlar
        $adminOnlyStatuses = ['approved', 'completed', 'cancelled'];

        if (in_array($newStatus, $adminOnlyStatuses)) {
            // Kullanıcının projects.approve yetkisi var mı kontrol et
            if (!$user->hasPermissionTo('projects.approve')) {
                return response()->json([
                    'message' => 'Bu duruma gecis icin yonetici yetkisi gereklidir.'
                ], 403);
            }
        }

        // Durum geçiş kuralları
        $allowedTransitions = [
            'draft' => ['pending', 'cancelled'],
            'pending' => ['draft', 'approved', 'cancelled'],
            'approved' => ['active', 'cancelled'],
            'active' => ['completed', 'cancelled'],
            'completed' => [], // Tamamlanmış projeler değiştirilemez
            'cancelled' => [], // İptal edilmiş projeler değiştirilemez
        ];

        if (!in_array($newStatus, $allowedTransitions[$project->status] ?? [])) {
            return response()->json([
                'message' => "'{$project->status}' durumundan '{$newStatus}' durumuna gecis yapilamaz."
            ], 422);
        }

        $updateData = ['status' => $newStatus];

        // Onay bilgilerini kaydet
        if ($newStatus === 'approved' && !$project->approved_at) {
            $updateData['approved_at'] = now();
            $updateData['approved_by'] = $user->id;
        }

        $project->update($updateData);

        return response()->json($project->load('customer'));
    }

    /**
     * Projeyi onayla (yönetici onayı)
     * pending -> approved
     */
    public function approve(Request $request, Project $project): JsonResponse
    {
        // Zaten onaylanmış mı kontrol et
        if ($project->isApproved()) {
            return response()->json([
                'message' => 'Bu proje zaten onaylanmis.'
            ], 422);
        }

        // Sadece pending durumundaki projeler onaylanabilir
        if ($project->status !== 'pending') {
            return response()->json([
                'message' => 'Sadece onay bekleyen projeler onaylanabilir.'
            ], 422);
        }

        $project->approve($request->user()->id);

        return response()->json([
            'message' => 'Proje basariyla onaylandi.',
            'project' => $project->load(['customer', 'approvedByUser']),
        ]);
    }

    /**
     * Proje onayını reddet (pending -> draft)
     */
    public function reject(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        // Sadece pending durumundaki projeler reddedilebilir
        if ($project->status !== 'pending') {
            return response()->json([
                'message' => 'Sadece onay bekleyen projeler reddedilebilir.'
            ], 422);
        }

        $reason = $validated['rejection_reason'] ?? 'Belirtilmedi';
        $notes = $project->notes ? $project->notes . "\n\n" : '';

        $project->update([
            'status' => 'draft',
            'notes' => $notes . "[RED SEBEBI - " . now()->format('d.m.Y H:i') . "]: " . $reason,
        ]);

        return response()->json([
            'message' => 'Proje reddedildi.',
            'project' => $project->load('customer'),
        ]);
    }

    /**
     * Projenin tahmini maliyetini hesapla
     */
    public function calculateCost(Project $project): JsonResponse
    {
        $project->load([
            'days.personnelAssignments',
            'days.inventoryAssignments.inventory',
            'days.expenses',
        ]);

        $personnelCost = 0;
        $overtimeCost = 0;
        $inventoryCost = 0;
        $approvedExpenses = 0;
        $pendingExpenses = 0;

        foreach ($project->days as $day) {
            // Personel maliyeti (yevmiye + mesai = total_earnings)
            foreach ($day->personnelAssignments as $pa) {
                $totalEarnings = (float) ($pa->total_earnings ?? 0);
                if ($totalEarnings > 0) {
                    $personnelCost += $totalEarnings;
                } else {
                    $dailyWage = (float) ($pa->daily_wage ?? 0);
                    $overtimeHours = (float) ($pa->overtime_hours ?? 0);
                    $overtimeRate = (float) ($pa->overtime_rate ?? 0);
                    $overtime = $overtimeHours * $overtimeRate;
                    $personnelCost += $dailyWage + $overtime;
                }

                // Mesai maliyetini ayrıca topla
                $overtimeHours = (float) ($pa->overtime_hours ?? 0);
                $overtimeRate = (float) ($pa->overtime_rate ?? 0);
                $overtimeCost += $overtimeHours * $overtimeRate;
            }

            // Envanter maliyeti (sadece kiralık)
            foreach ($day->inventoryAssignments as $inv) {
                if ($inv->inventory && $inv->inventory->type === 'rental') {
                    $inventoryCost += $inv->inventory->daily_rate * $inv->quantity;
                }
            }

            // Masraflar - durumlarına göre ayır
            foreach ($day->expenses as $expense) {
                if ($expense->status === 'approved') {
                    $approvedExpenses += $expense->amount;
                }
                elseif ($expense->status === 'pending') {
                    $pendingExpenses += $expense->amount;
                }
            }
        }

        // Toplam maliyet sadece onaylı giderleri içerir
        $totalCost = $personnelCost + $inventoryCost + $approvedExpenses;

        // Tahmini maliyeti güncelle
        $project->update(['estimated_cost' => $totalCost]);

        return response()->json([
            'personnel_cost' => $personnelCost,
            'overtime_cost' => $overtimeCost,
            'inventory_cost' => $inventoryCost,
            'approved_expenses' => $approvedExpenses,
            'pending_expenses' => $pendingExpenses,
            'total_cost' => $totalCost,
            'offer_price' => $project->offer_price,
            'profit' => $project->offer_price ? $project->offer_price - $totalCost : null,
        ]);
    }

    /**
     * Projeyi muhasebeleştir
     */
    public function finalize(Request $request, Project $project, AccountingService $accountingService): JsonResponse
    {
        if (!$project->canBeFinalized()) {
            return response()->json([
                'message' => 'Bu proje muhasebeleştirilemez. Proje tamamlanmış ve daha önce muhasebeleştirilmemiş olmalıdır.',
            ], 422);
        }

        try {
            $result = $accountingService->finalizeProject($project, $request->user()->id);

            return response()->json([
                'message' => 'Proje başarıyla muhasebeleştirildi.',
                'project' => $project->fresh(['customer', 'account', 'finalizedByUser']),
                'summary' => [
                    'personnel_debits_count' => count($result['personnel_debits']),
                    'group_commissions_count' => count($result['group_commissions']),
                    'total_personnel_cost' => $result['total_personnel_cost'],
                    'total_group_commission' => $result['total_group_commission'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Muhasebeleştirme sırasında bir hata oluştu.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Proje muhasebe özeti
     */
    public function accountingSummary(Project $project): JsonResponse
    {
        $project->load([
            'account',
            'customerPayments',
            'personnelPayments',
            'groupPayments',
            'days.expenses',
        ]);

        // Personel maliyeti
        $personnelCost = $project->personnelPayments()->where('type', 'debit')->sum('amount');

        // Grup komisyonları
        $groupCommissions = $project->groupPayments()->where('type', 'commission')->sum('amount');

        // Onaylı masraflar
        $approvedExpenses = 0;
        foreach ($project->days as $day) {
            $approvedExpenses += $day->expenses()->where('status', 'approved')->sum('amount');
        }

        // Müşteri ödemeleri
        $customerPayments = $project->customerPayments()->sum('amount');

        return response()->json([
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'offer_price' => $project->offer_price,
                'is_finalized' => $project->isFinalized(),
                'finalized_at' => $project->finalized_at,
            ],
            'costs' => [
                'personnel' => $personnelCost,
                'group_commissions' => $groupCommissions,
                'expenses' => $approvedExpenses,
                'total' => $personnelCost + $groupCommissions + $approvedExpenses,
            ],
            'income' => [
                'customer_payments' => $customerPayments,
                'offer_price' => $project->offer_price,
                'remaining' => $project->offer_price - $customerPayments,
            ],
            'profit' => $project->offer_price - ($personnelCost + $groupCommissions + $approvedExpenses),
        ]);
    }
}
