<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\ProjectDayPersonnel::observe(\App\Observers\ProjectDayPersonnelObserver::class);
        \App\Models\ProjectDay::observe(\App\Observers\ProjectDayObserver::class);
        \App\Models\ProjectDayPersonnel::observe(\App\Observers\LiveBroadcastObserver::class);
        \App\Models\ProjectDayInventory::observe(\App\Observers\LiveBroadcastObserver::class);
        \App\Models\ProjectExpense::observe(\App\Observers\LiveBroadcastObserver::class);

        // Websocket kanal yetkilendirmesi: mobil ve SPA Sanctum token ile /api/broadcasting/auth çağırır
        \Illuminate\Support\Facades\Broadcast::routes(['prefix' => 'api', 'middleware' => ['auth:sanctum']]);
        //
    }
}
