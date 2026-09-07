<?php

namespace App\Notifications;

use App\Models\ProjectDayPersonnel;

/** Personel bir proje gününe atandığında */
class PersonnelAssignedNotification extends BaseAppNotification
{
    public function __construct(public ProjectDayPersonnel $assignment)
    {
        $this->assignment->loadMissing('projectDay.project.customer');
    }

    public function title(): string
    {
        return 'Yeni görevlendirme';
    }

    public function body(): string
    {
        $day = $this->assignment->projectDay;
        $project = $day->project;

        return sprintf(
            '%s tarihinde "%s" etkinliğinde görevlendirildiniz.%s Yevmiye: %s ₺.',
            $day->date?->format('d.m.Y'),
            $project->name,
            $this->assignment->zone ? ' Alan: ' . $this->assignment->zone . '.' : '',
            number_format((float) $this->assignment->daily_wage, 0, ',', '.')
        );
    }

    public function payload(): array
    {
        return [
            'project_id' => $this->assignment->projectDay->project_id,
            'project_day_id' => $this->assignment->project_day_id,
            'assignment_id' => $this->assignment->id,
        ];
    }
}
