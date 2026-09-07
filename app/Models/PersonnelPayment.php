<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonnelPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'personnel_id',
        'account_id',
        'project_id',
        'type',
        'amount',
        'date',
        'description',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
    ];

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Alacak kaydı mı (personelin kazandığı)
     */
    public function isDebit(): bool
    {
        return $this->type === 'debit';
    }

    /**
     * Ödeme kaydı mı (personele ödenen)
     */
    public function isCredit(): bool
    {
        return $this->type === 'credit';
    }
}
