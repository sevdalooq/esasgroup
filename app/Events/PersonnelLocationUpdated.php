<?php

namespace App\Events;

use App\Models\PersonnelLocation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Personel konumu güncellendi (canlı harita) */
class PersonnelLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public PersonnelLocation $location)
    {
        $this->location->loadMissing('personnel:id,first_name,last_name,photo');
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('live')];
        if ($this->location->project_day_id) {
            $channels[] = new PrivateChannel('day.' . $this->location->project_day_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'personnel.location';
    }

    public function broadcastWith(): array
    {
        $p = $this->location->personnel;

        return [
            'personnel_id' => $this->location->personnel_id,
            'project_day_id' => $this->location->project_day_id,
            'name' => $p?->full_name,
            'photo' => $p?->photo,
            'lat' => (float) $this->location->lat,
            'lng' => (float) $this->location->lng,
            'accuracy' => $this->location->accuracy,
            'recorded_at' => $this->location->recorded_at?->toIso8601String(),
        ];
    }
}
