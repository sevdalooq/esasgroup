<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerContact;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Customer::with('contacts');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('tax_number', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->get('sortBy', 'name');
        $sortOrder = $request->get('sortOrder', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('perPage', 10);
        $customers = $query->paginate($perPage);

        return response()->json($customers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tax_number' => 'nullable|string|max:20',
            'mernis_no' => 'nullable|string|max:50',
            'trade_registry_no' => 'nullable|string|max:50',
            'trade_registry_office' => 'nullable|string|max:255',
            'tax_office' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'iban' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:1000',
            'is_e_invoice' => 'boolean',
            'is_e_archive' => 'boolean',
            'is_active' => 'boolean',
            'contacts' => 'nullable|array',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.title' => 'nullable|string|max:100',
            'contacts.*.phone' => 'nullable|string|max:20',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.is_primary' => 'boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $customer = Customer::create($validated);

        if (!empty($validated['contacts'])) {
            foreach ($validated['contacts'] as $contact) {
                $customer->contacts()->create($contact);
            }
        }

        return response()->json($customer->load('contacts'), 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json($customer->load('contacts'));
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tax_number' => 'nullable|string|max:20',
            'mernis_no' => 'nullable|string|max:50',
            'trade_registry_no' => 'nullable|string|max:50',
            'trade_registry_office' => 'nullable|string|max:255',
            'tax_office' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'iban' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:1000',
            'is_e_invoice' => 'boolean',
            'is_e_archive' => 'boolean',
            'is_active' => 'boolean',
            'contacts' => 'nullable|array',
            'contacts.*.id' => 'nullable|exists:customer_contacts,id',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.title' => 'nullable|string|max:100',
            'contacts.*.phone' => 'nullable|string|max:20',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.is_primary' => 'boolean',
        ]);

        $customer->update($validated);

        // Sync contacts
        if (isset($validated['contacts'])) {
            $existingIds = [];
            foreach ($validated['contacts'] as $contactData) {
                if (!empty($contactData['id'])) {
                    $contact = $customer->contacts()->find($contactData['id']);
                    if ($contact) {
                        $contact->update($contactData);
                        $existingIds[] = $contact->id;
                    }
                } else {
                    $contact = $customer->contacts()->create($contactData);
                    $existingIds[] = $contact->id;
                }
            }
            // Delete removed contacts
            $customer->contacts()->whereNotIn('id', $existingIds)->delete();
        }

        return response()->json($customer->load('contacts'));
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();
        return response()->json(['message' => 'Musteri silindi']);
    }

    public function all(): JsonResponse
    {
        $customers = Customer::orderBy('name')->get(['id', 'name', 'phone']);
        return response()->json($customers);
    }

    /**
     * Müşteri detay sayfası için tüm bilgiler
     */
    public function details(Customer $customer): JsonResponse
    {
        $customer->load('contacts');

        // Projeleri getir
        $projects = $customer->projects()
            ->select('id', 'name', 'start_date', 'end_date', 'status')
            ->get()
            ->map(function ($project) use ($customer) {
                // Proje toplam tutarını hesapla (faturalanmış tutar)
                $totalAmount = $project->invoices()->sum('total');

                // Bu proje için yapılan ödemeler
                $totalPaid = $customer->payments()
                    ->where('project_id', $project->id)
                    ->sum('amount');

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'start_date' => $project->start_date,
                    'end_date' => $project->end_date,
                    'status' => $project->status,
                    'total_amount' => $totalAmount,
                    'total_paid' => $totalPaid,
                ];
            });

        // Faturaları getir
        $invoices = $customer->invoices()
            ->orderByDesc('invoice_date')
            ->get()
            ->map(function ($invoice) use ($customer) {
                // Bu fatura için yapılan ödemeler (proje bazlı)
                $paidAmount = $customer->payments()
                    ->where('project_id', $invoice->project_id)
                    ->sum('amount');

                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_no,
                    'invoice_date' => $invoice->invoice_date,
                    'due_date' => $invoice->due_date,
                    'total_amount' => $invoice->total,
                    'paid_amount' => $paidAmount,
                    'status' => $invoice->status,
                ];
            });

        // Ödemeleri getir
        $payments = $customer->payments()
            ->with(['project:id,name', 'account:id,name'])
            ->orderByDesc('payment_date')
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'payment_date' => $payment->payment_date,
                    'payment_method' => $payment->payment_method,
                    'notes' => $payment->notes,
                    'project' => $payment->project,
                    'account' => $payment->account,
                ];
            });

        // Özet hesapla
        $totalInvoiced = $invoices->sum('total_amount');
        $totalPaid = $payments->sum('amount');
        $activeProjects = $projects->where('status', 'active')->count();

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'tax_number' => $customer->tax_number,
                'mernis_no' => $customer->mernis_no,
                'trade_registry_no' => $customer->trade_registry_no,
                'trade_registry_office' => $customer->trade_registry_office,
                'tax_office' => $customer->tax_office,
                'address' => $customer->address,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'is_e_invoice' => $customer->is_e_invoice,
                'is_e_archive' => $customer->is_e_archive,
                'is_active' => $customer->is_active,
            ],
            'contacts' => $customer->contacts,
            'projects' => $projects,
            'invoices' => $invoices,
            'payments' => $payments,
            'summary' => [
                'total_projects' => $projects->count(),
                'active_projects' => $activeProjects,
                'total_invoiced' => $totalInvoiced,
                'total_paid' => $totalPaid,
                'balance' => $totalInvoiced - $totalPaid,
            ],
        ]);
    }

    /**
     * Müşteriden ödeme al
     */
    public function storePayment(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'account_id' => 'required|exists:accounts,id',
            'project_id' => 'nullable|exists:projects,id',
            'payment_method' => 'required|in:bank,cash,credit_card,check',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $payment = $customer->payments()->create([
                'amount' => $validated['amount'],
                'account_id' => $validated['account_id'],
                'project_id' => $validated['project_id'] ?? null,
                'payment_method' => $validated['payment_method'],
                'payment_date' => now(),
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            // Kasaya giriş yap
            $account = \App\Models\Account::find($validated['account_id']);
            if ($account) {
                $account->transactions()->create([
                    'type' => 'income',
                    'amount' => $validated['amount'],
                    'description' => "Müşteri ödemesi: {$customer->name}",
                    'reference_type' => 'customer_payment',
                    'reference_id' => $payment->id,
                    'transaction_date' => now(),
                    'created_by' => $request->user()->id,
                ]);

                $account->increment('balance', $validated['amount']);
            }

            $payment->load(['project', 'account']);

            return response()->json($payment, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ödeme kaydedilirken bir hata oluştu.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
