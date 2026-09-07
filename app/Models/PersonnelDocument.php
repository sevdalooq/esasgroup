<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonnelDocument extends Model
{
    public const TYPES = [
        'ogg_card' => 'ÖGG Kimlik Kartı',
        'cv' => 'Özgeçmiş (CV)',
        'health_report' => 'Sağlık Raporu',
        'criminal_record' => 'Adli Sicil Kaydı',
        'diploma' => 'Diploma',
        'other' => 'Diğer',
    ];

    protected $fillable = ['personnel_id', 'type', 'name', 'file_path', 'mime_type', 'size', 'expires_at', 'is_verified'];

    protected $casts = ['expires_at' => 'date', 'is_verified' => 'boolean'];

    protected $appends = ['url', 'type_label'];

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function getUrlAttribute(): ?string
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : null;
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
