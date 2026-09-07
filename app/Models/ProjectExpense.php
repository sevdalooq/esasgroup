<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_day_id',
        'description',
        'amount',
        'category',
        'receipt_photo',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function projectDay(): BelongsTo
    {
        return $this->belongsTo(ProjectDay::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Onay bekliyor mu
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Onaylanmış mı
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Reddedilmiş mi
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Masrafı onayla ve kasadan düş
     */
    public function approve(int $userId): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        // Projenin kasasından gideri düş
        $project = $this->projectDay->project;
        if ($project->account_id) {
            Transaction::create([
                'account_id' => $project->account_id,
                'type' => 'out',
                'amount' => $this->amount,
                'category' => 'project_expense',
                'reference_type' => 'project_expense',
                'reference_id' => $this->id,
                'description' => "Proje gideri: {$project->name} - {$this->description}",
                'date' => now(),
            ]);
        }
    }

    /**
     * Masrafı reddet
     */
    public function reject(int $userId, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'approved_by' => $userId,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }
}
