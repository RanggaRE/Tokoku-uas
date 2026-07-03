<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed users with roles
        User::updateOrCreate(
            ['email' => 'admin@tokoku.com'],
            [
                'name' => 'Admin Gudang',
                'password' => Hash::make('password'),
                'role' => 'admin_gudang',
            ]
        );

        User::updateOrCreate(
            ['email' => 'kasir@tokoku.com'],
            [
                'name' => 'Kasir Toko',
                'password' => Hash::make('password'),
                'role' => 'kasir',
            ]
        );

        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'role' => 'kasir',
            ]
        );

        // Run other seeders
        $this->call([
            CategorySeeder::class,
            ProductSeeder::class,
        ]);
    }
}
