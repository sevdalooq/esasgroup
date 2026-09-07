<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Project;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CustomerPaymentController extends Controller
{
    /**
     * Müşteri ödeme geçmişi
     */
    public function index(Customer $customer): JsonResponse
    {
        $payments = CustomerPayment::where('customer_id', $customer->id)
            ->with(['project', 'account', 'createdByUser'])
            ->orderBy('payment_date', 'desc')
            ->get();

        $totalPayments = $payments->sum('amount');

        // Müşterinin tüm proje tutarları
        $totalProjectAmount = $customer->projects()
            ->where('status', 'completed')
            ->sum('offer_price');

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
            ],
            'payments' => $payments,
            'summary' => [
                'total_project_amount' => $totalProjectAmount,
                'total_payments' => $totalPayments,
                'balance' => $totalProjectAmount - $totalPayments,
            ],
        ]);
    }

    /**
     * Proje bazlı müşteri ödemeleri
     */
    public function projectPayments(Project $project): JsonResponse
    {
        $payments = CustomerPayment::where('project_id', $project->id)
            ->with(['account', 'createdByUser'])
            ->orderBy('payment_date', 'desc')
            ->get();

        $totalPayments = $payments->sum('amount');

        return response()->json([
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'offer_price' => $project->offer_price,
            ],
            'payments' => $payments,
            'summary' => [
                'offer_price' => $project->offer_price,
                'total_payments' => $totalPayments,
                'balance' => $project->offer_price - $totalPayments,
            ],
        ]);
    }

    /**
     * Müşteriden ödeme al
     */
    public function store(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'nullable|exists:projects,id',
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank,check,credit_card',
            'receipt_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $payment = DB::transaction(function () use ($validated, $customer, $request) {
                $payment = CustomerPayment::create([
                    'customer_id' => $customer->id,
                    'project_id' => $validated['project_id'] ?? null,
                    'account_id' => $validated['account_id'],
                    'amount' => $validated['amount'],
                    'payment_date' => $validated['payment_date'],
                    'payment_method' => $validated['payment_method'],
                    'receipt_no' => $validated['receipt_no'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]);

                // Kasa işlemi oluştur
                Transaction::create([
                    'account_id' => $validated['account_id'],
                    'type' => 'in',
                    'amount' => $validated['amount'],
                    'category' => 'customer_payment',
                    'reference_type' => CustomerPayment::class,
                    'reference_id' => $payment->id,
                    'description' => "Müşteri ödemesi: {$customer->name}",
                    'date' => $validated['payment_date'],
                ]);

                return $payment;
            });

            $payment->load(['project', 'account', 'createdByUser']);

            return response()->json($payment, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ödeme kaydedilirken bir hata oluştu.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ödeme güncelle
     */
    public function update(Request $request, CustomerPayment $payment): JsonResponse
    {
        $validated = $request->validate([
            'payment_date' => 'sometimes|required|date',
            'payment_method' => 'sometimes|required|in:cash,bank,check,credit_card',
            'receipt_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $payment->update($validated);
        $payment->load(['project', 'account', 'createdByUser']);

        return response()->json($payment);
    }

    /**
     * Ödeme sil
     */
    public function destroy(CustomerPayment $payment): JsonResponse
    {
        DB::transaction(function () use ($payment) {
            // İlgili işlemi sil
            Transaction::where('reference_type', CustomerPayment::class)
                ->where('reference_id', $payment->id)
                ->delete();

            $payment->delete();
        });

        return response()->json(null, 204);
    }
}
