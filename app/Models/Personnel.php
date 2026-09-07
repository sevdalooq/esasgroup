<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Personnel extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Models\Concerns\HasQrCode, \Illuminate\Notifications\Notifiable;

    public const QR_PREFIX = 'PER';

    protected $table = 'personnel';

    protected $fillable = [
        'qr_code',
        'user_id',
        'source',
        'applicant_status',
        'applied_at',
        'applicant_note',
        'city',
        'group_id',
        'personnel_group_id',
        'first_name',
        'last_name',
        'tc_no',
        'tc_no_hash',
        'birth_date',
        'ogg_number',
        'default_wage',
        'phone',
        'email',
        'fcm_token',
        'last_lat',
        'last_lng',
        'last_location_at',
        'address',
        'photo',
        'photo_1',
        'photo_2',
        'photo_3',
        'description',
        'bank_name',
        'iban',
        'account_holder_name',
        'is_active',
        // Kan Grubu
        'blood_type',
        // Fiziki Bilgiler
        'height',
        'weight',
        'pants_size',
        'shirt_size',
        'shoe_size',
        'eye_color',
        'skin_color',
        'hair_color',
        'coat_size',
        // Diğer Bilgiler
        'has_driver_license',
        'driver_license_date',
        'driver_license_class',
        'driver_license_no',
        'is_smoker',
        'has_health_issue',
        'health_issue_details',
        'has_travel_restriction',
        'travel_restriction_details',
        'has_criminal_record',
        'criminal_record_details',
        'can_relocate',
        'can_work_overtime',
        'residence_type',
        'has_vehicle',
        'vehicle_brand',
        'vehicle_model',
        'vehicle_plate',
        'education_level',
        'last_school',
        'ngo_membership',
        // Aile Bilgileri
        'marital_status',
        'marriage_date',
        'marriage_certificate_no',
        'spouse_name',
        'spouse_birth_date',
        'spouse_education',
        'spouse_occupation',
        'spouse_work_address',
        'spouse_work_phone',
        'past_illnesses',
        'regular_medications',
        // Askerlik
        'military_status',
        'military_duration',
        'military_duty',
        'military_discharge_date',
        'military_exemption_reason',
        'military_postpone_date',
        // Görev ve ücret talepleri
        'salary_expectation',
        'earliest_start_date',
        // Kariyer Hedefi
        'career_goals',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'default_wage' => 'decimal:2',
        'is_active' => 'boolean',
        'applied_at' => 'datetime',
        'last_location_at' => 'datetime',
        'last_lat' => 'float',
        'last_lng' => 'float',
        'height' => 'integer',
        'weight' => 'integer',
        'has_driver_license' => 'boolean',
        'driver_license_date' => 'date',
        'is_smoker' => 'boolean',
        'has_health_issue' => 'boolean',
        'has_travel_restriction' => 'boolean',
        'has_criminal_record' => 'boolean',
        'can_relocate' => 'boolean',
        'can_work_overtime' => 'boolean',
        'has_vehicle' => 'boolean',
        'marriage_date' => 'date',
        'spouse_birth_date' => 'date',
        'military_duration' => 'integer',
        'military_discharge_date' => 'date',
        'military_postpone_date' => 'date',
        'salary_expectation' => 'decimal:2',
        'earliest_start_date' => 'date',
    ];

    protected $appends = ['full_name', 'qr_payload'];

    protected $hidden = ['tc_no', 'tc_no_hash'];

    protected static function booted(): void
    {
        // tc_no şifreli; benzersizlik kontrolü için sha256 özetini tut
        static::saving(function (Personnel $model) {
            if ($model->isDirty('tc_no') || ($model->tc_no_hash === null && $model->getRawOriginal('tc_no'))) {
                $tc = $model->tc_no;
                $model->tc_no_hash = $tc ? self::hashTcNo($tc) : null;
            }
        });
    }

    public static function hashTcNo(string $tcNo): string
    {
        return hash('sha256', trim($tcNo));
    }

    /**
     * TC No encrypted
     */
    protected function tcNo(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? decrypt($value) : null,
            set: fn($value) => $value ? encrypt($value) : null,
        );
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function personnelGroup(): BelongsTo
    {
        return $this->belongsTo(PersonnelGroup::class);
    }

    public function projectDayAssignments(): HasMany
    {
        return $this->hasMany(ProjectDayPersonnel::class);
    }

    public function heldInventory(): HasMany
    {
        return $this->hasMany(Inventory::class, 'current_holder_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PersonnelPayment::class);
    }

    // İlişkili tablolar
    public function workHistory(): HasMany
    {
        return $this->hasMany(PersonnelWorkHistory::class);
    }

    public function references(): HasMany
    {
        return $this->hasMany(PersonnelReference::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(PersonnelChild::class);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(PersonnelEmergencyContact::class);
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(PersonnelTraining::class);
    }

    public function languages(): HasMany
    {
        return $this->hasMany(PersonnelLanguage::class);
    }

    public function computerSkills(): HasMany
    {
        return $this->hasMany(PersonnelComputerSkill::class);
    }

    public function technicalDevices(): HasMany
    {
        return $this->hasMany(PersonnelTechnicalDevice::class);
    }

    /**
     * Grup personeli mi?
     */
    public function isGroupPersonnel(): bool
    {
        return $this->group_id !== null;
    }

    /**
     * Toplam alacak (kazanılan)
     */
    public function getTotalDebitAttribute(): float
    {
        return $this->payments()->where('type', 'debit')->sum('amount');
    }

    /**
     * Toplam ödenen
     */
    public function getTotalCreditAttribute(): float
    {
        return $this->payments()->where('type', 'credit')->sum('amount');
    }

    /**
     * Kalan bakiye (alacak - ödenen)
     */
    public function getBalanceAttribute(): float
    {
        return $this->total_debit - $this->total_credit;
    }

    public function documents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PersonnelDocument::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Sadece onaylı (aday olmayan) personel */
    public function scopeActiveStaff($query)
    {
        return $query->where(fn ($q) => $q->whereNull('applicant_status')->orWhere('applicant_status', 'approved'));
    }

    public function scopeApplicants($query)
    {
        return $query->whereNotNull('applicant_status');
    }

    /** Bildirim e-postası: personelin kendi e-postası, yoksa bağlı kullanıcı hesabı */
    public function routeNotificationForMail($notification = null): ?string
    {
        return $this->email ?: $this->user?->email;
    }

    public function locations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PersonnelLocation::class);
    }
}
