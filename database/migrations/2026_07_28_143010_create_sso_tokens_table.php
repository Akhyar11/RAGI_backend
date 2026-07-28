<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabel SSO Token untuk Single Sign-On antar aplikasi ekosistem kampus.
     * Setiap baris mewakili satu sesi SSO dari satu client_app tertentu.
     */
    public function up(): void
    {
        Schema::create('sso_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Token akses berumur pendek (15 menit)
            $table->string('access_token', 128)->unique();

            // Token penyegar berumur panjang (30 hari)
            $table->string('refresh_token', 128)->unique();

            // Nama aplikasi klien yang meminta token
            // Contoh: 'spmb', 'siakad', 'sikeu', 'simpeg', 'lms'
            $table->string('client_app', 50)->index();

            $table->timestamp('access_expires_at');
            $table->timestamp('refresh_expires_at');
            $table->timestamp('created_at')->nullable();

            // Composite index: sering di-query berdasarkan user + client_app
            $table->index(['user_id', 'client_app']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sso_tokens');
    }
};
