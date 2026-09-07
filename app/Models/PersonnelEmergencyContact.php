<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonnelEmergencyContact extends Model
{
    protected $fillable = [
        'personnel_id',
        'name',
        'relationship',
        'address',
        'phone',
    ];

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }
}
