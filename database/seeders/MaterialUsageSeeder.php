<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MaterialUsage;

class MaterialUsageSeeder extends Seeder
{
    public function run(): void
    {
        MaterialUsage::insert([
            [
                'production_id' => 1,
                'material_id' => 1,
                'qty_dipakai' => 150,
                'created_at' => now()
            ],
        ]);
    }
}
