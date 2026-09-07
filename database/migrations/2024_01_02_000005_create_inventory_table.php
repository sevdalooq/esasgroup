<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['zimmet', 'rental']); // zimmetli veya kiralık
            $table->string('serial_number')->nullable();
            $table->decimal('daily_rate', 10, 2)->default(0); // kiralık için günlük ücret
            $table->decimal('purchase_cost', 10, 2)->default(0);
            $table->enum('current_status', ['available', 'in_use', 'maintenance', 'damaged', 'lost'])->default('available');
            $table->foreignId('current_holder_id')->nullable()->constrained('personnel')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
