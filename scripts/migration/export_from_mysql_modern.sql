-- ============================================================
-- SCRIPT EXPORT DATA SIKEU DARI MySQL MODERN (sikeudb)
-- Host: 116.206.197.228 | Database: sikeudb
-- Jalankan query ini di MySQL lama, lalu salin hasilnya ke:
--   /Users/it/Project/RAG/backend/storage/migration_data/
-- ============================================================

-- ── 1. Export Chart of Accounts ────────────────────────────────────────
SELECT
    id, kategori, code, value, text, parentId,
    hierarchyLevel, hierarchyId
FROM akun
ORDER BY hierarchyId;

-- ── 2. Export Master Biaya Mahasiswa ────────────────────────────────────
SELECT
    id, akun_id, kode_prodi, jenis_daftar,
    tahun_angkatan, nama_biaya, jumlah, smt,
    tambahan, tambahan_mhs, syarat_ujian,
    createdAt, updatedAt
FROM master_biaya_mhs
WHERE deleted_at IS NULL
ORDER BY tahun_angkatan, kode_prodi, smt;

-- ── 3. Export Master Biaya PMB ──────────────────────────────────────────
SELECT
    id, akun_id, kode_prodi, tahun_angkatan,
    nama_biaya, jumlah,
    createdAt, updatedAt
FROM master_biaya_pmbs
WHERE deleted_at IS NULL
ORDER BY tahun_angkatan, kode_prodi;

-- ── 4. Export Master Biaya Lainnya ──────────────────────────────────────
SELECT id, kode, nama, akun_id, jumlah, keterangan,
    createdAt, updatedAt
FROM master_biaya_lains
WHERE deleted_at IS NULL;

-- ── 5. Export Master Tarif per Angkatan (Setting Tarif) ─────────────────
SELECT
    id, angkatan, kode_prodi, jenis_daftar,
    jenis_biaya, biaya_id, tagihan,
    createdAt, updatedAt
FROM master_tagihan_krs
ORDER BY angkatan, kode_prodi;

-- ── 6. Export Transaksi Pembayaran Mahasiswa ─────────────────────────────
SELECT
    tb.id, tb.kode, tb.kode_bukti, tb.no_pend,
    tb.keterangan, tb.tanggal, tb.tanggal_nontunai,
    tb.total, tb.metode_bayar, tb.pendaftaran,
    tb.divisi, tb.username,
    tb.createdAt, tb.updatedAt
FROM transaksi_bayar tb
WHERE tb.deleted_at IS NULL
ORDER BY tb.tanggal;

-- ── 7. Export Detail Transaksi Pembayaran ───────────────────────────────
SELECT
    tbd.id, tbd.transaksi_bayar_id, tbd.jenis_biaya,
    tbd.biaya_id, tbd.tagihan, tbd.jumlah_potongan,
    tbd.jumlah_bayar, tbd.kekurangan, tbd.keterangan,
    tbd.koreksi, tbd.createdAt, tbd.updatedAt
FROM transaksi_bayar_details tbd
INNER JOIN transaksi_bayar tb ON tb.id = tbd.transaksi_bayar_id
WHERE tbd.deleted_at IS NULL AND tb.deleted_at IS NULL
ORDER BY tbd.transaksi_bayar_id;

-- ── 8. Export Potongan / Diskon ──────────────────────────────────────────
SELECT
    id, no_pend, keterangan, jenis_potongan,
    tanggal, jumlah_potongan, biaya_id,
    divisi, username,
    createdAt, updatedAt
FROM master_potongans
WHERE deleted_at IS NULL
ORDER BY tanggal;

-- ── 9. Export Transaksi Kas ──────────────────────────────────────────────
SELECT
    tk.id, tk.kode, tk.kode_bukti, tk.keterangan,
    tk.tanggal, tk.tanggal_nontunai, tk.metode_bayar,
    tk.total, tk.jenis_kas, tk.divisi, tk.username,
    tk.diterima_dari, tk.createdAt, tk.updatedAt
FROM transaksi_kas tk
WHERE tk.deleted_at IS NULL
ORDER BY tk.tanggal;

-- ── 10. Export Detail Transaksi Kas ─────────────────────────────────────
SELECT
    tkd.id, tkd.transaksi_kas_id, tkd.akun_id,
    tkd.jumlah, tkd.keterangan,
    tkd.createdAt, tkd.updatedAt
FROM transaksi_kas_details tkd
INNER JOIN transaksi_kas tk ON tk.id = tkd.transaksi_kas_id
WHERE tkd.deleted_at IS NULL AND tk.deleted_at IS NULL
ORDER BY tkd.transaksi_kas_id;

-- ── 11. Export Dispensasi ────────────────────────────────────────────────
SELECT
    mtd.id, mtd.smt, mtd.no_pend, mtd.nim, mtd.nama,
    mtd.angkatan, mtd.kode_prodi, mtd.nama_prodi,
    mtd.telepon_seluler, mtd.jenis_daftar, mtd.ttd,
    mtd.createdAt, mtd.updatedAt,
    -- Detail dispensasi
    mtdd.id AS detail_id, mtdd.master_biaya_id, mtdd.jenis_biaya,
    mtdd.tagihan AS detail_tagihan, mtdd.sisa_tagihan,
    mtdd.keterangan AS detail_keterangan
FROM master_tagihan_dispen mtd
LEFT JOIN master_tagihan_dispen_details mtdd ON mtdd.master_tagihan_dispen_id = mtd.id
WHERE mtd.deleted_at IS NULL
ORDER BY mtd.createdAt;

-- ── 12. Export Tutup Buku / Periode Akuntansi ───────────────────────────
SELECT
    id, divisi, tanggal, saldo_awal, saldo_masuk,
    saldo_keluar, saldo_akhir, kunci,
    createdAt, updatedAt
FROM tutup_bukus
WHERE deleted_at IS NULL
ORDER BY tanggal;

-- ── 13. Export UMK (Uang Muka Kegiatan) ─────────────────────────────────
SELECT
    id, nama_penerima, tanggal, tanggal_selesai,
    keterangan, total, status,
    createdAt, updatedAt
FROM transaksi_umk
WHERE deleted_at IS NULL
ORDER BY tanggal;

-- ============================================================
-- CARA EXPORT SEBAGAI JSON (jalankan via Node.js / Python):
-- ============================================================
-- Contoh dengan mysqldump:
--   mysqldump -h 116.206.197.228 -u ukeukeu -p'^Rtr251Gtf_hGt' sikeudb \
--     master_biaya_mhs master_biaya_pmbs master_tagihan_krs \
--     transaksi_bayar transaksi_bayar_details master_potongans \
--     transaksi_kas transaksi_kas_details master_tagihan_dispen \
--     tutup_bukus akun transaksi_umk \
--     > sikeudb_export.sql
--
-- Atau via MySQL CLI dengan output JSON:
--   mysql -h 116.206.197.228 -u ukeukeu -p'^Rtr251Gtf_hGt' sikeudb \
--     -e "SELECT JSON_ARRAYAGG(JSON_OBJECT(...)) FROM master_biaya_mhs WHERE deleted_at IS NULL;"
