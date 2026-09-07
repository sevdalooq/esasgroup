<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_day_personnel', function (Blueprint $t) {
            // assigned: atandı, checked_in: sahada, on_break: molada, checked_out: çıkış yaptı, absent: gelmedi
            $t->enum('presence', ['assigned', 'checked_in', 'on_break', 'checked_out', 'absent'])->default('assigned')->after('is_checked');
            $t->timestamp('break_started_at')->nullable()->after('presence');
            $t->unsignedInteger('break_minutes')->default(0)->after('break_started_at');
        });

        Schema::create('personnel_breaks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_day_personnel_id')->constrained('project_day_personnel')->cascadeOnDelete();
            $t->timestamp('started_at');
            $t->timestamp('ended_at')->nullable();
            $t->string('reason', 100)->nullable();
            $t->timestamps();
        });

        Schema::create('personnel_locations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('personnel_id')->constrained('personnel')->cascadeOnDelete();
            $t->foreignId('project_day_id')->nullable()->constrained('project_days')->nullOnDelete();
            $t->decimal('lat', 10, 7);
            $t->decimal('lng', 10, 7);
            $t->unsignedInteger('accuracy')->nullable()->comment('metre');
            $t->timestamp('recorded_at');
            $t->timestamps();
            $t->index(['personnel_id', 'recorded_at']);
        });

        Schema::table('personnel', function (Blueprint $t) {
            $t->decimal('last_lat', 10, 7)->nullable()->after('fcm_token');
            $t->decimal('last_lng', 10, 7)->nullable()->after('last_lat');
            $t->timestamp('last_location_at')->nullable()->after('last_lng');
        });

        Schema::table('project_days', function (Blueprint $t) {
            $t->decimal('venue_lat', 10, 7)->nullable()->after('notes');
            $t->decimal('venue_lng', 10, 7)->nullable()->after('venue_lat');
        });

        Schema::table('projects', function (Blueprint $t) {
            $t->string('venue_address')->nullable()->after('notes');
            $t->decimal('venue_lat', 10, 7)->nullable()->after('venue_address');
            $t->decimal('venue_lng', 10, 7)->nullable()->after('venue_lat');
        });

        // Mevcut kayıtların presence değerini türet
        \Illuminate\Support\Facades\DB::statement("UPDATE project_day_personnel SET presence = CASE WHEN check_out_time IS NOT NULL THEN 'checked_out' WHEN check_in_time IS NOT NULL THEN 'checked_in' ELSE 'assigned' END");
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $t) => $t->dropColumn(['venue_address', 'venue_lat', 'venue_lng']));
        Schema::table('project_days', fn (Blueprint $t) => $t->dropColumn(['venue_lat', 'venue_lng']));
        Schema::table('personnel', fn (Blueprint $t) => $t->dropColumn(['last_lat', 'last_lng', 'last_location_at']));
        Schema::dropIfExists('personnel_locations');
        Schema::dropIfExists('personnel_breaks');
        Schema::table('project_day_personnel', fn (Blueprint $t) => $t->dropColumn(['presence', 'break_started_at', 'break_minutes']));
    }
};
