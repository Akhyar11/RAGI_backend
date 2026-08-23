<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabel sesi pengguna aktif untuk pelacakan login per perangkat.
     * Berbeda dengan tabel sessions bawaan Laravel (yang berbasis file/cookie),
     * tabel ini menyimpan sesi API secara eksplisit untuk keperluan manajemen
     * (misal: "Lihat perangkat aktif" dan "Logout dari semua perangkat").
     * Nama tabel: user_sessions_iam (dibedakan dari tabel sessions bawaan Laravel)
     */
    public function up(): void
    {
        Schema::create('core_user_sessions_iam', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('core_users')->onDelete('cascade');

            // Token sesi unik (berbeda dengan SSO token)
            $table->string('token', 128)->unique();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Sesi kedaluwarsa setelah durasi tertentu (null = tidak pernah expired)
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->nullable();

            // Index untuk query: "tampilkan semua sesi aktif user X"
            $table->index(['user_id', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('core_user_sessions_iam');
    }
};
