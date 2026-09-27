<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Dışarıdan kiralanan envanter takibi (envanter yetersiz kaldığında). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_day_id')->nullable()->constrained('project_days')->nullOnDelete();
            $table->string('item_name');                       // Telsiz, Mojo Bariyer...
            $table->unsignedInteger('quantity')->default(1);
            $table->string('supplier')->nullable();
            $table->string('supplier_phone', 50)->nullable();
            $table->date('rented_at');
            $table->date('due_date')->nullable();              // iade edilmesi gereken tarih
            $table->timestamp('returned_at')->nullable();
            $table->decimal('daily_cost', 12, 2)->nullable();   // birim günlük kira
            $table->decimal('total_cost', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->text('return_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'returned_at']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_rentals');
    }
};
