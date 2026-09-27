<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personel kara listesi (yönetici onaylı) ve kara listeden atama onayı.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personnel', function (Blueprint $table) {
            $table->boolean('is_blacklisted')->default(false)->after('is_active');
            $table->timestamp('blacklisted_at')->nullable()->after('is_blacklisted');
            $table->text('blacklist_reason')->nullable()->after('blacklisted_at');
        });

        Schema::table('project_day_personnel', function (Blueprint $table) {
            // approved: normal; pending: kara listeden atandı, yönetici onayı bekliyor; rejected: reddedildi
            $table->string('approval_status', 20)->default('approved')->after('presence');
        });

        Schema::create('personnel_blacklist_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->cascadeOnDelete();
            $table->string('type', 20);                        // blacklist | assignment
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_day_personnel_id')->nullable()->constrained('project_day_personnel')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');  // pending | approved | rejected
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_blacklist_requests');
        Schema::table('project_day_personnel', fn (Blueprint $t) => $t->dropColumn('approval_status'));
        Schema::table('personnel', fn (Blueprint $t) => $t->dropColumn(['is_blacklisted', 'blacklisted_at', 'blacklist_reason']));
    }
};
