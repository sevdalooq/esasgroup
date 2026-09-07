<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_day_personnel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personnel_id')->constrained('personnel')->cascadeOnDelete();
            $table->decimal('daily_wage', 10, 2)->default(0);
            $table->string('zone')->nullable(); // kulis, sahne arkası vs.
            $table->timestamp('check_in_time')->nullable();
            $table->string('check_in_photo')->nullable();
            $table->timestamp('check_out_time')->nullable();
            $table->enum('payment_status', ['pending', 'partial', 'paid'])->default('pending');
            $table->enum('payment_method', ['cash', 'bank', 'mixed'])->nullable();
            $table->decimal('payment_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['project_day_id', 'personnel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_day_personnel');
    }
};
