<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectDayInventory extends Model
{
    use HasFactory;

    protected $table = 'project_day_inventory';

    protected $fillable = [
        'project_day_id',
        'inventory_id',
        'quantity',
        'assigned_to_personnel_id',
        'delivered_at',
        'delivered_by',
        'returned_at',
        'returned_by',
        'return_status',
        'damage_photo',
        'damage_description',
        'status',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function projectDay(): BelongsTo
    {
        return $this->belongsTo(ProjectDay::class);
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function deliveredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function returnedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function assignedToPersonnel(): BelongsTo
    {
        return $this->belongsTo(ProjectDayPersonnel::class, 'assigned_to_personnel_id');
    }

    public function damages(): HasMany
    {
        return $this->hasMany(InventoryDamage::class);
    }

    /**
     * Teslim edildi mi
     */
    public function isDelivered(): bool
    {
        return $this->status === 'delivered' || $this->status === 'returned' || $this->status === 'damaged';
    }

    /**
     * İade edildi mi
     */
    public function isReturned(): bool
    {
        return $this->status === 'returned';
    }

    /**
     * Hasarlı mı
     */
    public function isDamaged(): bool
    {
        return $this->status === 'damaged';
    }
}
