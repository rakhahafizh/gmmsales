<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Ambil wilayah yang udah di-seed sebelumnya
        $wokManadoTalaud = Wilayah::where('nama', 'WOK Manado Talaud')->first();
        $wokBitung = Wilayah::where('nama', 'WOK Bitung Minahasa')->first();
        $wokBolmong = Wilayah::where('nama', 'WOK Bolaangmongondow')->first();

        // Admin (tidak punya wilayah)
        User::create([
            'name' => 'Admin GMMSales',
            'username' => 'admin',
            'nomor_telepon' => '081234567800',
            'wilayah_id' => null,
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Sales 1
        User::create([
            'name' => 'Budi Santoso',
            'username' => 'budi',
            'nomor_telepon' => '081234567801',
            'wilayah_id' => $wokManadoTalaud?->id,
            'password' => Hash::make('password'),
            'role' => 'sales',
            'is_active' => true,
        ]);

        // Sales 2
        User::create([
            'name' => 'Siti Nurhaliza',
            'username' => 'siti',
            'nomor_telepon' => '081234567802',
            'wilayah_id' => $wokBitung?->id,
            'password' => Hash::make('password'),
            'role' => 'sales',
            'is_active' => true,
        ]);

        // Sales 3 (untuk variasi data)
        User::create([
            'name' => 'Andi Pratama',
            'username' => 'andi',
            'nomor_telepon' => '081234567803',
            'wilayah_id' => $wokBolmong?->id,
            'password' => Hash::make('password'),
            'role' => 'sales',
            'is_active' => true,
        ]);
    }
}