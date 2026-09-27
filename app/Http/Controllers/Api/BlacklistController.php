<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use App\Models\PersonnelBlacklistRequest;
use App\Services\BlacklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Kara liste talepleri ve onayları */
class BlacklistController extends Controller
{
    public function __construct(private readonly BlacklistService $service) {}

    /** Talepler (varsayılan: bekleyenler). Onay yetkisi olmayan kullanıcı yalnızca kendi taleplerini görür. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = PersonnelBlacklistRequest::with(['personnel:id,first_name,last_name,phone,photo_1,is_blacklisted', 'project:id,name', 'requester:id,name', 'reviewer:id,name', 'assignment.projectDay:id,date'])
            ->latest();

        $status = $request->get('status', 'pending');
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if (!$user->hasPermission(BlacklistService::APPROVE_PERMISSION)) {
            $query->where('requested_by', $user->id);
        }

        return response()->json([
            'data' => $query->limit(200)->get(),
            'pending_count' => PersonnelBlacklistRequest::pending()->count(),
        ]);
    }

    /** Kara listedeki personeller */
    public function blacklisted(): JsonResponse
    {
        $items = Personnel::where('is_blacklisted', true)
            ->with('group:id,name')
            ->orderBy('blacklisted_at', 'desc')
            ->get(['id', 'first_name', 'last_name', 'phone', 'photo_1', 'group_id', 'is_blacklisted', 'blacklisted_at', 'blacklist_reason']);

        return response()->json($items);
    }

    public function requestBlacklist(Request $request, Personnel $personnel): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:2000']);
        $entry = $this->service->requestBlacklist($personnel, $request->user(), $data['reason']);

        return response()->json([
            'message' => $entry->status === 'approved' ? 'Personel kara listeye alındı.' : 'Kara liste talebi oluşturuldu; yönetici onayı bekleniyor.',
            'request' => $entry,
            'personnel' => $personnel->fresh(),
        ], 201);
    }

    public function removeFromBlacklist(Request $request, Personnel $personnel): JsonResponse
    {
        $data = $request->validate(['note' => 'nullable|string|max:2000']);
        $this->service->removeFromBlacklist($personnel, $request->user(), $data['note'] ?? null);

        return response()->json(['message' => 'Personel kara listeden çıkarıldı.', 'personnel' => $personnel->fresh()]);
    }

    public function approve(Request $request, PersonnelBlacklistRequest $blacklistRequest): JsonResponse
    {
        $data = $request->validate(['note' => 'nullable|string|max:2000']);
        $entry = $this->service->approve($blacklistRequest, $request->user(), $data['note'] ?? null);

        return response()->json(['message' => 'Onaylandı.', 'request' => $entry]);
    }

    public function reject(Request $request, PersonnelBlacklistRequest $blacklistRequest): JsonResponse
    {
        $data = $request->validate(['note' => 'nullable|string|max:2000']);
        $entry = $this->service->reject($blacklistRequest, $request->user(), $data['note'] ?? null);

        return response()->json(['message' => 'Reddedildi.', 'request' => $entry]);
    }
}
