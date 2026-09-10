<?php

namespace App\Observers;

use App\Events\DayUpdated;
use App\Models\ProjectDayInventory;
use App\Models\ProjectDayPersonnel;
use App\Models\ProjectExpense;
use Illuminate\Database\Eloquent\Model;

/**
 * Gün içi kayıtlardaki her değişikliği websocket üzerinden yayınlar.
 * Hangi yoldan (saha ekranı, proje detayı, mobil) yapıldığı fark etmez.
 */
class LiveBroadcastObserver
{
    public function created(Model $model): void
    {
        $this->broadcast($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->broadcast($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->broadcast($model, 'deleted');
    }

    private function broadcast(Model $model, string $action): void
    {
        $day = $model->projectDay ?? null;
        if (!$day) {
            return;
        }

        [$type, $payload] = match (true) {
            $model instanceof ProjectDayPersonnel => [$this->personnelType($model, $action), [
                'assignment_id' => $model->id,
                'personnel_id' => $model->personnel_id,
                'name' => $model->personnel?->full_name,
                'presence' => $model->presence,
                'zone' => $model->zone,
                'check_in_time' => $model->check_in_time?->toIso8601String(),
                'check_out_time' => $model->check_out_time?->toIso8601String(),
                'break_started_at' => $model->break_started_at?->toIso8601String(),
                'payment_status' => $model->payment_status,
                'action' => $action,
            ]],
            $model instanceof ProjectDayInventory => ['inventory', [
                'assignment_id' => $model->id,
                'inventory_id' => $model->inventory_id,
                'name' => $model->inventory?->name,
                'status' => $model->status,
                'return_status' => $model->return_status,
                'assigned_to_personnel_id' => $model->assigned_to_personnel_id,
                'action' => $action,
            ]],
            $model instanceof ProjectExpense => ['expense', [
                'expense_id' => $model->id,
                'description' => $model->description,
                'amount' => (float) $model->amount,
                'status' => $model->status,
                'action' => $action,
            ]],
            default => ['change', ['action' => $action]],
        };

        // Yayını HTTP yanıtı gönderildikten sonra yap; websocket sunucusu yavaşsa kullanıcı beklemesin
        $event = DayUpdated::forDay($day, $type, $payload);
        app()->terminating(function () use ($event) {
            try {
                event($event);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    private function personnelType(ProjectDayPersonnel $a, string $action): string
    {
        if ($action === 'created') {
            return 'assignment';
        }
        if ($action === 'deleted') {
            return 'assignment_removed';
        }
        if ($a->wasChanged('presence')) {
            return match ($a->presence) {
                'checked_in' => $a->wasChanged('check_in_time') ? 'check_in' : 'break_end',
                'on_break' => 'break_start',
                'checked_out' => 'check_out',
                'absent' => 'absent',
                'assigned' => $a->getOriginal('presence') === 'absent' ? 'absent_cleared' : 'assignment',
                default => 'assignment',
            };
        }

        return 'assignment';
    }
}
