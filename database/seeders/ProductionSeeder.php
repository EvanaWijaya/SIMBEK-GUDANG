<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Production;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        Production::insert([
            [
                'product_id' => 1,
                'tgl_produksi' => now()->subDays(2),
                'jumlah_produksi' => 200,
                'satuan' => 'kg',
                'expired_date' => now()->addMonths(4),
                'status' => 'selesai',
                'user_id' => 2,
                'created_at' => now()
            ],
        ]);
    }
}
