<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'project_id',
        'invoice_no',
        'type',
        'subtotal',
        'tax_rate',
        'total',
        'status',
        'invoice_date',
        'due_date',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'total' => 'decimal:2',
        'invoice_date' => 'date',
        'due_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Satış faturası mı
     */
    public function isSales(): bool
    {
        return $this->type === 'sales';
    }

    /**
     * Alış faturası mı
     */
    public function isPurchase(): bool
    {
        return $this->type === 'purchase';
    }

    /**
     * Vergi tutarı
     */
    public function getTaxAmountAttribute(): float
    {
        return $this->subtotal * ($this->tax_rate / 100);
    }
}
