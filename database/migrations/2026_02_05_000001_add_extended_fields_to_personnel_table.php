<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personnel', function (Blueprint $table) {
            // Kan Grubu
            $table->string('blood_type', 10)->nullable()->after('address');

            // Fiziki Bilgiler
            $table->integer('height')->nullable()->after('blood_type'); // Boy (cm)
            $table->integer('weight')->nullable(); // Kilo (kg)
            $table->string('pants_size', 10)->nullable(); // Pantolon
            $table->string('shirt_size', 10)->nullable(); // Gömlek
            $table->string('shoe_size', 10)->nullable(); // Ayakkabı
            $table->string('eye_color', 50)->nullable(); // Göz Rengi
            $table->string('skin_color', 50)->nullable(); // Ten Rengi
            $table->string('hair_color', 50)->nullable(); // Saç Rengi
            $table->string('coat_size', 10)->nullable(); // Kaban

            // Diğer Bilgiler
            $table->boolean('has_driver_license')->default(false); // Sürücü belgesi var mı
            $table->date('driver_license_date')->nullable(); // Veriliş tarihi
            $table->string('driver_license_class', 20)->nullable(); // Sınıf
            $table->string('driver_license_no', 50)->nullable(); // Belge no
            $table->boolean('is_smoker')->default(false); // Sigara kullanıyor mu
            $table->boolean('has_health_issue')->default(false); // Sağlık sorunu var mı
            $table->text('health_issue_details')->nullable(); // Sağlık sorunu açıklaması
            $table->boolean('has_travel_restriction')->default(false); // Seyahat engeli var mı
            $table->text('travel_restriction_details')->nullable(); // Seyahat engeli açıklaması
            $table->boolean('has_criminal_record')->default(false); // Adli sicil kaydı var mı
            $table->text('criminal_record_details')->nullable(); // Adli sicil açıklaması
            $table->boolean('can_relocate')->default(false); // İkamet değişikliği yapabilir mi
            $table->boolean('can_work_overtime')->default(false); // Fazla mesai yapabilir mi
            $table->string('residence_type', 50)->nullable(); // İkamet şekli
            $table->boolean('has_vehicle')->default(false); // Binek aracı var mı
            $table->string('vehicle_brand', 50)->nullable(); // Araç marka
            $table->string('vehicle_model', 50)->nullable(); // Araç model
            $table->string('vehicle_plate', 20)->nullable(); // Araç plaka
            $table->string('education_level', 50)->nullable(); // Eğitim düzeyi
            $table->string('last_school', 200)->nullable(); // Son diploma alınan okul
            $table->string('ngo_membership', 200)->nullable(); // Sivil toplum kuruluşu

            // Aile Bilgileri
            $table->string('marital_status', 30)->nullable(); // Medeni hal
            $table->date('marriage_date')->nullable(); // Evlenme tarihi
            $table->string('marriage_certificate_no', 50)->nullable(); // Evlilik cüzdan no
            $table->string('spouse_name', 150)->nullable(); // Eşin adı soyadı
            $table->date('spouse_birth_date')->nullable(); // Eşin doğum tarihi
            $table->string('spouse_education', 100)->nullable(); // Eşin tahsili
            $table->string('spouse_occupation', 100)->nullable(); // Eşin mesleği
            $table->string('spouse_work_address', 300)->nullable(); // Eşin iş adresi
            $table->string('spouse_work_phone', 30)->nullable(); // Eşin iş telefonu
            $table->text('past_illnesses')->nullable(); // Geçirilmiş rahatsızlıklar
            $table->text('regular_medications')->nullable(); // Sürekli kullanılan ilaçlar

            // Askerlik
            $table->string('military_status', 30)->nullable(); // Askerlik durumu (yaptım, muafım, tecilliyim)
            $table->integer('military_duration')->nullable(); // Kaç ay
            $table->string('military_duty', 100)->nullable(); // Görev
            $table->date('military_discharge_date')->nullable(); // Terhis tarihi
            $table->text('military_exemption_reason')->nullable(); // Muafiyet/tecil sebebi
            $table->date('military_postpone_date')->nullable(); // Tecil tarihi

            // Görev ve ücret talepleri
            $table->decimal('salary_expectation', 10, 2)->nullable(); // Ücret beklentisi
            $table->date('earliest_start_date')->nullable(); // En yakın işe başlama tarihi

            // Kurumsal Beklentiler ve Kariyer Hedefi
            $table->text('career_goals')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('personnel', function (Blueprint $table) {
            $table->dropColumn([
                'blood_type',
                'height', 'weight', 'pants_size', 'shirt_size', 'shoe_size',
                'eye_color', 'skin_color', 'hair_color', 'coat_size',
                'has_driver_license', 'driver_license_date', 'driver_license_class', 'driver_license_no',
                'is_smoker', 'has_health_issue', 'health_issue_details',
                'has_travel_restriction', 'travel_restriction_details',
                'has_criminal_record', 'criminal_record_details',
                'can_relocate', 'can_work_overtime', 'residence_type',
                'has_vehicle', 'vehicle_brand', 'vehicle_model', 'vehicle_plate',
                'education_level', 'last_school', 'ngo_membership',
                'marital_status', 'marriage_date', 'marriage_certificate_no',
                'spouse_name', 'spouse_birth_date', 'spouse_education',
                'spouse_occupation', 'spouse_work_address', 'spouse_work_phone',
                'past_illnesses', 'regular_medications',
                'military_status', 'military_duration', 'military_duty',
                'military_discharge_date', 'military_exemption_reason', 'military_postpone_date',
                'salary_expectation', 'earliest_start_date', 'career_goals',
            ]);
        });
    }
};
