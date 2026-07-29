<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel manajemen aplikasi klien yang terdaftar dalam ekosistem SSO kampus.
     * Setiap baris mewakili satu aplikasi (SIAKAD, SPMB, SIKEU, dll.)
     * yang diizinkan melakukan Authorization Code Flow ke IAM.
     */
    public function up(): void
    {
        Schema::create('oauth_app_clients', function (Blueprint $table) {
            $table->id();

            // Identifier pendek untuk aplikasi, contoh: 'siakad', 'spmb'
            $table->string('client_app', 50)->unique();

            // Nama tampil di halaman persetujuan OAuth
            $table->string('client_name', 100);

            // Relasi ke tabel oauth_clients milik Passport (UUID di Passport v13)
            $table->string('passport_client_id', 36)->nullable();

            // Daftar redirect URI yang diizinkan (validasi keamanan)
            $table->json('allowed_redirect_uris');

            // Nonaktifkan aplikasi tanpa menghapus data
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['client_app', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_app_clients');
    }
};
