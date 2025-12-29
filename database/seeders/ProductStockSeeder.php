<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductStock;

class ProductStockSeeder extends Seeder
{
    public function run(): void
    {
        ProductStock::insert([
            [
                'product_id' => 1,
                'production_id' => 1,
                'qty' => 200,
                'created_at' => now()
            ],
        ]);
    }
}
