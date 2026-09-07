<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Yemek',
                'slug' => 'food',
                'icon' => 'tabler-tools-kitchen-2',
                'color' => 'warning',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Ulasim',
                'slug' => 'transport',
                'icon' => 'tabler-car',
                'color' => 'info',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Malzeme',
                'slug' => 'material',
                'icon' => 'tabler-package',
                'color' => 'primary',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Konaklama',
                'slug' => 'accommodation',
                'icon' => 'tabler-building',
                'color' => 'secondary',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Diger',
                'slug' => 'other',
                'icon' => 'tabler-dots',
                'color' => 'default',
                'is_active' => true,
                'sort_order' => 99,
            ],
        ];

        foreach ($categories as $category) {
            ExpenseCategory::firstOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
