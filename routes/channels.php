<?php

use App\Models\ProjectDay;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', fn ($user, $id) => (int) $user->id === (int) $id);

// Canlı izleme: saha erişimi olan herkes (yönetici, müdür, saha sorumlusu)
Broadcast::channel('live', fn ($user) => $user->hasPermission('field.access') || $user->hasPermission('projects.view'));

// Belirli bir gün: günü yönetenler + o güne atanmış personel (kendi hesabıyla)
Broadcast::channel('day.{dayId}', function ($user, $dayId) {
    if ($user->hasPermission('projects.manage_days') || $user->hasPermission('projects.view')) {
        return true;
    }
    $day = ProjectDay::find($dayId);
    if (!$day) {
        return false;
    }
    if ($day->supervisor_id === $user->id) {
        return true;
    }
    $personnel = \App\Models\Personnel::where('user_id', $user->id)->first();

    return $personnel && $day->personnelAssignments()->where('personnel_id', $personnel->id)->exists();
});
