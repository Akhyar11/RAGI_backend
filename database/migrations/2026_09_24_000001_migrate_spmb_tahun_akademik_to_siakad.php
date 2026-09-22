<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Drop existing foreign keys pointing to spmb_master_tahun_akademik
        $fkList = [
            ['table' => 'siakad_krs', 'fk' => 'siakad_krs_tahun_akademik_id_foreign'],
            ['table' => 'siakad_khs', 'fk' => 'siakad_khs_tahun_akademik_id_foreign'],
            ['table' => 'siakad_cuti_mahasiswa', 'fk' => 'siakad_cuti_mahasiswa_tahun_akademik_id_foreign'],
            ['table' => 'siakad_kelulusan', 'fk' => 'siakad_kelulusan_tahun_akademik_id_foreign'],
            ['table' => 'siakad_kelas', 'fk' => 'siakad_kelas_tahun_akademik_id_foreign'],
            ['table' => 'siakad_dosen_penugasan', 'fk' => 'siakad_dosen_penugasan_tahun_akademik_id_foreign'],
        ];

        foreach ($fkList as $item) {
            if (Schema::hasTable($item['table'])) {
                try {
                    Schema::table($item['table'], function (Blueprint $table) use ($item) {
                        $table->dropForeign($item['fk']);
                    });
                } catch (\Throwable $e) {
                    // Foreign key might not exist or might have a different name
                }
            }
        }

        // 2. Rename or create siakad_tahun_akademik
        if (Schema::hasTable('spmb_master_tahun_akademik') && !Schema::hasTable('siakad_tahun_akademik')) {
            Schema::rename('spmb_master_tahun_akademik', 'siakad_tahun_akademik');
        } elseif (!Schema::hasTable('siakad_tahun_akademik')) {
            Schema::create('siakad_tahun_akademik', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 20)->unique();
                $table->string('nama');
                $table->integer('tahun_mulai');
                $table->integer('tahun_selesai');
                $table->boolean('is_active')->default(true);
                $table->string('mode_penilaian', 20)->default('persentase');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // If both existed for any reason, drop the old spmb table to prevent duplication
        if (Schema::hasTable('spmb_master_tahun_akademik') && Schema::hasTable('siakad_tahun_akademik')) {
            Schema::dropIfExists('spmb_master_tahun_akademik');
        }

        // 3. Re-attach foreign keys to siakad_tahun_akademik
        if (Schema::hasTable('siakad_krs')) {
            Schema::table('siakad_krs', function (Blueprint $table) {
                $table->foreign('tahun_akademik_id', 'siakad_krs_tahun_akademik_id_foreign')
                    ->references('id')
                    ->on('siakad_tahun_akademik');
            });
        }

        if (Schema::hasTable('siakad_khs')) {
            Schema::table('siakad_khs', function (Blueprint $table) {
                $table->foreign('tahun_akademik_id', 'siakad_khs_tahun_akademik_id_foreign')
                    ->references('id')
                    ->on('siakad_tahun_akademik');
            });
        }

        if (Schema::hasTable('siakad_cuti_mahasiswa')) {
            Schema::table('siakad_cuti_mahasiswa', function (Blueprint $table) {
                $table->foreign('tahun_akademik_id', 'siakad_cuti_mahasiswa_tahun_akademik_id_foreign')
                    ->references('id')
                    ->on('siakad_tahun_akademik');
            });
        }

        if (Schema::hasTable('siakad_kelulusan')) {
            Schema::table('siakad_kelulusan', function (Blueprint $table) {
                $table->foreign('tahun_akademik_id', 'siakad_kelulusan_tahun_akademik_id_foreign')
                    ->references('id')
                    ->on('siakad_tahun_akademik');
            });
        }

        if (Schema::hasTable('siakad_kelas')) {
            Schema::table('siakad_kelas', function (Blueprint $table) {
                $table->foreign('tahun_akademik_id', 'siakad_kelas_tahun_akademik_id_foreign')
                    ->references('id')
                    ->on('siakad_tahun_akademik');
            });
        }

        if (Schema::hasTable('siakad_dosen_penugasan')) {
            Schema::table('siakad_dosen_penugasan', function (Blueprint $table) {
                $table->foreign('tahun_akademik_id', 'siakad_dosen_penugasan_tahun_akademik_id_foreign')
                    ->references('id')
                    ->on('siakad_tahun_akademik')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $fkList = [
            ['table' => 'siakad_krs', 'fk' => 'siakad_krs_tahun_akademik_id_foreign'],
            ['table' => 'siakad_khs', 'fk' => 'siakad_khs_tahun_akademik_id_foreign'],
            ['table' => 'siakad_cuti_mahasiswa', 'fk' => 'siakad_cuti_mahasiswa_tahun_akademik_id_foreign'],
            ['table' => 'siakad_kelulusan', 'fk' => 'siakad_kelulusan_tahun_akademik_id_foreign'],
            ['table' => 'siakad_kelas', 'fk' => 'siakad_kelas_tahun_akademik_id_foreign'],
            ['table' => 'siakad_dosen_penugasan', 'fk' => 'siakad_dosen_penugasan_tahun_akademik_id_foreign'],
        ];

        foreach ($fkList as $item) {
            if (Schema::hasTable($item['table'])) {
                try {
                    Schema::table($item['table'], function (Blueprint $table) use ($item) {
                        $table->dropForeign($item['fk']);
                    });
                } catch (\Throwable $e) {
                }
            }
        }

        if (Schema::hasTable('siakad_tahun_akademik') && !Schema::hasTable('spmb_master_tahun_akademik')) {
            Schema::rename('siakad_tahun_akademik', 'spmb_master_tahun_akademik');
        }

        foreach ($fkList as $item) {
            if (Schema::hasTable($item['table']) && Schema::hasTable('spmb_master_tahun_akademik')) {
                try {
                    Schema::table($item['table'], function (Blueprint $table) use ($item) {
                        $table->foreign('tahun_akademik_id', $item['fk'])
                            ->references('id')
                            ->on('spmb_master_tahun_akademik');
                    });
                } catch (\Throwable $e) {
                }
            }
        }
    }
};
