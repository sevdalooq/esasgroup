<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_damages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_day_inventory_id')->constrained('project_day_inventory')->cascadeOnDelete();
            $table->text('description');
            $table->string('photo')->nullable();
            $table->decimal('deduction_amount', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_damages');
    }
};
