<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonnelWorkHistory extends Model
{
    protected $table = 'personnel_work_history';

    protected $fillable = [
        'personnel_id',
        'company_name',
        'phone',
        'position',
        'leaving_reason',
        'last_salary',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'last_salary' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }
}
