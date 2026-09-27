<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\ProjectDay;
use App\Models\ProjectDayInventory;
use Illuminate\Support\Collection;

/**
 * Tarih bazlı envanter müsaitliği. Envanter tekil kayıt (seri no); aynı isimli kayıtlar bir havuzdur.
 * Bir tarihte müsait adet = havuzdaki kullanılabilir birim sayısı − o tarihte (iptal edilmemiş) başka projelere atanmış birimler.
 */
class InventoryAvailabilityService
{
    private const UNUSABLE = ['maintenance', 'damaged', 'lost'];

    /** Belirli tarihte başka günlere atanmış envanter id'leri (proje iptal değilse). */
    public function bookedUnitIds(string $date, ?int $excludeDayId = null): Collection
    {
        return ProjectDayInventory::query()
            ->join('project_days', 'project_days.id', '=', 'project_day_inventory.project_day_id')
            ->join('projects', 'projects.id', '=', 'project_days.project_id')
            ->whereDate('project_days.date', $date)
            ->where('projects.status', '!=', 'cancelled')
            ->whereNull('projects.deleted_at')
            ->when($excludeDayId, fn ($q) => $q->where('project_days.id', '!=', $excludeDayId))
            ->pluck('project_day_inventory.inventory_id')
            ->unique()
            ->values();
    }

    /**
     * Ürün adına göre gruplanmış müsaitlik.
     * @return array<int, array{name:string,type:string,unit:?string,daily_rate:float,total:int,booked:int,available:int,on_this_day:int}>
     */
    public function availabilityByName(ProjectDay $day): array
    {
        $date = $day->date->toDateString();
        $booked = $this->bookedUnitIds($date, $day->id)->flip();
        $onThisDay = $day->inventoryAssignments()->pluck('inventory_id')->flip();

        $groups = [];
        Inventory::query()
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'unit', 'daily_rate', 'current_status', 'current_holder_id'])
            ->each(function (Inventory $inv) use (&$groups, $booked, $onThisDay) {
                $key = mb_strtolower(trim($inv->name));
                $groups[$key] ??= [
                    'name' => $inv->name,
                    'type' => $inv->type,
                    'unit' => $inv->unit,
                    'daily_rate' => (float) $inv->daily_rate,
                    'total' => 0,
                    'unusable' => 0,
                    'booked' => 0,
                    'available' => 0,
                    'on_this_day' => 0,
                ];
                $g = &$groups[$key];
                if ($onThisDay->has($inv->id)) {
                    $g['on_this_day']++;
                    $g['total']++;

                    return;
                }
                if (in_array($inv->current_status, self::UNUSABLE, true)) {
                    $g['unusable']++;

                    return;
                }
                $g['total']++;
                if ($booked->has($inv->id)) {
                    $g['booked']++;
                } else {
                    $g['available']++;
                }
            });

        return array_values($groups);
    }

    /** Bir tarihte, verilen ürün adı için atanabilir birimler (müsait olanlar önce boşta duranlar). */
    public function availableUnits(string $name, ProjectDay $day, int $limit): Collection
    {
        $date = $day->date->toDateString();
        $booked = $this->bookedUnitIds($date, $day->id);
        $onThisDay = $day->inventoryAssignments()->pluck('inventory_id');

        return Inventory::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($name))])
            ->whereNotIn('current_status', self::UNUSABLE)
            ->whereNotIn('id', $booked->all())
            ->whereNotIn('id', $onThisDay->all())
            ->orderByRaw("CASE WHEN current_status = 'available' THEN 0 ELSE 1 END")
            ->orderBy('serial_number')
            ->limit($limit)
            ->get();
    }

    /** Tek bir birim bu tarihte başka projede mi? Çakışan projenin adını döner. */
    public function conflictFor(Inventory $inventory, ProjectDay $day): ?string
    {
        $row = ProjectDayInventory::query()
            ->join('project_days', 'project_days.id', '=', 'project_day_inventory.project_day_id')
            ->join('projects', 'projects.id', '=', 'project_days.project_id')
            ->where('project_day_inventory.inventory_id', $inventory->id)
            ->whereDate('project_days.date', $day->date->toDateString())
            ->where('project_days.id', '!=', $day->id)
            ->where('projects.status', '!=', 'cancelled')
            ->whereNull('projects.deleted_at')
            ->first(['projects.name as project_name']);

        return $row?->project_name;
    }
}
