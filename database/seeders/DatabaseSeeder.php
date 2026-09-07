<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@esasgroup.com.tr'],
            ['name' => 'Yönetici', 'password' => bcrypt('EsasAdmin2026!'), 'is_active' => true]
        );

        $this->call([
            RolesAndPermissionsSeeder::class,
            SettingsSeeder::class,
            ExpenseCategoriesSeeder::class,
            PersonnelGroupSeeder::class,
        ]);

        // Gerçekçi demo verisi yalnızca yerel geliştirme ortamında
        if (app()->environment('local')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
