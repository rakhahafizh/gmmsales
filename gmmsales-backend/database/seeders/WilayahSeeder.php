<?php

namespace Database\Seeders;

use App\Models\Wilayah;
use Illuminate\Database\Seeder;

class WilayahSeeder extends Seeder
{
    public function run(): void
    {
        $wilayahs = [
            'WOK Manado Talaud',
            'WOK Bitung Minahasa',
            'WOK Bolaangmongondow',
            'WOK Ternate Morotai',
            'WOK Halmahera Tidore',
        ];

        foreach ($wilayahs as $nama) {
            Wilayah::create(['nama' => $nama]);
        }
    }
}