<?php

namespace App\Events;

use App\Models\ProjectDay;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Bir proje gününde değişiklik oldu (giriş/çıkış/mola/envanter/masraf/gün durumu).
 * Kanallar: private-day.{id} (o günü izleyenler) ve private-live (canlı izleme ekranı).
 */
class DayUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $projectDayId,
        public int $projectId,
        public string $type,      // check_in, check_out, break_start, break_end, inventory, expense, day_status, assignment, location
        public array $payload = [],
        public ?int $actorId = null,
    ) {
    }

    public static function forDay(ProjectDay $day, string $type, array $payload = []): self
    {
        return new self($day->id, $day->project_id, $type, $payload, auth()->id());
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('day.' . $this->projectDayId),
            new PrivateChannel('live'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'day.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'project_day_id' => $this->projectDayId,
            'project_id' => $this->projectId,
            'type' => $this->type,
            'payload' => $this->payload,
            'actor_id' => $this->actorId,
            'at' => now()->toIso8601String(),
        ];
    }
}
