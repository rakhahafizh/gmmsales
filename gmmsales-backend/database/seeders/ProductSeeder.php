<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['nama_produk' => '20 Mbps Internet', 'harga' => 230000],
            ['nama_produk' => '30 Mbps Internet', 'harga' => 245000],
            ['nama_produk' => '50 Mbps Internet', 'harga' => 270000],
            ['nama_produk' => '100 Mbps Internet', 'harga' => 365000],
            ['nama_produk' => '20 Mbps + TV', 'harga' => 305000],
            ['nama_produk' => '30 Mbps + TV', 'harga' => 320000],
            ['nama_produk' => '50 Mbps + TV', 'harga' => 345000],
            ['nama_produk' => '100 Mbps + TV', 'harga' => 440000],
            ['nama_produk' => 'Paket Phoenix 50 Mbps', 'harga' => 375000],
            ['nama_produk' => 'Paket Phoenix 100 Mbps', 'harga' => 475000],
            ['nama_produk' => 'Paket Family 20 Mbps', 'harga' => 280000],
            ['nama_produk' => 'Paket Family 50 Mbps', 'harga' => 395000],
            ['nama_produk' => 'Paket Pro 100 Mbps', 'harga' => 525000],
            ['nama_produk' => 'Paket Pro 200 Mbps', 'harga' => 695000],
            ['nama_produk' => 'Paket Premium 50 Mbps + TV', 'harga' => 425000],
            ['nama_produk' => 'Paket Premium 100 Mbps + TV', 'harga' => 565000],
            ['nama_produk' => 'Paket Bisnis 100 Mbps', 'harga' => 750000],
            ['nama_produk' => 'Paket Bisnis 200 Mbps', 'harga' => 985000],
            ['nama_produk' => 'Paket Streamer 50 Mbps', 'harga' => 410000],
            ['nama_produk' => 'Paket Streamer 100 Mbps', 'harga' => 545000],
            ['nama_produk' => 'Paket Hemat 10 Mbps', 'harga' => 195000],
            ['nama_produk' => 'Paket Hemat 15 Mbps', 'harga' => 215000],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}