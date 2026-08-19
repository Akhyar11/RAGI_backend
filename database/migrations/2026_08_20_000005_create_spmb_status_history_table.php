<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat transisi status pendaftaran SPMB (append-only).
     */
    public function up(): void
    {
        Schema::create('spmb_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('pendaftaran_calon_mhs')->cascadeOnDelete()->index();
            $table->string('status_lama', 50)->nullable();
            $table->string('status_baru', 50);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['pendaftaran_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spmb_status_history');
    }
};
