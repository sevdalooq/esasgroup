<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\ProjectDay;

/** Saha sorumlusu / yöneticiye giden zamanlı hatırlatma (personel ekle, etkinlik yaklaşıyor, kiralık iade...) */
class ProjectReminderNotification extends BaseAppNotification
{
    public function __construct(
        public Project $project,
        public string $reminderType,
        public string $titleText,
        public string $bodyText,
        public ?ProjectDay $day = null,
        public array $extra = [],
    ) {}

    public function title(): string
    {
        return $this->titleText;
    }

    public function body(): string
    {
        return $this->bodyText;
    }

    public function payload(): array
    {
        return array_merge([
            'reminder_type' => $this->reminderType,
            'project_id' => $this->project->id,
            'project_day_id' => $this->day?->id,
        ], $this->extra);
    }
}
