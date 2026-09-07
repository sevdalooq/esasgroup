<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonnelChild extends Model
{
    protected $fillable = [
        'personnel_id',
        'name',
        'birth_date',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }
}
