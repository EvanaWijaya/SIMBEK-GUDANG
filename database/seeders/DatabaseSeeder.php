<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * 
     * Jalankan semua seeder dengan urutan yang benar
     */
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('🌱 Starting Database Seeding...');
        $this->command->info('================================');

        // Urutan penting karena ada foreign key dependencies
        $this->call([
            UserSeeder::class,        // 1. Users dulu (untuk foreign key di production, sales, dll)
            MaterialSeeder::class,    // 2. Materials (untuk foreign key di formula_details)
            ProductSeeder::class,     // 3. Products (untuk foreign key di formulas)
            FormulaSeeder::class,     // 4. Formulas & Details (terakhir karena butuh products & materials)
        ]);

        $this->command->info('================================');
        $this->command->info('✅ Database Seeding Completed!');
        $this->command->info('');
        $this->command->info('📝 Login Credentials:');
        $this->command->info('   Admin: admin@simbek.com / password123');
        $this->command->info('   Owner: owner@simbek.com / password123');
        $this->command->info('');
    }
}