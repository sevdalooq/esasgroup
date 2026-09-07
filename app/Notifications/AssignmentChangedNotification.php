<?php

namespace App\Notifications;

use App\Models\ProjectDay;

/** Proje gününde tarih/durum değişikliği veya iptal */
class AssignmentChangedNotification extends BaseAppNotification
{
    public function __construct(public ProjectDay $day, public string $change)
    {
        $this->day->loadMissing('project');
    }

    public function title(): string
    {
        return 'Görev değişikliği';
    }

    public function body(): string
    {
        return sprintf('"%s" etkinliği (%s): %s', $this->day->project->name, $this->day->date?->format('d.m.Y'), $this->change);
    }

    public function payload(): array
    {
        return ['project_id' => $this->day->project_id, 'project_day_id' => $this->day->id];
    }
}
