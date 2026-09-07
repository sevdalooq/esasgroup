<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonnelBreak extends Model
{
    protected $fillable = ['project_day_personnel_id', 'started_at', 'ended_at', 'reason'];

    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime'];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ProjectDayPersonnel::class, 'project_day_personnel_id');
    }

    public function getDurationMinutesAttribute(): int
    {
        return (int) $this->started_at->diffInMinutes($this->ended_at ?? now());
    }
}
