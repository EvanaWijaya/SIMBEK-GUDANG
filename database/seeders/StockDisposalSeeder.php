<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StockDisposal;

class StockDisposalSeeder extends Seeder
{
    public function run(): void
    {
        StockDisposal::insert([
            [
                'product_id' => 1,
                'qty' => 2,
                'alasan' => 'Expired',
                'tindakan' => 'Dimusnahkan',
                'tgl_disposal' => now(),
                'user_id' => 1,
                'created_at' => now()
            ],
        ]);
    }
}
