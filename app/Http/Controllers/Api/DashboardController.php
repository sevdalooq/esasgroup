<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Personnel;
use App\Models\Customer;
use App\Models\Group;
use App\Models\Inventory;
use App\Models\Account;
use App\Models\ProjectExpense;
use App\Models\PersonnelPayment;
use App\Models\GroupPayment;
use App\Models\CustomerPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->is_admin;

        $data = [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->name ?? 'Kullanici',
                'is_admin' => $isAdmin,
            ],
        ];

        // Temel istatistikler - herkes gorebilir
        $data['stats'] = $this->getBasicStats($user);

        // Aktif projeler - projects.view yetkisi gerekli
        if ($isAdmin || $user->hasPermission('projects.view')) {
            $data['active_projects'] = $this->getActiveProjects();
            $data['upcoming_projects'] = $this->getUpcomingProjects();
            $data['project_stats'] = $this->getProjectStats();
        }

        // Muhasebe ozeti - accounting.view yetkisi gerekli
        if ($isAdmin || $user->hasPermission('accounting.view')) {
            $data['accounting'] = $this->getAccountingSummary();
            $data['pending_payments'] = $this->getPendingPayments();
        }

        // Onay bekleyen masraflar - accounting.approve_expenses yetkisi gerekli
        if ($isAdmin || $user->hasPermission('accounting.approve_expenses')) {
            $data['pending_expenses'] = $this->getPendingExpenses();
        }

        // Son aktiviteler
        $data['recent_activities'] = $this->getRecentActivities($user);

        return response()->json($data);
    }

    private function getBasicStats($user)
    {
        $stats = [];
        $isAdmin = $user->is_admin;

        // Proje sayilari
        if ($isAdmin || $user->hasPermission('projects.view')) {
            $stats['total_projects'] = Project::count();
            $stats['active_projects'] = Project::whereIn('status', ['pending', 'approved', 'active'])->count();
            $stats['completed_projects'] = Project::where('status', 'completed')->count();
        }

        // Personel sayisi
        if ($isAdmin || $user->hasPermission('personnel.view')) {
            $stats['total_personnel'] = Personnel::where('is_active', true)->count();
        }

        // Musteri sayisi
        if ($isAdmin || $user->hasPermission('customers.view')) {
            $stats['total_customers'] = Customer::count();
        }

        // Grup sayisi
        if ($isAdmin || $user->hasPermission('groups.view')) {
            $stats['total_groups'] = Group::count();
        }

        // Envanter sayisi
        if ($isAdmin || $user->hasPermission('inventory.view')) {
            $stats['total_inventory'] = Inventory::where('current_status', 'available')->count();
        }

        return $stats;
    }

    private function getActiveProjects()
    {
        return Project::with(['customer:id,name', 'days' => function ($query) {
                $query->whereDate('date', '>=', now()->subDays(1))
                      ->whereDate('date', '<=', now()->addDays(7))
                      ->orderBy('date');
            }])
            ->whereIn('status', ['approved', 'active'])
            ->orderBy('start_date')
            ->limit(5)
            ->get()
            ->map(function ($project) {
                $todayDay = $project->days->firstWhere('date', now()->format('Y-m-d'));

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'customer' => $project->customer->name ?? '-',
                    'status' => $project->status,
                    'start_date' => $project->start_date,
                    'end_date' => $project->end_date,
                    'today_status' => $todayDay?->status ?? null,
                    'total_days' => $project->days->count(),
                    'completed_days' => $project->days->where('status', 'completed')->count(),
                ];
            });
    }

    private function getUpcomingProjects()
    {
        return Project::with('customer:id,name')
            ->where('status', 'approved')
            ->whereDate('start_date', '>=', today())
            ->whereDate('start_date', '<=', today()->addDays(14))
            ->orderBy('start_date')
            ->limit(5)
            ->get()
            ->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'customer' => $project->customer->name ?? '-',
                    'start_date' => $project->start_date,
                    'days_until' => max(0, (int) today()->diffInDays(Carbon::parse($project->start_date)->startOfDay(), false)),
                ];
            });
    }

    private function getProjectStats()
    {
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        return [
            'this_month' => [
                'total' => Project::where('created_at', '>=', $thisMonth)->count(),
                'completed' => Project::where('status', 'completed')
                    ->where('updated_at', '>=', $thisMonth)
                    ->count(),
            ],
            'last_month' => [
                'total' => Project::whereBetween('created_at', [$lastMonth, $thisMonth])->count(),
                'completed' => Project::where('status', 'completed')
                    ->whereBetween('updated_at', [$lastMonth, $thisMonth])
                    ->count(),
            ],
            'by_status' => [
                'draft' => Project::where('status', 'draft')->count(),
                'pending' => Project::where('status', 'pending')->count(),
                'approved' => Project::where('status', 'approved')->count(),
                'active' => Project::where('status', 'active')->count(),
                'completed' => Project::where('status', 'completed')->count(),
                'cancelled' => Project::where('status', 'cancelled')->count(),
            ],
        ];
    }

    private function getAccountingSummary()
    {
        // Kasa bakiyeleri
        $accounts = Account::select('id', 'name', 'balance', 'currency')->get();

        // Bu ayin gelirleri (musteri odemeleri)
        $thisMonthIncome = CustomerPayment::where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');

        // Bu ayin giderleri
        $thisMonthExpenses = ProjectExpense::where('status', 'approved')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');

        // Personel borçları: muhasebeleştirilmiş hakedişler (debit) - yapılan ödemeler (credit)
        $personnelDebt = PersonnelPayment::where('type', 'debit')->sum('amount');
        $personnelPaid = PersonnelPayment::where('type', 'credit')->sum('amount');

        // Ekip/aracı firma borçları: hesaplanan komisyonlar - yapılan ödemeler
        $groupDebt = GroupPayment::where('type', 'commission')->sum('amount');
        $groupPaid = GroupPayment::where('type', 'payment')->sum('amount');

        return [
            'accounts' => $accounts,
            'total_balance' => $accounts->sum('balance'),
            'this_month_income' => $thisMonthIncome,
            'this_month_expenses' => $thisMonthExpenses,
            'personnel_debt' => $personnelDebt - $personnelPaid,
            'group_debt' => $groupDebt - $groupPaid,
        ];
    }

    private function getPendingPayments()
    {
        // Tamamlanmis ve teklif fiyati olan projeler
        $projects = Project::with('customer:id,name')
            ->where('status', 'completed')
            ->where('offer_price', '>', 0)
            ->orderByDesc('end_date')
            ->limit(10)
            ->get()
            ->map(function ($project) {
                // Bu projeye yapilan toplam odeme
                $totalReceived = CustomerPayment::where('project_id', $project->id)->sum('amount');
                $remaining = $project->offer_price - $totalReceived;

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'customer' => $project->customer?->name ?? '-',
                    'total' => $project->offer_price,
                    'received' => $totalReceived,
                    'remaining' => $remaining,
                ];
            })
            ->filter(fn($p) => $p['remaining'] > 0)
            ->take(5)
            ->values();

        return $projects;
    }

    private function getPendingExpenses()
    {
        return ProjectExpense::with(['projectDay.project:id,name'])
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'description' => $expense->description,
                    'amount' => $expense->amount,
                    'category' => $expense->category ?? '-',
                    'project' => $expense->projectDay?->project?->name ?? '-',
                    'date' => $expense->created_at->format('Y-m-d'),
                ];
            });
    }

    private function getRecentActivities($user)
    {
        $activities = collect();

        // Son tamamlanan proje gunleri
        if ($user->is_admin || $user->hasPermission('projects.view')) {
            $recentDays = DB::table('project_days')
                ->join('projects', 'project_days.project_id', '=', 'projects.id')
                ->where('project_days.status', 'completed')
                ->where('project_days.updated_at', '>=', now()->subDays(7))
                ->select(
                    'project_days.id',
                    'project_days.date',
                    'project_days.updated_at',
                    'projects.name as project_name'
                )
                ->orderByDesc('project_days.updated_at')
                ->limit(5)
                ->get()
                ->map(function ($day) {
                    return [
                        'type' => 'day_completed',
                        'message' => "{$day->project_name} - {$day->date} tamamlandi",
                        'date' => $day->updated_at,
                        'icon' => 'tabler-calendar-check',
                        'color' => 'success',
                    ];
                });

            $activities = $activities->merge($recentDays);
        }

        // Son odemeler
        if ($user->is_admin || $user->hasPermission('accounting.view')) {
            $recentPayments = CustomerPayment::with('customer:id,name')
                ->where('created_at', '>=', now()->subDays(7))
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(function ($payment) {
                    return [
                        'type' => 'payment_received',
                        'message' => "{$payment->customer->name} - " . number_format($payment->amount, 2) . " TL odeme alindi",
                        'date' => $payment->created_at,
                        'icon' => 'tabler-cash',
                        'color' => 'info',
                    ];
                });

            $activities = $activities->merge($recentPayments);
        }

        return $activities->sortByDesc('date')->take(10)->values();
    }
}
