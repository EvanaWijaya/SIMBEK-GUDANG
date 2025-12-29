<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Membuat 2 user:
     * 1. Admin Inventory (full access)
     * 2. Owner Inventory (read only)
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin Inventory',
                'email' => 'admin@simbek.com',
                'password' => Hash::make('password123'),
                'alamat' => 'Jl. Raya Bandar Lampung No. 123',
                'kota' => 'Bandar Lampung',
                'provinsi' => 'Lampung',
                'role' => 'admin_inventory',
            ],
            [
                'name' => 'Owner Peternakan',
                'email' => 'owner@simbek.com',
                'password' => Hash::make('password123'),
                'alamat' => 'Jl. Peternakan Kambing No. 456',
                'kota' => 'Bandar Lampung',
                'provinsi' => 'Lampung',
                'role' => 'owner_inventory',
            ],
        ];

        foreach ($users as $userData) {
            User::create($userData);
        }

        $this->command->info('✅ 2 Users created successfully!');
        $this->command->info('   Admin: admin@simbek.com / password123');
        $this->command->info('   Owner: owner@simbek.com / password123');
    }
}