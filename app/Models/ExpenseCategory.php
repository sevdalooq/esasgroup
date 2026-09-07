<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'color',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Bu kategoriye ait giderler
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(ProjectExpense::class, 'category', 'slug');
    }

    /**
     * Aktif kategorileri getir
     */
    public static function getActive()
    {
        return self::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Bu kategorinin kullanımda olup olmadığını kontrol et
     */
    public function isInUse(): bool
    {
        return ProjectExpense::where('category', $this->slug)->exists();
    }

    /**
     * Kullanım sayısını getir
     */
    public function getUsageCountAttribute(): int
    {
        return ProjectExpense::where('category', $this->slug)->count();
    }
}
