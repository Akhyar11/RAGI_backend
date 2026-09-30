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
        Schema::create('sinapra_riwayat_penyusutan_aset', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aset_id')
                ->constrained('sinapra_aset')
                ->cascadeOnDelete();
            $table->foreignId('jurnal_umum_id')
                ->nullable()
                ->constrained('sikeu_jurnal_umum')
                ->nullOnDelete();
            $table->unsignedSmallInteger('periode_tahun')->index();
            $table->decimal('nilai_perolehan', 15, 2);
            $table->decimal('persentase_penyusutan', 5, 2);
            $table->decimal('beban_penyusutan', 15, 2);
            $table->decimal('nilai_buku_setelah', 15, 2);
            $table->date('tanggal_posting')->index();
            $table->text('catatan')->nullable();
            $table->foreignId('diposting_oleh')
                ->nullable()
                ->constrained('core_users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['aset_id', 'periode_tahun'], 'uniq_aset_periode_penyusutan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sinapra_riwayat_penyusutan_aset');
    }
};
