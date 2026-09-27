<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Gönderilmiş hatırlatma kaydı (aynı hatırlatma ikinci kez gitmesin). */
class ReminderLog extends Model
{
    protected $fillable = ['project_id', 'project_day_id', 'type', 'subject_key', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];

    public static function alreadySent(int $projectId, ?int $dayId, string $type, ?string $subjectKey = null): bool
    {
        return static::where('project_id', $projectId)
            ->where('project_day_id', $dayId)
            ->where('type', $type)
            ->where('subject_key', $subjectKey)
            ->exists();
    }

    public static function record(int $projectId, ?int $dayId, string $type, ?string $subjectKey = null): void
    {
        static::create([
            'project_id' => $projectId,
            'project_day_id' => $dayId,
            'type' => $type,
            'subject_key' => $subjectKey,
            'sent_at' => now(),
        ]);
    }
}
