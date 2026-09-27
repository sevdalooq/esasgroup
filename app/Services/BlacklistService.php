<?php

namespace App\Services;

use App\Models\Personnel;
use App\Models\PersonnelBlacklistRequest;
use App\Models\Project;
use App\Models\ProjectDayPersonnel;
use App\Models\User;
use App\Notifications\GenericAppNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kara liste akışı:
 *  - Saha sorumlusu kara liste talebi açar → yönetici onaylar → personel kara listeye girer.
 *  - Kara listedeki personel bir güne atanırsa atama "onay bekliyor" olur; yönetici onaylayana kadar giriş yapılamaz.
 *  - personnel.blacklist_approve yetkisi olan kullanıcıların işlemleri anında uygulanır.
 */
class BlacklistService
{
    public const APPROVE_PERMISSION = 'personnel.blacklist_approve';

    /** Kara liste talebi (onay yetkisi varsa anında uygulanır) */
    public function requestBlacklist(Personnel $personnel, User $user, ?string $reason): PersonnelBlacklistRequest
    {
        if ($personnel->is_blacklisted) {
            throw ValidationException::withMessages(['personnel' => 'Bu personel zaten kara listede.']);
        }

        $existing = $personnel->blacklistRequests()->pending()->where('type', PersonnelBlacklistRequest::TYPE_BLACKLIST)->first();
        if ($existing) {
            throw ValidationException::withMessages(['personnel' => 'Bu personel için bekleyen bir kara liste talebi zaten var.']);
        }

        return DB::transaction(function () use ($personnel, $user, $reason) {
            $request = $personnel->blacklistRequests()->create([
                'type' => PersonnelBlacklistRequest::TYPE_BLACKLIST,
                'reason' => $reason,
                'status' => 'pending',
                'requested_by' => $user->id,
            ]);

            if ($user->hasPermission(self::APPROVE_PERMISSION)) {
                $this->approve($request, $user, 'Yetkili tarafından doğrudan eklendi.');
            } else {
                $this->notifyApprovers(
                    'Kara liste talebi',
                    sprintf('%s, "%s" adlı personel için kara liste talebi oluşturdu. Sebep: %s', $user->name, $personnel->full_name, $reason ?: '-'),
                    ['blacklist_request_id' => $request->id, 'personnel_id' => $personnel->id]
                );
            }

            return $request->fresh();
        });
    }

    /** Kara listeden çıkar (sadece onay yetkisi) */
    public function removeFromBlacklist(Personnel $personnel, User $user, ?string $note = null): void
    {
        DB::transaction(function () use ($personnel, $user, $note) {
            Personnel::whereKey($personnel->id)->update(['is_blacklisted' => false, 'blacklisted_at' => null, 'blacklist_reason' => null]);
            $personnel->refresh();
            $personnel->blacklistRequests()->create([
                'type' => 'unblacklist',
                'reason' => $note,
                'status' => 'approved',
                'requested_by' => $user->id,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);
        });
    }

    /**
     * Kara listedeki personel bir güne atanırken çağrılır.
     * Atama onay bekliyor durumuna alınır ve yöneticilere bildirim gider (yetkili kullanıcıda anında onaylı).
     * @return bool onay bekliyor mu
     */
    public function handleBlacklistedAssignment(ProjectDayPersonnel $assignment, User $user, ?string $reason = null): bool
    {
        $assignment->loadMissing('personnel', 'projectDay.project');
        $personnel = $assignment->personnel;

        if (!$personnel?->is_blacklisted || $user->hasPermission(self::APPROVE_PERMISSION)) {
            if ($assignment->approval_status !== 'approved') {
                $assignment->update(['approval_status' => 'approved']);
            }

            return false;
        }

        $assignment->update(['approval_status' => 'pending']);

        $project = $assignment->projectDay->project;
        $request = PersonnelBlacklistRequest::create([
            'personnel_id' => $personnel->id,
            'type' => PersonnelBlacklistRequest::TYPE_ASSIGNMENT,
            'project_id' => $project->id,
            'project_day_personnel_id' => $assignment->id,
            'reason' => $reason,
            'status' => 'pending',
            'requested_by' => $user->id,
        ]);

        $this->notifyApprovers(
            'Kara listeden personel ataması onay bekliyor',
            sprintf('%s, kara listedeki "%s" adlı personeli "%s" projesinin %s gününe eklemek istiyor.',
                $user->name, $personnel->full_name, $project->name, $assignment->projectDay->date?->format('d.m.Y')),
            ['blacklist_request_id' => $request->id, 'project_id' => $project->id, 'personnel_id' => $personnel->id]
        );

        return true;
    }

    public function approve(PersonnelBlacklistRequest $request, User $reviewer, ?string $note = null): PersonnelBlacklistRequest
    {
        $this->ensurePending($request);

        return DB::transaction(function () use ($request, $reviewer, $note) {
            $request->update(['status' => 'approved', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note]);

            if ($request->type === PersonnelBlacklistRequest::TYPE_BLACKLIST) {
                Personnel::whereKey($request->personnel_id)->update([
                    'is_blacklisted' => true,
                    'blacklisted_at' => now(),
                    'blacklist_reason' => $request->reason,
                ]);
                $request->personnel->refresh();
                $this->notifyRequester($request, 'Kara liste talebiniz onaylandı',
                    sprintf('"%s" kara listeye alındı.', $request->personnel->full_name));
            } else {
                $request->assignment?->update(['approval_status' => 'approved']);
                $this->notifyRequester($request, 'Atama onaylandı',
                    sprintf('Kara listedeki "%s" için "%s" projesine atama onaylandı.', $request->personnel->full_name, $request->project?->name));
            }

            return $request->fresh(['personnel', 'project', 'requester', 'reviewer']);
        });
    }

    public function reject(PersonnelBlacklistRequest $request, User $reviewer, ?string $note = null): PersonnelBlacklistRequest
    {
        $this->ensurePending($request);

        return DB::transaction(function () use ($request, $reviewer, $note) {
            $request->update(['status' => 'rejected', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note]);

            if ($request->type === PersonnelBlacklistRequest::TYPE_ASSIGNMENT && $request->assignment) {
                // Reddedilen atama kaldırılır (henüz giriş yapılmamış)
                $request->assignment->update(['approval_status' => 'rejected']);
                if (!$request->assignment->check_in_time) {
                    $request->assignment->delete();
                }
                $this->notifyRequester($request, 'Atama reddedildi',
                    sprintf('Kara listedeki "%s" için "%s" projesine atama reddedildi. %s', $request->personnel->full_name, $request->project?->name, $note ?: ''));
            } else {
                $this->notifyRequester($request, 'Kara liste talebiniz reddedildi',
                    sprintf('"%s" için kara liste talebi reddedildi. %s', $request->personnel->full_name, $note ?: ''));
            }

            return $request->fresh(['personnel', 'project', 'requester', 'reviewer']);
        });
    }

    /** Onay bekleyen atama giriş yapamaz */
    public static function assertCanCheckIn(ProjectDayPersonnel $assignment): void
    {
        if ($assignment->approval_status === 'pending') {
            throw ValidationException::withMessages([
                'personnel_id' => ($assignment->personnel?->full_name ?? 'Personel').' kara listede; ataması yönetici onayı bekliyor, giriş yapılamaz.',
            ]);
        }
        if ($assignment->approval_status === 'rejected') {
            throw ValidationException::withMessages([
                'personnel_id' => ($assignment->personnel?->full_name ?? 'Personel').' için atama yönetici tarafından reddedildi.',
            ]);
        }
    }

    private function ensurePending(PersonnelBlacklistRequest $request): void
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'Bu talep zaten sonuçlandırılmış.']);
        }
    }

    private function notifyApprovers(string $title, string $body, array $payload): void
    {
        $users = User::where('is_active', true)->get()->filter(fn (User $u) => $u->hasPermission(self::APPROVE_PERMISSION));
        foreach ($users as $u) {
            try {
                $u->notify(new GenericAppNotification($title, $body, $payload));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    private function notifyRequester(PersonnelBlacklistRequest $request, string $title, string $body): void
    {
        if (!$request->requester || $request->requested_by === $request->reviewed_by) {
            return;
        }
        try {
            $request->requester->notify(new GenericAppNotification($title, $body, ['blacklist_request_id' => $request->id, 'personnel_id' => $request->personnel_id, 'project_id' => $request->project_id]));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
