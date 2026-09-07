<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupPayment;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GroupPaymentController extends Controller
{
    public function __construct(
        private AccountingService $accountingService
    ) {}

    /**
     * Grup ödeme/komisyon geçmişi
     */
    public function index(Group $group): JsonResponse
    {
        $summary = $this->accountingService->getGroupBalanceSummary($group->id);

        // Gruba bağlı personelleri getir
        $personnel = $group->personnel()->get()->map(function ($p) {
            $payments = $p->payments;
            $totalDebit = $payments->where('type', 'debit')->sum('amount');
            $totalCredit = $payments->where('type', 'credit')->sum('amount');

            return [
                'id' => $p->id,
                'full_name' => $p->full_name,
                'phone' => $p->phone,
                'total_earned' => $totalDebit,
                'total_paid' => $totalCredit,
                'balance' => $totalDebit - $totalCredit,
            ];
        });

        // Grubun çalıştığı projeleri getir
        $projects = $this->getGroupProjects($group);

        return response()->json([
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'commission_type' => $group->commission_type,
                'commission_value' => $group->commission_value,
            ],
            'payments' => $summary['payments']->load(['project', 'account']),
            'projects' => $projects,
            'personnel' => $personnel,
            'summary' => [
                'total_commission' => $summary['total_commission'],
                'total_payment' => $summary['total_payment'],
                'balance' => $summary['balance'],
                'total_projects' => count($projects),
                'total_personnel' => $personnel->count(),
            ],
        ]);
    }

    /**
     * Grubun çalıştığı projeleri getir
     */
    private function getGroupProjects(Group $group): array
    {
        $commissions = GroupPayment::where('group_id', $group->id)
            ->where('type', 'commission')
            ->with('project.customer')
            ->get();

        $projectData = [];

        foreach ($commissions as $commission) {
            if (!$commission->project) continue;

            $project = $commission->project;
            $projectId = $project->id;

            if (!isset($projectData[$projectId])) {
                // Bu projede kaç personel çalışmış
                $personnelCount = \App\Models\ProjectDayPersonnel::whereHas('projectDay', function ($q) use ($projectId) {
                    $q->where('project_id', $projectId);
                })->whereHas('personnel', function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                })->distinct('personnel_id')->count('personnel_id');

                // Bu proje için yapılan ödemeler
                $totalPaid = GroupPayment::where('group_id', $group->id)
                    ->where('project_id', $projectId)
                    ->where('type', 'payment')
                    ->sum('amount');

                $projectData[$projectId] = [
                    'project_id' => $projectId,
                    'project_name' => $project->name,
                    'customer_name' => $project->customer->name ?? 'Bilinmiyor',
                    'start_date' => $project->start_date,
                    'end_date' => $project->end_date,
                    'personnel_count' => $personnelCount,
                    'total_commission' => $commission->amount,
                    'total_paid' => $totalPaid,
                ];
            }
        }

        return array_values($projectData);
    }

    /**
     * Gruba ödeme yap
     */
    public function store(Request $request, Group $group): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'account_id' => 'required|exists:accounts,id',
            'project_id' => 'nullable|exists:projects,id',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $payment = $this->accountingService->makeGroupPayment(
                $group->id,
                $validated['amount'],
                $validated['account_id'],
                $request->user()->id,
                $validated['project_id'] ?? null,
                'bank', // Varsayılan olarak bank
                $validated['notes'] ?? null
            );

            $payment->load(['group', 'account', 'project']);

            return response()->json($payment, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ödeme yapılırken bir hata oluştu.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Tüm grupların bakiye özeti
     */
    public function allBalances(): JsonResponse
    {
        $groups = Group::with('personnel')
            ->withSum(['payments as total_commission' => function ($query) {
                $query->where('type', 'commission');
            }], 'amount')
            ->withSum(['payments as total_payment' => function ($query) {
                $query->where('type', 'payment');
            }], 'amount')
            ->get()
            ->map(function ($g) {
                $g->balance = ($g->total_commission ?? 0) - ($g->total_payment ?? 0);
                return $g;
            })
            ->filter(function ($g) {
                return $g->balance != 0;
            })
            ->values();

        return response()->json($groups);
    }
}
