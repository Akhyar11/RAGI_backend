<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabel untuk mengelola proses lupa password.
     * Token bersifat single-use (is_used) dan memiliki waktu kedaluwarsa (expires_at).
     * Menggantikan tabel bawaan Laravel password_reset_tokens yang berbasis email.
     */
    public function up(): void
    {
        Schema::create('password_resets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Token reset: dikirim via email, hashed sebelum disimpan
            $table->string('token', 128)->unique();

            // Token kedaluwarsa setelah 60 menit
            $table->timestamp('expires_at');

            // Mencegah token dipakai lebih dari sekali
            $table->boolean('is_used')->default(false);

            $table->timestamp('created_at')->nullable();

            // Index untuk validasi: cari token aktif milik user
            $table->index(['user_id', 'is_used', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_resets');
    }
};
