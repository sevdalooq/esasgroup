<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'date',
        'supervisor_id',
        'status',
        'start_photo',
        'end_photo',
        'notes',
        'venue_lat',
        'venue_lng',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function personnelAssignments(): HasMany
    {
        return $this->hasMany(ProjectDayPersonnel::class);
    }

    public function inventoryAssignments(): HasMany
    {
        return $this->hasMany(ProjectDayInventory::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ProjectExpense::class);
    }

    /**
     * Gün tamamlandı mı
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Günün toplam personel maliyeti
     */
    public function getTotalPersonnelCostAttribute(): float
    {
        return $this->personnelAssignments->sum('daily_wage');
    }
}
