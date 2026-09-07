<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZoneOption extends Model
{
    use HasFactory, \App\Models\Concerns\HasQrCode;

    public const QR_PREFIX = 'ZONE';

    protected $appends = ['qr_payload'];

    protected $fillable = [
        'qr_code',
        'project_id',
        'name',
        'usage_count',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Bölge kullanım sayısını artır
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Proje ve global bölgeleri getir
     */
    public static function getSuggestions(?int $projectId, ?string $search = null, int $limit = 10): array
    {
        $query = self::query()
            ->where(function ($q) use ($projectId) {
                $q->whereNull('project_id')
                    ->orWhere('project_id', $projectId);
            })
            ->orderByDesc('usage_count')
            ->limit($limit);

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->pluck('name')->toArray();
    }

    /**
     * Bölge ekle veya güncelle
     */
    public static function addOrUpdate(?int $projectId, string $name): self
    {
        $zone = self::firstOrNew([
            'project_id' => $projectId,
            'name' => $name,
        ]);

        if ($zone->exists) {
            $zone->incrementUsage();
        } else {
            $zone->usage_count = 1;
            $zone->save();
        }

        return $zone;
    }
}
