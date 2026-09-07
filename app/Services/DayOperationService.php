<?php

namespace App\Services;

use App\Models\InventoryDamage;
use App\Models\ProjectDay;
use App\Models\ProjectDayInventory;
use App\Models\ProjectDayPersonnel;
use App\Models\ZoneOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Gün operasyonları (gün başlat/bitir, check-in/out, zimmet teslim/iade) için ortak iş mantığı.
 * Muhasebe kayıtları (hakediş/komisyon) burada değil, AccountingService::finalizeProject içinde oluşur.
 * Hem web sihirbazı (DayOperationsController) hem saha ekranı (FieldController) bu servisi kullanır.
 */
class DayOperationService
{
    /**
     * Base64 (data:image/...) ise dosyaya kaydeder, aksi halde verilen yolu döndürür.
     */
    public function savePhoto(?string $photo, string $folder): ?string
    {
        if (!$photo) {
            return null;
        }

        if (str_starts_with($photo, 'data:image')) {
            $image = preg_replace('/^data:image\/\w+;base64,/', '', $photo);
            $image = base64_decode($image);
            $filename = uniqid() . '_' . time() . '.jpg';
            $path = $folder . '/' . $filename;
            Storage::disk('public')->put($path, $image);

            return $path;
        }

        return $photo;
    }

    /**
     * İstekteki fotoğrafı (multipart dosya veya base64 string) kaydeder.
     */
    public function storeRequestPhoto(Request $request, string $field, string $folder): ?string
    {
        if ($request->hasFile($field)) {
            return $request->file($field)->store($folder, 'public');
        }

        $value = $request->input($field);

        return is_string($value) && $value !== '' ? $this->savePhoto($value, $folder) : null;
    }

    /**
     * Günü başlat.
     */
    public function start(ProjectDay $projectDay, ?string $photoPath): ProjectDay
    {
        if ($projectDay->status === 'completed') {
            throw ValidationException::withMessages(['status' => 'Bu gün zaten kapatılmış, tekrar başlatılamaz.']);
        }

        $data = ['status' => 'active'];
        if ($photoPath) {
            $data['start_photo'] = $photoPath;
        }

        $projectDay->update($data);

        return $projectDay;
    }

    /**
     * Günü bitir: tüm check-in'ler check-out olmuş olmalı; hakedişler muhasebeye yazılır.
     *
     * @return array{day: ProjectDay, summary: array}
     */
    public function end(ProjectDay $projectDay, ?string $photoPath): array
    {
        if ($projectDay->status === 'completed') {
            throw ValidationException::withMessages(['status' => 'Bu gün zaten kapatılmış.']);
        }

        $pendingCheckouts = $projectDay->personnelAssignments()
            ->whereNotNull('check_in_time')
            ->whereNull('check_out_time')
            ->count();

        if ($pendingCheckouts > 0) {
            throw ValidationException::withMessages([
                'status' => "{$pendingCheckouts} personelin çıkışı henüz yapılmadı.",
            ]);
        }

        // Not: hakediş/komisyon kayıtları burada oluşturulmaz; proje kapanışında
        // AccountingService::finalizeProject() tek yetkili adımdır.
        $data = ['status' => 'completed'];
        if ($photoPath) {
            $data['end_photo'] = $photoPath;
        }
        $projectDay->update($data);

        return [
            'day' => $projectDay,
            'summary' => $this->calculateDaySummary($projectDay->fresh()),
        ];
    }

    /**
     * Personel giriş (check-in).
     */
    public function checkIn(ProjectDayPersonnel $assignment, ?string $zone, bool $isChecked = true, ?string $photoPath = null): ProjectDayPersonnel
    {
        $data = [
            'check_in_time' => now(),
            'zone' => $zone ?: $assignment->zone,
            'is_checked' => $isChecked,
            'presence' => 'checked_in',
        ];
        if ($photoPath) {
            $data['check_in_photo'] = $photoPath;
        }

        $assignment->update($data);

        if ($zone) {
            ZoneOption::addOrUpdate($assignment->projectDay->project_id, $zone);
        }

        return $assignment;
    }

    /**
     * Personel çıkış (check-out) – saha ekranı için hafif sürüm (mesai/ödeme sonradan web panelinde girilir).
     */
    /**
     * Personel çıkış (check-out). Mesai, ödeme ve zimmet iadesi bilgileriyle birlikte.
     *
     * @param array{check_out_time?:string|null, overtime_hours?:float|null, overtime_rate?:float|null,
     *              payment_status?:string|null, payment_method?:string|null, payment_amount?:float|null,
     *              inventory_returns?:array<int, array{id:int, return_status:string, damage_description?:string|null, deduction_amount?:float|null}>} $options
     */
    public function checkOut(ProjectDayPersonnel $assignment, ?string $photoPath = null, array $options = []): ProjectDayPersonnel
    {
        if (!$assignment->check_in_time) {
            throw ValidationException::withMessages(['assignment' => 'Bu personel henüz giriş yapmamış.']);
        }

        return DB::transaction(function () use ($assignment, $photoPath, $options) {
            $overtimeHours = (float) ($options['overtime_hours'] ?? 0);
            $overtimeRate = $overtimeHours > 0
                ? (float) ($options['overtime_rate'] ?? $assignment->suggested_overtime_rate)
                : (float) ($options['overtime_rate'] ?? $assignment->overtime_rate ?? 0);
            $totalEarnings = (float) $assignment->daily_wage + ($overtimeHours * $overtimeRate);

            $paymentStatus = $options['payment_status'] ?? $assignment->payment_status ?? 'pending';
            $paymentAmount = $paymentStatus === 'pending' ? 0 : (float) ($options['payment_amount'] ?? 0);
            if ($paymentStatus === 'paid' && $paymentAmount <= 0) {
                $paymentAmount = $totalEarnings;
            }

            if ($assignment->presence === 'on_break') {
                $this->endBreak($assignment);
            }

            $data = [
                'presence' => 'checked_out',
                'check_out_time' => !empty($options['check_out_time']) ? $options['check_out_time'] : now(),
                'overtime_hours' => $overtimeHours,
                'overtime_rate' => $overtimeRate,
                'total_earnings' => $totalEarnings,
                'payment_status' => $paymentStatus,
                'payment_method' => $paymentStatus === 'pending' ? null : ($options['payment_method'] ?? 'cash'),
                'payment_amount' => $paymentAmount,
            ];
            if ($photoPath) {
                $data['check_out_photo'] = $photoPath;
            }
            $assignment->update($data);

            foreach ($options['inventory_returns'] ?? [] as $return) {
                $item = ProjectDayInventory::find($return['id']);
                if (!$item || $item->returned_at || !$item->delivered_at) {
                    continue;
                }
                $this->returnItem(
                    $item,
                    ($return['return_status'] ?? 'returned') === 'damaged',
                    $return['damage_description'] ?? null,
                    isset($return['deduction_amount']) ? (float) $return['deduction_amount'] : null,
                );
            }

            return $assignment->fresh();
        });
    }

    /**
     * Mola başlat.
     */
    public function startBreak(ProjectDayPersonnel $assignment, ?string $reason = null): ProjectDayPersonnel
    {
        if ($assignment->presence !== 'checked_in') {
            throw ValidationException::withMessages(['assignment' => 'Sadece sahada olan personel molaya çıkabilir.']);
        }

        return DB::transaction(function () use ($assignment, $reason) {
            $assignment->breaks()->create(['started_at' => now(), 'reason' => $reason]);
            $assignment->update(['presence' => 'on_break', 'break_started_at' => now()]);

            return $assignment->fresh();
        });
    }

    /**
     * Moladan dön.
     */
    public function endBreak(ProjectDayPersonnel $assignment): ProjectDayPersonnel
    {
        if ($assignment->presence !== 'on_break') {
            throw ValidationException::withMessages(['assignment' => 'Bu personel molada değil.']);
        }

        return DB::transaction(function () use ($assignment) {
            $break = $assignment->breaks()->whereNull('ended_at')->latest('started_at')->first();
            $minutes = 0;
            if ($break) {
                $break->update(['ended_at' => now()]);
                $minutes = (int) $break->started_at->diffInMinutes(now());
            }
            $assignment->update([
                'presence' => 'checked_in',
                'break_started_at' => null,
                'break_minutes' => $assignment->break_minutes + $minutes,
            ]);

            return $assignment->fresh();
        });
    }

    /**
     * Gelmedi olarak işaretle (veya geri al).
     */
    public function markAbsent(ProjectDayPersonnel $assignment, bool $absent = true): ProjectDayPersonnel
    {
        if ($assignment->check_in_time) {
            throw ValidationException::withMessages(['assignment' => 'Giriş yapmış personel gelmedi olarak işaretlenemez.']);
        }
        $assignment->update(['presence' => $absent ? 'absent' : 'assigned']);

        return $assignment->fresh();
    }

    /**
     * Zimmet/envanter teslim et. Envanter kartındaki durum ve sahip bilgisi de güncellenir.
     */
    public function deliver(ProjectDayInventory $assignment, ?ProjectDayPersonnel $toAssignment = null): ProjectDayInventory
    {
        if ($assignment->delivered_at && !$assignment->returned_at) {
            throw ValidationException::withMessages(['inventory' => 'Bu envanter zaten teslim edilmiş.']);
        }

        if ($assignment->returned_at) {
            throw ValidationException::withMessages(['inventory' => 'Bu envanter bugün teslim edilip iade alınmış.']);
        }

        return DB::transaction(function () use ($assignment, $toAssignment) {
            $assignment->update([
                'delivered_at' => now(),
                'delivered_by' => auth()->id(),
                'status' => 'delivered',
                'assigned_to_personnel_id' => $toAssignment?->id ?? $assignment->assigned_to_personnel_id,
            ]);

            $assignment->inventory()->update([
                'current_status' => 'in_use',
                'current_holder_id' => $toAssignment?->personnel_id ?? $assignment->assignedToPersonnel?->personnel_id,
            ]);

            return $assignment;
        });
    }

    /**
     * Zimmet/envanter iade al; hasarlıysa hasar kaydı oluşturur ve envanter durumunu "damaged" yapar.
     */
    public function returnItem(
        ProjectDayInventory $assignment,
        bool $damaged,
        ?string $damageDescription = null,
        ?float $deductionAmount = null,
        ?string $damagePhotoPath = null,
    ): ProjectDayInventory {
        if (!$assignment->delivered_at) {
            throw ValidationException::withMessages(['inventory' => 'Bu envanter bugün teslim edilmemiş, iade alınamaz.']);
        }

        if ($assignment->returned_at) {
            throw ValidationException::withMessages(['inventory' => 'Bu envanter zaten iade alınmış.']);
        }

        return DB::transaction(function () use ($assignment, $damaged, $damageDescription, $deductionAmount, $damagePhotoPath) {
            $status = $damaged ? 'damaged' : 'returned';

            $assignment->update([
                'returned_at' => now(),
                'returned_by' => auth()->id(),
                'return_status' => $status,
                'status' => $status,
                'damage_photo' => $damaged ? $damagePhotoPath : null,
                'damage_description' => $damaged ? $damageDescription : null,
            ]);

            if ($damaged) {
                InventoryDamage::create([
                    'project_day_inventory_id' => $assignment->id,
                    'description' => $damageDescription ?: 'Hasar bildirildi',
                    'photo' => $damagePhotoPath,
                    'deduction_amount' => $deductionAmount ?? 0,
                ]);
            }

            $assignment->inventory()->update([
                'current_status' => $damaged ? 'damaged' : 'available',
                'current_holder_id' => null,
            ]);

            return $assignment;
        });
    }

    /**
     * Gün özeti (sayaçlar ve tutarlar).
     */
    public function calculateDaySummary(ProjectDay $projectDay): array
    {
        $projectDay->loadMissing(['personnelAssignments', 'inventoryAssignments']);

        $personnelAssignments = $projectDay->personnelAssignments;
        $inventoryAssignments = $projectDay->inventoryAssignments;

        $totalEarnings = $personnelAssignments->sum('total_earnings');
        $totalPaid = $personnelAssignments->sum('payment_amount');
        $totalOvertime = $personnelAssignments->sum(fn ($pa) => $pa->overtime_hours * $pa->overtime_rate);
        $overtimeCount = $personnelAssignments->where('overtime_hours', '>', 0)->count();

        $returnedCount = $inventoryAssignments->where('return_status', 'returned')->count();
        $damagedCount = $inventoryAssignments->where('return_status', 'damaged')->count();
        $pendingReturns = $inventoryAssignments->where('return_status', 'pending')
            ->whereNotNull('delivered_at')->count();

        return [
            'personnel_count' => $personnelAssignments->count(),
            'checked_in_count' => $personnelAssignments->whereNotNull('check_in_time')->count(),
            'checked_out_count' => $personnelAssignments->whereNotNull('check_out_time')->count(),
            'total_earnings' => $totalEarnings,
            'total_paid' => $totalPaid,
            'total_pending' => $totalEarnings - $totalPaid,
            'total_overtime' => $totalOvertime,
            'overtime_personnel_count' => $overtimeCount,
            'inventory_count' => $inventoryAssignments->count(),
            'inventory_delivered' => $inventoryAssignments->whereNotNull('delivered_at')->count(),
            'inventory_returned' => $returnedCount,
            'inventory_damaged' => $damagedCount,
            'inventory_pending_return' => $pendingReturns,
        ];
    }
}
