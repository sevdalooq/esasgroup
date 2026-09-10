<?php

namespace App\Observers;

use App\Models\ProjectDay;
use App\Notifications\AssignmentChangedNotification;

class ProjectDayObserver
{
    public function updated(ProjectDay $day): void
    {
        if ($day->wasChanged(['status', 'start_photo', 'end_photo', 'date', 'supervisor_id'])) {
            $event = \App\Events\DayUpdated::forDay($day, 'day_status', [
                'status' => $day->status,
                'date' => $day->date?->toDateString(),
                'start_photo' => $day->start_photo,
                'end_photo' => $day->end_photo,
            ]);
            app()->terminating(function () use ($event) {
                try {
                    event($event);
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        }

        $changes = [];
        if ($day->wasChanged('date')) {
            $changes[] = 'tarih ' . $day->getOriginal('date')?->format('d.m.Y') . ' → ' . $day->date?->format('d.m.Y') . ' olarak güncellendi';
        }
        if ($day->wasChanged('status') && $day->status === 'cancelled') {
            $changes[] = 'görev iptal edildi';
        }
        if (!$changes) {
            return;
        }

        $notification = new AssignmentChangedNotification($day, implode('; ', $changes));
        $day->loadMissing('personnelAssignments.personnel');
        foreach ($day->personnelAssignments as $assignment) {
            try {
                $assignment->personnel?->notify($notification);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
