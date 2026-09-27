<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Teklifteki bir kategori tablosu (Personel Hizmeti, Bariyer Kiralama...). */
class ProposalSection extends Model
{
    protected $fillable = [
        'project_id', 'title', 'unit_label', 'show_duration', 'show_days', 'show_unit_price', 'sort_order',
    ];

    protected $casts = [
        'show_duration' => 'boolean',
        'show_days' => 'boolean',
        'show_unit_price' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProposalItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function getTotalAttribute(): float
    {
        return (float) $this->items->sum('total_price');
    }
}
