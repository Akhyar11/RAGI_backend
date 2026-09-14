<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buat tabel staging untuk proses migrasi data SIKEU dari sistem lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom legacy_id & legacy_source ke tabel SIKEU utama
        $tables = [
            'sikeu_master_biaya',
            'sikeu_tagihan_mahasiswa',
            'sikeu_detail_tagihan',
            'sikeu_pembayaran',
            'sikeu_potongan_tagihan',
            'sikeu_dispensasi_tagihan',
            'sikeu_jurnal_umum',
            'sikeu_transaksi_kas_unit',
            'sikeu_akun_keuangan',
            'sikeu_periode_akuntansi',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'legacy_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('legacy_id')->nullable()->after('id')
                        ->comment('ID dari sistem lama (Indonusa SIKEU)');
                    $t->string('legacy_source')->nullable()->after('legacy_id')
                        ->comment('Sumber: STMIK_SQL_2016 | SEQUELIZE_MODERN | MANUAL');
                });
            }
        }

        // 2. Tabel Staging: Record yang GAGAL dimigrasi
        Schema::create('_mig_unresolved', function (Blueprint $table) {
            $table->id();
            $table->string('source_table')->comment('Nama tabel asal di sistem lama');
            $table->string('legacy_key')->comment('PK / kode unik di sistem lama');
            $table->json('raw_data')->comment('Data mentah dari sistem lama');
            $table->string('failure_reason')->comment('Alasan kenapa gagal dimigrasi');
            $table->enum('status', ['pending_review', 'resolved_manual', 'skipped'])
                ->default('pending_review');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['source_table', 'status']);
        });

        // 3. Tabel Log: Rekam setiap run migrasi
        Schema::create('_mig_run_log', function (Blueprint $table) {
            $table->id();
            $table->string('command_name');
            $table->string('source')->comment('Sumber data: SQL_DUMP | MYSQL_MODERN');
            $table->integer('total_records')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('skipped_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->decimal('total_nominal_migrated', 20, 2)->default(0);
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        // 4. Tabel Staging: Mapping NIM lama → mahasiswa_id RAG
        Schema::create('_mig_mahasiswa_mapping', function (Blueprint $table) {
            $table->id();
            $table->string('nim_lama')->comment('NIM atau no_pend dari sistem lama');
            $table->string('nama_lama')->nullable();
            $table->string('kode_lama')->nullable()->comment('Kode angkatan+prodi (misal: 200311)');
            $table->unsignedBigInteger('mahasiswa_id_baru')->nullable();
            $table->string('nim_baru')->nullable();
            $table->enum('status', ['mapped', 'not_found', 'ambiguous'])->default('not_found');
            $table->timestamps();
            $table->index('nim_lama');
            $table->index('status');
        });

        // 5. Tabel Staging: Mapping kode akun lama → akun_id RAG
        Schema::create('_mig_akun_mapping', function (Blueprint $table) {
            $table->id();
            $table->string('kd_perkiraan_lama')->comment('Kode akun dari sistem lama');
            $table->string('nama_akun_lama')->nullable();
            $table->unsignedBigInteger('akun_id_baru')->nullable();
            $table->string('kode_akun_baru')->nullable();
            $table->timestamps();
            $table->index('kd_perkiraan_lama');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('_mig_akun_mapping');
        Schema::dropIfExists('_mig_mahasiswa_mapping');
        Schema::dropIfExists('_mig_run_log');
        Schema::dropIfExists('_mig_unresolved');

        $tables = [
            'sikeu_master_biaya', 'sikeu_tagihan_mahasiswa', 'sikeu_detail_tagihan',
            'sikeu_pembayaran', 'sikeu_potongan_tagihan', 'sikeu_dispensasi_tagihan',
            'sikeu_jurnal_umum', 'sikeu_transaksi_kas_unit', 'sikeu_akun_keuangan',
            'sikeu_periode_akuntansi',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'legacy_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn(['legacy_id', 'legacy_source']);
                });
            }
        }
    }
};
