<?php

use App\Models\Personnel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personnel', function (Blueprint $t) {
            // tc_no şifreli olduğu için benzersizlik/arama kontrolü sha256 özet üzerinden yapılır
            $t->string('tc_no_hash', 64)->nullable()->after('tc_no')->index();
        });

        // Mevcut kayıtları doldur (model accessor ile deşifre edip özetle)
        Personnel::withTrashed()->whereNotNull('tc_no')->chunkById(200, function ($rows) {
            foreach ($rows as $p) {
                try {
                    $tc = $p->tc_no;
                } catch (\Throwable $e) {
                    $tc = null;
                }
                if ($tc) {
                    DB::table('personnel')->where('id', $p->id)->update(['tc_no_hash' => Personnel::hashTcNo($tc)]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('personnel', function (Blueprint $t) {
            $t->dropIndex(['tc_no_hash']);
            $t->dropColumn('tc_no_hash');
        });
    }
};
