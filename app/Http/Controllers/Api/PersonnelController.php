<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use App\Models\PersonnelWorkHistory;
use App\Models\PersonnelReference;
use App\Models\PersonnelChild;
use App\Models\PersonnelEmergencyContact;
use App\Models\PersonnelTraining;
use App\Models\PersonnelLanguage;
use App\Models\PersonnelComputerSkill;
use App\Models\PersonnelTechnicalDevice;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class PersonnelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Personnel::with('group:id,name,commission_type', 'personnelGroup:id,name')
            // Aday havuzu: incelenmemiş/reddedilmiş başvurular personel listesinde görünmez
            ->where(fn($q) => $q->whereNull('applicant_status')->orWhere('applicant_status', 'approved'));

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('ogg_number', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($request->has('group_id')) {
            if ($request->group_id === 'own') {
                $query->whereNull('group_id');
            } else {
                $query->where('group_id', $request->group_id);
            }
        }

        if ($request->has('personnel_group_id')) {
            if ($request->personnel_group_id === 'none') {
                $query->whereNull('personnel_group_id');
            } else {
                $query->where('personnel_group_id', $request->personnel_group_id);
            }
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Banka bilgisi filtresi
        if ($request->has('has_bank_info')) {
            if ($request->boolean('has_bank_info')) {
                $query->whereNotNull('iban')->where('iban', '!=', '');
            } else {
                $query->where(function ($q) {
                    $q->whereNull('iban')->orWhere('iban', '');
                });
            }
        }

        // OGG numarası filtresi
        if ($request->has('has_ogg')) {
            if ($request->boolean('has_ogg')) {
                $query->whereNotNull('ogg_number')->where('ogg_number', '!=', '');
            } else {
                $query->where(function ($q) {
                    $q->whereNull('ogg_number')->orWhere('ogg_number', '');
                });
            }
        }

        $sortBy = $request->get('sortBy', 'first_name');
        $sortOrder = $request->get('sortOrder', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('perPage', 10);
        $personnel = $query->paginate($perPage);

        return response()->json($personnel);
    }

    public function store(Request $request): JsonResponse
    {
        // JSON string olarak gelen ilişkili verileri parse et
        $this->parseJsonFields($request);

        $validated = $this->validatePersonnel($request);

        DB::beginTransaction();
        try {
            // Fotoğrafları yükle
            foreach (['photo_1', 'photo_2', 'photo_3'] as $photoField) {
                if ($request->hasFile($photoField)) {
                    $validated[$photoField] = $request->file($photoField)->store('personnel', 'public');
                }
            }

            $personnel = Personnel::create($validated);

            // İlişkili verileri kaydet
            $this->saveRelatedData($request, $personnel);

            DB::commit();

            $personnel->load('group:id,name', 'personnelGroup:id,name');
            return response()->json($personnel, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Personel kaydedilemedi: ' . $e->getMessage()], 500);
        }
    }

    public function show(Personnel $personnel): JsonResponse
    {
        $personnel->load([
            'group',
            'personnelGroup',
            'workHistory',
            'references',
            'children',
            'emergencyContacts',
            'trainings',
            'languages',
            'computerSkills',
            'technicalDevices',
        ]);

        // TC'yi görüntüleme için ayrıca gönder
        $data = $personnel->toArray();
        $data['tc_no_display'] = $personnel->tc_no;

        return response()->json($data);
    }

    public function update(Request $request, Personnel $personnel): JsonResponse
    {
        // JSON string olarak gelen ilişkili verileri parse et
        $this->parseJsonFields($request);

        $validated = $this->validatePersonnel($request, $personnel->id);

        DB::beginTransaction();
        try {
            // Silinecek fotoğrafları işle
            if ($request->has('delete_photos')) {
                $photosToDelete = json_decode($request->delete_photos, true) ?? [];
                foreach ($photosToDelete as $photoField) {
                    if (in_array($photoField, ['photo_1', 'photo_2', 'photo_3'])) {
                        if ($personnel->{$photoField}) {
                            Storage::disk('public')->delete($personnel->{$photoField});
                        }
                        $validated[$photoField] = null;
                    }
                }
            }
            unset($validated['delete_photos']);

            // Yeni fotoğrafları yükle
            foreach (['photo_1', 'photo_2', 'photo_3'] as $photoField) {
                if ($request->hasFile($photoField)) {
                    // Eski fotoğrafı sil
                    if ($personnel->{$photoField}) {
                        Storage::disk('public')->delete($personnel->{$photoField});
                    }
                    $validated[$photoField] = $request->file($photoField)->store('personnel', 'public');
                }
            }

            $personnel->update($validated);

            // İlişkili verileri güncelle
            $this->saveRelatedData($request, $personnel);

            DB::commit();

            $personnel->load('group:id,name', 'personnelGroup:id,name');
            return response()->json($personnel);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Personel guncellenemedi: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Personnel $personnel): JsonResponse
    {
        // Aktif proje ataması var mı kontrol et
        $hasActiveAssignments = $personnel->projectDayAssignments()
            ->whereHas('projectDay', function ($q) {
                $q->whereHas('project', function ($q2) {
                    $q2->whereIn('status', ['pending', 'approved', 'active']);
                });
            })
            ->exists();

        if ($hasActiveAssignments) {
            return response()->json([
                'message' => 'Bu personelin aktif proje atamalari var. Once atamalari kaldiriniz.'
            ], 422);
        }

        // Fotoğrafları sil
        foreach (['photo_1', 'photo_2', 'photo_3'] as $photoField) {
            if ($personnel->{$photoField}) {
                Storage::disk('public')->delete($personnel->{$photoField});
            }
        }

        $personnel->delete();
        return response()->json(['message' => 'Personel silindi']);
    }

    public function all(Request $request): JsonResponse
    {
        $query = Personnel::where('is_active', true)
            ->where(fn($q) => $q->whereNull('applicant_status')->orWhere('applicant_status', 'approved'));

        if ($request->has('group_id')) {
            if ($request->group_id === 'own') {
                $query->whereNull('group_id');
            } else {
                $query->where('group_id', $request->group_id);
            }
        }

        if ($request->has('personnel_group_id')) {
            if ($request->personnel_group_id === 'none') {
                $query->whereNull('personnel_group_id');
            } else {
                $query->where('personnel_group_id', $request->personnel_group_id);
            }
        }

        $personnel = $query->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'group_id', 'personnel_group_id', 'default_wage']);

        return response()->json($personnel);
    }

    /**
     * JSON string olarak gelen ilişkili verileri parse et
     */
    private function parseJsonFields(Request $request): void
    {
        $jsonFields = [
            'work_history',
            'references',
            'children',
            'emergency_contacts',
            'trainings',
            'languages',
            'computer_skills',
            'technical_devices',
        ];

        foreach ($jsonFields as $field) {
            if ($request->has($field) && is_string($request->input($field))) {
                $decoded = json_decode($request->input($field), true);
                if (is_array($decoded)) {
                    $request->merge([$field => $decoded]);
                }
            }
        }
    }

    /**
     * Personel validasyonu
     */
    private function validatePersonnel(Request $request, ?int $personnelId = null): array
    {
        $rules = [
            'group_id' => 'nullable|exists:groups,id',
            'personnel_group_id' => 'nullable|exists:personnel_groups,id',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'tc_no' => 'required|string|size:11',
            'birth_date' => 'nullable|date',
            'ogg_number' => 'nullable|string|max:50',
            'default_wage' => 'required|numeric|min:0',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:1000',
            'bank_name' => 'nullable|string|max:100',
            'iban' => 'nullable|string|max:50',
            'account_holder_name' => 'nullable|string|max:150',
            'photo_1' => 'nullable|image|max:5120',
            'photo_2' => 'nullable|image|max:5120',
            'photo_3' => 'nullable|image|max:5120',
            'delete_photos' => 'nullable|string',
            'is_active' => 'boolean',
            // Kan Grubu
            'blood_type' => 'nullable|string|max:10',
            // Fiziki Bilgiler
            'height' => 'nullable|integer|min:0|max:300',
            'weight' => 'nullable|integer|min:0|max:500',
            'pants_size' => 'nullable|string|max:10',
            'shirt_size' => 'nullable|string|max:10',
            'shoe_size' => 'nullable|string|max:10',
            'eye_color' => 'nullable|string|max:50',
            'skin_color' => 'nullable|string|max:50',
            'hair_color' => 'nullable|string|max:50',
            'coat_size' => 'nullable|string|max:10',
            // Diğer Bilgiler
            'has_driver_license' => 'boolean',
            'driver_license_date' => 'nullable|date',
            'driver_license_class' => 'nullable|string|max:20',
            'driver_license_no' => 'nullable|string|max:50',
            'is_smoker' => 'boolean',
            'has_health_issue' => 'boolean',
            'health_issue_details' => 'nullable|string|max:1000',
            'has_travel_restriction' => 'boolean',
            'travel_restriction_details' => 'nullable|string|max:1000',
            'has_criminal_record' => 'boolean',
            'criminal_record_details' => 'nullable|string|max:1000',
            'can_relocate' => 'boolean',
            'can_work_overtime' => 'boolean',
            'residence_type' => 'nullable|string|max:50',
            'has_vehicle' => 'boolean',
            'vehicle_brand' => 'nullable|string|max:50',
            'vehicle_model' => 'nullable|string|max:50',
            'vehicle_plate' => 'nullable|string|max:20',
            'education_level' => 'nullable|string|max:50',
            'last_school' => 'nullable|string|max:200',
            'ngo_membership' => 'nullable|string|max:200',
            // Aile Bilgileri
            'marital_status' => 'nullable|string|max:30',
            'marriage_date' => 'nullable|date',
            'marriage_certificate_no' => 'nullable|string|max:50',
            'spouse_name' => 'nullable|string|max:150',
            'spouse_birth_date' => 'nullable|date',
            'spouse_education' => 'nullable|string|max:100',
            'spouse_occupation' => 'nullable|string|max:100',
            'spouse_work_address' => 'nullable|string|max:300',
            'spouse_work_phone' => 'nullable|string|max:30',
            'past_illnesses' => 'nullable|string|max:1000',
            'regular_medications' => 'nullable|string|max:1000',
            // Askerlik
            'military_status' => 'nullable|string|max:30',
            'military_duration' => 'nullable|integer|min:0',
            'military_duty' => 'nullable|string|max:100',
            'military_discharge_date' => 'nullable|date',
            'military_exemption_reason' => 'nullable|string|max:500',
            'military_postpone_date' => 'nullable|date',
            // Görev ve ücret talepleri
            'salary_expectation' => 'nullable|numeric|min:0',
            'earliest_start_date' => 'nullable|date',
            // Kariyer Hedefi
            'career_goals' => 'nullable|string|max:2000',
            // İlişkili veriler (JSON olarak gönderilecek)
            'work_history' => 'nullable|array',
            'work_history.*.company_name' => 'required|string|max:200',
            'work_history.*.phone' => 'nullable|string|max:30',
            'work_history.*.position' => 'nullable|string|max:100',
            'work_history.*.leaving_reason' => 'nullable|string|max:500',
            'work_history.*.last_salary' => 'nullable|numeric|min:0',
            'work_history.*.start_date' => 'nullable|date',
            'work_history.*.end_date' => 'nullable|date',
            'references' => 'nullable|array',
            'references.*.name' => 'required|string|max:150',
            'references.*.company' => 'nullable|string|max:200',
            'references.*.position' => 'nullable|string|max:100',
            'references.*.phone' => 'nullable|string|max:30',
            'children' => 'nullable|array',
            'children.*.name' => 'required|string|max:150',
            'children.*.birth_date' => 'nullable|date',
            'emergency_contacts' => 'nullable|array',
            'emergency_contacts.*.name' => 'required|string|max:150',
            'emergency_contacts.*.relationship' => 'nullable|string|max:50',
            'emergency_contacts.*.address' => 'nullable|string|max:500',
            'emergency_contacts.*.phone' => 'nullable|string|max:30',
            'trainings' => 'nullable|array',
            'trainings.*.institution' => 'required|string|max:200',
            'trainings.*.subject' => 'required|string|max:200',
            'trainings.*.start_date' => 'nullable|date',
            'trainings.*.end_date' => 'nullable|date',
            'trainings.*.duration' => 'nullable|string|max:50',
            'languages' => 'nullable|array',
            'languages.*.language' => 'required|string|max:50',
            'languages.*.level' => 'required|string|max:20',
            'computer_skills' => 'nullable|array',
            'computer_skills.*.program' => 'required|string|max:100',
            'computer_skills.*.level' => 'required|string|max:20',
            'technical_devices' => 'nullable|array',
            'technical_devices.*.device' => 'required|string|max:100',
            'technical_devices.*.description' => 'nullable|string|max:500',
        ];

        $messages = [
            // Genel mesajlar
            'required' => ':attribute alanı zorunludur.',
            'string' => ':attribute metin olmalıdır.',
            'max' => ':attribute en fazla :max karakter olabilir.',
            'min' => ':attribute en az :min olmalıdır.',
            'numeric' => ':attribute sayısal bir değer olmalıdır.',
            'integer' => ':attribute tam sayı olmalıdır.',
            'boolean' => ':attribute alanı evet/hayır değeri olmalıdır.',
            'date' => ':attribute geçerli bir tarih olmalıdır.',
            'image' => ':attribute bir görsel dosyası olmalıdır.',
            'exists' => 'Seçilen :attribute geçersiz.',
            'size' => ':attribute tam olarak :size karakter olmalıdır.',
            'array' => ':attribute bir liste olmalıdır.',
            // Özel alan mesajları
            'tc_no.size' => 'TC Kimlik Numarası tam olarak 11 karakter olmalıdır.',
            'photo_1.max' => 'Fotoğraf 1 en fazla 5 MB olabilir.',
            'photo_2.max' => 'Fotoğraf 2 en fazla 5 MB olabilir.',
            'photo_3.max' => 'Fotoğraf 3 en fazla 5 MB olabilir.',
            'height.max' => 'Boy en fazla 300 cm olabilir.',
            'weight.max' => 'Kilo en fazla 500 kg olabilir.',
            // İlişkili veri mesajları
            'work_history.*.company_name.required' => 'İş geçmişinde firma adı zorunludur.',
            'references.*.name.required' => 'Referans adı zorunludur.',
            'children.*.name.required' => 'Çocuk adı zorunludur.',
            'emergency_contacts.*.name.required' => 'Acil durum kişisi adı zorunludur.',
            'trainings.*.institution.required' => 'Eğitim kurumu adı zorunludur.',
            'trainings.*.subject.required' => 'Eğitim konusu zorunludur.',
            'languages.*.language.required' => 'Dil adı zorunludur.',
            'languages.*.level.required' => 'Dil seviyesi zorunludur.',
            'computer_skills.*.program.required' => 'Program adı zorunludur.',
            'computer_skills.*.level.required' => 'Program seviyesi zorunludur.',
            'technical_devices.*.device.required' => 'Cihaz adı zorunludur.',
        ];

        $attributes = [
            'group_id' => 'Grup',
            'personnel_group_id' => 'Personel Grubu',
            'first_name' => 'Ad',
            'last_name' => 'Soyad',
            'tc_no' => 'TC Kimlik Numarası',
            'birth_date' => 'Doğum Tarihi',
            'ogg_number' => 'OGG Numarası',
            'default_wage' => 'Günlük Ücret',
            'phone' => 'Telefon',
            'address' => 'Adres',
            'description' => 'Açıklama',
            'bank_name' => 'Banka Adı',
            'iban' => 'IBAN',
            'account_holder_name' => 'Hesap Sahibi Adı',
            'photo_1' => 'Fotoğraf 1',
            'photo_2' => 'Fotoğraf 2',
            'photo_3' => 'Fotoğraf 3',
            'is_active' => 'Aktif',
            'blood_type' => 'Kan Grubu',
            'height' => 'Boy',
            'weight' => 'Kilo',
            'pants_size' => 'Pantolon Bedeni',
            'shirt_size' => 'Gömlek Bedeni',
            'shoe_size' => 'Ayakkabı Numarası',
            'eye_color' => 'Göz Rengi',
            'skin_color' => 'Ten Rengi',
            'hair_color' => 'Saç Rengi',
            'coat_size' => 'Mont Bedeni',
            'has_driver_license' => 'Ehliyet',
            'driver_license_date' => 'Ehliyet Tarihi',
            'driver_license_class' => 'Ehliyet Sınıfı',
            'driver_license_no' => 'Ehliyet Numarası',
            'is_smoker' => 'Sigara Kullanımı',
            'has_health_issue' => 'Sağlık Sorunu',
            'health_issue_details' => 'Sağlık Sorunu Detayı',
            'has_travel_restriction' => 'Seyahat Engeli',
            'travel_restriction_details' => 'Seyahat Engeli Detayı',
            'has_criminal_record' => 'Sabıka Kaydı',
            'criminal_record_details' => 'Sabıka Kaydı Detayı',
            'can_relocate' => 'Taşınabilir',
            'can_work_overtime' => 'Fazla Mesai Yapabilir',
            'residence_type' => 'İkamet Türü',
            'has_vehicle' => 'Araç Sahibi',
            'vehicle_brand' => 'Araç Markası',
            'vehicle_model' => 'Araç Modeli',
            'vehicle_plate' => 'Araç Plakası',
            'education_level' => 'Eğitim Seviyesi',
            'last_school' => 'Son Okul',
            'ngo_membership' => 'Sivil Toplum Üyeliği',
            'marital_status' => 'Medeni Durum',
            'marriage_date' => 'Evlilik Tarihi',
            'marriage_certificate_no' => 'Evlilik Cüzdanı No',
            'spouse_name' => 'Eş Adı',
            'spouse_birth_date' => 'Eş Doğum Tarihi',
            'spouse_education' => 'Eş Eğitim Durumu',
            'spouse_occupation' => 'Eş Mesleği',
            'spouse_work_address' => 'Eş İş Adresi',
            'spouse_work_phone' => 'Eş İş Telefonu',
            'past_illnesses' => 'Geçirilen Hastalıklar',
            'regular_medications' => 'Düzenli Kullanılan İlaçlar',
            'military_status' => 'Askerlik Durumu',
            'military_duration' => 'Askerlik Süresi',
            'military_duty' => 'Askerlik Görevi',
            'military_discharge_date' => 'Terhis Tarihi',
            'military_exemption_reason' => 'Muafiyet Sebebi',
            'military_postpone_date' => 'Tecil Tarihi',
            'salary_expectation' => 'Ücret Beklentisi',
            'earliest_start_date' => 'En Erken Başlama Tarihi',
            'career_goals' => 'Kariyer Hedefi',
            'work_history' => 'İş Geçmişi',
            'references' => 'Referanslar',
            'children' => 'Çocuklar',
            'emergency_contacts' => 'Acil Durum Kişileri',
            'trainings' => 'Eğitimler',
            'languages' => 'Yabancı Diller',
            'computer_skills' => 'Bilgisayar Becerileri',
            'technical_devices' => 'Teknik Cihazlar',
        ];

        return $request->validate($rules, $messages, $attributes);
    }

    /**
     * İlişkili verileri kaydet
     */
    private function saveRelatedData(Request $request, Personnel $personnel): void
    {
        // İş Geçmişi
        if ($request->has('work_history')) {
            $personnel->workHistory()->delete();
            $workHistory = $request->input('work_history', []);
            if (is_string($workHistory)) {
                $workHistory = json_decode($workHistory, true) ?? [];
            }
            foreach ($workHistory as $item) {
                if (!empty($item['company_name'])) {
                    $personnel->workHistory()->create($item);
                }
            }
        }

        // Referanslar
        if ($request->has('references')) {
            $personnel->references()->delete();
            $references = $request->input('references', []);
            if (is_string($references)) {
                $references = json_decode($references, true) ?? [];
            }
            foreach ($references as $item) {
                if (!empty($item['name'])) {
                    $personnel->references()->create($item);
                }
            }
        }

        // Çocuklar
        if ($request->has('children')) {
            $personnel->children()->delete();
            $children = $request->input('children', []);
            if (is_string($children)) {
                $children = json_decode($children, true) ?? [];
            }
            foreach ($children as $item) {
                if (!empty($item['name'])) {
                    $personnel->children()->create($item);
                }
            }
        }

        // Acil Durum Kişileri
        if ($request->has('emergency_contacts')) {
            $personnel->emergencyContacts()->delete();
            $contacts = $request->input('emergency_contacts', []);
            if (is_string($contacts)) {
                $contacts = json_decode($contacts, true) ?? [];
            }
            foreach ($contacts as $item) {
                if (!empty($item['name'])) {
                    $personnel->emergencyContacts()->create($item);
                }
            }
        }

        // Eğitimler
        if ($request->has('trainings')) {
            $personnel->trainings()->delete();
            $trainings = $request->input('trainings', []);
            if (is_string($trainings)) {
                $trainings = json_decode($trainings, true) ?? [];
            }
            foreach ($trainings as $item) {
                if (!empty($item['institution']) && !empty($item['subject'])) {
                    $personnel->trainings()->create($item);
                }
            }
        }

        // Diller
        if ($request->has('languages')) {
            $personnel->languages()->delete();
            $languages = $request->input('languages', []);
            if (is_string($languages)) {
                $languages = json_decode($languages, true) ?? [];
            }
            foreach ($languages as $item) {
                if (!empty($item['language']) && !empty($item['level'])) {
                    $personnel->languages()->create($item);
                }
            }
        }

        // Bilgisayar Becerileri
        if ($request->has('computer_skills')) {
            $personnel->computerSkills()->delete();
            $skills = $request->input('computer_skills', []);
            if (is_string($skills)) {
                $skills = json_decode($skills, true) ?? [];
            }
            foreach ($skills as $item) {
                if (!empty($item['program']) && !empty($item['level'])) {
                    $personnel->computerSkills()->create($item);
                }
            }
        }

        // Teknik Cihazlar
        if ($request->has('technical_devices')) {
            $personnel->technicalDevices()->delete();
            $devices = $request->input('technical_devices', []);
            if (is_string($devices)) {
                $devices = json_decode($devices, true) ?? [];
            }
            foreach ($devices as $item) {
                if (!empty($item['device'])) {
                    $personnel->technicalDevices()->create($item);
                }
            }
        }
    }
}
