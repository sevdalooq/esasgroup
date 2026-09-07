<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use App\Models\PersonnelDocument;
use App\Models\PersonnelGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Dışarıdan (auth gerektirmeyen) personel başvurusu – aday havuzu girişi.
 */
class PublicApplicationController extends Controller
{
    public const EDUCATION_LEVELS = [
        'okur_yazar' => 'Okur Yazar',
        'ilkogretim' => 'İlköğretim',
        'ortaogretim' => 'Ortaöğretim (Lise)',
        'on_lisans' => 'Ön Lisans',
        'lisans' => 'Lisans',
        'yuksek_lisans' => 'Yüksek Lisans',
        'doktora' => 'Doktora',
    ];

    public const MARITAL_STATUSES = [
        'bekar' => 'Bekar',
        'evli' => 'Evli',
        'ayrilmis' => 'Eşinden ayrılmış',
    ];

    public const MILITARY_STATUSES = [
        'yaptim' => 'Yaptım',
        'muaf' => 'Muafım',
        'tecilli' => 'Tecilliyim',
    ];

    public const CITIES = [
        'Adana', 'Adıyaman', 'Afyonkarahisar', 'Ağrı', 'Aksaray', 'Amasya', 'Ankara', 'Antalya', 'Ardahan', 'Artvin',
        'Aydın', 'Balıkesir', 'Bartın', 'Batman', 'Bayburt', 'Bilecik', 'Bingöl', 'Bitlis', 'Bolu', 'Burdur',
        'Bursa', 'Çanakkale', 'Çankırı', 'Çorum', 'Denizli', 'Diyarbakır', 'Düzce', 'Edirne', 'Elazığ', 'Erzincan',
        'Erzurum', 'Eskişehir', 'Gaziantep', 'Giresun', 'Gümüşhane', 'Hakkari', 'Hatay', 'Iğdır', 'Isparta', 'İstanbul',
        'İzmir', 'Kahramanmaraş', 'Karabük', 'Karaman', 'Kars', 'Kastamonu', 'Kayseri', 'Kilis', 'Kırıkkale', 'Kırklareli',
        'Kırşehir', 'Kocaeli', 'Konya', 'Kütahya', 'Malatya', 'Manisa', 'Mardin', 'Mersin', 'Muğla', 'Muş',
        'Nevşehir', 'Niğde', 'Ordu', 'Osmaniye', 'Rize', 'Sakarya', 'Samsun', 'Şanlıurfa', 'Siirt', 'Sinop',
        'Sivas', 'Şırnak', 'Tekirdağ', 'Tokat', 'Trabzon', 'Tunceli', 'Uşak', 'Van', 'Yalova', 'Yozgat', 'Zonguldak',
    ];

    /**
     * Başvuru formunun ihtiyaç duyduğu seçenek listeleri.
     */
    public function options(): JsonResponse
    {
        $toList = fn(array $map) => collect($map)->map(fn($title, $value) => ['title' => $title, 'value' => $value])->values();

        return response()->json([
            'personnel_groups' => PersonnelGroup::orderBy('name')->get(['id', 'name']),
            'document_types' => $toList(PersonnelDocument::TYPES),
            'education_levels' => $toList(self::EDUCATION_LEVELS),
            'marital_statuses' => $toList(self::MARITAL_STATUSES),
            'military_statuses' => $toList(self::MILITARY_STATUSES),
            'cities' => self::CITIES,
        ]);
    }

    /**
     * Yeni başvuru (multipart/form-data).
     */
    public function store(Request $request): JsonResponse
    {
        // İş geçmişi JSON string olarak gelebilir
        if ($request->has('work_history') && is_string($request->input('work_history'))) {
            $decoded = json_decode($request->input('work_history'), true);
            $request->merge(['work_history' => is_array($decoded) ? $decoded : []]);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'tc_no' => ['required', 'string', 'regex:/^[1-9][0-9]{10}$/'],
            'birth_date' => 'required|date|before:today',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:150',
            'city' => 'required|string|max:100',
            'address' => 'nullable|string|max:500',
            'ogg_number' => 'nullable|string|max:50',
            'has_ogg_card' => 'nullable|boolean',
            'education_level' => 'nullable|string|in:' . implode(',', array_keys(self::EDUCATION_LEVELS)),
            'last_school' => 'nullable|string|max:200',
            'marital_status' => 'nullable|string|in:' . implode(',', array_keys(self::MARITAL_STATUSES)),
            'military_status' => 'nullable|string|in:' . implode(',', array_keys(self::MILITARY_STATUSES)),
            'has_driver_license' => 'nullable|boolean',
            'driver_license_class' => 'nullable|string|max:20',
            'height' => 'nullable|integer|min:100|max:250',
            'weight' => 'nullable|integer|min:30|max:300',
            'blood_type' => 'nullable|string|max:10',
            'experience_summary' => 'nullable|string|max:3000',
            'about' => 'nullable|string|max:3000',
            'default_wage' => 'nullable|numeric|min:0|max:100000',
            'kvkk_consent' => 'accepted',
            'cv' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'ogg_card' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'photo' => 'nullable|image|max:5120',
            'work_history' => 'nullable|array|max:20',
            'work_history.*.company_name' => 'required|string|max:200',
            'work_history.*.position' => 'nullable|string|max:100',
            'work_history.*.start_date' => 'nullable|date',
            'work_history.*.end_date' => 'nullable|date',
            'work_history.*.leaving_reason' => 'nullable|string|max:500',
        ], [
            'required' => ':attribute alanı zorunludur.',
            'string' => ':attribute metin olmalıdır.',
            'max' => ':attribute en fazla :max olabilir.',
            'min' => ':attribute en az :min olmalıdır.',
            'numeric' => ':attribute sayısal bir değer olmalıdır.',
            'integer' => ':attribute tam sayı olmalıdır.',
            'boolean' => ':attribute alanı evet/hayır değeri olmalıdır.',
            'date' => ':attribute geçerli bir tarih olmalıdır.',
            'email' => ':attribute geçerli bir e-posta adresi olmalıdır.',
            'image' => ':attribute bir görsel dosyası olmalıdır.',
            'file' => ':attribute bir dosya olmalıdır.',
            'mimes' => ':attribute şu türlerden biri olmalıdır: :values.',
            'in' => 'Seçilen :attribute geçersiz.',
            'accepted' => 'Devam etmek için KVKK aydınlatma metnini onaylamanız gerekir.',
            'before' => ':attribute bugünden önce olmalıdır.',
            'tc_no.regex' => 'TC Kimlik Numarası 11 haneli ve rakamlardan oluşmalıdır.',
            'cv.max' => 'CV dosyası en fazla 10 MB olabilir.',
            'ogg_card.max' => 'ÖGG kartı dosyası en fazla 10 MB olabilir.',
            'photo.max' => 'Fotoğraf en fazla 5 MB olabilir.',
            'work_history.*.company_name.required' => 'İş geçmişinde firma adı zorunludur.',
        ], [
            'first_name' => 'Ad',
            'last_name' => 'Soyad',
            'tc_no' => 'TC Kimlik Numarası',
            'birth_date' => 'Doğum Tarihi',
            'phone' => 'Telefon',
            'email' => 'E-posta',
            'city' => 'Şehir',
            'address' => 'Adres',
            'ogg_number' => 'ÖGG Numarası',
            'has_ogg_card' => 'ÖGG Kartı',
            'education_level' => 'Eğitim Seviyesi',
            'last_school' => 'Son Okul',
            'marital_status' => 'Medeni Durum',
            'military_status' => 'Askerlik Durumu',
            'has_driver_license' => 'Ehliyet',
            'driver_license_class' => 'Ehliyet Sınıfı',
            'height' => 'Boy',
            'weight' => 'Kilo',
            'blood_type' => 'Kan Grubu',
            'experience_summary' => 'Deneyim Özeti',
            'about' => 'Hakkında',
            'default_wage' => 'Günlük Ücret Beklentisi',
            'kvkk_consent' => 'KVKK Onayı',
            'cv' => 'CV',
            'ogg_card' => 'ÖGG Kartı',
            'photo' => 'Fotoğraf',
            'work_history' => 'İş Geçmişi',
        ]);

        // Aynı TC ile mevcut/ bekleyen kayıt kontrolü (tc_no şifreli -> hash üzerinden)
        $hash = Personnel::hashTcNo($validated['tc_no']);
        $existing = Personnel::where('tc_no_hash', $hash)->orderByDesc('id')->first();
        if ($existing) {
            if ($existing->applicant_status === 'pending') {
                return response()->json([
                    'message' => 'Bu TC kimlik numarası ile değerlendirme aşamasında bir başvurunuz zaten bulunmaktadır. Başvuru No: ADAY-' . $existing->id,
                    'errors' => ['tc_no' => ['Bu TC kimlik numarası ile bekleyen bir başvuru var.']],
                ], 422);
            }
            if ($existing->applicant_status === null || $existing->applicant_status === 'approved') {
                return response()->json([
                    'message' => 'Bu TC kimlik numarası sistemde kayıtlı. Lütfen bizimle iletişime geçin.',
                    'errors' => ['tc_no' => ['Bu TC kimlik numarası sistemde kayıtlı.']],
                ], 422);
            }
            // rejected: yeniden başvuruya izin verilir
        }

        $noteParts = [];
        if (!empty($validated['experience_summary'])) {
            $noteParts[] = "Deneyim özeti:\n" . trim($validated['experience_summary']);
        }
        if (!empty($validated['about'])) {
            $noteParts[] = "Hakkında:\n" . trim($validated['about']);
        }
        if ($request->boolean('has_ogg_card')) {
            $noteParts[] = 'ÖGG kartı: Var';
        } else {
            $noteParts[] = 'ÖGG kartı: Yok';
        }

        $storedFiles = [];

        try {
            $personnel = DB::transaction(function () use ($request, $validated, $noteParts, &$storedFiles) {
                $data = [
                    'first_name' => trim($validated['first_name']),
                    'last_name' => trim($validated['last_name']),
                    'tc_no' => $validated['tc_no'],
                    'birth_date' => $validated['birth_date'],
                    'phone' => $validated['phone'],
                    'email' => $validated['email'] ?? null,
                    'city' => $validated['city'],
                    'address' => $validated['address'] ?? null,
                    'ogg_number' => $validated['ogg_number'] ?? null,
                    'education_level' => $validated['education_level'] ?? null,
                    'last_school' => $validated['last_school'] ?? null,
                    'marital_status' => $validated['marital_status'] ?? null,
                    'military_status' => $validated['military_status'] ?? null,
                    'has_driver_license' => $request->boolean('has_driver_license'),
                    'driver_license_class' => $request->boolean('has_driver_license') ? ($validated['driver_license_class'] ?? null) : null,
                    'height' => $validated['height'] ?? null,
                    'weight' => $validated['weight'] ?? null,
                    'blood_type' => $validated['blood_type'] ?? null,
                    'default_wage' => $validated['default_wage'] ?? 0,
                    'salary_expectation' => $validated['default_wage'] ?? null,
                    'applicant_note' => implode("\n\n", $noteParts),
                    'source' => 'freelance',
                    'applicant_status' => 'pending',
                    'applied_at' => now(),
                    'is_active' => false,
                ];

                if ($request->hasFile('photo')) {
                    $path = $request->file('photo')->store('personnel', 'public');
                    $storedFiles[] = $path;
                    $data['photo'] = $path;
                    $data['photo_1'] = $path;
                }

                $personnel = Personnel::create($data);

                foreach ($validated['work_history'] ?? [] as $item) {
                    if (empty($item['company_name'])) {
                        continue;
                    }
                    $personnel->workHistory()->create([
                        'company_name' => $item['company_name'],
                        'position' => $item['position'] ?? null,
                        'start_date' => $item['start_date'] ?? null,
                        'end_date' => $item['end_date'] ?? null,
                        'leaving_reason' => $item['leaving_reason'] ?? null,
                    ]);
                }

                foreach (['cv' => 'cv', 'ogg_card' => 'ogg_card'] as $field => $type) {
                    if ($request->hasFile($field)) {
                        $storedFiles[] = self::storeDocument($personnel, $request->file($field), $type);
                    }
                }

                return $personnel;
            });
        } catch (\Throwable $e) {
            foreach ($storedFiles as $path) {
                Storage::disk('public')->delete($path);
            }
            report($e);
            return response()->json(['message' => 'Başvuru kaydedilemedi. Lütfen daha sonra tekrar deneyin.'], 500);
        }

        return response()->json([
            'message' => 'Başvurunuz alındı. Değerlendirme sonrası sizinle iletişime geçilecektir.',
            'application_no' => 'ADAY-' . $personnel->id,
        ], 201);
    }

    /**
     * Belgeyi storage/app/public/personnel-documents/{id}/ altına kaydet ve kaydını oluştur.
     * Dönüş: kaydedilen dosya yolu.
     */
    public static function storeDocument(Personnel $personnel, UploadedFile $file, string $type, ?string $expiresAt = null): string
    {
        $path = $file->store("personnel-documents/{$personnel->id}", 'public');

        $personnel->documents()->create([
            'type' => $type,
            'name' => $file->getClientOriginalName() ?: basename($path),
            'file_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'expires_at' => $expiresAt,
        ]);

        return $path;
    }
}
