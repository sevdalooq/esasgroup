<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::table('personnel', function (Blueprint $table) {
            $table->string('email')->nullable()->after('phone');
            $table->string('fcm_token')->nullable()->after('email')->comment('Mobil push bildirim token');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('fcm_token')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('fcm_token'));
        Schema::table('personnel', fn (Blueprint $t) => $t->dropColumn(['email', 'fcm_token']));
        Schema::dropIfExists('notifications');
    }
};
