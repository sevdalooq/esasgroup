<?php

namespace App\Http\Controllers\Api;

use App\Events\PersonnelLocationUpdated;
use App\Http\Controllers\Controller;
use App\Models\Personnel;
use App\Models\PersonnelLocation;
use App\Models\ProjectDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Canlı izleme: aktif günler, personel durumları ve konumlar.
 * Anlık güncellemeler websocket (private-live / private-day.{id}) üzerinden gelir; bu uçlar ilk yükleme içindir.
 */
class LiveController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $days = ProjectDay::query()
            ->where(function ($q) {
                $q->where('status', 'active')
                  ->orWhereBetween('date', [today()->subDay(), today()->addDay()]);
            })
            ->with([
                'project:id,name,customer_id,status,venue_address,venue_lat,venue_lng',
                'project.customer:id,name',
                'supervisor:id,name',
                'personnelAssignments' => fn ($q) => $q->orderBy('zone')->orderBy('id'),
                'personnelAssignments.personnel:id,first_name,last_name,phone,photo,group_id,last_lat,last_lng,last_location_at',
                'personnelAssignments.personnel.group:id,name',
                'inventoryAssignments:id,project_day_id,status,return_status',
                'expenses:id,project_day_id,amount,status',
            ])
            ->orderByRaw("FIELD(status, 'active', 'pending', 'completed')")
            ->orderBy('date')
            ->get();

        $payload = $days->map(function (ProjectDay $day) {
            $assignments = $day->personnelAssignments;
            $counts = [
                'assigned' => $assignments->where('presence', 'assigned')->count(),
                'checked_in' => $assignments->where('presence', 'checked_in')->count(),
                'on_break' => $assignments->where('presence', 'on_break')->count(),
                'checked_out' => $assignments->where('presence', 'checked_out')->count(),
                'absent' => $assignments->where('presence', 'absent')->count(),
                'total' => $assignments->count(),
            ];

            return [
                'id' => $day->id,
                'date' => $day->date?->toDateString(),
                'status' => $day->status,
                'start_photo' => $day->start_photo,
                'venue_lat' => $day->venue_lat ?? $day->project?->venue_lat,
                'venue_lng' => $day->venue_lng ?? $day->project?->venue_lng,
                'project' => [
                    'id' => $day->project_id,
                    'name' => $day->project?->name,
                    'customer' => $day->project?->customer?->name,
                    'venue_address' => $day->project?->venue_address,
                ],
                'supervisor' => $day->supervisor?->only(['id', 'name']),
                'counts' => $counts,
                'inventory' => [
                    'total' => $day->inventoryAssignments->count(),
                    'delivered' => $day->inventoryAssignments->whereIn('status', ['delivered'])->count(),
                    'returned' => $day->inventoryAssignments->whereIn('status', ['returned', 'damaged'])->count(),
                ],
                'expenses_total' => (float) $day->expenses->sum('amount'),
                'personnel' => $assignments->map(fn ($a) => [
                    'assignment_id' => $a->id,
                    'personnel_id' => $a->personnel_id,
                    'name' => $a->personnel?->full_name,
                    'photo' => $a->personnel?->photo,
                    'phone' => $a->personnel?->phone,
                    'group' => $a->personnel?->group?->name,
                    'zone' => $a->zone,
                    'presence' => $a->presence,
                    'check_in_time' => $a->check_in_time?->toIso8601String(),
                    'check_out_time' => $a->check_out_time?->toIso8601String(),
                    'break_started_at' => $a->break_started_at?->toIso8601String(),
                    'break_minutes' => $a->break_minutes,
                    'location' => $a->personnel?->last_lat ? [
                        'lat' => (float) $a->personnel->last_lat,
                        'lng' => (float) $a->personnel->last_lng,
                        'at' => $a->personnel->last_location_at?->toIso8601String(),
                    ] : null,
                ])->values(),
            ];
        });

        return response()->json(['days' => $payload, 'server_time' => now()->toIso8601String()]);
    }

    /**
     * Konum bildir (mobil uygulama, periyodik). Kullanıcı bir personel kaydına bağlı olmalı.
     */
    public function storeLocation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|integer|min:0',
            'project_day_id' => 'nullable|integer|exists:project_days,id',
            'recorded_at' => 'nullable|date',
            'personnel_id' => 'nullable|integer|exists:personnel,id',
        ]);

        $personnel = Personnel::where('user_id', $request->user()->id)->first();
        if (!$personnel && !empty($data['personnel_id']) && $request->user()->hasPermission('projects.manage_days')) {
            $personnel = Personnel::find($data['personnel_id']);
        }
        if (!$personnel) {
            return response()->json(['message' => 'Bu kullanıcı bir personel kaydına bağlı değil.'], 422);
        }

        $dayId = $data['project_day_id'] ?? $this->todaysDayIdFor($personnel);

        app(\App\Services\LocationService::class)->record(
            $personnel,
            $dayId,
            (float) $data['lat'],
            (float) $data['lng'],
            isset($data['accuracy']) ? (int) $data['accuracy'] : null,
            'gps',
            $data['recorded_at'] ?? null,
        );

        return response()->json(['message' => 'Konum kaydedildi.', 'project_day_id' => $dayId]);
    }

    private function todaysDayIdFor(Personnel $personnel): ?int
    {
        return $personnel->projectDayAssignments()
            ->whereHas('projectDay', fn ($q) => $q->whereDate('date', today())->where('status', '!=', 'completed'))
            ->value('project_day_id');
    }
}
