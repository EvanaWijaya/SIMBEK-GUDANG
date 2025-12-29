<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel transaksi penjualan produk
     * Menggunakan INT (serial) untuk ID
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->increments('id'); // INT UNSIGNED AUTO_INCREMENT
            
            // User yang melakukan transaksi
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict');
            
            // Produk yang dijual
            $table->unsignedInteger('product_id');
            $table->foreign('product_id')
                  ->references('id')
                  ->on('products')
                  ->onDelete('restrict');
            
            // Detail Transaksi
            $table->decimal('qty', 10, 2); // Jumlah yang dijual
            $table->decimal('total_harga', 12, 2); // Total harga penjualan
            
            // Payment Info
            $table->string('metode_bayar'); // Cash / Transfer / Credit
            $table->enum('status', ['pending', 'selesai', 'batal'])->default('selesai');
            
            // Tanggal Transaksi
            $table->date('tgl_transaksi');
            
            $table->timestamps();
            
            // Indexes
            $table->index('tgl_transaksi');
            $table->index('status');
            $table->index(['product_id', 'tgl_transaksi']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};