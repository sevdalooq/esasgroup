<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        // Transaction oluşturulduğunda bakiyeyi güncelle
        static::created(function (Transaction $transaction) {
            $transaction->account->recalculateBalance();
        });

        // Transaction güncellendiğinde bakiyeyi güncelle
        static::updated(function (Transaction $transaction) {
            $transaction->account->recalculateBalance();
            // Eğer account değiştiyse eski account'un bakiyesini de güncelle
            if ($transaction->wasChanged('account_id')) {
                $oldAccountId = $transaction->getOriginal('account_id');
                if ($oldAccountId) {
                    Account::find($oldAccountId)?->recalculateBalance();
                }
            }
        });

        // Transaction silindiğinde bakiyeyi güncelle
        static::deleted(function (Transaction $transaction) {
            $transaction->account->recalculateBalance();
        });
    }

    protected $fillable = [
        'account_id',
        'type',
        'amount',
        'category',
        'reference_type',
        'reference_id',
        'description',
        'date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Gelir mi
     */
    public function isIncome(): bool
    {
        return $this->type === 'in';
    }

    /**
     * Gider mi
     */
    public function isExpense(): bool
    {
        return $this->type === 'out';
    }
}
