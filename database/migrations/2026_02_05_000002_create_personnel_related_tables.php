<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // İş Geçmişi
        Schema::create('personnel_work_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->cascadeOnDelete();
            $table->string('company_name', 200);
            $table->string('phone', 30)->nullable();
            $table->string('position', 100)->nullable();
            $table->text('leaving_reason')->nullable();
            $table->decimal('last_salary', 10, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });

        // Referanslar
        Schema::create('personnel_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('company', 200)->nullable();
            $table->string('position', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->timestamps();
        });

        // Çocuklar
        Schema::create('personnel_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->cascadeOnDelete();
            $table->string('name', 150);
            $table->date('birth_date')->nullable();
            $table->timestamps();
        });

        // Acil Durumda Haber Verilecek Kişiler
        Schema::create('personnel_emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('relationship', 50)->nullable(); // Yakınlık derecesi
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->timestamps();
        });

        // Eğitim ve Seminerler
        Schema::create('personnel_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->cascadeOnDelete();
            $table->string('institution', 200); // Eğitim veren kuruluş
            $table->string('subject', 200); // Eğitimin konusu
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('duration', 50)->nullable(); // Süresi
            $table->timestamps();
        });

        // Yabancı Diller
        Schema::create('personnel_languages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->cascadeOnDelete();
            $table->string('language', 50);
            $table->string('level', 20); // yetersiz, orta, iyi, çok iyi
            $table->timestamps();
        });

        // Bilgisayar Bilgileri
        Schema::create('personnel_computer_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->cascadeOnDelete();
            $table->string('program', 100);
            $table->string('level', 20); // yetersiz, orta, iyi, çok iyi
            $table->timestamps();
        });

        // Teknik Cihazlar
        Schema::create('personnel_technical_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->cascadeOnDelete();
            $table->string('device', 100);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_technical_devices');
        Schema::dropIfExists('personnel_computer_skills');
        Schema::dropIfExists('personnel_languages');
        Schema::dropIfExists('personnel_trainings');
        Schema::dropIfExists('personnel_emergency_contacts');
        Schema::dropIfExists('personnel_children');
        Schema::dropIfExists('personnel_references');
        Schema::dropIfExists('personnel_work_history');
    }
};
