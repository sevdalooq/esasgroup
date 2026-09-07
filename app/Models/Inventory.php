<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    use \App\Models\Concerns\HasQrCode;

    public const QR_PREFIX = 'INV';

    use HasFactory, SoftDeletes;

    protected $table = 'inventory';

    protected $fillable = [
        'qr_code',
        'nfc_uid',
        'name',
        'type',
        'unit',
        'unit_price',
        'serial_number',
        'daily_rate',
        'purchase_cost',
        'current_status',
        'current_holder_id',
        'notes',
    ];

    protected $appends = ['qr_payload'];

    protected $casts = [
        'daily_rate' => 'decimal:2',
        'purchase_cost' => 'decimal:2',
        'unit_price' => 'decimal:2',
    ];

    public function currentHolder(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'current_holder_id');
    }

    public function projectDayUsages(): HasMany
    {
        return $this->hasMany(ProjectDayInventory::class);
    }

    /**
     * Kiralık malzeme mi?
     */
    public function isRental(): bool
    {
        return $this->type === 'rental';
    }

    /**
     * Zimmetli malzeme mi?
     */
    public function isZimmet(): bool
    {
        return $this->type === 'zimmet';
    }

    /**
     * Kullanılabilir mi?
     */
    public function isAvailable(): bool
    {
        return $this->current_status === 'available';
    }
}
