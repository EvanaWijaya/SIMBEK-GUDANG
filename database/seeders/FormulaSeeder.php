<?php

namespace Database\Seeders;

use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\Product;
use App\Models\Material;
use Illuminate\Database\Seeder;

class FormulaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Membuat formula/resep untuk produk pakan
     * Formula berdasarkan best practice pakan kambing
     */
    public function run(): void
    {
        // Formula 1: Pakan Kambing Starter
        $product1 = Product::where('kode_produk', 'PKN-001')->first();
        if ($product1) {
            $formula1 = Formula::create([
                'product_id' => $product1->id,
                'nama_formula' => 'Formula Starter Standard',
                'catatan' => 'Formula untuk anak kambing 0-3 bulan. Tinggi protein 18-20%',
                'is_active' => true,
            ]);

            // Komposisi Pakan Starter (per 1 kg produk jadi)
            $materials1 = [
                ['nama' => 'Jagung Giling', 'qty' => 0.40],      // 40%
                ['nama' => 'Dedak Halus', 'qty' => 0.20],         // 20%
                ['nama' => 'Konsentrat Kambing', 'qty' => 0.25],  // 25%
                ['nama' => 'Tepung Ikan', 'qty' => 0.10],         // 10%
                ['nama' => 'Garam Mineral', 'qty' => 0.03],       // 3%
                ['nama' => 'Vitamin B-Complex', 'qty' => 0.02],   // 2%
            ];

            foreach ($materials1 as $mat) {
                $material = Material::where('nama_material', $mat['nama'])->first();
                if ($material) {
                    FormulaDetail::create([
                        'formula_id' => $formula1->id,
                        'material_id' => $material->id,
                        'qty' => $mat['qty'],
                        'satuan' => 'kg',
                    ]);
                }
            }
        }

        // Formula 2: Pakan Kambing Grower
        $product2 = Product::where('kode_produk', 'PKN-002')->first();
        if ($product2) {
            $formula2 = Formula::create([
                'product_id' => $product2->id,
                'nama_formula' => 'Formula Grower Standard',
                'catatan' => 'Formula untuk kambing muda 4-8 bulan. Protein 16-18%',
                'is_active' => true,
            ]);

            $materials2 = [
                ['nama' => 'Jagung Giling', 'qty' => 0.45],
                ['nama' => 'Dedak Halus', 'qty' => 0.25],
                ['nama' => 'Konsentrat Kambing', 'qty' => 0.20],
                ['nama' => 'Bungkil Kelapa', 'qty' => 0.07],
                ['nama' => 'Garam Mineral', 'qty' => 0.02],
                ['nama' => 'Vitamin AD3E', 'qty' => 0.01],
            ];

            foreach ($materials2 as $mat) {
                $material = Material::where('nama_material', $mat['nama'])->first();
                if ($material) {
                    FormulaDetail::create([
                        'formula_id' => $formula2->id,
                        'material_id' => $material->id,
                        'qty' => $mat['qty'],
                        'satuan' => 'kg',
                    ]);
                }
            }
        }

        // Formula 3: Pakan Kambing Finisher
        $product3 = Product::where('kode_produk', 'PKN-003')->first();
        if ($product3) {
            $formula3 = Formula::create([
                'product_id' => $product3->id,
                'nama_formula' => 'Formula Finisher Standard',
                'catatan' => 'Formula untuk kambing penggemukan. Protein 14-16%, tinggi energi',
                'is_active' => true,
            ]);

            $materials3 = [
                ['nama' => 'Jagung Giling', 'qty' => 0.50],
                ['nama' => 'Dedak Halus', 'qty' => 0.30],
                ['nama' => 'Konsentrat Kambing', 'qty' => 0.15],
                ['nama' => 'Bungkil Kelapa', 'qty' => 0.03],
                ['nama' => 'Garam Mineral', 'qty' => 0.02],
            ];

            foreach ($materials3 as $mat) {
                $material = Material::where('nama_material', $mat['nama'])->first();
                if ($material) {
                    FormulaDetail::create([
                        'formula_id' => $formula3->id,
                        'material_id' => $material->id,
                        'qty' => $mat['qty'],
                        'satuan' => 'kg',
                    ]);
                }
            }
        }

        // Formula 4: Pakan Kambing Indukan
        $product4 = Product::where('kode_produk', 'PKN-004')->first();
        if ($product4) {
            $formula4 = Formula::create([
                'product_id' => $product4->id,
                'nama_formula' => 'Formula Indukan Standard',
                'catatan' => 'Formula untuk kambing bunting & menyusui. Tinggi kalsium dan protein',
                'is_active' => true,
            ]);

            $materials4 = [
                ['nama' => 'Jagung Giling', 'qty' => 0.35],
                ['nama' => 'Dedak Halus', 'qty' => 0.25],
                ['nama' => 'Konsentrat Kambing', 'qty' => 0.25],
                ['nama' => 'Tepung Ikan', 'qty' => 0.05],
                ['nama' => 'Bungkil Kelapa', 'qty' => 0.05],
                ['nama' => 'Garam Mineral', 'qty' => 0.03],
                ['nama' => 'Vitamin AD3E', 'qty' => 0.02],
            ];

            foreach ($materials4 as $mat) {
                $material = Material::where('nama_material', $mat['nama'])->first();
                if ($material) {
                    FormulaDetail::create([
                        'formula_id' => $formula4->id,
                        'material_id' => $material->id,
                        'qty' => $mat['qty'],
                        'satuan' => 'kg',
                    ]);
                }
            }
        }

        // Formula 5: Suplemen Mineral
        $product5 = Product::where('kode_produk', 'SUP-001')->first();
        if ($product5) {
            $formula5 = Formula::create([
                'product_id' => $product5->id,
                'nama_formula' => 'Formula Suplemen Mineral',
                'catatan' => 'Campuran mineral lengkap untuk kambing',
                'is_active' => true,
            ]);

            $materials5 = [
                ['nama' => 'Garam Mineral', 'qty' => 0.90],
                ['nama' => 'Vitamin B-Complex', 'qty' => 0.05],
                ['nama' => 'Vitamin AD3E', 'qty' => 0.05],
            ];

            foreach ($materials5 as $mat) {
                $material = Material::where('nama_material', $mat['nama'])->first();
                if ($material) {
                    FormulaDetail::create([
                        'formula_id' => $formula5->id,
                        'material_id' => $material->id,
                        'qty' => $mat['qty'],
                        'satuan' => 'kg',
                    ]);
                }
            }
        }

        // Formula 6: Vitamin Booster
        $product6 = Product::where('kode_produk', 'SUP-002')->first();
        if ($product6) {
            $formula6 = Formula::create([
                'product_id' => $product6->id,
                'nama_formula' => 'Formula Vitamin Booster',
                'catatan' => 'Kombinasi vitamin untuk meningkatkan imunitas',
                'is_active' => true,
            ]);

            $materials6 = [
                ['nama' => 'Vitamin B-Complex', 'qty' => 0.50],
                ['nama' => 'Vitamin AD3E', 'qty' => 0.40],
                ['nama' => 'Garam Mineral', 'qty' => 0.10],
            ];

            foreach ($materials6 as $mat) {
                $material = Material::where('nama_material', $mat['nama'])->first();
                if ($material) {
                    FormulaDetail::create([
                        'formula_id' => $formula6->id,
                        'material_id' => $material->id,
                        'qty' => $mat['qty'],
                        'satuan' => 'kg',
                    ]);
                }
            }
        }

        // Formula 7: Obat Cacing Plus
        $product7 = Product::where('kode_produk', 'OBT-001')->first();
        if ($product7) {
            $formula7 = Formula::create([
                'product_id' => $product7->id,
                'nama_formula' => 'Formula Obat Cacing',
                'catatan' => 'Obat cacing dengan bahan aktif albendazole',
                'is_active' => true,
            ]);

            $materials7 = [
                ['nama' => 'Obat Cacing (Albendazole)', 'qty' => 0.95],
                ['nama' => 'Vitamin B-Complex', 'qty' => 0.05],
            ];

            foreach ($materials7 as $mat) {
                $material = Material::where('nama_material', $mat['nama'])->first();
                if ($material) {
                    FormulaDetail::create([
                        'formula_id' => $formula7->id,
                        'material_id' => $material->id,
                        'qty' => $mat['qty'],
                        'satuan' => 'kg',
                    ]);
                }
            }
        }

        // Formula 8: Antibiotik Injeksi
        $product8 = Product::where('kode_produk', 'OBT-002')->first();
        if ($product8) {
            $formula8 = Formula::create([
                'product_id' => $product8->id,
                'nama_formula' => 'Formula Antibiotik',
                'catatan' => 'Antibiotik spektrum luas untuk terapi infeksi',
                'is_active' => true,
            ]);

            $materials8 = [
                ['nama' => 'Antibiotik (Oxytetracycline)', 'qty' => 1.00],
            ];

            foreach ($materials8 as $mat) {
                $material = Material::where('nama_material', $mat['nama'])->first();
                if ($material) {
                    FormulaDetail::create([
                        'formula_id' => $formula8->id,
                        'material_id' => $material->id,
                        'qty' => $mat['qty'],
                        'satuan' => 'kg',
                    ]);
                }
            }
        }

        $this->command->info('✅ Formulas & Formula Details created successfully!');
    }
}