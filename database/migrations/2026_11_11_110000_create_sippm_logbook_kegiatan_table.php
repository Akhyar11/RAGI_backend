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
        if (!Schema::hasTable('sippm_logbook_kegiatan')) {
            Schema::create('sippm_logbook_kegiatan', function (Blueprint $table) {
                $table->id();
                $table->foreignId('proposal_id')
                    ->constrained('sippm_proposal_kegiatan')
                    ->cascadeOnDelete();
                $table->date('tgl_kegiatan');
                $table->decimal('persentase_capaian', 5, 2)->default(0);
                $table->text('uraian_kegiatan');
                $table->text('hambatan')->nullable();
                $table->string('status_verifikasi', 50)->default('pending');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['proposal_id', 'tgl_kegiatan'], 'sippm_logbook_prop_tgl_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sippm_logbook_kegiatan');
    }
};
