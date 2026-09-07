<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_day_personnel', function (Blueprint $table) {
            $table->decimal('overtime_hours', 4, 2)->default(0)->after('daily_wage');
            $table->decimal('overtime_rate', 10, 2)->default(0)->after('overtime_hours');
            $table->decimal('total_earnings', 10, 2)->default(0)->after('overtime_rate');
            $table->boolean('is_checked')->default(false)->after('notes');
            $table->string('check_out_photo')->nullable()->after('check_out_time');
        });
    }

    public function down(): void
    {
        Schema::table('project_day_personnel', function (Blueprint $table) {
            $table->dropColumn(['overtime_hours', 'overtime_rate', 'total_earnings', 'is_checked', 'check_out_photo']);
        });
    }
};
