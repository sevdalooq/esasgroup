<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_payments', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('project_id')->constrained('accounts')->nullOnDelete();
            $table->enum('type', ['commission', 'payment'])->default('payment')->after('amount');
            $table->decimal('commission_base', 12, 2)->nullable()->after('type'); // Komisyon hesaplanan tutar
            $table->string('commission_type')->nullable()->after('commission_base'); // fixed, percentage
            $table->decimal('commission_rate', 8, 2)->nullable()->after('commission_type'); // Oran veya sabit tutar
            $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('group_payments', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropForeign(['created_by']);
            $table->dropColumn(['account_id', 'type', 'commission_base', 'commission_type', 'commission_rate', 'created_by']);
        });
    }
};
