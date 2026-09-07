<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    public function index(): JsonResponse
    {
        $accounts = Account::orderBy('name')->get();

        return response()->json($accounts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:cash,bank',
            'currency' => 'nullable|string|max:10',
            'balance' => 'nullable|numeric',
            'is_active' => 'boolean',
        ]);

        $account = Account::create($validated);

        return response()->json($account, 201);
    }

    public function show(Account $account): JsonResponse
    {
        $account->load(['transactions' => function ($query) {
            $query->orderBy('date', 'desc')->limit(50);
        }]);

        return response()->json($account);
    }

    public function update(Request $request, Account $account): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:cash,bank',
            'currency' => 'nullable|string|max:10',
            'is_active' => 'boolean',
        ]);

        $account->update($validated);

        return response()->json($account);
    }

    public function destroy(Account $account): JsonResponse
    {
        // Aktif projelerde kullanılıyorsa silme
        if ($account->projects()->whereNull('finalized_at')->exists()) {
            return response()->json([
                'message' => 'Bu kasa aktif projelerde kullanılmaktadır.',
            ], 422);
        }

        $account->delete();

        return response()->json(null, 204);
    }

    /**
     * Tüm kasaların bakiyelerini yeniden hesapla
     */
    public function recalculateBalances(): JsonResponse
    {
        $accounts = Account::all();
        $results = [];

        foreach ($accounts as $account) {
            $oldBalance = $account->balance;
            $newBalance = $account->recalculateBalance();
            $results[] = [
                'id' => $account->id,
                'name' => $account->name,
                'old_balance' => $oldBalance,
                'new_balance' => $newBalance,
            ];
        }

        return response()->json([
            'message' => 'Tüm bakiyeler yeniden hesaplandı.',
            'accounts' => $results,
        ]);
    }

    public function transactions(Account $account): JsonResponse
    {
        $transactions = $account->transactions()
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($transactions);
    }
}
