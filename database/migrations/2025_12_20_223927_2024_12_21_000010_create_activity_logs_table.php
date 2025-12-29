<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel activity log untuk audit trail
     * Menggunakan INT (serial) untuk ID
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->increments('id'); // INT UNSIGNED AUTO_INCREMENT
            
            // User yang melakukan aksi
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            
            // Jenis Aksi
            $table->string('aksi'); // 'login', 'logout', 'produksi', 'penjualan', 'disposal', dll
            
            // Catatan Detail
            $table->text('catatan')->nullable(); // JSON atau plain text dengan detail aksi
            
            // IP Address (optional, untuk security)
            $table->string('ip_address', 45)->nullable();
            
            // User Agent (optional)
            $table->text('user_agent')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('aksi');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};