<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use App\Models\ProjectDayInventory;
use App\Models\ProjectDayPersonnel;
use App\Services\AccountingService;
use Illuminate\Http\JsonResponse;

/**
 * Personel detayı: çalışma geçmişi, envanter (zimmet/teslim), ödemeler ve bakiye, son proje, özet sayaçlar, kara liste.
 */
class PersonnelActivityController extends Controller
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function show(Personnel $personnel): JsonResponse
    {
        $assignments = ProjectDayPersonnel::where('personnel_id', $personnel->id)
            ->with(['projectDay:id,project_id,date,status,start_time', 'projectDay.project:id,name,customer_id,status', 'projectDay.project.customer:id,name'])
            ->get()
            ->sortByDesc(fn ($a) => $a->projectDay?->date?->timestamp ?? 0)
            ->values();

        $today = today();

        $workHistory = $assignments->map(function (ProjectDayPersonnel $a) {
            $day = $a->projectDay;
            $earned = (float) $a->total_earnings > 0
                ? (float) $a->total_earnings
                : (float) $a->daily_wage + (float) $a->overtime_hours * (float) $a->overtime_rate;

            return [
                'assignment_id' => $a->id,
                'project_day_id' => $a->project_day_id,
                'date' => $day?->date?->toDateString(),
                'day_status' => $day?->status,
                'project_id' => $day?->project_id,
                'project_name' => $day?->project?->name,
                'project_status' => $day?->project?->status,
                'customer_name' => $day?->project?->customer?->name,
                'zone' => $a->zone,
                'daily_wage' => (float) $a->daily_wage,
                'overtime_hours' => (float) $a->overtime_hours,
                'overtime_rate' => (float) $a->overtime_rate,
                'earned' => $earned,
                'check_in_time' => $a->check_in_time?->toIso8601String(),
                'check_out_time' => $a->check_out_time?->toIso8601String(),
                'break_minutes' => (int) $a->break_minutes,
                'presence' => $a->presence,
                'approval_status' => $a->approval_status,
                'payment_status' => $a->payment_status,
                'payment_amount' => (float) $a->payment_amount,
                'notes' => $a->notes,
            ];
        });

        $completed = $workHistory->filter(fn ($w) => $w['day_status'] === 'completed' || $w['check_out_time']);
        $past = $workHistory->filter(fn ($w) => $w['date'] && $w['date'] < $today->toDateString());
        $upcoming = $workHistory->filter(fn ($w) => $w['date'] && $w['date'] >= $today->toDateString() && $w['project_status'] !== 'cancelled');
        $absent = $workHistory->filter(fn ($w) => $w['presence'] === 'absent');
        $lastWorked = $completed->first() ?: $past->first();

        // Envanter: üzerindeki sürekli zimmetler + gün bazlı teslim geçmişi
        $held = $personnel->heldInventory()->get(['id', 'name', 'type', 'serial_number', 'current_status', 'updated_at']);

        $inventoryHistory = ProjectDayInventory::whereIn('assigned_to_personnel_id', $assignments->pluck('id'))
            ->with(['inventory:id,name,type,serial_number', 'projectDay:id,project_id,date', 'projectDay.project:id,name', 'damages'])
            ->get()
            ->sortByDesc(fn ($i) => $i->projectDay?->date?->timestamp ?? 0)
            ->values()
            ->map(fn (ProjectDayInventory $i) => [
                'id' => $i->id,
                'date' => $i->projectDay?->date?->toDateString(),
                'project_id' => $i->projectDay?->project_id,
                'project_name' => $i->projectDay?->project?->name,
                'inventory_id' => $i->inventory_id,
                'name' => $i->inventory?->name,
                'serial_number' => $i->inventory?->serial_number,
                'type' => $i->inventory?->type,
                'quantity' => $i->quantity,
                'delivered_at' => $i->delivered_at?->toIso8601String(),
                'returned_at' => $i->returned_at?->toIso8601String(),
                'status' => $i->status,
                'return_status' => $i->return_status,
                'damage_description' => $i->damage_description,
                'damages' => $i->damages->map(fn ($d) => ['description' => $d->description, 'deduction_amount' => (float) $d->deduction_amount])->values(),
            ]);

        // Ödemeler ve bakiye
        $balance = $this->accounting->getPersonnelBalanceSummary($personnel);

        // Kara liste geçmişi
        $blacklist = $personnel->blacklistRequests()->with(['project:id,name', 'requester:id,name', 'reviewer:id,name'])->get();

        return response()->json([
            'summary' => [
                'total_assignments' => $workHistory->count(),
                'days_worked' => $completed->count(),
                'upcoming_assignments' => $upcoming->count(),
                'absent_count' => $absent->count(),
                'projects_count' => $workHistory->pluck('project_id')->filter()->unique()->count(),
                'total_earned' => round($completed->sum('earned'), 2),
                'total_debit' => (float) $balance['total_debit'],
                'total_credit' => (float) $balance['total_credit'],
                'balance' => (float) $balance['balance'],
                'held_inventory_count' => $held->count(),
                'unreturned_inventory_count' => $inventoryHistory->filter(fn ($i) => $i['delivered_at'] && !$i['returned_at'])->count(),
                'damage_count' => $inventoryHistory->filter(fn ($i) => $i['status'] === 'damaged' || count($i['damages']) > 0)->count(),
                'last_worked' => $lastWorked ? [
                    'date' => $lastWorked['date'],
                    'project_id' => $lastWorked['project_id'],
                    'project_name' => $lastWorked['project_name'],
                    'customer_name' => $lastWorked['customer_name'],
                    'zone' => $lastWorked['zone'],
                    'presence' => $lastWorked['presence'],
                    'earned' => $lastWorked['earned'],
                    'check_in_time' => $lastWorked['check_in_time'],
                    'check_out_time' => $lastWorked['check_out_time'],
                ] : null,
                'next_assignment' => $upcoming->last() ? [
                    'date' => $upcoming->last()['date'],
                    'project_id' => $upcoming->last()['project_id'],
                    'project_name' => $upcoming->last()['project_name'],
                ] : null,
                'is_blacklisted' => (bool) $personnel->is_blacklisted,
            ],
            'work_history' => $workHistory->values(),
            'projects' => $balance['projects'],
            'inventory' => [
                'held' => $held,
                'history' => $inventoryHistory,
            ],
            'payments' => $balance['payments'],
            'blacklist' => $blacklist,
        ]);
    }
}
