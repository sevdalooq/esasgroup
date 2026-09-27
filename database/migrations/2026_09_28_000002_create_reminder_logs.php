<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Hatırlatma bildirimlerinin tekrarını önleyen kayıt + gün başlangıç saati. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_day_id')->nullable()->constrained('project_days')->cascadeOnDelete();
            $table->string('type', 50);                 // personnel_missing, event_soon, no_supervisor, rental_due...
            $table->string('subject_key', 100)->nullable(); // aynı tür için ek anahtar (ör. kiralama id)
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->index(['project_id', 'project_day_id', 'type', 'subject_key'], 'reminder_logs_lookup');
        });

        Schema::table('project_days', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('date')->comment('Etkinlik/vardiya başlangıç saati (hatırlatmalar için)');
        });
    }

    public function down(): void
    {
        Schema::table('project_days', fn (Blueprint $t) => $t->dropColumn('start_time'));
        Schema::dropIfExists('reminder_logs');
    }
};
