<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryDamage extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_day_inventory_id',
        'description',
        'photo',
        'deduction_amount',
    ];

    protected $casts = [
        'deduction_amount' => 'decimal:2',
    ];

    public function projectDayInventory(): BelongsTo
    {
        return $this->belongsTo(ProjectDayInventory::class);
    }
}
