<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Personel tablosuna yeni alanlar
        Schema::table('personnel', function (Blueprint $table) {
            if (!Schema::hasColumn('personnel', 'address')) {
                $table->text('address')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('personnel', 'photo_1')) {
                $table->string('photo_1')->nullable()->after('address');
            }
            if (!Schema::hasColumn('personnel', 'photo_2')) {
                $table->string('photo_2')->nullable()->after('photo_1');
            }
            if (!Schema::hasColumn('personnel', 'photo_3')) {
                $table->string('photo_3')->nullable()->after('photo_2');
            }
            if (!Schema::hasColumn('personnel', 'description')) {
                $table->text('description')->nullable()->after('photo_3');
            }
            // Banka bilgileri
            if (!Schema::hasColumn('personnel', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('description');
            }
            if (!Schema::hasColumn('personnel', 'iban')) {
                $table->string('iban')->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('personnel', 'account_holder_name')) {
                $table->string('account_holder_name')->nullable()->after('iban');
            }
        });

        // Envanter tablosuna birim ve birim fiyat
        Schema::table('inventory', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory', 'unit')) {
                $table->string('unit')->nullable()->after('type'); // adet, metre, kg vs.
            }
            if (!Schema::hasColumn('inventory', 'unit_price')) {
                $table->decimal('unit_price', 10, 2)->default(0)->after('unit');
            }
        });

        // Müşteri tablosuna IBAN ve açıklama
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'iban')) {
                $table->string('iban')->nullable()->after('email');
            }
            if (!Schema::hasColumn('customers', 'description')) {
                $table->text('description')->nullable()->after('iban');
            }
        });

        // Proje tablosuna teklif no, hizmet teslim tipi ve onay durumu
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'offer_number')) {
                $table->string('offer_number')->nullable()->after('id'); // Teklif No
            }
            if (!Schema::hasColumn('projects', 'delivery_type')) {
                $table->string('delivery_type')->nullable()->after('notes'); // Hizmet teslim tipi
            }
            if (!Schema::hasColumn('projects', 'requires_approval')) {
                $table->boolean('requires_approval')->default(true)->after('status');
            }
            if (!Schema::hasColumn('projects', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('requires_approval');
            }
            if (!Schema::hasColumn('projects', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personnel', function (Blueprint $table) {
            $columns = ['address', 'photo_1', 'photo_2', 'photo_3', 'description', 'bank_name', 'iban', 'account_holder_name'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('personnel', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('inventory', function (Blueprint $table) {
            $columns = ['unit', 'unit_price'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('inventory', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            $columns = ['iban', 'description'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            // Drop foreign key first if exists
            if (Schema::hasColumn('projects', 'approved_by')) {
                $table->dropForeign(['approved_by']);
            }

            $columns = ['offer_number', 'delivery_type', 'requires_approval', 'approved_at', 'approved_by'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('projects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
