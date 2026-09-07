<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use App\Models\PersonnelDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Aday havuzu yönetimi + personel belgeleri.
 */
class CandidateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->get('status', 'pending');
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $status = 'pending';
        }

        $query = Personnel::applicants()
            ->where('applicant_status', $status)
            ->with(['documents', 'group:id,name', 'personnelGroup:id,name'])
            ->withCount('documents');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('ogg_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('city')) {
            $query->where('city', $request->city);
        }

        $sortBy = in_array($request->get('sortBy'), ['applied_at', 'first_name', 'last_name', 'city', 'created_at'], true)
            ? $request->get('sortBy') : 'applied_at';
        $sortOrder = $request->get('sortOrder', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = (int) $request->get('perPage', 10);
        $result = $query->paginate($perPage);

        // Sekme sayaçları
        $counts = Personnel::applicants()
            ->select('applicant_status', DB::raw('count(*) as total'))
            ->groupBy('applicant_status')
            ->pluck('total', 'applicant_status');

        $payload = $result->toArray();
        $payload['counts'] = [
            'pending' => (int) ($counts['pending'] ?? 0),
            'approved' => (int) ($counts['approved'] ?? 0),
            'rejected' => (int) ($counts['rejected'] ?? 0),
        ];

        return response()->json($payload);
    }

    public function show(Personnel $personnel): JsonResponse
    {
        if ($personnel->applicant_status === null) {
            return response()->json(['message' => 'Bu kayıt bir başvuru değil.'], 404);
        }

        $personnel->load(['documents', 'group:id,name', 'personnelGroup:id,name', 'workHistory', 'references', 'trainings', 'languages']);

        $data = $personnel->toArray();
        $data['tc_no_display'] = $personnel->tc_no;
        $data['application_no'] = 'ADAY-' . $personnel->id;

        return response()->json($data);
    }

    public function approve(Request $request, Personnel $personnel): JsonResponse
    {
        if ($personnel->applicant_status === null) {
            return response()->json(['message' => 'Bu kayıt bir başvuru değil.'], 422);
        }
        if ($personnel->applicant_status === 'approved') {
            return response()->json(['message' => 'Bu başvuru zaten onaylanmış.'], 422);
        }

        $validated = $request->validate([
            'source' => 'nullable|in:freelance,team,internal',
            'group_id' => 'nullable|exists:groups,id',
            'personnel_group_id' => 'nullable|exists:personnel_groups,id',
            'default_wage' => 'nullable|numeric|min:0',
        ], [
            'in' => 'Seçilen :attribute geçersiz.',
            'exists' => 'Seçilen :attribute geçersiz.',
            'numeric' => ':attribute sayısal olmalıdır.',
        ], [
            'source' => 'Personel Tipi',
            'group_id' => 'Ekip',
            'personnel_group_id' => 'Personel Grubu',
            'default_wage' => 'Günlük Ücret',
        ]);

        $source = $validated['source'] ?? $personnel->source ?? 'freelance';
        if ($source === 'team' && empty($validated['group_id'])) {
            return response()->json([
                'message' => 'Ekip personeli için bir ekip seçmelisiniz.',
                'errors' => ['group_id' => ['Ekip seçimi zorunludur.']],
            ], 422);
        }

        DB::transaction(function () use ($personnel, $validated, $source) {
            $personnel->fill([
                'source' => $source,
                'group_id' => $source === 'team' ? $validated['group_id'] : null,
                'personnel_group_id' => $validated['personnel_group_id'] ?? $personnel->personnel_group_id,
                'default_wage' => $validated['default_wage'] ?? $personnel->default_wage,
                'applicant_status' => 'approved',
                'is_active' => true,
            ]);
            $personnel->save();
        });

        $personnel->load(['documents', 'group:id,name', 'personnelGroup:id,name']);

        return response()->json([
            'message' => 'Başvuru onaylandı, aday personel havuzuna eklendi.',
            'personnel' => $personnel,
        ]);
    }

    public function reject(Request $request, Personnel $personnel): JsonResponse
    {
        if ($personnel->applicant_status === null) {
            return response()->json(['message' => 'Bu kayıt bir başvuru değil.'], 422);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ], [
            'required' => 'Red gerekçesi zorunludur.',
            'max' => 'Red gerekçesi en fazla 1000 karakter olabilir.',
        ]);

        $note = trim((string) $personnel->applicant_note);
        $note .= ($note ? "\n\n" : '') . '[Red - ' . now()->format('d.m.Y H:i') . '] ' . trim($validated['reason']);

        $personnel->update([
            'applicant_status' => 'rejected',
            'applicant_note' => $note,
            'is_active' => false,
        ]);

        return response()->json([
            'message' => 'Başvuru reddedildi.',
            'personnel' => $personnel->fresh(['documents']),
        ]);
    }

    // ----------------------------------------------------------------
    // Personel belgeleri (aday veya kayıtlı personel)
    // ----------------------------------------------------------------

    public function documents(Personnel $personnel): JsonResponse
    {
        return response()->json($personnel->documents()->orderByDesc('id')->get());
    }

    public function storeDocument(Request $request, Personnel $personnel): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:' . implode(',', array_keys(PersonnelDocument::TYPES)),
            'file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'expires_at' => 'nullable|date',
        ], [
            'required' => ':attribute alanı zorunludur.',
            'in' => 'Seçilen :attribute geçersiz.',
            'file' => ':attribute bir dosya olmalıdır.',
            'mimes' => ':attribute şu türlerden biri olmalıdır: :values.',
            'max' => ':attribute en fazla 10 MB olabilir.',
            'date' => ':attribute geçerli bir tarih olmalıdır.',
        ], [
            'type' => 'Belge Türü',
            'file' => 'Dosya',
            'expires_at' => 'Geçerlilik Tarihi',
        ]);

        PublicApplicationController::storeDocument(
            $personnel,
            $request->file('file'),
            $validated['type'],
            $validated['expires_at'] ?? null,
        );

        $document = $personnel->documents()->orderByDesc('id')->first();

        return response()->json($document, 201);
    }

    public function destroyDocument(PersonnelDocument $document): JsonResponse
    {
        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }
        $document->delete();

        return response()->json(['message' => 'Belge silindi.']);
    }

    public function verifyDocument(Request $request, PersonnelDocument $document): JsonResponse
    {
        $document->update(['is_verified' => $request->has('is_verified') ? $request->boolean('is_verified') : true]);

        return response()->json($document->fresh());
    }
}
