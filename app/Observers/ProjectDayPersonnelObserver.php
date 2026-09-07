<?php

namespace App\Observers;

use App\Models\ProjectDayPersonnel;
use App\Notifications\PersonnelAssignedNotification;

class ProjectDayPersonnelObserver
{
    public function created(ProjectDayPersonnel $assignment): void
    {
        $personnel = $assignment->personnel;
        if (!$personnel) {
            return;
        }

        try {
            $personnel->notify(new PersonnelAssignedNotification($assignment));
        } catch (\Throwable $e) {
            report($e); // bildirim hatası iş akışını durdurmasın
        }
    }
}
