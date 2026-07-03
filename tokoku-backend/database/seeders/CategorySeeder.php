<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Makanan', 'description' => 'Semua jenis makanan ringan dan berat'],
            ['name' => 'Minuman', 'description' => 'Air mineral, soda, jus, kopi, teh, dll'],
            ['name' => 'Sembako', 'description' => 'Bahan pokok makanan sehari-hari'],
            ['name' => 'Alat Tulis', 'description' => 'Pena, buku, penggaris, pensil, dll'],
            ['name' => 'Kebutuhan Rumah Tangga', 'description' => 'Sabun, deterjen, pewangi pakaian, dll'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(['name' => $category['name']], $category);
        }
    }
}
