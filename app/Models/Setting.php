<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'display_name',
        'description',
    ];

    /**
     * Cache key prefix
     */
    private const CACHE_PREFIX = 'settings_';
    private const CACHE_TTL = 3600; // 1 saat

    /**
     * Ayar değerini tipine göre dönüştür
     */
    public function getTypedValueAttribute()
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($this->value) ? (float) $this->value : null,
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }

    /**
     * Bir ayar değerini al
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = self::CACHE_PREFIX . $key;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            return $setting ? $setting->typed_value : $default;
        });
    }

    /**
     * Bir ayar değerini ayarla
     */
    public static function set(string $key, mixed $value, string $type = 'string', string $group = 'general'): self
    {
        // JSON tipinde değeri encode et
        if ($type === 'json' && is_array($value)) {
            $value = json_encode($value);
        }

        // Boolean tipinde değeri string'e çevir
        if ($type === 'boolean') {
            $value = $value ? 'true' : 'false';
        }

        $setting = self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'group' => $group,
            ]
        );

        // Cache'i temizle
        Cache::forget(self::CACHE_PREFIX . $key);
        Cache::forget(self::CACHE_PREFIX . 'group_' . $group);

        return $setting;
    }

    /**
     * Gruba göre tüm ayarları al
     */
    public static function getByGroup(string $group): array
    {
        $cacheKey = self::CACHE_PREFIX . 'group_' . $group;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($group) {
            $settings = self::where('group', $group)->get();
            $result = [];

            foreach ($settings as $setting) {
                $result[$setting->key] = $setting->typed_value;
            }

            return $result;
        });
    }

    /**
     * Tüm ayarları gruplandırılmış olarak al
     */
    public static function getAllGrouped(): array
    {
        $settings = self::orderBy('group')->orderBy('key')->get();
        $result = [];

        foreach ($settings as $setting) {
            if (!isset($result[$setting->group])) {
                $result[$setting->group] = [];
            }
            $result[$setting->group][$setting->key] = [
                'value' => $setting->typed_value,
                'type' => $setting->type,
                'display_name' => $setting->display_name,
                'description' => $setting->description,
            ];
        }

        return $result;
    }

    /**
     * Tüm cache'i temizle
     */
    public static function clearCache(): void
    {
        $settings = self::all();
        foreach ($settings as $setting) {
            Cache::forget(self::CACHE_PREFIX . $setting->key);
            Cache::forget(self::CACHE_PREFIX . 'group_' . $setting->group);
        }
    }
}
