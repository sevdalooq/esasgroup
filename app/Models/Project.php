<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'account_id',
        'offer_number',
        'name',
        'start_date',
        'end_date',
        'status',
        'approved_at',
        'approved_by',
        'estimated_cost',
        'offer_price',
        'delivery_type',
        'finalized_at',
        'finalized_by',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'approved_at' => 'datetime',
        'finalized_at' => 'datetime',
        'estimated_cost' => 'decimal:2',
        'offer_price' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function finalizedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function days(): HasMany
    {
        return $this->hasMany(ProjectDay::class)->orderBy('date');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function groupPayments(): HasMany
    {
        return $this->hasMany(GroupPayment::class);
    }

    public function customerPayments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function personnelPayments(): HasMany
    {
        return $this->hasMany(PersonnelPayment::class);
    }

    /**
     * Proje kaç gün sürecek
     */
    public function getDurationDaysAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * Tahmini maliyet hesapla
     */
    public function calculateEstimatedCost(): float
    {
        $personnelCost = 0;
        $inventoryCost = 0;

        foreach ($this->days as $day) {
            foreach ($day->personnel as $assignment) {
                $personnelCost += $assignment->daily_wage;
            }

            foreach ($day->inventory as $item) {
                if ($item->inventory->isRental()) {
                    $inventoryCost += $item->inventory->daily_rate * $item->quantity;
                }
            }
        }

        return $personnelCost + $inventoryCost;
    }

    /**
     * Proje muhasebeleştirilmiş mi
     */
    public function isFinalized(): bool
    {
        return $this->finalized_at !== null;
    }

    /**
     * Proje muhasebeleştirilebilir mi
     */
    public function canBeFinalized(): bool
    {
        return $this->status === 'completed' && !$this->isFinalized();
    }

    /**
     * Toplam personel maliyeti
     */
    public function getTotalPersonnelCostAttribute(): float
    {
        $total = 0;
        foreach ($this->days as $day) {
            foreach ($day->personnel as $assignment) {
                $total += $assignment->daily_wage;
                $total += $assignment->overtime_amount ?? 0;
            }
        }
        return $total;
    }

    /**
     * Toplam onaylı masraflar
     */
    public function getTotalApprovedExpensesAttribute(): float
    {
        $total = 0;
        foreach ($this->days as $day) {
            foreach ($day->expenses as $expense) {
                if ($expense->status === 'approved') {
                    $total += $expense->amount;
                }
            }
        }
        return $total;
    }

    /**
     * Müşteriden alınan toplam ödeme
     */
    public function getTotalCustomerPaymentsAttribute(): float
    {
        return $this->customerPayments()->sum('amount');
    }

    /**
     * Proje onaylanmış mı
     */
    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Proje onay bekliyor mu
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Projeyi onayla
     */
    public function approve(int $userId): void
    {
        $this->update([
            'approved_at' => now(),
            'approved_by' => $userId,
            'status' => 'approved',
        ]);
    }

    /**
     * Otomatik teklif numarası oluştur
     */
    public static function generateOfferNumber(): string
    {
        $year = date('Y');
        $lastProject = self::whereYear('created_at', $year)
            ->whereNotNull('offer_number')
            ->orderBy('offer_number', 'desc')
            ->first();

        if ($lastProject && preg_match('/(\d+)$/', $lastProject->offer_number, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('TKL-%s-%04d', $year, $nextNumber);
    }
}
