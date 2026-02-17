<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buat user Admin
        User::create([
            'name' => 'Admin GMMSales',
            'username' => 'admin',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        // Buat user Sales
        User::create([
            'name' => 'Sales GMMSales',
            'username' => 'sales',
            'password' => Hash::make('password'),
            'role' => 'sales',
        ]);

        // Buat beberapa sales tambahan untuk testing
        User::create([
            'name' => 'Budi Santoso',
            'username' => 'budi',
            'password' => Hash::make('password'),
            'role' => 'sales',
        ]);

        User::create([
            'name' => 'Siti Nurhaliza',
            'username' => 'siti',
            'password' => Hash::make('password'),
            'role' => 'sales',
        ]);
    }
}
