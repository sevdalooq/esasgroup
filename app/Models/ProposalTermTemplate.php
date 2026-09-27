<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ayarlarda tanımlanan standart teklif şartı; her yeni projeye kopyalanır. */
class ProposalTermTemplate extends Model
{
    protected $fillable = ['title', 'body', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
