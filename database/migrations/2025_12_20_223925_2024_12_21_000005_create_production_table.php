<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel transaksi produksi
     * Menggunakan INT (serial) untuk ID
     */
    public function up(): void
    {
        Schema::create('production', function (Blueprint $table) {
            $table->increments('id'); // INT UNSIGNED AUTO_INCREMENT
            
            // Relasi ke Produk
            $table->unsignedInteger('product_id');
            $table->foreign('product_id')
                  ->references('id')
                  ->on('products')
                  ->onDelete('restrict');
            
            // Relasi ke Formula
            $table->unsignedInteger('formula_id');
            $table->foreign('formula_id')
                  ->references('id')
                  ->on('formulas')
                  ->onDelete('restrict');
            
            // Info Produksi
            $table->date('tgl_produksi');
            $table->decimal('jumlah', 10, 2); // Jumlah produk yang diproduksi
            $table->string('satuan')->default('kg');
            
            // Expiry Date (penting untuk pakan & obat)
            $table->date('expired_date')->nullable();
            
            // Status Produksi
            $table->enum('status', ['pending', 'selesai', 'batal'])->default('pending');
            
            // User yang melakukan produksi
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict');
            
            $table->timestamps();
            
            // Indexes
            $table->index('tgl_produksi');
            $table->index('status');
            $table->index(['product_id', 'tgl_produksi']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production');
    }
};