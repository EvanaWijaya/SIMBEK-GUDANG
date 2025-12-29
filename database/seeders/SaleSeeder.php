<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Sale;

class SaleSeeder extends Seeder
{
    public function run(): void
    {
        Sale::insert([
            [
                'pelanggan'  => 'Warung Pak Wawan',
                'product_id' => 1,
                'qty' => 20,
                'total_harga' => 170000,
                'metode_bayar' => 'tunai',
                'tgl_transaksi' => now(),
                'created_at' => now()
            ],
        ]);
    }
}
