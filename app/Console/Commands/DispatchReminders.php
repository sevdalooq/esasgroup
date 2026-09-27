<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class DispatchReminders extends Command
{
    protected $signature = 'reminders:dispatch';

    protected $description = 'Saha sorumlusu / yönetici hatırlatma bildirimlerini gönderir (zamanlayıcıda 15 dk\'da bir)';

    public function handle(ReminderService $service): int
    {
        $sent = $service->dispatch();

        foreach ($sent as $type => $count) {
            if ($count > 0) {
                $this->info("{$type}: {$count}");
            }
        }

        $this->line('Hatırlatmalar kontrol edildi: '.array_sum($sent).' bildirim gönderildi.');

        return self::SUCCESS;
    }
}
