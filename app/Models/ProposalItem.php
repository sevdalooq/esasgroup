<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Teklif satırı. Birim fiyat varsa toplam = birim × miktar × gün, yoksa toplam elle girilir. */
class ProposalItem extends Model
{
    protected $fillable = [
        'proposal_section_id', 'description', 'note', 'duration_label',
        'quantity', 'days', 'unit_price', 'total_price', 'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(ProposalSection::class, 'proposal_section_id');
    }

    public static function computeTotal(?float $unitPrice, float $quantity, int $days, ?float $manualTotal): float
    {
        if ($unitPrice !== null && $unitPrice > 0) {
            return round($unitPrice * $quantity * max($days, 1), 2);
        }

        return round((float) ($manualTotal ?? 0), 2);
    }
}
