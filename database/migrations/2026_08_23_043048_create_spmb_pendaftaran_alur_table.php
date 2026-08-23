<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spmb_pendaftaran_alur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('spmb_pendaftaran_calon_mhs')->cascadeOnDelete();
            $table->foreignId('master_tipe_jalur_alur_id')->constrained('spmb_master_tipe_jalur_alur')->cascadeOnDelete();
            $table->string('status', 30)->nullable(); // pending, in_progress, completed, failed
            $table->text('catatan')->nullable();
            $table->foreignId('diperbarui_oleh')->nullable()->constrained('core_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spmb_pendaftaran_alur');
    }
};