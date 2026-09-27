<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teklif yapısı: standart şart şablonları, proje bazlı şartlar,
 * kategori/satır bazlı teklif kalemleri ve projeye ön yazı / hizmet bilgisi / saha sorumlusu alanları.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_term_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('project_proposal_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('proposal_term_templates')->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('proposal_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');                                   // Personel Hizmeti, Bariyer Kiralama Hizmeti...
            $table->string('unit_label', 30)->default('Kişi');         // Kişi / Adet / Metre / Saat
            $table->boolean('show_duration')->default(false);          // "Çalışma Süresi" kolonu (12 Saat)
            $table->boolean('show_days')->default(true);               // "Gün" kolonu
            $table->boolean('show_unit_price')->default(true);         // "Birim Fiyat" kolonu
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('proposal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_section_id')->constrained('proposal_sections')->cascadeOnDelete();
            $table->string('description');                             // Hizmet adı
            $table->string('note', 500)->nullable();                   // Satır altı açıklama
            $table->string('duration_label', 50)->nullable();          // 12 Saat
            $table->decimal('quantity', 10, 2)->default(1);
            $table->unsignedInteger('days')->default(1);
            $table->decimal('unit_price', 12, 2)->nullable();          // boşsa toplam elle girilir
            $table->decimal('total_price', 12, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->text('cover_letter')->nullable()->after('notes');
            $table->string('service_location')->nullable()->after('cover_letter');
            $table->string('service_name')->nullable()->after('service_location');
            $table->foreignId('supervisor_id')->nullable()->after('account_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
            $table->dropColumn(['cover_letter', 'service_location', 'service_name', 'supervisor_id']);
        });
        Schema::dropIfExists('proposal_items');
        Schema::dropIfExists('proposal_sections');
        Schema::dropIfExists('project_proposal_terms');
        Schema::dropIfExists('proposal_term_templates');
    }
};
