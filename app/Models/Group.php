<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'contact_person',
        'phone',
        'email',
        'commission_type',
        'commission_value',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'commission_value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function personnel(): HasMany
    {
        return $this->hasMany(Personnel::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(GroupPayment::class);
    }

    /**
     * Komisyon hesapla
     */
    public function calculateCommission(float $baseAmount): float
    {
        return match ($this->commission_type) {
            'fixed' => $this->commission_value,
            'percentage' => $baseAmount * ($this->commission_value / 100),
            'custom' => $this->commission_value, // Özel anlaşma için manuel değer
            default => 0,
        };
    }
}
