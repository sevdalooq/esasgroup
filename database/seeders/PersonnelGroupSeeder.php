<?php

namespace Database\Seeders;

use App\Models\PersonnelGroup;
use Illuminate\Database\Seeder;

class PersonnelGroupSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'Güvenlik',
            'Bodyguard',
            'Host',
            'Hostes',
        ];

        foreach ($defaults as $name) {
            PersonnelGroup::firstOrCreate(
                ['name' => $name],
                ['is_active' => true]
            );
        }
    }
}
