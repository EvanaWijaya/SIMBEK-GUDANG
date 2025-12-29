<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StockMovement;

class StockMovementSeeder extends Seeder
{
    public function run(): void
    {
        StockMovement::insert([
            [
                'product_id' => 1,
                'tipe' => 'IN',
                'sumber' => 'PRODUCTION',
                'qty' => 200,
                'ref_id' => 1,
                'created_at' => now()
            ],
            [
                'product_id' => 1,
                'tipe' => 'OUT',
                'sumber' => 'SALES',
                'qty' => 20,
                'ref_id' => 1,
                'created_at' => now()
            ],
        ]);
    }
}
