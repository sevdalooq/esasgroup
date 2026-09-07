<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'project_id',
        'account_id',
        'amount',
        'type',
        'commission_base',
        'commission_type',
        'commission_rate',
        'payment_date',
        'payment_method',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'commission_base' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
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
     * Komisyon kaydı mı
     */
    public function isCommission(): bool
    {
        return $this->type === 'commission';
    }

    /**
     * Ödeme kaydı mı
     */
    public function isPayment(): bool
    {
        return $this->type === 'payment';
    }
}
