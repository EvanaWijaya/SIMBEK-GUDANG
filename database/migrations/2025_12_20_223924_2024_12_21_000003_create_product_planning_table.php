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
        Schema::create('product_planning', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('product_id');
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->on('cascade');

            $table->decimal('stok_min', 10, 2)->default(0);
            $table->integer('lead_time_days')->default(7);
            $table->decimal('safety_stock', 10, 2)->default(0);

            $table->timestamps();

            $table->unique('product_id');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_planning');
    }
};