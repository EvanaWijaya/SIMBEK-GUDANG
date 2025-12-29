<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * CREATE tabel users BARU untuk sistem inventory (simbek_gudang)
     * Menggunakan INT (serial) untuk ID
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id'); // INT UNSIGNED AUTO_INCREMENT
            
            // Info User
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            
            // Alamat Lengkap
            $table->text('alamat')->nullable();
            $table->string('kota')->nullable();
            $table->string('provinsi')->nullable();
            
            // Role untuk sistem inventory
            $table->enum('role', ['admin_inventory', 'owner_inventory'])
                  ->default('admin_inventory')
                  ->comment('admin_inventory: full access, owner_inventory: read only');
            
            // Laravel default
            $table->rememberToken();
            $table->timestamps();
            
            // Indexes
            $table->index('email');
            $table->index('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};