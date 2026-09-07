<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'project_id',
        'account_id',
        'amount',
        'payment_date',
        'payment_method',
        'receipt_no',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Ödeme yöntemi metni
     */
    public function getPaymentMethodTextAttribute(): string
    {
        return match ($this->payment_method) {
            'cash' => 'Nakit',
            'bank' => 'Banka Transferi',
            'check' => 'Çek',
            'credit_card' => 'Kredi Kartı',
            default => $this->payment_method,
        };
    }
}
