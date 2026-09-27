<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hatırlatma bildirimleri (üretimde cron: * * * * * php artisan schedule:run)
Schedule::command('reminders:dispatch')->everyFifteenMinutes()->withoutOverlapping();
