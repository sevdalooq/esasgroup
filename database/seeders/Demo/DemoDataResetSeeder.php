<?php

namespace Database\Seeders\Demo;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Yalnızca DemoDataSeeder'ın doldurduğu alan tablolarını boşaltır.
 * Kullanıcılar, roller, izinler, ayarlar, masraf kategorileri ve personel grupları KORUNUR.
 * (personel@ kullanıcısı da korunur; personnel tablosu boşaldığı için user_id bağı düşer ve
 * DemoDataSeeder::seedLiveDemo yeni ilk TV100 görevlisine yeniden bağlar — benzersizlik çakışması olmaz.)
 *
 * php artisan db:seed --class=Database\\Seeders\\Demo\\DemoDataResetSeeder
 * php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataResetSeeder extends Seeder
{
    private const TABLES = [
        'notifications',
        'personnel_locations',
        'personnel_breaks',
        'transactions',
        'customer_payments',
        'personnel_payments',
        'group_payments',
        'invoices',
        'inventory_damages',
        'project_expenses',
        'project_day_inventory',
        'project_day_personnel',
        'project_days',
        'zone_options',
        'projects',
        'inventory',
        'personnel_documents',
        'personnel_work_history',
        'personnel_trainings',
        'personnel_references',
        'personnel_children',
        'personnel_emergency_contacts',
        'personnel_languages',
        'personnel_computer_skills',
        'personnel_technical_devices',
        'personnel',
        'groups',
        'customer_contacts',
        'customers',
        'accounts',
    ];

    public function run(): void
    {
        if (!app()->environment('local')) {
            $this->command?->error('DemoDataResetSeeder yalnızca local ortamda çalışır.');
            return;
        }

        Schema::disableForeignKeyConstraints();
        try {
            foreach (self::TABLES as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->command?->info('Demo alan tabloları temizlendi (' . count(self::TABLES) . ' tablo). Kullanıcı/rol/ayar tabloları korundu.');
    }
}
