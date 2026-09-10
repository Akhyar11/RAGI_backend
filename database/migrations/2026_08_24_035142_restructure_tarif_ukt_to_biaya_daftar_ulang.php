<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Restrukturisasi tabel spmb_tarif_ukt menjadi Biaya Daftar Ulang.
     * Field baru: nama, deskripsi, master_sikeu_biaya_id, master_program_studi_id.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        $isMysql = $driver === 'mysql';
        $isSqlite = $driver === 'sqlite';

        // Penanganan Khusus SQLite:
        // Di SQLite, drop column pada tabel yang memiliki foreign key constraint akan gagal karena
        // SQLite menduplikasi tabel dengan definisi foreign key lama.
        if ($isSqlite) {
            Schema::disableForeignKeyConstraints();

            // Buat tabel temporary baru dengan struktur akhir
            Schema::create('spmb_tarif_ukt_new', function (Blueprint $table) {
                $table->id();
                $table->string('nama')->nullable();
                $table->text('deskripsi')->nullable();
                $table->foreignId('master_program_studi_id')->nullable()->constrained('spmb_master_program_studi')->onDelete('restrict');
                $table->foreignId('master_sikeu_biaya_id')->nullable()->constrained('sikeu_master_biaya')->onDelete('set null');
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['master_program_studi_id', 'master_sikeu_biaya_id'], 'biaya_daftar_ulang_unique_idx');
            });

            // Salin data lama jika ada
            if (Schema::hasTable('spmb_tarif_ukt')) {
                $hasOldProdi = Schema::hasColumn('spmb_tarif_ukt', 'program_studi_id');
                $hasOldBiaya = Schema::hasColumn('spmb_tarif_ukt', 'master_biaya_id');

                $prodiCol = $hasOldProdi ? 'program_studi_id' : (Schema::hasColumn('spmb_tarif_ukt', 'master_program_studi_id') ? 'master_program_studi_id' : 'NULL');
                $biayaCol = $hasOldBiaya ? 'master_biaya_id' : (Schema::hasColumn('spmb_tarif_ukt', 'master_sikeu_biaya_id') ? 'master_sikeu_biaya_id' : 'NULL');

                DB::statement("
                    INSERT OR IGNORE INTO spmb_tarif_ukt_new (id, master_program_studi_id, master_sikeu_biaya_id, created_at, updated_at, deleted_at)
                    SELECT id, {$prodiCol}, {$biayaCol}, created_at, updated_at, deleted_at
                    FROM spmb_tarif_ukt
                ");

                Schema::drop('spmb_tarif_ukt');
            }

            Schema::rename('spmb_tarif_ukt_new', 'spmb_tarif_ukt');
            Schema::enableForeignKeyConstraints();
            return;
        }

        // 1. Drop foreign keys lama yang mengikat program_studi_id, tahun_akademik_id, master_biaya_id terlebih dahulu (MySQL)
        if ($isMysql) {
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'spmb_tarif_ukt' 
                  AND COLUMN_NAME IN ('program_studi_id', 'tahun_akademik_id', 'master_biaya_id')
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            foreach ($foreignKeys as $fk) {
                DB::statement("ALTER TABLE `spmb_tarif_ukt` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            }
        }

        // 2. Drop unique index lama dengan aman jika ada
        if ($isMysql) {
            $uniqueIndexes = DB::select("
                SELECT DISTINCT INDEX_NAME 
                FROM information_schema.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'spmb_tarif_ukt' 
                  AND INDEX_NAME = 'tarif_ukt_spmb_unique_idx'
            ");

            if (!empty($uniqueIndexes)) {
                Schema::table('spmb_tarif_ukt', function (Blueprint $table) {
                    $table->dropUnique('tarif_ukt_spmb_unique_idx');
                });
            }
        }

        // 3. Rename kolom program_studi_id -> master_program_studi_id
        if (Schema::hasColumn('spmb_tarif_ukt', 'program_studi_id')) {
            Schema::table('spmb_tarif_ukt', function (Blueprint $table) {
                $table->renameColumn('program_studi_id', 'master_program_studi_id');
            });
        }

        // 4. Rename kolom master_biaya_id -> master_sikeu_biaya_id
        if (Schema::hasColumn('spmb_tarif_ukt', 'master_biaya_id')) {
            Schema::table('spmb_tarif_ukt', function (Blueprint $table) {
                $table->renameColumn('master_biaya_id', 'master_sikeu_biaya_id');
            });
        }

        // 5. Tambah kolom nama & deskripsi jika belum ada
        Schema::table('spmb_tarif_ukt', function (Blueprint $table) {
            if (!Schema::hasColumn('spmb_tarif_ukt', 'nama')) {
                $table->string('nama')->nullable()->after('id');
            }
            if (!Schema::hasColumn('spmb_tarif_ukt', 'deskripsi')) {
                $table->text('deskripsi')->nullable()->after('nama');
            }
        });

        // 6. Hapus kolom yang tidak lagi dibutuhkan
        foreach (['tahun_akademik_id', 'kelompok_ukt', 'nominal', 'is_active'] as $col) {
            if (Schema::hasColumn('spmb_tarif_ukt', $col)) {
                Schema::table('spmb_tarif_ukt', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }

        // 7. Tambah foreign key & index baru jika belum ada
        if ($isMysql) {
            $newFks = collect(DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'spmb_tarif_ukt' 
                  AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            "))->pluck('CONSTRAINT_NAME')->all();

            Schema::table('spmb_tarif_ukt', function (Blueprint $table) use ($newFks) {
                if (!in_array('spmb_tarif_ukt_master_program_studi_id_foreign', $newFks) && Schema::hasColumn('spmb_tarif_ukt', 'master_program_studi_id')) {
                    $table->foreign('master_program_studi_id')->references('id')->on('spmb_master_program_studi')->onDelete('restrict');
                }
                if (!in_array('spmb_tarif_ukt_master_sikeu_biaya_id_foreign', $newFks) && Schema::hasColumn('spmb_tarif_ukt', 'master_sikeu_biaya_id')) {
                    $table->foreign('master_sikeu_biaya_id')->references('id')->on('sikeu_master_biaya')->onDelete('set null');
                }
            });

            $hasNewUnique = DB::select("
                SELECT DISTINCT INDEX_NAME 
                FROM information_schema.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'spmb_tarif_ukt' 
                  AND INDEX_NAME = 'biaya_daftar_ulang_unique_idx'
            ");

            if (empty($hasNewUnique)) {
                Schema::table('spmb_tarif_ukt', function (Blueprint $table) {
                    $table->unique(['master_program_studi_id', 'master_sikeu_biaya_id'], 'biaya_daftar_ulang_unique_idx');
                });
            }
        }
    }

    public function down(): void
    {
        $isMysql = DB::getDriverName() === 'mysql';

        // 1. Drop foreign keys baru jika ada
        if ($isMysql) {
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'spmb_tarif_ukt' 
                  AND COLUMN_NAME IN ('master_program_studi_id', 'master_sikeu_biaya_id')
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            foreach ($foreignKeys as $fk) {
                DB::statement("ALTER TABLE `spmb_tarif_ukt` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            }

            // 2. Drop unique index baru jika ada
            $newUnique = DB::select("
                SELECT DISTINCT INDEX_NAME 
                FROM information_schema.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'spmb_tarif_ukt' 
                  AND INDEX_NAME = 'biaya_daftar_ulang_unique_idx'
            ");

            if (!empty($newUnique)) {
                Schema::table('spmb_tarif_ukt', function (Blueprint $table) {
                    $table->dropUnique('biaya_daftar_ulang_unique_idx');
                });
            }
        } else {
            try {
                Schema::table('spmb_tarif_ukt', function (Blueprint $table) {
                    $table->dropUnique('biaya_daftar_ulang_unique_idx');
                });
            } catch (\Throwable $e) {}
        }

        Schema::table('spmb_tarif_ukt', function (Blueprint $table) {
            if (Schema::hasColumn('spmb_tarif_ukt', 'nama')) {
                $table->dropColumn('nama');
            }
            if (Schema::hasColumn('spmb_tarif_ukt', 'deskripsi')) {
                $table->dropColumn('deskripsi');
            }

            if (Schema::hasColumn('spmb_tarif_ukt', 'master_program_studi_id')) {
                $table->renameColumn('master_program_studi_id', 'program_studi_id');
            }
            if (Schema::hasColumn('spmb_tarif_ukt', 'master_sikeu_biaya_id')) {
                $table->renameColumn('master_sikeu_biaya_id', 'master_biaya_id');
            }

            foreach (['tahun_akademik_id', 'kelompok_ukt', 'nominal', 'is_active'] as $col) {
                if (!Schema::hasColumn('spmb_tarif_ukt', $col)) {
                    $table->{$col === 'nominal' ? 'decimal' : 'string'}($col, $col === 'nominal' ? 15 : 100)->nullable();
                }
            }
        });
    }
};