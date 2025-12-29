<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ActivityLog;

class ActivityLogSeeder extends Seeder
{
    public function run(): void
    {
        ActivityLog::insert([
            [
                'user_id' => 1,
                'aksi' => 'CREATE_PRODUCTION',
                'catatan' => 'Produksi pakan ayam starter',
                'created_at' => now()
            ],
        ]);
    }
}
