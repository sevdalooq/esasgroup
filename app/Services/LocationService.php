<?php

namespace App\Services;

use App\Events\PersonnelLocationUpdated;
use App\Models\Personnel;
use App\Models\PersonnelLocation;
use App\Models\ZoneOption;

/**
 * Personel konum kaydı: cihaz GPS'inden ya da okutulan alan QR'ının koordinatından.
 */
class LocationService
{
    public function record(Personnel $personnel, ?int $projectDayId, float $lat, float $lng, ?int $accuracy = null, string $source = 'gps', $recordedAt = null): PersonnelLocation
    {
        $location = PersonnelLocation::create([
            'personnel_id' => $personnel->id,
            'project_day_id' => $projectDayId,
            'lat' => $lat,
            'lng' => $lng,
            'accuracy' => $accuracy,
            'source' => $source,
            'recorded_at' => $recordedAt ?? now(),
        ]);

        $personnel->forceFill([
            'last_lat' => $lat,
            'last_lng' => $lng,
            'last_location_at' => $location->recorded_at,
        ])->save();

        app()->terminating(function () use ($location) {
            try {
                event(new PersonnelLocationUpdated($location));
            } catch (\Throwable $e) {
                report($e);
            }
        });

        return $location;
    }

    /**
     * Giriş sırasında konum: GPS verilmişse o, yoksa alanın koordinatı (varsa).
     */
    public function recordOnCheckIn(Personnel $personnel, int $projectDayId, ?ZoneOption $zone, ?float $lat, ?float $lng, ?int $accuracy = null): ?PersonnelLocation
    {
        if ($lat !== null && $lng !== null) {
            return $this->record($personnel, $projectDayId, $lat, $lng, $accuracy, 'gps');
        }
        if ($zone && $zone->lat !== null && $zone->lng !== null) {
            return $this->record($personnel, $projectDayId, (float) $zone->lat, (float) $zone->lng, null, 'zone');
        }

        return null;
    }
}
