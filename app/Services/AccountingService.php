<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Personnel;
use App\Models\PersonnelPayment;
use App\Models\GroupPayment;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Exception;

class AccountingService
{
    /**
     * Projeyi muhasebeleştir
     * - Personel alacaklarını oluştur
     * - Grup komisyonlarını hesapla
     * - İşlemleri kaydet
     */
    public function finalizeProject(Project $project, int $userId): array
    {
        if (!$project->canBeFinalized()) {
            throw new Exception('Bu proje muhasebeleştirilemez. Proje tamamlanmış ve daha önce muhasebeleştirilmemiş olmalıdır.');
        }

        // İlişkileri yükle
        $project->load(['days.personnelAssignments.personnel.group']);

        return DB::transaction(function () use ($project, $userId) {
            $result = [
                'personnel_debits' => [],
                'personnel_credits' => [],
                'group_commissions' => [],
                'total_personnel_cost' => 0,
                'total_personnel_paid' => 0,
                'total_group_commission' => 0,
            ];

            // Personel alacaklarını oluştur
            $personnelTotals = $this->calculatePersonnelTotals($project);

            foreach ($personnelTotals as $personnelId => $data) {
                if ($data['total'] > 0) {
                    // Alacak kaydı oluştur (personelin kazandığı tutar)
                    $debitPayment = PersonnelPayment::create([
                        'personnel_id' => $personnelId,
                        'project_id' => $project->id,
                        'type' => 'debit',
                        'amount' => $data['total'],
                        'date' => $project->end_date,
                        'description' => "Proje: {$project->name} - {$data['days']} gün çalışma",
                        'created_by' => $userId,
                    ]);

                    $result['personnel_debits'][] = $debitPayment;
                    $result['total_personnel_cost'] += $data['total'];

                    // Proje içinde yapılan ödemeler için credit kaydı oluştur
                    if ($data['paid'] > 0) {
                        $creditPayment = PersonnelPayment::create([
                            'personnel_id' => $personnelId,
                            'project_id' => $project->id,
                            'type' => 'credit',
                            'amount' => $data['paid'],
                            'date' => $project->end_date,
                            'description' => "Proje içi ödeme: {$project->name}",
                            'created_by' => $userId,
                        ]);

                        $result['personnel_credits'][] = $creditPayment;
                        $result['total_personnel_paid'] += $data['paid'];
                    }
                }
            }

            // Grup komisyonlarını hesapla
            $groupTotals = $this->calculateGroupCommissions($project, $personnelTotals);

            foreach ($groupTotals as $groupId => $data) {
                if ($data['commission'] > 0) {
                    $groupPayment = GroupPayment::create([
                        'group_id' => $groupId,
                        'project_id' => $project->id,
                        'amount' => $data['commission'],
                        'type' => 'commission',
                        'commission_base' => $data['base'],
                        'commission_type' => $data['commission_type'],
                        'commission_rate' => $data['commission_rate'],
                        'payment_date' => $project->end_date,
                        'notes' => "Proje: {$project->name} komisyonu",
                        'created_by' => $userId,
                    ]);

                    $result['group_commissions'][] = $groupPayment;
                    $result['total_group_commission'] += $data['commission'];
                }
            }

            // Projeyi muhasebeleştirilmiş olarak işaretle
            $project->update([
                'finalized_at' => now(),
                'finalized_by' => $userId,
            ]);

            return $result;
        });
    }

    /**
     * Personel bazlı toplam hesapla (alacak ve ödenenler)
     */
    private function calculatePersonnelTotals(Project $project): array
    {
        $totals = [];

        foreach ($project->days as $day) {
            foreach ($day->personnelAssignments as $assignment) {
                $personnelId = $assignment->personnel_id;

                if (!isset($totals[$personnelId])) {
                    $totals[$personnelId] = [
                        'personnel' => $assignment->personnel,
                        'total' => 0,
                        'paid' => 0,
                        'days' => 0,
                        'overtime_hours' => 0,
                        'overtime_amount' => 0,
                        'group_id' => $assignment->personnel->group_id,
                        'payments' => [], // Gün bazlı ödeme detayları
                    ];
                }

                // total_earnings varsa onu kullan, yoksa daily_wage + mesai hesapla
                $totalEarnings = (float) ($assignment->total_earnings ?? 0);
                if ($totalEarnings > 0) {
                    $dayTotal = $totalEarnings;
                } else {
                    $dailyWage = (float) ($assignment->daily_wage ?? 0);
                    $overtimeHours = (float) ($assignment->overtime_hours ?? 0);
                    $overtimeRate = (float) ($assignment->overtime_rate ?? 0);
                    $overtimeAmount = $overtimeHours * $overtimeRate;
                    $dayTotal = $dailyWage + $overtimeAmount;
                }

                $totals[$personnelId]['total'] += $dayTotal;
                $totals[$personnelId]['days']++;

                // Mesai bilgilerini topla
                $overtimeHours = (float) ($assignment->overtime_hours ?? 0);
                $overtimeRate = (float) ($assignment->overtime_rate ?? 0);
                if ($overtimeHours > 0) {
                    $totals[$personnelId]['overtime_hours'] += $overtimeHours;
                    $totals[$personnelId]['overtime_amount'] += ($overtimeHours * $overtimeRate);
                }

                // Proje içinde yapılan ödemeleri topla
                if ($assignment->payment_status === 'paid' || $assignment->payment_status === 'partial') {
                    $paidAmount = (float) ($assignment->payment_amount ?? 0);
                    if ($paidAmount > 0) {
                        $totals[$personnelId]['paid'] += $paidAmount;
                        $totals[$personnelId]['payments'][] = [
                            'date' => $day->date,
                            'amount' => $paidAmount,
                            'method' => $assignment->payment_method,
                        ];
                    }
                }
            }
        }

        return $totals;
    }

    /**
     * Grup komisyonlarını hesapla
     */
    private function calculateGroupCommissions(Project $project, array $personnelTotals): array
    {
        $groupTotals = [];

        foreach ($personnelTotals as $data) {
            $groupId = $data['group_id'];

            if (!$groupId) {
                continue; // Bağımsız personel, komisyon yok
            }

            if (!isset($groupTotals[$groupId])) {
                $group = $data['personnel']->group;
                $groupTotals[$groupId] = [
                    'group' => $group,
                    'base' => 0,
                    'commission' => 0,
                    'commission_type' => $group->commission_type,
                    'commission_rate' => $group->commission_value,
                ];
            }

            $groupTotals[$groupId]['base'] += $data['total'];
        }

        // Komisyonları hesapla
        foreach ($groupTotals as $groupId => &$data) {
            $data['commission'] = $data['group']->calculateCommission($data['base']);
        }

        return $groupTotals;
    }

    /**
     * Personele ödeme yap
     */
    public function makePersonnelPayment(
        Personnel $personnel,
        float $amount,
        int $accountId,
        int $userId,
        ?int $projectId = null,
        ?string $description = null
    ): PersonnelPayment {
        return DB::transaction(function () use ($personnel, $amount, $accountId, $userId, $projectId, $description) {
            // Ödeme kaydı oluştur
            $payment = PersonnelPayment::create([
                'personnel_id' => $personnel->id,
                'account_id' => $accountId,
                'project_id' => $projectId,
                'type' => 'credit',
                'amount' => $amount,
                'date' => now(),
                'description' => $description ?? 'Personel ödemesi',
                'created_by' => $userId,
            ]);

            // Kasa işlemi oluştur
            Transaction::create([
                'account_id' => $accountId,
                'type' => 'out',
                'amount' => $amount,
                'category' => 'personnel_payment',
                'reference_type' => PersonnelPayment::class,
                'reference_id' => $payment->id,
                'description' => "Personel ödemesi: {$personnel->full_name}",
                'date' => now(),
            ]);

            return $payment;
        });
    }

    /**
     * Gruba ödeme yap
     */
    public function makeGroupPayment(
        int $groupId,
        float $amount,
        int $accountId,
        int $userId,
        ?int $projectId = null,
        string $paymentMethod = 'bank',
        ?string $notes = null
    ): GroupPayment {
        return DB::transaction(function () use ($groupId, $amount, $accountId, $userId, $projectId, $paymentMethod, $notes) {
            $groupPayment = GroupPayment::create([
                'group_id' => $groupId,
                'project_id' => $projectId,
                'account_id' => $accountId,
                'amount' => $amount,
                'type' => 'payment',
                'payment_date' => now(),
                'payment_method' => $paymentMethod,
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            // Kasa işlemi oluştur
            $group = $groupPayment->group;
            Transaction::create([
                'account_id' => $accountId,
                'type' => 'out',
                'amount' => $amount,
                'category' => 'group_payment',
                'reference_type' => GroupPayment::class,
                'reference_id' => $groupPayment->id,
                'description' => "Aracı firma ödemesi: {$group->name}",
                'date' => now(),
            ]);

            return $groupPayment;
        });
    }

    /**
     * Personel bakiye özeti
     */
    public function getPersonnelBalanceSummary(Personnel $personnel): array
    {
        $payments = $personnel->payments()
            ->with(['project', 'account'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalDebit = $payments->where('type', 'debit')->sum('amount');
        $totalCredit = $payments->where('type', 'credit')->sum('amount');

        // Çalıştığı projeleri getir
        $projects = $this->getPersonnelProjects($personnel);

        return [
            'personnel' => $personnel,
            'payments' => $payments,
            'projects' => $projects,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balance' => $totalDebit - $totalCredit,
            'total_days_worked' => collect($projects)->sum('days_worked'),
            'total_projects' => count($projects),
        ];
    }

    /**
     * Personelin çalıştığı projeleri getir
     */
    private function getPersonnelProjects(Personnel $personnel): array
    {
        $assignments = \App\Models\ProjectDayPersonnel::where('personnel_id', $personnel->id)
            ->with(['projectDay.project.customer'])
            ->get();

        $projectData = [];

        foreach ($assignments as $assignment) {
            $project = $assignment->projectDay->project;
            $projectId = $project->id;

            if (!isset($projectData[$projectId])) {
                $projectData[$projectId] = [
                    'project_id' => $projectId,
                    'project_name' => $project->name,
                    'customer_name' => $project->customer->name ?? 'Bilinmiyor',
                    'start_date' => $project->start_date,
                    'end_date' => $project->end_date,
                    'days_worked' => 0,
                    'total_earned' => 0,
                    'total_paid' => 0,
                ];
            }

            $projectData[$projectId]['days_worked']++;

            // total_earnings varsa onu kullan, yoksa daily_wage + mesai hesapla
            $totalEarnings = (float) ($assignment->total_earnings ?? 0);
            if ($totalEarnings > 0) {
                $projectData[$projectId]['total_earned'] += $totalEarnings;
            } else {
                $dailyWage = (float) ($assignment->daily_wage ?? 0);
                $overtimeHours = (float) ($assignment->overtime_hours ?? 0);
                $overtimeRate = (float) ($assignment->overtime_rate ?? 0);
                $projectData[$projectId]['total_earned'] += $dailyWage + ($overtimeHours * $overtimeRate);
            }

            if ($assignment->payment_status === 'paid' || $assignment->payment_status === 'partial') {
                $projectData[$projectId]['total_paid'] += $assignment->payment_amount ?? 0;
            }
        }

        return array_values($projectData);
    }

    /**
     * Grup bakiye özeti
     */
    public function getGroupBalanceSummary(int $groupId): array
    {
        $payments = GroupPayment::where('group_id', $groupId)
            ->orderBy('payment_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalCommission = $payments->where('type', 'commission')->sum('amount');
        $totalPayment = $payments->where('type', 'payment')->sum('amount');

        return [
            'payments' => $payments,
            'total_commission' => $totalCommission,
            'total_payment' => $totalPayment,
            'balance' => $totalCommission - $totalPayment,
        ];
    }
}
