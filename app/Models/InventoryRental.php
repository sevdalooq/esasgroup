<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dışarıdan kiralanan envanter kaydı */
class InventoryRental extends Model
{
    protected $fillable = [
        'project_id', 'project_day_id', 'item_name', 'quantity', 'supplier', 'supplier_phone',
        'rented_at', 'due_date', 'returned_at', 'daily_cost', 'total_cost', 'notes', 'return_notes',
        'created_by', 'returned_by',
    ];

    protected $casts = [
        'rented_at' => 'date:Y-m-d',
        'due_date' => 'date:Y-m-d',
        'returned_at' => 'datetime',
        'daily_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    protected $appends = ['status'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function projectDay(): BelongsTo
    {
        return $this->belongsTo(ProjectDay::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    /** rented | overdue | returned */
    public function getStatusAttribute(): string
    {
        if ($this->returned_at) {
            return 'returned';
        }
        if ($this->due_date && $this->due_date->lt(today())) {
            return 'overdue';
        }

        return 'rented';
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('returned_at');
    }
}
