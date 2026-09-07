<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use App\Models\PersonnelPayment;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PersonnelPaymentController extends Controller
{
    public function __construct(
        private AccountingService $accountingService
    ) {}

    /**
     * Personel ödeme geçmişi
     */
    public function index(Personnel $personnel): JsonResponse
    {
        $summary = $this->accountingService->getPersonnelBalanceSummary($personnel);

        return response()->json([
            'personnel' => [
                'id' => $personnel->id,
                'full_name' => $personnel->full_name,
                'phone' => $personnel->phone,
                'group' => $personnel->group,
            ],
            'payments' => $summary['payments'],
            'projects' => $summary['projects'],
            'summary' => [
                'total_debit' => $summary['total_debit'],
                'total_credit' => $summary['total_credit'],
                'balance' => $summary['balance'],
                'total_days_worked' => $summary['total_days_worked'],
                'total_projects' => $summary['total_projects'],
            ],
        ]);
    }

    /**
     * Personele ödeme yap
     */
    public function store(Request $request, Personnel $personnel): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'account_id' => 'required|exists:accounts,id',
            'project_id' => 'nullable|exists:projects,id',
            'description' => 'nullable|string|max:500',
        ]);

        try {
            $payment = $this->accountingService->makePersonnelPayment(
                $personnel,
                $validated['amount'],
                $validated['account_id'],
                $request->user()->id,
                $validated['project_id'] ?? null,
                $validated['description'] ?? null
            );

            $payment->load(['personnel', 'account', 'project']);

            return response()->json($payment, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ödeme yapılırken bir hata oluştu.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Tüm personel bakiye özeti
     */
    public function allBalances(): JsonResponse
    {
        $personnel = Personnel::with('group')
            ->withSum(['payments as total_debit' => function ($query) {
                $query->where('type', 'debit');
            }], 'amount')
            ->withSum(['payments as total_credit' => function ($query) {
                $query->where('type', 'credit');
            }], 'amount')
            ->get()
            ->map(function ($p) {
                $p->balance = ($p->total_debit ?? 0) - ($p->total_credit ?? 0);
                return $p;
            })
            ->filter(function ($p) {
                return $p->balance != 0;
            })
            ->values();

        return response()->json($personnel);
    }
}
