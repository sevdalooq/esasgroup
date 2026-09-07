<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * QR/NFC etiketi taşıyan modeller için ortak davranış.
 * qr_code kolonu UUID olarak otomatik üretilir; etiket içeriği "ESAS:<PREFIX>:<uuid>" biçimindedir.
 */
trait HasQrCode
{
    public static function bootHasQrCode(): void
    {
        static::creating(function ($model) {
            if (empty($model->qr_code)) {
                $model->qr_code = (string) Str::uuid();
            }
        });
    }

    /** Etikete basılacak metin (QR içeriği / NFC payload) */
    public function getQrPayloadAttribute(): string
    {
        return 'ESAS:' . static::QR_PREFIX . ':' . $this->qr_code;
    }

    /** "ESAS:INV:uuid" -> ['INV', 'uuid'] ; geçersizse null */
    public static function parseQrPayload(?string $payload): ?array
    {
        if (!$payload || !preg_match('/^ESAS:(INV|PER|ZONE):([0-9a-fA-F-]{36})$/', trim($payload), $m)) {
            return null;
        }

        return ['type' => $m[1], 'uuid' => $m[2]];
    }

    public function scopeByQr($query, string $uuid)
    {
        return $query->where('qr_code', $uuid);
    }
}
