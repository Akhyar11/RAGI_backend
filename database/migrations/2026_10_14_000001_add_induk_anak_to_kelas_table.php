<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siakad_kelas', function (Blueprint $table) {
            if (!Schema::hasColumn('siakad_kelas', 'parent_kelas_id')) {
                $table->foreignId('parent_kelas_id')->nullable()->after('is_gabungan')
                    ->constrained('siakad_kelas')->nullOnDelete();
            }
            if (!Schema::hasColumn('siakad_kelas', 'tipe_sesi')) {
                // teori = sesi tatap muka gabungan, praktik = sesi lab per rombel, gabungan = kelas biasa
                $table->string('tipe_sesi', 20)->default('gabungan')->after('parent_kelas_id');
            }
        });

        if (!Schema::hasTable('siakad_kelas_rombel')) {
            Schema::create('siakad_kelas_rombel', function (Blueprint $table) {
                $table->id();
                $table->foreignId('kelas_id')->constrained('siakad_kelas')->cascadeOnDelete();
                $table->foreignId('master_kelas_id')->constrained('siakad_master_kelas')->cascadeOnDelete();
                $table->timestamp('created_at')->nullable();

                $table->unique(['kelas_id', 'master_kelas_id'], 'siakad_kelas_rombel_unique');
                $table->index(['master_kelas_id'], 'siakad_kelas_rombel_mk_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('siakad_kelas_rombel');
        Schema::table('siakad_kelas', function (Blueprint $table) {
            if (Schema::hasColumn('siakad_kelas', 'tipe_sesi')) {
                $table->dropColumn('tipe_sesi');
            }
            if (Schema::hasColumn('siakad_kelas', 'parent_kelas_id')) {
                $table->dropForeign(['parent_kelas_id']);
                $table->dropColumn('parent_kelas_id');
            }
        });
    }
};
