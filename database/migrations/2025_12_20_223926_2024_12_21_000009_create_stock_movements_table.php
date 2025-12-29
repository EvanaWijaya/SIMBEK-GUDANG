<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel tracking pergerakan stok
     * Menggunakan INT (serial) untuk ID
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->increments('id'); // INT UNSIGNED AUTO_INCREMENT
            
            // Tipe Pergerakan
            $table->enum('tipe', ['masuk', 'keluar']); // in / out
            
            // Sumber Pergerakan
            $table->string('sumber'); // 'production', 'sale', 'disposal', 'adjustment', 'purchase'
            
            // Jumlah
            $table->decimal('qty', 10, 2);
            
            // Relasi ke Product Stock (untuk produk jadi)
            $table->unsignedInteger('product_stock_id')->nullable();
            $table->foreign('product_stock_id')
                  ->references('id')
                  ->on('product_stock')
                  ->onDelete('set null');
            
            // Relasi ke Material (untuk bahan baku)
            $table->unsignedInteger('material_id')->nullable();
            $table->foreign('material_id')
                  ->references('id')
                  ->on('materials')
                  ->onDelete('set null');
            
            // Reference ID (ID dari transaksi sumber)
            $table->unsignedInteger('ref_id')->nullable()->comment('ID dari production/sale/disposal');
            
            // Catatan
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes untuk analytics & ROP calculation
            $table->index('tipe');
            $table->index('sumber');
            $table->index('created_at');
            $table->index(['material_id', 'tipe', 'created_at']); // Untuk daily usage calculation
            $table->index(['product_stock_id', 'tipe']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};