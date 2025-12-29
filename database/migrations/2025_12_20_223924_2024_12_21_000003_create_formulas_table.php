<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel formula/resep produksi
     * Menggunakan INT (serial) untuk ID
     */
    public function up(): void
    {
        Schema::create('formulas', function (Blueprint $table) {
            $table->increments('id'); // INT UNSIGNED AUTO_INCREMENT
            
            // Relasi ke Produk
            $table->unsignedInteger('product_id');
            $table->foreign('product_id')
                  ->references('id')
                  ->on('products')
                  ->onDelete('cascade');
            
            // Info Formula
            $table->string('nama_formula');
            $table->text('catatan')->nullable();
            
            // Status (untuk versioning kalau butuh)
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            // Indexes
            $table->index('product_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formulas');
    }
};