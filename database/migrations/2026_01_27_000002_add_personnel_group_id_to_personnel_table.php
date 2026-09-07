<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personnel', function (Blueprint $table) {
            $table->foreignId('personnel_group_id')
                ->nullable()
                ->after('group_id')
                ->constrained('personnel_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('personnel', function (Blueprint $table) {
            $table->dropConstrainedForeignId('personnel_group_id');
        });
    }
};
