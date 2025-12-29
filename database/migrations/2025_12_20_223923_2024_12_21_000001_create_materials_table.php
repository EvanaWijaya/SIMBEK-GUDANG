<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel bahan baku untuk pakan & obat ternak
     * Menggunakan INT (serial) untuk ID
     */
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->increments('id'); // INT UNSIGNED AUTO_INCREMENT
            
            // Informasi Material
            $table->string('kategori'); // Pakan / Obat / Vitamin / Mineral
            $table->string('nama_material');
            $table->string('satuan')->default('kg'); // Konsisten pakai kg
            
            // Stok Management
            $table->decimal('stok', 10, 2)->default(0); // Stok saat ini
            $table->decimal('stok_min', 10, 2)->default(0); // Stok minimum
            
            // ROP Calculation Fields
            $table->integer('lead_time_days')->default(7)->comment('Waktu tunggu restock dalam hari');
            $table->decimal('safety_stock', 10, 2)->default(0)->comment('Stok pengaman');
            
            // Harga & Supplier
            $table->decimal('harga', 12, 2); // Harga per kg
            $table->string('supplier')->nullable();
            
            // Expiry Tracking
            $table->date('expired_date')->nullable();
            
            $table->timestamps();
            
            // Indexes untuk performance
            $table->index('kategori');
            $table->index('stok');
            $table->index('expired_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};