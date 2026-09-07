<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_day_inventory', function (Blueprint $table) {
            $table->foreignId('assigned_to_personnel_id')->nullable()->after('quantity')
                ->constrained('project_day_personnel')->nullOnDelete();
            $table->enum('return_status', ['pending', 'returned', 'damaged'])->default('pending')->after('returned_by');
            $table->string('damage_photo')->nullable()->after('return_status');
            $table->text('damage_description')->nullable()->after('damage_photo');
        });
    }

    public function down(): void
    {
        Schema::table('project_day_inventory', function (Blueprint $table) {
            $table->dropForeign(['assigned_to_personnel_id']);
            $table->dropColumn(['assigned_to_personnel_id', 'return_status', 'damage_photo', 'damage_description']);
        });
    }
};
