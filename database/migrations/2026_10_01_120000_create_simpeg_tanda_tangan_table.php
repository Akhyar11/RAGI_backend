<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('simpeg_tanda_tangan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('core_users')->onDelete('cascade');
            $table->foreignId('pegawai_id')->nullable()->constrained('simpeg_pegawai')->onDelete('set null');
            $table->string('file_path', 255);
            $table->string('tipe', 50)->default('gambar_spesimen');
            $table->string('judul', 100)->default('Tanda Tangan Utama');
            $table->string('qr_token', 100)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_active']);
            $table->index(['pegawai_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_tanda_tangan');
    }
};
