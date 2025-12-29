<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel disposal (pembuangan produk rusak/expired)
     * Menggunakan INT (serial) untuk ID
     */
    public function up(): void
    {
        Schema::create('stock_disposal', function (Blueprint $table) {
            $table->increments('id'); // INT UNSIGNED AUTO_INCREMENT
            
            // Relasi ke Product Stock yang di-dispose
            $table->unsignedInteger('product_stock_id');
            $table->foreign('product_stock_id')
                  ->references('id')
                  ->on('product_stock')
                  ->onDelete('restrict');
            
            // Jumlah yang dibuang
            $table->decimal('qty', 10, 2);
            
            // Alasan (WAJIB)
            $table->enum('alasan', ['expired', 'rusak', 'hilang', 'lainnya']);
            
            // Tindakan yang diambil
            $table->text('tindakan')->nullable()->comment('Catatan tindakan yang diambil');
            
            // Tanggal Disposal
            $table->date('tgl_disposal');
            
            // User yang melakukan disposal
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict');
            
            $table->timestamps();
            
            // Indexes
            $table->index('tgl_disposal');
            $table->index('alasan');
            $table->index('product_stock_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_disposal');
    }
};