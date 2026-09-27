<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kara liste talebi (type=blacklist) veya kara listedeki personelin projeye atanma onayı (type=assignment).
 */
class PersonnelBlacklistRequest extends Model
{
    public const TYPE_BLACKLIST = 'blacklist';
    public const TYPE_ASSIGNMENT = 'assignment';

    protected $fillable = [
        'personnel_id', 'type', 'project_id', 'project_day_personnel_id', 'reason',
        'status', 'requested_by', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ProjectDayPersonnel::class, 'project_day_personnel_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
