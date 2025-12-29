<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel stok produk hasil produksi
     * Menggunakan INT (serial) untuk ID
     */
    public function up(): void
    {
        Schema::create('product_stock', function (Blueprint $table) {
            $table->increments('id'); // INT UNSIGNED AUTO_INCREMENT
            
            // Relasi ke Produk
            $table->unsignedInteger('product_id');
            $table->foreign('product_id')
                  ->references('id')
                  ->on('products')
                  ->onDelete('restrict');
            
            // Relasi ke Produksi (batch tracking)
            $table->unsignedInteger('production_id');
            $table->foreign('production_id')
                  ->references('id')
                  ->on('production')
                  ->onDelete('restrict');
            
            // Jumlah Stok
            $table->decimal('qty', 10, 2); // Stok tersisa dari batch ini
            
            $table->timestamps();
            
            // Indexes
            $table->index('product_id');
            $table->index('production_id');
            $table->index(['product_id', 'qty']); // Untuk cek ketersediaan stok
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_stock');
    }
};