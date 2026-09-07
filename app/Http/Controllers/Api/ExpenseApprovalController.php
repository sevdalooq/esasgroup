<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectExpense;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExpenseApprovalController extends Controller
{
    /**
     * Onay bekleyen masraflar
     */
    public function pending(): JsonResponse
    {
        $expenses = ProjectExpense::where('status', 'pending')
            ->with([
                'projectDay.project',
                'createdByUser',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($expenses);
    }

    /**
     * Proje bazlı masraflar
     */
    public function projectExpenses(Project $project): JsonResponse
    {
        $expenses = ProjectExpense::whereHas('projectDay', function ($query) use ($project) {
            $query->where('project_id', $project->id);
        })
            ->with([
                'projectDay',
                'createdByUser',
                'approvedByUser',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $summary = [
            'total_pending' => $expenses->where('status', 'pending')->sum('amount'),
            'total_approved' => $expenses->where('status', 'approved')->sum('amount'),
            'total_rejected' => $expenses->where('status', 'rejected')->sum('amount'),
        ];

        return response()->json([
            'expenses' => $expenses,
            'summary' => $summary,
        ]);
    }

    /**
     * Masrafı onayla
     */
    public function approve(Request $request, ProjectExpense $expense): JsonResponse
    {
        if (!$expense->isPending()) {
            return response()->json([
                'message' => 'Bu masraf zaten işlenmiş.',
            ], 422);
        }

        $expense->approve($request->user()->id);
        $expense->load(['projectDay.project', 'createdByUser', 'approvedByUser']);

        return response()->json($expense);
    }

    /**
     * Masrafı reddet
     */
    public function reject(Request $request, ProjectExpense $expense): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if (!$expense->isPending()) {
            return response()->json([
                'message' => 'Bu masraf zaten işlenmiş.',
            ], 422);
        }

        $expense->reject($request->user()->id, $validated['reason']);
        $expense->load(['projectDay.project', 'createdByUser', 'approvedByUser']);

        return response()->json($expense);
    }

    /**
     * Toplu onaylama
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'expense_ids' => 'required|array',
            'expense_ids.*' => 'exists:project_expenses,id',
        ]);

        $approved = 0;
        $userId = $request->user()->id;

        foreach ($validated['expense_ids'] as $expenseId) {
            $expense = ProjectExpense::find($expenseId);
            if ($expense && $expense->isPending()) {
                $expense->approve($userId);
                $approved++;
            }
        }

        return response()->json([
            'message' => "{$approved} masraf onaylandı.",
            'approved_count' => $approved,
        ]);
    }
}
