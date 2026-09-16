<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zone_options', function (Blueprint $t) {
            $t->decimal('lat', 10, 7)->nullable()->after('name');
            $t->decimal('lng', 10, 7)->nullable()->after('lat');
            $t->string('description', 255)->nullable()->after('lng');
        });
        Schema::table('personnel_locations', function (Blueprint $t) {
            // gps: cihazdan, zone: alan QR'ı okutulduğunda alanın koordinatı
            $t->string('source', 10)->default('gps')->after('accuracy');
        });
    }

    public function down(): void
    {
        Schema::table('personnel_locations', fn (Blueprint $t) => $t->dropColumn('source'));
        Schema::table('zone_options', fn (Blueprint $t) => $t->dropColumn(['lat', 'lng', 'description']));
    }
};
