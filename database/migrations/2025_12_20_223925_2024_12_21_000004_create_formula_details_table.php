<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Detail komposisi bahan baku dalam formula
     * Menggunakan INT (serial) untuk ID
     */
    public function up(): void
    {
        Schema::create('formula_details', function (Blueprint $table) {
            $table->increments('id'); // INT UNSIGNED AUTO_INCREMENT
            
            // Relasi ke Formula
            $table->unsignedInteger('formula_id');
            $table->foreign('formula_id')
                  ->references('id')
                  ->on('formulas')
                  ->onDelete('cascade');
            
            // Relasi ke Material
            $table->unsignedInteger('material_id');
            $table->foreign('material_id')
                  ->references('id')
                  ->on('materials')
                  ->onDelete('restrict'); // Prevent delete kalau masih dipakai
            
            // Jumlah Kebutuhan
            $table->decimal('qty', 10, 2); // Jumlah material yang dibutuhkan
            $table->string('satuan')->default('kg'); // Harus sama dengan material (kg)
            
            $table->timestamps();
            
            // Composite index untuk query cepat
            $table->index(['formula_id', 'material_id']);
            
            // Unique constraint: satu material hanya sekali per formula
            $table->unique(['formula_id', 'material_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formula_details');
    }
};