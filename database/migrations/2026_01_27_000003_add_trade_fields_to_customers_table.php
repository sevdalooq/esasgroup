<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('mernis_no')->nullable()->after('tax_number');
            $table->string('trade_registry_no')->nullable()->after('mernis_no');
            $table->string('trade_registry_office')->nullable()->after('trade_registry_no');
            $table->boolean('is_e_invoice')->default(false)->after('trade_registry_office');
            $table->boolean('is_e_archive')->default(false)->after('is_e_invoice');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'mernis_no',
                'trade_registry_no',
                'trade_registry_office',
                'is_e_invoice',
                'is_e_archive',
            ]);
        });
    }
};
