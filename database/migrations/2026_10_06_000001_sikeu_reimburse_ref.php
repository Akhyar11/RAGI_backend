<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Reimbursement operasional + ref akuntansi:
     * - parent_pengajuan_id: link pengajuan reimbursement (RMB) ke pengajuan asal (PO, OPR, CAIR-ST).
     * - jenis_pengajuan: sertakan 'sarpras' (dipakai PengadaanService) agar tidak violating enum MySQL.
     * - jenis_sumber jurnal: tambah 'reimbursement' (JRN-RMB) agar terpisah dari pencairan_kas.
     * - indeks (jenis_sumber, referensi_id) untuk pelacakan ref oleh keuangan.
     */
    public function up(): void
    {
        Schema::table('sikeu_pengajuan_pencairan_kas', function (Blueprint $table) {
            if (!Schema::hasColumn('sikeu_pengajuan_pencairan_kas', 'parent_pengajuan_id')) {
                $table->unsignedBigInteger('parent_pengajuan_id')->nullable()->after('pemohon_id');
                $table->index('parent_pengajuan_id');
            }
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE sikeu_pengajuan_pencairan_kas MODIFY COLUMN jenis_pengajuan ENUM('operasional','kegiatan','reimbursement','sarpras','lainnya') NOT NULL DEFAULT 'operasional'");
            DB::statement("ALTER TABLE sikeu_jurnal_umum MODIFY COLUMN jenis_sumber ENUM('pembayaran_mahasiswa','pemasukan_hibah','pencairan_kas','pengeluaran_manual','penyesuaian','penutupan','reimbursement') NOT NULL DEFAULT 'penyesuaian'");
        } else {
            // SQLite menegakkan enum via CHECK constraint dan tidak mendukung
            // MODIFY COLUMN, sehingga tabel jurnal dibangun ulang dengan
            // daftar nilai yang sudah mencakup 'reimbursement'.
            $this->rebuildJurnalTableSqlite([
                'pembayaran_mahasiswa', 'pemasukan_hibah', 'pencairan_kas',
                'pengeluaran_manual', 'penyesuaian', 'penutupan', 'reimbursement',
            ]);
        }

        Schema::table('sikeu_jurnal_umum', function (Blueprint $table) {
            try {
                $table->index(['jenis_sumber', 'referensi_id'], 'jurnal_sumber_ref_idx');
            } catch (\Throwable $e) {
                // Indeks sudah ada — abaikan agar migrasi idempotent.
            }
        });

        // FK self-reference dibuat terpisah agar idempotent di rerun.
        try {
            Schema::table('sikeu_pengajuan_pencairan_kas', function (Blueprint $table) {
                $table->foreign('parent_pengajuan_id')->references('id')->on('sikeu_pengajuan_pencairan_kas')->onDelete('set null');
            });
        } catch (\Throwable $e) {
            // FK sudah ada — abaikan.
        }
    }

    public function down(): void
    {
        try {
            Schema::table('sikeu_pengajuan_pencairan_kas', function (Blueprint $table) {
                $table->dropForeign(['parent_pengajuan_id']);
            });
        } catch (\Throwable $e) {
        }

        Schema::table('sikeu_pengajuan_pencairan_kas', function (Blueprint $table) {
            $table->dropIndex(['parent_pengajuan_id']);
            $table->dropColumn('parent_pengajuan_id');
        });

        try {
            Schema::table('sikeu_jurnal_umum', function (Blueprint $table) {
                $table->dropIndex('jurnal_sumber_ref_idx');
            });
        } catch (\Throwable $e) {
        }

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE sikeu_pengajuan_pencairan_kas MODIFY COLUMN jenis_pengajuan ENUM('operasional','kegiatan','reimbursement','lainnya') NOT NULL DEFAULT 'operasional'");
            DB::statement("ALTER TABLE sikeu_jurnal_umum MODIFY COLUMN jenis_sumber ENUM('pembayaran_mahasiswa','pemasukan_hibah','pencairan_kas','pengeluaran_manual','penyesuaian','penutupan') NOT NULL DEFAULT 'penyesuaian'");
        } else {
            $this->rebuildJurnalTableSqlite([
                'pembayaran_mahasiswa', 'pemasukan_hibah', 'pencairan_kas',
                'pengeluaran_manual', 'penyesuaian', 'penutupan',
            ]);
        }
    }

    /**
     * Bangun ulang sikeu_jurnal_umum di SQLite dengan CHECK jenis_sumber
     * sesuai daftar nilai yang diberikan, tanpa menghilangkan data.
     * Pola create-new lalu rename (BUKAN rename-old dulu): rename tabel
     * asal lebih dulu akan membuat SQLite menulis ulang FK tabel lain
     * (detail jurnal, gaji, penyusutan) ke nama sementara yang lalu di-drop.
     */
    protected function rebuildJurnalTableSqlite(array $jenisSumber): void
    {
        $in = implode(', ', array_map(fn ($v) => "'{$v}'", $jenisSumber));

        DB::statement('PRAGMA foreign_keys=OFF');
        try {
            DB::transaction(function () use ($in) {
                DB::statement("CREATE TABLE sikeu_jurnal_umum_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    nomor_jurnal VARCHAR NOT NULL,
                    tanggal_jurnal DATE NOT NULL,
                    periode_id INTEGER,
                    jenis_sumber VARCHAR CHECK (jenis_sumber IN ({$in})) NOT NULL DEFAULT 'penyesuaian',
                    referensi_id INTEGER,
                    keterangan TEXT,
                    status_posting VARCHAR CHECK (status_posting IN ('draft', 'posted')) NOT NULL DEFAULT 'posted',
                    total_debet NUMERIC NOT NULL DEFAULT 0,
                    total_kredit NUMERIC NOT NULL DEFAULT 0,
                    created_by INTEGER,
                    posted_by INTEGER,
                    posted_at DATETIME,
                    created_at DATETIME,
                    updated_at DATETIME,
                    legacy_id VARCHAR,
                    legacy_source VARCHAR,
                    FOREIGN KEY (periode_id) REFERENCES sikeu_periode_akuntansi (id) ON DELETE SET NULL
                )");

                DB::statement('INSERT INTO sikeu_jurnal_umum_new
                    (id, nomor_jurnal, tanggal_jurnal, periode_id, jenis_sumber, referensi_id, keterangan,
                     status_posting, total_debet, total_kredit, created_by, posted_by, posted_at,
                     created_at, updated_at, legacy_id, legacy_source)
                    SELECT id, nomor_jurnal, tanggal_jurnal, periode_id, jenis_sumber, referensi_id, keterangan,
                     status_posting, total_debet, total_kredit, created_by, posted_by, posted_at,
                     created_at, updated_at, legacy_id, legacy_source
                    FROM sikeu_jurnal_umum');

                DB::statement('DROP TABLE sikeu_jurnal_umum');
                DB::statement('ALTER TABLE sikeu_jurnal_umum_new RENAME TO sikeu_jurnal_umum');
                DB::statement('CREATE UNIQUE INDEX sikeu_jurnal_umum_nomor_jurnal_unique ON sikeu_jurnal_umum (nomor_jurnal)');
            });
        } finally {
            DB::statement('PRAGMA foreign_keys=ON');
        }
    }
};
