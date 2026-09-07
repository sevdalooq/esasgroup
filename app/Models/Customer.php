<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'tax_number',
        'mernis_no',
        'trade_registry_no',
        'trade_registry_office',
        'tax_office',
        'address',
        'phone',
        'email',
        'iban',
        'description',
        'is_e_invoice',
        'is_e_archive',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_e_invoice' => 'boolean',
        'is_e_archive' => 'boolean',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function primaryContact()
    {
        return $this->contacts()->where('is_primary', true)->first();
    }

    /**
     * Toplam alınan ödeme
     */
    public function getTotalPaymentsAttribute(): float
    {
        return $this->payments()->sum('amount');
    }
}
