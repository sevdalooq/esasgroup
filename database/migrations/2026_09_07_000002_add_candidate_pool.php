<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personnel', function (Blueprint $t) {
            // internal: şirket çalışanı, freelance: doğrudan çalışılan bağımsız görevli, team: ekip (group) personeli
            $t->enum('source', ['internal', 'freelance', 'team'])->default('freelance')->after('group_id');
            // Dışarıdan başvuranlar için: pending -> approved/rejected. Kayıtlı personelde null.
            $t->enum('applicant_status', ['pending', 'approved', 'rejected'])->nullable()->after('is_active');
            $t->timestamp('applied_at')->nullable()->after('applicant_status');
            $t->text('applicant_note')->nullable()->after('applied_at');
            $t->string('city', 100)->nullable()->after('address');
            $t->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete()->comment('Mobil/portal girişi olan personelin kullanıcı hesabı');
        });

        Schema::create('personnel_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('personnel_id')->constrained('personnel')->cascadeOnDelete();
            // ogg_card: ÖGG kimlik, cv, health_report, criminal_record, diploma, other
            $t->string('type', 50);
            $t->string('name');
            $t->string('file_path');
            $t->string('mime_type', 100)->nullable();
            $t->unsignedInteger('size')->nullable();
            $t->date('expires_at')->nullable();
            $t->boolean('is_verified')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_documents');
        Schema::table('personnel', function (Blueprint $t) {
            $t->dropConstrainedForeignId('user_id');
            $t->dropColumn(['source', 'applicant_status', 'applied_at', 'applicant_note', 'city']);
        });
    }
};
