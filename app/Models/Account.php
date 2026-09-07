<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'currency',
        'balance',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function personnelPayments(): HasMany
    {
        return $this->hasMany(PersonnelPayment::class);
    }

    public function customerPayments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function groupPayments(): HasMany
    {
        return $this->hasMany(GroupPayment::class);
    }

    /**
     * Nakit kasa mı
     */
    public function isCash(): bool
    {
        return $this->type === 'cash';
    }

    /**
     * Banka hesabı mı
     */
    public function isBank(): bool
    {
        return $this->type === 'bank';
    }

    /**
     * Bakiyeyi yeniden hesapla
     */
    public function recalculateBalance(): float
    {
        $income = $this->transactions()->where('type', 'in')->sum('amount');
        $expense = $this->transactions()->where('type', 'out')->sum('amount');

        $this->balance = $income - $expense;
        $this->save();

        return $this->balance;
    }
}
