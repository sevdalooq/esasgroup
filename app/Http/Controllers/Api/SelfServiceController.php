<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use App\Models\ProjectDayPersonnel;
use App\Models\ZoneOption;
use App\Services\DayOperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Personel modu: görevlinin kendi telefonundan görevlerini görmesi, alan QR'ı ile "geldim" demesi, mola.
 */
class SelfServiceController extends Controller
{
    public function __construct(private readonly DayOperationService $dayOps)
    {
    }

    public function assignments(Request $request): JsonResponse
    {
        $personnel = $this->personnel($request);

        $assignments = ProjectDayPersonnel::query()
            ->where('personnel_id', $personnel->id)
            ->whereHas('projectDay', fn ($q) => $q->whereBetween('date', [today()->subDay(), today()->addDays(7)]))
            ->with(['projectDay:id,project_id,date,status,supervisor_id,notes,venue_lat,venue_lng', 'projectDay.project:id,name,customer_id,venue_address,venue_lat,venue_lng', 'projectDay.project.customer:id,name', 'projectDay.supervisor:id,name,phone'])
            ->get()
            ->sortBy(fn ($a) => $a->projectDay->date)
            ->values();

        return response()->json([
            'personnel' => $personnel->only(['id', 'first_name', 'last_name', 'full_name', 'photo', 'qr_payload']),
            'assignments' => $assignments,
            'today' => today()->toDateString(),
        ]);
    }

    /** Alan QR'ı okutarak giriş ("geldim") */
    public function checkIn(Request $request): JsonResponse
    {
        $personnel = $this->personnel($request);
        $data = $request->validate([
            'zone_payload' => 'nullable|string|max:255',
            'zone' => 'nullable|string|max:100',
            'project_day_id' => 'nullable|integer|exists:project_days,id',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        $assignment = $this->todaysAssignment($personnel, $data['project_day_id'] ?? null);

        $zone = $data['zone'] ?? null;
        $zoneOption = null;
        if (!empty($data['zone_payload'])) {
            $parsed = ZoneOption::parseQrPayload($data['zone_payload']);
            if (!$parsed || $parsed['type'] !== 'ZONE') {
                throw ValidationException::withMessages(['zone_payload' => 'Geçerli bir alan QR kodu değil.']);
            }
            $zoneOption = ZoneOption::byQr($parsed['uuid'])->first();
            if (!$zoneOption) {
                throw ValidationException::withMessages(['zone_payload' => 'Alan bulunamadı.']);
            }
            if ($zoneOption->project_id && $zoneOption->project_id !== $assignment->projectDay->project_id) {
                throw ValidationException::withMessages(['zone_payload' => 'Bu QR başka bir etkinliğe ait.']);
            }
            $zone = $zoneOption->name;
        }

        if ($assignment->check_in_time) {
            throw ValidationException::withMessages(['assignment' => 'Bugün zaten giriş yaptınız (' . $assignment->check_in_time->format('H:i') . ').']);
        }

        // Personelin kendi girişi supervisor doğrulaması bekler (is_checked=false)
        $assignment = $this->dayOps->checkIn($assignment, $zone, false, null);

        app(\App\Services\LocationService::class)->recordOnCheckIn(
            $personnel,
            $assignment->project_day_id,
            $zoneOption,
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null,
        );

        return response()->json(['message' => 'Girişiniz alındı' . ($zone ? " – {$zone}" : '') . '. Saha sorumlusu doğrulayacak.', 'assignment' => $assignment]);
    }

    public function breakStart(Request $request): JsonResponse
    {
        $personnel = $this->personnel($request);
        $assignment = $this->todaysAssignment($personnel, $request->integer('project_day_id') ?: null);
        $assignment = $this->dayOps->startBreak($assignment, $request->input('reason'));

        return response()->json(['message' => 'Mola başladı.', 'assignment' => $assignment]);
    }

    public function breakEnd(Request $request): JsonResponse
    {
        $personnel = $this->personnel($request);
        $assignment = $this->todaysAssignment($personnel, $request->integer('project_day_id') ?: null);
        $assignment = $this->dayOps->endBreak($assignment);

        return response()->json(['message' => 'Moladan dönüldü.', 'assignment' => $assignment]);
    }

    private function personnel(Request $request): Personnel
    {
        $personnel = Personnel::where('user_id', $request->user()->id)->first();
        if (!$personnel) {
            throw ValidationException::withMessages(['user' => 'Bu kullanıcı bir personel kaydına bağlı değil.']);
        }

        return $personnel;
    }

    private function todaysAssignment(Personnel $personnel, ?int $dayId): ProjectDayPersonnel
    {
        $query = ProjectDayPersonnel::where('personnel_id', $personnel->id)
            ->whereHas('projectDay', fn ($q) => $dayId ? $q->where('id', $dayId) : $q->whereDate('date', today()))
            ->with('projectDay');
        $assignment = $query->first();
        if (!$assignment) {
            throw ValidationException::withMessages(['assignment' => 'Bugün için görevlendirmeniz bulunmuyor.']);
        }
        if ($assignment->projectDay->status === 'completed') {
            throw ValidationException::withMessages(['assignment' => 'Bu gün kapatılmış.']);
        }

        return $assignment;
    }
}
