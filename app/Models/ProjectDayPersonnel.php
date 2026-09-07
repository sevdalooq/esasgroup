<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDayPersonnel extends Model
{
    use HasFactory;

    protected $table = 'project_day_personnel';

    protected $fillable = [
        'project_day_id',
        'personnel_id',
        'daily_wage',
        'overtime_hours',
        'overtime_rate',
        'total_earnings',
        'zone',
        'check_in_time',
        'check_in_photo',
        'check_out_time',
        'check_out_photo',
        'payment_status',
        'payment_method',
        'payment_amount',
        'notes',
        'is_checked',
    ];

    protected $casts = [
        'daily_wage' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'overtime_rate' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'payment_amount' => 'decimal:2',
        'is_checked' => 'boolean',
    ];

    public function projectDay(): BelongsTo
    {
        return $this->belongsTo(ProjectDay::class);
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    /**
     * Check-in yapıldı mı
     */
    public function hasCheckedIn(): bool
    {
        return $this->check_in_time !== null;
    }

    /**
     * Check-out yapıldı mı
     */
    public function hasCheckedOut(): bool
    {
        return $this->check_out_time !== null;
    }

    /**
     * Ödeme tamamlandı mı
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Kalan ödeme tutarı
     */
    public function getRemainingPaymentAttribute(): float
    {
        return $this->total_earnings - $this->payment_amount;
    }

    /**
     * Toplam hakediş hesapla (yevmiye + mesai)
     */
    public function calculateTotalEarnings(): float
    {
        $base = (float) $this->daily_wage;
        $overtime = (float) $this->overtime_hours * (float) $this->overtime_rate;
        return $base + $overtime;
    }

    /**
     * Mesai ücreti hesapla (saatlik ücret önerisi)
     */
    public function getSuggestedOvertimeRateAttribute(): float
    {
        // Yevmiyenin 8 saate bölünmesiyle saatlik ücret * 1.5 (mesai katsayısı)
        return ((float) $this->daily_wage / 8) * 1.5;
    }

    /**
     * Teslim edilen zimmetler
     */
    public function assignedInventory()
    {
        return $this->hasMany(ProjectDayInventory::class, 'assigned_to_personnel_id');
    }
}
