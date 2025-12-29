<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel produk jadi (pakan/obat hasil produksi)
     * Menggunakan INT (serial) untuk ID
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->increments('id'); // INT UNSIGNED AUTO_INCREMENT
            
            // Identifikasi Produk
            $table->string('kode_produk')->unique();
            $table->string('nama_produk');
            $table->string('kategori'); // Pakan Starter / Pakan Grower / Obat / dll
            
            // Unit & Pricing
            $table->string('satuan')->default('kg'); // Konsisten pakai kg
            $table->decimal('harga_jual', 12, 2);
            
            // Deskripsi
            $table->text('deskripsi')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('kode_produk');
            $table->index('kategori');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};