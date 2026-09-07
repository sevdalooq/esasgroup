<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Concerns\HasQrCode;
use App\Models\Inventory;
use App\Models\Personnel;
use App\Models\Project;
use App\Models\ProjectDay;
use App\Models\ProjectDayInventory;
use App\Models\ProjectDayPersonnel;
use App\Models\ZoneOption;
use App\Services\DayOperationService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saha (mobil) ekranı: QR/NFC ile personel giriş-çıkış, zimmet teslim/iade, gün başlat/bitir.
 */
class FieldController extends Controller
{
    private const PERSONNEL_SELECT = 'id,first_name,last_name,phone,photo,photo_1,qr_code,default_wage,group_id';
    private const INVENTORY_SELECT = 'id,name,type,serial_number,qr_code,nfc_uid,current_status,current_holder_id';

    public function __construct(private readonly DayOperationService $dayOps)
    {
    }

    /**
     * Bugünkü görevler: bugün ±1 gün veya aktif durumdaki günler.
     * Yalnızca supervisor olduğu günler; projects.manage_days yetkisi olanlar hepsini görür.
     */
    public function today(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = ProjectDay::query()
            ->with(['project:id,name,customer_id,status', 'project.customer:id,name', 'supervisor:id,name'])
            ->withCount([
                'personnelAssignments as personnel_total',
                'personnelAssignments as personnel_checked_in' => fn ($q) => $q->whereNotNull('check_in_time'),
                'personnelAssignments as personnel_checked_out' => fn ($q) => $q->whereNotNull('check_out_time'),
                'inventoryAssignments as inventory_total',
                'inventoryAssignments as inventory_delivered' => fn ($q) => $q->whereNotNull('delivered_at'),
                'inventoryAssignments as inventory_returned' => fn ($q) => $q->whereNotNull('returned_at'),
            ])
            ->whereHas('project', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->where(function ($q) {
                $q->whereBetween('date', [today()->subDay()->toDateString(), today()->addDay()->toDateString()])
                    ->orWhere('status', 'active');
            })
            ->orderBy('date')
            ->orderBy('id');

        if (!$user->hasPermission('projects.manage_days')) {
            $query->where('supervisor_id', $user->id);
        }

        return response()->json([
            'today' => today()->toDateString(),
            'days' => $query->get(),
        ]);
    }

    /**
     * Gün detayı (personel, envanter, alanlar, fotoğraflar).
     */
    public function show(Request $request, ProjectDay $projectDay): JsonResponse
    {
        $this->authorizeDay($request, $projectDay);

        return response()->json($this->dayPayload($projectDay));
    }

    /**
     * QR/NFC okutma: içeriği çözümler ve o gün için ne yapılabileceğini döner.
     */
    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payload' => 'required_without:nfc_uid|nullable|string|max:255',
            'nfc_uid' => 'required_without:payload|nullable|string|max:64',
            'project_day_id' => 'nullable|integer|exists:project_days,id',
        ]);

        $projectDay = !empty($validated['project_day_id']) ? ProjectDay::find($validated['project_day_id']) : null;
        if ($projectDay) {
            $this->authorizeDay($request, $projectDay);
        }

        // NFC ile envanter
        if (!empty($validated['nfc_uid'])) {
            $inventory = Inventory::where('nfc_uid', trim($validated['nfc_uid']))->first();
            if (!$inventory) {
                $this->fail('Bu NFC etiketine kayıtlı envanter bulunamadı.', 'nfc_uid');
            }

            return response()->json($this->inventoryScanResult($inventory, $projectDay));
        }

        $parsed = HasQrCode::parseQrPayload($validated['payload'] ?? null);
        if (!$parsed) {
            $this->fail('Kod tanınamadı. Lütfen ESAS sistemine ait bir QR etiketi okutun.', 'payload');
        }

        return match ($parsed['type']) {
            'INV' => response()->json($this->inventoryScanResult($this->findByQr(Inventory::class, $parsed['uuid'], 'Bu QR koduna kayıtlı envanter bulunamadı.'), $projectDay)),
            'PER' => response()->json($this->personnelScanResult($this->findByQr(Personnel::class, $parsed['uuid'], 'Bu QR koduna kayıtlı personel bulunamadı.'), $projectDay)),
            'ZONE' => response()->json($this->zoneScanResult($this->findByQr(ZoneOption::class, $parsed['uuid'], 'Bu QR koduna kayıtlı alan bulunamadı.'), $projectDay)),
        };
    }

    /**
     * Personel giriş (check-in). Personel güne atanmamışsa varsayılan yevmiyesiyle atanır.
     */
    public function checkIn(Request $request, ProjectDay $projectDay): JsonResponse
    {
        $this->authorizeDay($request, $projectDay);
        $this->ensureDayOpen($projectDay);

        $validated = $request->validate([
            'personnel_payload' => 'required_without:personnel_id|nullable|string|max:255',
            'personnel_id' => 'required_without:personnel_payload|nullable|integer|exists:personnel,id',
            'zone_payload' => 'nullable|string|max:255',
            'zone' => 'nullable|string|max:100',
            'photo' => $this->photoRule($request, 'photo'),
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        $personnel = $this->resolvePersonnel($validated);
        $zone = $this->resolveZoneName($validated, $projectDay);

        $assignment = DB::transaction(function () use ($request, $projectDay, $personnel, $zone) {
            $assignment = $this->findOrCreateAssignment($projectDay, $personnel);

            if ($assignment->check_in_time) {
                $this->fail("{$personnel->full_name} bugün zaten giriş yapmış (" . $assignment->check_in_time->format('H:i') . ').', 'personnel_id');
            }

            $photoPath = $this->dayOps->storeRequestPhoto($request, 'photo', 'check-in-photos');

            return $this->dayOps->checkIn($assignment, $zone, true, $photoPath);
        });

        $assignment->load('personnel:' . self::PERSONNEL_SELECT);

        return response()->json([
            'message' => "{$personnel->full_name} giriş yaptı" . ($zone ? " – {$zone}" : '') . '.',
            'assignment' => $assignment,
            'summary' => $this->dayOps->calculateDaySummary($projectDay->fresh()),
        ]);
    }

    /**
     * Personel çıkış (check-out).
     */
    public function checkOut(Request $request, ProjectDay $projectDay): JsonResponse
    {
        $this->authorizeDay($request, $projectDay);
        $this->ensureDayOpen($projectDay);

        $validated = $request->validate([
            'personnel_payload' => 'required_without_all:assignment_id,personnel_id|nullable|string|max:255',
            'personnel_id' => 'nullable|integer|exists:personnel,id',
            'assignment_id' => 'nullable|integer|exists:project_day_personnel,id',
            'photo' => $this->photoRule($request, 'photo'),
            'check_out_time' => 'nullable|date',
            'overtime_hours' => 'nullable|numeric|min:0|max:16',
            'overtime_rate' => 'nullable|numeric|min:0',
            'payment_status' => 'nullable|in:pending,partial,paid',
            'payment_method' => 'nullable|in:cash,bank,mixed',
            'payment_amount' => 'nullable|numeric|min:0',
            'inventory_returns' => 'nullable|array',
            'inventory_returns.*.id' => 'required|integer|exists:project_day_inventory,id',
            'inventory_returns.*.return_status' => 'required|in:returned,damaged',
            'inventory_returns.*.damage_description' => 'nullable|string|max:500',
            'inventory_returns.*.deduction_amount' => 'nullable|numeric|min:0',
        ]);

        if (!empty($validated['assignment_id'])) {
            $assignment = $projectDay->personnelAssignments()->find($validated['assignment_id']);
        } else {
            $personnel = $this->resolvePersonnel($validated);
            $assignment = $projectDay->personnelAssignments()->where('personnel_id', $personnel->id)->first();
        }

        if (!$assignment) {
            $this->fail('Bu personel bu güne atanmamış.', 'personnel_id');
        }

        if ($assignment->check_out_time) {
            $this->fail('Bu personel zaten çıkış yapmış (' . $assignment->check_out_time->format('H:i') . ').', 'personnel_id');
        }

        $photoPath = $this->dayOps->storeRequestPhoto($request, 'photo', 'check-out-photos');
        $assignment = $this->dayOps->checkOut($assignment, $photoPath, $validated);
        $assignment->load('assignedInventory.inventory:' . self::INVENTORY_SELECT);
        $assignment->load('personnel:' . self::PERSONNEL_SELECT);

        return response()->json([
            'message' => "{$assignment->personnel->full_name} çıkış yaptı.",
            'assignment' => $assignment,
            'summary' => $this->dayOps->calculateDaySummary($projectDay->fresh()),
        ]);
    }

    /**
     * Envanter teslim et. Güne atanmamışsa atama oluşturulur.
     */
    public function deliverInventory(Request $request, ProjectDay $projectDay): JsonResponse
    {
        $this->authorizeDay($request, $projectDay);
        $this->ensureDayOpen($projectDay);

        $validated = $request->validate([
            'inventory_payload' => 'required_without_all:nfc_uid,inventory_id|nullable|string|max:255',
            'nfc_uid' => 'nullable|string|max:64',
            'inventory_id' => 'nullable|integer|exists:inventory,id',
            'personnel_payload' => 'nullable|string|max:255',
            'personnel_id' => 'nullable|integer|exists:personnel,id',
            'quantity' => 'nullable|integer|min:1',
        ]);

        $inventory = $this->resolveInventory($validated);
        $personnel = (!empty($validated['personnel_payload']) || !empty($validated['personnel_id']))
            ? $this->resolvePersonnel($validated)
            : null;

        $assignment = DB::transaction(function () use ($projectDay, $inventory, $personnel, $validated) {
            $assignment = $projectDay->inventoryAssignments()->where('inventory_id', $inventory->id)->first();

            if (!$assignment) {
                if (in_array($inventory->current_status, ['maintenance', 'damaged', 'lost'], true)) {
                    $this->fail("{$inventory->name} şu anda teslim edilemez (durum: " . $this->inventoryStatusText($inventory->current_status) . ').', 'inventory_id');
                }

                $assignment = $projectDay->inventoryAssignments()->create([
                    'inventory_id' => $inventory->id,
                    'quantity' => $validated['quantity'] ?? 1,
                ]);
            }

            $toAssignment = $personnel ? $this->findOrCreateAssignment($projectDay, $personnel) : null;

            return $this->dayOps->deliver($assignment, $toAssignment);
        });

        $assignment->load(['inventory:' . self::INVENTORY_SELECT, 'assignedToPersonnel.personnel:id,first_name,last_name']);

        $holder = $assignment->assignedToPersonnel?->personnel;

        return response()->json([
            'message' => "{$inventory->name} teslim edildi" . ($holder ? " → {$holder->full_name}" : '') . '.',
            'assignment' => $assignment,
            'summary' => $this->dayOps->calculateDaySummary($projectDay->fresh()),
        ]);
    }

    /**
     * Envanter iade al (hasar kaydı ile).
     */
    public function returnInventory(Request $request, ProjectDay $projectDay): JsonResponse
    {
        $this->authorizeDay($request, $projectDay);

        $validated = $request->validate([
            'inventory_payload' => 'required_without_all:nfc_uid,inventory_id|nullable|string|max:255',
            'nfc_uid' => 'nullable|string|max:64',
            'inventory_id' => 'nullable|integer|exists:inventory,id',
            'damaged' => 'required|boolean',
            'damage_description' => 'nullable|string|max:1000|required_if:damaged,1,true',
            'deduction_amount' => 'nullable|numeric|min:0',
            'damage_photo' => $this->photoRule($request, 'damage_photo'),
        ], [
            'damage_description.required_if' => 'Hasarlı iadede hasar açıklaması zorunludur.',
            'damaged.required' => 'Hasar durumu belirtilmelidir.',
        ]);

        $inventory = $this->resolveInventory($validated);
        $assignment = $projectDay->inventoryAssignments()->where('inventory_id', $inventory->id)->first();

        if (!$assignment) {
            $this->fail("{$inventory->name} bu güne atanmamış, iade alınamaz.", 'inventory_id');
        }

        $damaged = filter_var($validated['damaged'], FILTER_VALIDATE_BOOLEAN);
        $photoPath = $damaged ? $this->dayOps->storeRequestPhoto($request, 'damage_photo', 'damage-photos') : null;

        $assignment = $this->dayOps->returnItem(
            $assignment,
            $damaged,
            $validated['damage_description'] ?? null,
            isset($validated['deduction_amount']) ? (float) $validated['deduction_amount'] : null,
            $photoPath,
        );

        $assignment->load(['inventory:' . self::INVENTORY_SELECT, 'damages', 'assignedToPersonnel.personnel:id,first_name,last_name']);

        return response()->json([
            'message' => $damaged
                ? "{$inventory->name} HASARLI olarak iade alındı."
                : "{$inventory->name} iade alındı.",
            'assignment' => $assignment,
            'summary' => $this->dayOps->calculateDaySummary($projectDay->fresh()),
        ]);
    }

    /**
     * Günü başlat (fotoğraf isteğe bağlı).
     */
    public function startDay(Request $request, ProjectDay $projectDay): JsonResponse
    {
        $this->authorizeDay($request, $projectDay);

        $request->validate([
            'photo' => $this->photoRule($request, 'photo'),
            'start_photo' => $this->photoRule($request, 'start_photo'),
        ]);

        $photoPath = $this->dayOps->storeRequestPhoto($request, 'photo', 'day-photos/start')
            ?? $this->dayOps->storeRequestPhoto($request, 'start_photo', 'day-photos/start');

        $this->dayOps->start($projectDay, $photoPath);

        return response()->json([
            'message' => 'Gün başlatıldı.',
            ...$this->dayPayload($projectDay->fresh()),
        ]);
    }

    /**
     * Günü bitir (fotoğraf isteğe bağlı).
     */
    public function endDay(Request $request, ProjectDay $projectDay): JsonResponse
    {
        $this->authorizeDay($request, $projectDay);

        $request->validate([
            'photo' => $this->photoRule($request, 'photo'),
            'end_photo' => $this->photoRule($request, 'end_photo'),
        ]);

        $photoPath = $this->dayOps->storeRequestPhoto($request, 'photo', 'day-photos/end')
            ?? $this->dayOps->storeRequestPhoto($request, 'end_photo', 'day-photos/end');

        $result = $this->dayOps->end($projectDay, $photoPath);

        return response()->json([
            'message' => 'Gün kapatıldı.',
            ...$this->dayPayload($projectDay->fresh()),
            'summary' => $result['summary'],
        ]);
    }

    /**
     * QR etiket sayfası için tüm envanter (silinmemiş) – qr_payload dahil.
     */
    /** Mola başlat / bitir / gelmedi (supervisor tarafından) */
    public function breakStart(Request $request, ProjectDay $projectDay): JsonResponse
    {
        $this->authorizeDay($request, $projectDay);
        $this->ensureDayOpen($projectDay);
        $assignment = $this->resolveAssignment($request, $projectDay);
        $assignment = $this->dayOps->startBreak($assignment, $request->input('reason'));
        $assignment->load('personnel:' . self::PERSONNEL_SELECT);

        return response()->json(['message' => "{$assignment->personnel->full_name} molaya çıktı.", 'assignment' => $assignment, 'summary' => $this->dayOps->calculateDaySummary($projectDay->fresh())]);
    }

    public function breakEnd(Request $request, ProjectDay $projectDay): JsonResponse
    {
        $this->authorizeDay($request, $projectDay);
        $this->ensureDayOpen($projectDay);
        $assignment = $this->resolveAssignment($request, $projectDay);
        $assignment = $this->dayOps->endBreak($assignment);
        $assignment->load('personnel:' . self::PERSONNEL_SELECT);

        return response()->json(['message' => "{$assignment->personnel->full_name} moladan döndü.", 'assignment' => $assignment, 'summary' => $this->dayOps->calculateDaySummary($projectDay->fresh())]);
    }

    public function markAbsent(Request $request, ProjectDay $projectDay): JsonResponse
    {
        $this->authorizeDay($request, $projectDay);
        $this->ensureDayOpen($projectDay);
        $assignment = $this->resolveAssignment($request, $projectDay);
        $absent = $request->boolean('absent', true);
        $assignment = $this->dayOps->markAbsent($assignment, $absent);
        $assignment->load('personnel:' . self::PERSONNEL_SELECT);

        return response()->json(['message' => $absent ? "{$assignment->personnel->full_name} gelmedi olarak işaretlendi." : 'İşaret kaldırıldı.', 'assignment' => $assignment, 'summary' => $this->dayOps->calculateDaySummary($projectDay->fresh())]);
    }

    /** assignment_id | personnel_id | personnel_payload ile günün atamasını bul */
    private function resolveAssignment(Request $request, ProjectDay $projectDay): ProjectDayPersonnel
    {
        $validated = $request->validate([
            'assignment_id' => 'nullable|integer|exists:project_day_personnel,id',
            'personnel_id' => 'nullable|integer|exists:personnel,id',
            'personnel_payload' => 'nullable|string|max:255',
        ]);
        if (!empty($validated['assignment_id'])) {
            $assignment = $projectDay->personnelAssignments()->find($validated['assignment_id']);
        } else {
            $personnel = $this->resolvePersonnel($validated);
            $assignment = $projectDay->personnelAssignments()->where('personnel_id', $personnel->id)->first();
        }
        if (!$assignment) {
            $this->fail('Bu personel bu güne atanmamış.', 'personnel_id');
        }

        return $assignment;
    }

    /**
     * Sahada masraf girişi (fiş fotoğrafı ile). Onay muhasebede yapılır.
     */
    public function storeExpense(Request $request, ProjectDay $projectDay): JsonResponse
    {
        $this->authorizeDay($request, $projectDay);

        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'category' => 'nullable|string|in:food,transport,material,accommodation,other',
            'receipt_photo' => $this->photoRule($request, 'receipt_photo'),
        ]);

        $expense = $projectDay->expenses()->create([
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'category' => $validated['category'] ?? 'other',
            'receipt_photo' => $this->dayOps->storeRequestPhoto($request, 'receipt_photo', 'receipt-photos'),
            'status' => 'pending',
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Masraf kaydedildi, onay bekliyor.',
            'expense' => $expense,
            'expenses' => $projectDay->expenses()->latest()->get(),
        ], 201);
    }

    public function destroyExpense(Request $request, ProjectDay $projectDay, \App\Models\ProjectExpense $expense): JsonResponse
    {
        $this->authorizeDay($request, $projectDay);
        if ($expense->project_day_id !== $projectDay->id) {
            $this->fail('Masraf bu güne ait değil.', 'expense');
        }
        if ($expense->status !== 'pending') {
            $this->fail('Onaylanmış/reddedilmiş masraf silinemez.', 'expense');
        }
        $expense->delete();

        return response()->json(['message' => 'Masraf silindi.']);
    }

    public function inventoryLabels(Request $request): JsonResponse
    {
        $query = Inventory::query()->orderBy('name');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('current_status', $request->status);
        }

        return response()->json(
            $query->get(['id', 'name', 'type', 'serial_number', 'current_status', 'qr_code', 'nfc_uid'])
        );
    }

    // ---------------------------------------------------------------------
    // Alan (zone) QR yönetimi
    // ---------------------------------------------------------------------

    public function zones(Project $project): JsonResponse
    {
        return response()->json([
            'project' => $project->only(['id', 'name', 'status', 'start_date', 'end_date']),
            'zones' => ZoneOption::where('project_id', $project->id)->orderBy('name')->get(),
        ]);
    }

    public function storeZone(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $name = trim($validated['name']);

        $existing = ZoneOption::where('project_id', $project->id)->where('name', $name)->first();
        if ($existing) {
            $this->fail('Bu projede aynı isimde bir alan zaten var.', 'name');
        }

        $zone = ZoneOption::create([
            'project_id' => $project->id,
            'name' => $name,
            'usage_count' => 0,
        ]);

        return response()->json(['message' => 'Alan eklendi.', 'zone' => $zone], 201);
    }

    public function destroyZone(ZoneOption $zone): JsonResponse
    {
        $zone->delete();

        return response()->json(['message' => 'Alan silindi.']);
    }

    // ---------------------------------------------------------------------
    // Yardımcılar
    // ---------------------------------------------------------------------

    private function dayPayload(ProjectDay $projectDay): array
    {
        $summary = $this->dayOps->calculateDaySummary($projectDay);

        $projectDay->load([
            'project:id,name,customer_id,status,start_date,end_date',
            'project.customer:id,name',
            'supervisor:id,name',
            'personnelAssignments' => fn ($q) => $q->orderBy('id'),
            'personnelAssignments.personnel:' . self::PERSONNEL_SELECT,
            'personnelAssignments.assignedInventory.inventory:id,name,serial_number',
            'inventoryAssignments' => fn ($q) => $q->orderBy('id'),
            'inventoryAssignments.inventory:' . self::INVENTORY_SELECT,
            'inventoryAssignments.assignedToPersonnel.personnel:id,first_name,last_name',
            'expenses' => fn ($q) => $q->latest(),
        ]);

        return [
            'day' => $projectDay,
            'zones' => ZoneOption::where('project_id', $projectDay->project_id)->orderBy('name')->get(),
            'summary' => $summary,
        ];
    }

    private function personnelScanResult(Personnel $personnel, ?ProjectDay $projectDay): array
    {
        $assignment = $projectDay
            ? $projectDay->personnelAssignments()->where('personnel_id', $personnel->id)->first()
            : null;

        return [
            'type' => 'personnel',
            'entity' => $personnel->only(['id', 'first_name', 'last_name', 'full_name', 'phone', 'photo', 'photo_1', 'qr_payload', 'default_wage']),
            'context' => [
                'assigned' => (bool) $assignment,
                'checked_in' => (bool) $assignment?->check_in_time,
                'checked_out' => (bool) $assignment?->check_out_time,
                'assignment' => $assignment,
                'hint' => !$projectDay
                    ? 'Gün seçilmedi.'
                    : (!$assignment
                        ? 'Bu personel güne atanmamış; giriş yapılırsa otomatik eklenir.'
                        : ($assignment->check_out_time
                            ? 'Çıkış yapılmış.'
                            : ($assignment->check_in_time ? 'Giriş yapılmış, çıkış yapılabilir.' : 'Giriş yapılabilir.'))),
            ],
        ];
    }

    private function inventoryScanResult(Inventory $inventory, ?ProjectDay $projectDay): array
    {
        $assignment = $projectDay
            ? $projectDay->inventoryAssignments()
                ->with('assignedToPersonnel.personnel:id,first_name,last_name')
                ->where('inventory_id', $inventory->id)
                ->first()
            : null;

        $inventory->load('currentHolder:id,first_name,last_name');

        return [
            'type' => 'inventory',
            'entity' => $inventory->only(['id', 'name', 'type', 'serial_number', 'qr_payload', 'nfc_uid', 'current_status', 'current_holder']),
            'context' => [
                'assigned' => (bool) $assignment,
                'delivered' => (bool) $assignment?->delivered_at,
                'returned' => (bool) $assignment?->returned_at,
                'assignment' => $assignment,
                'hint' => !$projectDay
                    ? 'Gün seçilmedi.'
                    : (!$assignment
                        ? 'Bu envanter güne atanmamış; teslim edilirse otomatik eklenir.'
                        : ($assignment->returned_at
                            ? 'İade alınmış.'
                            : ($assignment->delivered_at ? 'Teslim edilmiş, iade alınabilir.' : 'Teslim edilebilir.'))),
            ],
        ];
    }

    private function zoneScanResult(ZoneOption $zone, ?ProjectDay $projectDay): array
    {
        return [
            'type' => 'zone',
            'entity' => $zone,
            'context' => [
                'belongs_to_project' => $projectDay ? ($zone->project_id === null || $zone->project_id === $projectDay->project_id) : true,
                'hint' => $projectDay && $zone->project_id && $zone->project_id !== $projectDay->project_id
                    ? 'Bu alan başka bir projeye ait.'
                    : 'Alan seçildi.',
            ],
        ];
    }

    private function findByQr(string $modelClass, string $uuid, string $notFoundMessage)
    {
        $model = $modelClass::byQr($uuid)->first();
        if (!$model) {
            $this->fail($notFoundMessage, 'payload');
        }

        return $model;
    }

    private function resolvePersonnel(array $validated): Personnel
    {
        if (!empty($validated['personnel_id'])) {
            return Personnel::findOrFail($validated['personnel_id']);
        }

        $parsed = HasQrCode::parseQrPayload($validated['personnel_payload'] ?? null);
        if (!$parsed || $parsed['type'] !== 'PER') {
            $this->fail('Personel QR kodu tanınamadı.', 'personnel_payload');
        }

        return $this->findByQr(Personnel::class, $parsed['uuid'], 'Bu QR koduna kayıtlı personel bulunamadı.');
    }

    private function resolveInventory(array $validated): Inventory
    {
        if (!empty($validated['inventory_id'])) {
            return Inventory::findOrFail($validated['inventory_id']);
        }

        if (!empty($validated['nfc_uid'])) {
            $inventory = Inventory::where('nfc_uid', trim($validated['nfc_uid']))->first();
            if (!$inventory) {
                $this->fail('Bu NFC etiketine kayıtlı envanter bulunamadı.', 'nfc_uid');
            }

            return $inventory;
        }

        $parsed = HasQrCode::parseQrPayload($validated['inventory_payload'] ?? null);
        if (!$parsed || $parsed['type'] !== 'INV') {
            $this->fail('Envanter QR kodu tanınamadı.', 'inventory_payload');
        }

        return $this->findByQr(Inventory::class, $parsed['uuid'], 'Bu QR koduna kayıtlı envanter bulunamadı.');
    }

    private function resolveZoneName(array $validated, ProjectDay $projectDay): ?string
    {
        if (!empty($validated['zone_payload'])) {
            $parsed = HasQrCode::parseQrPayload($validated['zone_payload']);
            if (!$parsed || $parsed['type'] !== 'ZONE') {
                $this->fail('Alan QR kodu tanınamadı.', 'zone_payload');
            }

            $zone = $this->findByQr(ZoneOption::class, $parsed['uuid'], 'Bu QR koduna kayıtlı alan bulunamadı.');
            if ($zone->project_id && $zone->project_id !== $projectDay->project_id) {
                $this->fail('Bu alan QR kodu başka bir projeye ait.', 'zone_payload');
            }

            return $zone->name;
        }

        $zone = trim((string) ($validated['zone'] ?? ''));

        return $zone !== '' ? $zone : null;
    }

    private function findOrCreateAssignment(ProjectDay $projectDay, Personnel $personnel): ProjectDayPersonnel
    {
        $assignment = $projectDay->personnelAssignments()->where('personnel_id', $personnel->id)->first();

        if (!$assignment) {
            $assignment = $projectDay->personnelAssignments()->create([
                'personnel_id' => $personnel->id,
                'daily_wage' => $personnel->default_wage ?? 0,
            ]);
        }

        return $assignment;
    }

    /**
     * Gün erişimi: supervisor'ı olduğu gün veya projects.manage_days yetkisi.
     */
    private function authorizeDay(Request $request, ProjectDay $projectDay): void
    {
        $user = $request->user();

        if ($user->hasPermission('projects.manage_days') || (int) $projectDay->supervisor_id === (int) $user->id) {
            return;
        }

        throw new HttpResponseException(response()->json(['message' => 'Bu günün saha sorumlusu değilsiniz.'], 403));
    }

    private function ensureDayOpen(ProjectDay $projectDay): void
    {
        if ($projectDay->status === 'completed') {
            $this->fail('Bu gün kapatılmış; işlem yapılamaz.', 'status');
        }
    }

    private function photoRule(Request $request, string $field): string
    {
        return $request->hasFile($field) ? 'nullable|image|max:8192' : 'nullable|string';
    }

    private function inventoryStatusText(string $status): string
    {
        return match ($status) {
            'available' => 'Müsait',
            'in_use' => 'Kullanımda',
            'maintenance' => 'Bakımda',
            'damaged' => 'Hasarlı',
            'lost' => 'Kayıp',
            default => $status,
        };
    }

    private function fail(string $message, string $field = 'payload'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
