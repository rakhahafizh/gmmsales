<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerPhoto;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Seed beberapa customer dummy dari sales yang berbeda
     * supaya endpoint monitoring admin (US-10, US-11) bisa di-test
     * tanpa harus input manual lewat frontend.
     */
    public function run(): void
    {
        $salesBudi = User::where('username', 'budi')->first();
        $salesSiti = User::where('username', 'siti')->first();

        if (!$salesBudi || !$salesSiti) {
            return;
        }

        $dummyCustomers = [
            [
                'user_id' => $salesBudi->id,
                'nama_customer' => 'Toko Jaya Abadi',
                'alamat' => 'Jl. Merdeka No. 10, Jakarta Pusat',
                'nomor_telepon' => '081234567890',
                'latitude' => -6.1751,
                'longitude' => 106.8650,
                'visited_at' => now()->subDays(2),
            ],
            [
                'user_id' => $salesBudi->id,
                'nama_customer' => 'CV Sumber Rejeki',
                'alamat' => 'Jl. Sudirman No. 25, Jakarta Selatan',
                'nomor_telepon' => '081234567891',
                'latitude' => -6.2088,
                'longitude' => 106.8456,
                'visited_at' => now()->subDay(),
            ],
            [
                'user_id' => $salesSiti->id,
                'nama_customer' => 'PT Makmur Sentosa',
                'alamat' => 'Jl. Gatot Subroto No. 5, Jakarta Barat',
                'nomor_telepon' => '081234567892',
                'latitude' => -6.2297,
                'longitude' => 106.8086,
                'visited_at' => now(),
            ],
        ];

        foreach ($dummyCustomers as $data) {
            $customer = Customer::create($data);

            // Foto placeholder (path dummy, bisa diganti waktu testing manual)
            CustomerPhoto::create([
                'customer_id' => $customer->id,
                'photo_path' => "customers/{$customer->id}/placeholder.jpg",
            ]);
        }
    }
}