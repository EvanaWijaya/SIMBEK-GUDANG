<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Data produk pakan & obat kambing
     */
    public function run(): void
    {
        $products = [
            // PAKAN
            [
                'kode_produk' => 'PKN-001',
                'nama_produk' => 'Pakan Kambing Starter',
                'kategori' => 'Pakan',
                'satuan' => 'kg',
                'harga_jual' => 15000.00,
                'deskripsi' => 'Pakan khusus untuk anak kambing umur 0-3 bulan. Tinggi protein dan mudah dicerna.',
            ],
            [
                'kode_produk' => 'PKN-002',
                'nama_produk' => 'Pakan Kambing Grower',
                'kategori' => 'Pakan',
                'satuan' => 'kg',
                'harga_jual' => 12000.00,
                'deskripsi' => 'Pakan untuk kambing muda umur 4-8 bulan. Mendukung pertumbuhan optimal.',
            ],
            [
                'kode_produk' => 'PKN-003',
                'nama_produk' => 'Pakan Kambing Finisher',
                'kategori' => 'Pakan',
                'satuan' => 'kg',
                'harga_jual' => 10000.00,
                'deskripsi' => 'Pakan untuk kambing penggemukan. Formulasi khusus untuk penambahan berat badan.',
            ],
            [
                'kode_produk' => 'PKN-004',
                'nama_produk' => 'Pakan Kambing Indukan',
                'kategori' => 'Pakan',
                'satuan' => 'kg',
                'harga_jual' => 13000.00,
                'deskripsi' => 'Pakan untuk kambing bunting dan menyusui. Tinggi kalsium dan vitamin.',
            ],

            // SUPLEMEN
            [
                'kode_produk' => 'SUP-001',
                'nama_produk' => 'Suplemen Mineral Lengkap',
                'kategori' => 'Suplemen',
                'satuan' => 'kg',
                'harga_jual' => 35000.00,
                'deskripsi' => 'Suplemen mineral lengkap untuk mencegah defisiensi. Campurkan ke pakan.',
            ],
            [
                'kode_produk' => 'SUP-002',
                'nama_produk' => 'Vitamin Booster',
                'kategori' => 'Suplemen',
                'satuan' => 'kg',
                'harga_jual' => 45000.00,
                'deskripsi' => 'Vitamin kompleks untuk meningkatkan daya tahan tubuh kambing.',
            ],

            // OBAT
            [
                'kode_produk' => 'OBT-001',
                'nama_produk' => 'Obat Cacing Plus',
                'kategori' => 'Obat',
                'satuan' => 'kg',
                'harga_jual' => 80000.00,
                'deskripsi' => 'Obat cacing broad spectrum untuk kambing. Dosis sesuai berat badan.',
            ],
            [
                'kode_produk' => 'OBT-002',
                'nama_produk' => 'Antibiotik Injeksi',
                'kategori' => 'Obat',
                'satuan' => 'kg',
                'harga_jual' => 120000.00,
                'deskripsi' => 'Antibiotik spektrum luas untuk infeksi bakteri pada kambing.',
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }

        $this->command->info('✅ ' . count($products) . ' Products created successfully!');
    }
}