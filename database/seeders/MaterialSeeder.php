<?php

namespace Database\Seeders;

use App\Models\Material;
use Illuminate\Database\Seeder;

class MaterialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Data bahan baku untuk pakan & obat kambing
     */
    public function run(): void
    {
        $materials = [
            // PAKAN
            [
                'kategori' => 'Pakan',
                'nama_material' => 'Jagung Giling',
                'satuan' => 'kg',
                'stok' => 500.00,
                'stok_min' => 100.00,
                'lead_time_days' => 7,
                'safety_stock' => 50.00,
                'harga' => 5000.00,
                'supplier' => 'Toko Pertanian Jaya',
                'expired_date' => now()->addMonths(6)->format('Y-m-d'),
            ],
            [
                'kategori' => 'Pakan',
                'nama_material' => 'Dedak Halus',
                'satuan' => 'kg',
                'stok' => 300.00,
                'stok_min' => 80.00,
                'lead_time_days' => 5,
                'safety_stock' => 40.00,
                'harga' => 3500.00,
                'supplier' => 'CV. Pakan Ternak',
                'expired_date' => now()->addMonths(4)->format('Y-m-d'),
            ],
            [
                'kategori' => 'Pakan',
                'nama_material' => 'Konsentrat Kambing',
                'satuan' => 'kg',
                'stok' => 200.00,
                'stok_min' => 50.00,
                'lead_time_days' => 10,
                'safety_stock' => 30.00,
                'harga' => 8000.00,
                'supplier' => 'PT. Nutrisi Ternak Indonesia',
                'expired_date' => now()->addMonths(8)->format('Y-m-d'),
            ],
            [
                'kategori' => 'Pakan',
                'nama_material' => 'Bungkil Kelapa',
                'satuan' => 'kg',
                'stok' => 150.00,
                'stok_min' => 40.00,
                'lead_time_days' => 7,
                'safety_stock' => 25.00,
                'harga' => 4500.00,
                'supplier' => 'Toko Pertanian Jaya',
                'expired_date' => now()->addMonths(5)->format('Y-m-d'),
            ],
            [
                'kategori' => 'Pakan',
                'nama_material' => 'Tepung Ikan',
                'satuan' => 'kg',
                'stok' => 100.00,
                'stok_min' => 30.00,
                'lead_time_days' => 14,
                'safety_stock' => 20.00,
                'harga' => 12000.00,
                'supplier' => 'CV. Pakan Ternak',
                'expired_date' => now()->addMonths(6)->format('Y-m-d'),
            ],

            // MINERAL & VITAMIN
            [
                'kategori' => 'Mineral',
                'nama_material' => 'Garam Mineral',
                'satuan' => 'kg',
                'stok' => 80.00,
                'stok_min' => 20.00,
                'lead_time_days' => 14,
                'safety_stock' => 15.00,
                'harga' => 15000.00,
                'supplier' => 'PT. Nutrisi Ternak Indonesia',
                'expired_date' => now()->addYears(2)->format('Y-m-d'),
            ],
            [
                'kategori' => 'Vitamin',
                'nama_material' => 'Vitamin B-Complex',
                'satuan' => 'kg',
                'stok' => 50.00,
                'stok_min' => 15.00,
                'lead_time_days' => 21,
                'safety_stock' => 10.00,
                'harga' => 25000.00,
                'supplier' => 'Apotek Hewan Sejahtera',
                'expired_date' => now()->addMonths(18)->format('Y-m-d'),
            ],
            [
                'kategori' => 'Vitamin',
                'nama_material' => 'Vitamin AD3E',
                'satuan' => 'kg',
                'stok' => 40.00,
                'stok_min' => 12.00,
                'lead_time_days' => 21,
                'safety_stock' => 8.00,
                'harga' => 30000.00,
                'supplier' => 'Apotek Hewan Sejahtera',
                'expired_date' => now()->addMonths(15)->format('Y-m-d'),
            ],

            // OBAT
            [
                'kategori' => 'Obat',
                'nama_material' => 'Obat Cacing (Albendazole)',
                'satuan' => 'kg',
                'stok' => 30.00,
                'stok_min' => 10.00,
                'lead_time_days' => 30,
                'safety_stock' => 8.00,
                'harga' => 50000.00,
                'supplier' => 'Apotek Hewan Sejahtera',
                'expired_date' => now()->addMonths(24)->format('Y-m-d'),
            ],
            [
                'kategori' => 'Obat',
                'nama_material' => 'Antibiotik (Oxytetracycline)',
                'satuan' => 'kg',
                'stok' => 25.00,
                'stok_min' => 8.00,
                'lead_time_days' => 30,
                'safety_stock' => 6.00,
                'harga' => 75000.00,
                'supplier' => 'Apotek Hewan Sejahtera',
                'expired_date' => now()->addMonths(20)->format('Y-m-d'),
            ],
        ];

        foreach ($materials as $material) {
            Material::create($material);
        }

        $this->command->info('✅ ' . count($materials) . ' Materials created successfully!');
    }
}