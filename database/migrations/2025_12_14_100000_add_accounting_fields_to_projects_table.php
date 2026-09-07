<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('customer_id')->constrained('accounts')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable()->after('approved_at');
            $table->foreignId('finalized_by')->nullable()->after('finalized_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropForeign(['finalized_by']);
            $table->dropColumn(['account_id', 'finalized_at', 'finalized_by']);
        });
    }
};
