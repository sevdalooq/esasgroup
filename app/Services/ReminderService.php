<?php

namespace App\Services;

use App\Models\InventoryRental;
use App\Models\Project;
use App\Models\ProjectDay;
use App\Models\ReminderLog;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ProjectReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Zamanlı hatırlatmalar. `reminders:dispatch` komutu ile (zamanlayıcıda 15 dakikada bir) çalışır.
 * Kurallar Ayarlar > Bildirimler grubundan okunur; her hatırlatma ReminderLog ile bir kez gönderilir.
 */
class ReminderService
{
    public const DEFAULT_START_TIME = '09:00';

    /** @return array<string,int> tür => gönderilen bildirim sayısı */
    public function dispatch(?Carbon $now = null): array
    {
        $now ??= now();
        $settings = Setting::getByGroup('notifications');

        if (array_key_exists('reminders_enabled', $settings) && !$settings['reminders_enabled']) {
            return [];
        }

        $sent = [
            'no_supervisor' => 0,
            'personnel_missing' => 0,
            'event_soon' => 0,
            'rental_due' => 0,
        ];

        $personnelDays = (int) ($settings['reminder_personnel_days_before'] ?? 2);
        $eventHours = (int) ($settings['reminder_event_hours_before'] ?? 6);
        $supervisorDays = (int) ($settings['reminder_supervisor_days_before'] ?? 3);

        $projects = Project::with(['supervisor', 'days.personnelAssignments', 'days.supervisor', 'customer'])
            ->whereIn('status', ['approved', 'active'])
            ->whereDate('end_date', '>=', $now->toDateString())
            ->get();

        foreach ($projects as $project) {
            $startsAt = $this->projectStartsAt($project);

            // 1) Saha sorumlusu atanmamış → yöneticilere
            if (!$project->supervisor_id && $now->diffInHours($startsAt, false) <= $supervisorDays * 24 && $startsAt->gt($now)) {
                if (!ReminderLog::alreadySent($project->id, null, 'no_supervisor')) {
                    $this->notifyManagers(new ProjectReminderNotification(
                        $project,
                        'no_supervisor',
                        'Saha sorumlusu atanmadı',
                        sprintf('"%s" projesi %s tarihinde başlıyor ancak henüz saha sorumlusu atanmadı.', $project->name, $project->start_date->format('d.m.Y')),
                    ));
                    ReminderLog::record($project->id, null, 'no_supervisor');
                    $sent['no_supervisor']++;
                }
            }

            // 2) Personel eklenmemiş günler var → X gün önce sorumluya
            $emptyDays = $project->days->filter(fn (ProjectDay $d) => $d->personnelAssignments->isEmpty() && $d->status === 'pending');
            if ($emptyDays->isNotEmpty() && $startsAt->gt($now) && $now->diffInHours($startsAt, false) <= $personnelDays * 24) {
                if (!ReminderLog::alreadySent($project->id, null, 'personnel_missing')) {
                    $dates = $emptyDays->map(fn ($d) => $d->date->format('d.m'))->implode(', ');
                    $this->notifyResponsibles($project, new ProjectReminderNotification(
                        $project,
                        'personnel_missing',
                        'Personel planı eksik',
                        sprintf('"%s" projesi %s tarihinde başlıyor. Şu günlerde henüz personel atanmadı: %s. Lütfen personel listesini tamamlayın.', $project->name, $project->start_date->format('d.m.Y'), $dates),
                    ));
                    ReminderLog::record($project->id, null, 'personnel_missing');
                    $sent['personnel_missing']++;
                }
            }

            // 3) Etkinlik günü yaklaşıyor → X saat önce o günün sorumlusuna
            foreach ($project->days as $day) {
                if ($day->status !== 'pending') {
                    continue;
                }
                $dayStart = $this->dayStartsAt($day);
                $hoursLeft = $now->diffInHours($dayStart, false);
                if ($dayStart->gt($now) && $hoursLeft <= $eventHours && !ReminderLog::alreadySent($project->id, $day->id, 'event_soon')) {
                    $count = $day->personnelAssignments->count();
                    $this->notifyResponsibles($project, new ProjectReminderNotification(
                        $project,
                        'event_soon',
                        'Etkinlik yaklaşıyor',
                        sprintf('"%s" – %s günü %s saatinde başlıyor. %s Gün başlatma, personel girişleri ve envanter teslimi için hazır olun.',
                            $project->name,
                            $day->date->format('d.m.Y'),
                            $dayStart->format('H:i'),
                            $count > 0 ? "{$count} personel planlandı." : 'Henüz personel atanmadı!'),
                        $day,
                    ), $day);
                    ReminderLog::record($project->id, $day->id, 'event_soon');
                    $sent['event_soon']++;
                }
            }
        }

        // 4) Kiralık envanter iade günü geldi / geçti
        if (class_exists(InventoryRental::class)) {
            $dueRentals = InventoryRental::with('project.supervisor')
                ->whereNull('returned_at')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<=', $now->toDateString())
                ->get();

            foreach ($dueRentals as $rental) {
                $key = 'rental:'.$rental->id.':'.$now->toDateString();
                if (!$rental->project || ReminderLog::alreadySent($rental->project_id, null, 'rental_due', $key)) {
                    continue;
                }
                $overdue = $rental->due_date->lt($now->startOfDay());
                $this->notifyResponsibles($rental->project, new ProjectReminderNotification(
                    $rental->project,
                    'rental_due',
                    $overdue ? 'Kiralık envanter iadesi gecikti' : 'Kiralık envanter iade günü',
                    sprintf('%s adet "%s" (%s) için iade tarihi %s. Lütfen iadeyi tamamlayıp sistemde işaretleyin.',
                        $rental->quantity, $rental->item_name, $rental->supplier ?: 'tedarikçi belirtilmemiş', $rental->due_date->format('d.m.Y')),
                    null,
                    ['rental_id' => $rental->id],
                ));
                ReminderLog::record($rental->project_id, null, 'rental_due', $key);
                $sent['rental_due']++;
            }
        }

        return $sent;
    }

    private function projectStartsAt(Project $project): Carbon
    {
        $firstDay = $project->days->sortBy('date')->first();

        return $firstDay ? $this->dayStartsAt($firstDay) : Carbon::parse($project->start_date->toDateString().' '.self::DEFAULT_START_TIME);
    }

    private function dayStartsAt(ProjectDay $day): Carbon
    {
        $time = $day->start_time ? substr($day->start_time, 0, 5) : self::DEFAULT_START_TIME;

        return Carbon::parse($day->date->toDateString().' '.$time);
    }

    /** Proje sorumlusu + (varsa) günün sorumlusu; hiçbiri yoksa yöneticiler */
    private function notifyResponsibles(Project $project, ProjectReminderNotification $notification, ?ProjectDay $day = null): void
    {
        $recipients = collect([$project->supervisor, $day?->supervisor])->filter()->unique('id');

        if ($recipients->isEmpty()) {
            $recipients = $project->days->pluck('supervisor')->filter()->unique('id');
        }

        if ($recipients->isEmpty()) {
            $this->notifyManagers($notification);

            return;
        }

        $this->send($recipients, $notification);
    }

    private function notifyManagers(ProjectReminderNotification $notification): void
    {
        $managers = User::where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'manager']))
            ->get();

        $this->send($managers, $notification);
    }

    private function send(Collection $users, ProjectReminderNotification $notification): void
    {
        foreach ($users as $user) {
            try {
                $user->notify($notification);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
