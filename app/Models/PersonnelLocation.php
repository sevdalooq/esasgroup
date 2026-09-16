<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonnelLocation extends Model
{
    protected $fillable = ['personnel_id', 'project_day_id', 'lat', 'lng', 'accuracy', 'source', 'recorded_at'];

    protected $casts = ['lat' => 'float', 'lng' => 'float', 'recorded_at' => 'datetime'];

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function projectDay(): BelongsTo
    {
        return $this->belongsTo(ProjectDay::class);
    }
}
