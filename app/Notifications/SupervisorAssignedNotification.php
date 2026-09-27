<?php

namespace App\Notifications;

use App\Models\Project;

/** Bir kullanıcı projeye saha sorumlusu olarak atandığında */
class SupervisorAssignedNotification extends BaseAppNotification
{
    public function __construct(public Project $project)
    {
        $this->project->loadMissing('customer');
    }

    public function title(): string
    {
        return 'Saha sorumlusu olarak atandınız';
    }

    public function body(): string
    {
        return sprintf(
            '"%s" projesine (%s, %s – %s) saha sorumlusu olarak atandınız. Personel ve envanter planlamasını zamanında tamamlamanız gerekmektedir.',
            $this->project->name,
            $this->project->customer?->name,
            $this->project->start_date?->format('d.m.Y'),
            $this->project->end_date?->format('d.m.Y')
        );
    }

    public function payload(): array
    {
        return ['project_id' => $this->project->id];
    }
}
