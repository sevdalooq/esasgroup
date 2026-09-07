<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['inventory', 'personnel', 'zone_options'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->uuid('qr_code')->nullable()->unique()->after('id');
            });
            foreach (DB::table($table)->whereNull('qr_code')->pluck('id') as $id) {
                DB::table($table)->where('id', $id)->update(['qr_code' => (string) Str::uuid()]);
            }
        }

        Schema::table('inventory', function (Blueprint $t) {
            $t->string('nfc_uid', 64)->nullable()->unique()->after('qr_code')->comment('NFC etiket UID (opsiyonel)');
        });
    }

    public function down(): void
    {
        Schema::table('inventory', fn (Blueprint $t) => $t->dropColumn('nfc_uid'));
        foreach (['inventory', 'personnel', 'zone_options'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('qr_code'));
        }
    }
};
