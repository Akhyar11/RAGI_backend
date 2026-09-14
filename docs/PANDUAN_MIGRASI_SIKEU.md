# Panduan Migrasi Data SIKEU — Indonusa → RAG

> **Versi**: 1.0  
> **Tanggal**: 14 September 2026  
> **Estimasi Waktu**: 7–9 hari kerja

---

## Gambaran Umum

Panduan ini menjelaskan langkah-langkah teknis untuk memindahkan seluruh data historis SIKEU dari **sistem lama Indonusa** ke **sistem baru RAG**. Ada **dua sumber data** yang perlu dimigrasi:

| Sumber | Format | Lokasi | Periode Data |
|---|---|---|---|
| **Sistem Sangat Lama (STMIK)** | SQL Dump MySQL | `/Users/it/Project/indonusa/SIK_indonusa/localhost.sql` | 2006–2016 |
| **Sistem Modern (Sequelize)** | MySQL Live / Dump | Host: `116.206.197.228`, DB: `sikeudb` | 2016–sekarang |

---

## Pra-Syarat

- [ ] Backup database RAG yang sedang berjalan: `cp backend/database/database.sqlite backend/database/database.sqlite.bak`
- [ ] Backup database lama: `mysqldump -h 116.206.197.228 -u ukeukeu -p'^Rtr251Gtf_hGt' sikeudb > sikeudb_backup.sql`
- [ ] Pastikan server Laravel RAG dapat diakses: `cd backend && php artisan --version`
- [ ] Pastikan folder `storage/migration_data/` sudah ada dan writable

---

## Fase 0: Setup Infrastruktur

```bash
# Jalankan migrasi tabel staging
php artisan migrate --path=database/migrations/2026_09_14_000001_create_sikeu_migration_staging_tables.php
```

Ini akan membuat:
- Kolom `legacy_id` & `legacy_source` di tabel SIKEU utama
- Tabel `_mig_unresolved` — menampung record yang gagal dimigrasi
- Tabel `_mig_run_log` — rekam setiap run migrasi
- Tabel `_mig_mahasiswa_mapping` — mapping NIM lama → mahasiswa_id baru
- Tabel `_mig_akun_mapping` — mapping kode akun lama → akun_id baru

---

## Fase 1: Migrasi Data Sangat Lama (SQL Dump 2006–2016)

### Step 1.1: Migrate Chart of Accounts (COA)
```bash
# Insert COA standar kampus (tidak butuh file eksternal)
php artisan migrate:sikeu-coa --source=default
```

### Step 1.2: Migrate dari SQL Dump Lama
```bash
# Dry run dulu (tidak menyimpan ke DB)
php artisan migrate:sikeu-legacy-sql \
  --sql-file=/Users/it/Project/indonusa/SIK_indonusa/localhost.sql \
  --dry-run

# Jika output terlihat benar, jalankan sesungguhnya:
php artisan migrate:sikeu-legacy-sql \
  --sql-file=/Users/it/Project/indonusa/SIK_indonusa/localhost.sql
```

> **Catatan**: Data dari SQL dump ini adalah data periode 2006–2016 (era FoxPro → MySQL awal). Semua transaksi dianggap sudah `lunas`.

---

## Fase 2: Migrasi Data Modern (Sequelize MySQL → RAG)

### Step 2.1: Export Data dari MySQL Modern

Jalankan query di file `scripts/migration/export_from_mysql_modern.sql` di server MySQL lama, lalu simpan hasil export sebagai JSON ke folder `storage/migration_data/`:

```
storage/migration_data/
├── akun.json                      ← Dari query #1
├── master_biaya_mhs.json          ← Dari query #2
├── master_biaya_pmbs.json         ← Dari query #3
├── master_biaya_lain.json         ← Dari query #4
├── master_tagihan_krs.json        ← Dari query #5
├── transaksi_bayar.json           ← Dari query #6
├── transaksi_bayar_details.json   ← Dari query #7
├── transaksi_potongan.json        ← Dari query #8
├── transaksi_kas.json             ← Dari query #9
├── transaksi_kas_details.json     ← Dari query #10
├── master_tagihan_dispen.json     ← Dari query #11
├── tutup_bukus.json               ← Dari query #12
└── transaksi_umk.json             ← Dari query #13
```

Cara export via MySQL CLI:
```bash
mysql -h 116.206.197.228 -u ukeukeu -p'^Rtr251Gtf_hGt' sikeudb \
  -e "SELECT JSON_ARRAYAGG(JSON_OBJECT('id', id, 'akun_id', akun_id, 'kode_prodi', kode_prodi, 'jenis_daftar', jenis_daftar, 'tahun_angkatan', tahun_angkatan, 'nama_biaya', nama_biaya, 'jumlah', jumlah, 'smt', smt)) FROM master_biaya_mhs WHERE deleted_at IS NULL;" \
  > storage/migration_data/master_biaya_mhs.json
```

Atau gunakan `mysqldump` dan konversi dengan tool seperti `mysql2json` atau Python:
```python
import mysql.connector, json
conn = mysql.connector.connect(host='116.206.197.228', user='ukeukeu', password='^Rtr251Gtf_hGt', database='sikeudb')
cursor = conn.cursor(dictionary=True)
cursor.execute("SELECT * FROM master_biaya_mhs WHERE deleted_at IS NULL")
with open('storage/migration_data/master_biaya_mhs.json', 'w') as f:
    json.dump(cursor.fetchall(), f, default=str)
```

### Step 2.2: Jalankan Command Migrasi Berurutan

```bash
# 1. Master Biaya (dengan tarif per angkatan)
php artisan migrate:sikeu-master-biaya --setting-tarif

# 2. Tagihan & Pembayaran Mahasiswa
php artisan migrate:sikeu-tagihan --dry-run   # test dulu
php artisan migrate:sikeu-tagihan --skip-existing

# 3. Transaksi Kas
php artisan migrate:sikeu-kas --skip-existing

# 4. Dispensasi
php artisan migrate:sikeu-dispensasi
```

---

## Fase 3: Mapping Mahasiswa Manual

Setelah migrasi pertama, pasti ada record di `_mig_mahasiswa_mapping` dengan `status = 'not_found'`. Ini perlu diselesaikan secara manual:

```sql
-- Lihat mahasiswa yang belum terpetakan
SELECT * FROM _mig_mahasiswa_mapping WHERE status = 'not_found' LIMIT 50;

-- Update mapping secara manual (contoh):
UPDATE _mig_mahasiswa_mapping
SET mahasiswa_id_baru = 123, nim_baru = '2301001001', status = 'mapped'
WHERE nim_lama = '030101023';
```

Setelah mapping diperbarui, jalankan ulang command tagihan dengan `--skip-existing` untuk memproses ulang record yang sebelumnya gagal:
```bash
php artisan migrate:sikeu-tagihan --skip-existing
```

---

## Fase 4: Verifikasi & Rekonsiliasi

```bash
# Lihat laporan lengkap
php artisan migrate:sikeu-verify

# Simpan laporan ke file Markdown
php artisan migrate:sikeu-verify --report-file=storage/migration_data/laporan_migrasi.md
```

Laporan ini akan menampilkan:
- ✅ Total record berhasil per entitas
- ⚠️ Record yang gagal dimigrasi (orphan) beserta alasannya
- 📊 Rekonsiliasi nominal total penerimaan vs data lama
- 📋 Panduan menyelesaikan data pending review

---

## Fase 5: Penyelesaian Unresolved Records

Semua record yang tidak bisa dimigrasi otomatis tersimpan di `_mig_unresolved`. Tim keuangan perlu mereview:

```sql
-- Lihat semua yang perlu di-review
SELECT source_table, failure_reason, COUNT(*) as jumlah
FROM _mig_unresolved
WHERE status = 'pending_review'
GROUP BY source_table, failure_reason;

-- Tandai yang bisa diskip (data terlalu lama/tidak relevan)
UPDATE _mig_unresolved
SET status = 'skipped', notes = 'Data pre-2010, sudah tidak relevan'
WHERE source_table = 'trbyr' AND failure_reason = 'nim_not_mapped'
  AND JSON_EXTRACT(raw_data, '$.tgltran') < '2010-01-01';
```

---

## Risiko & Mitigasi

| Risiko | Kemungkinan | Mitigasi |
|---|---|---|
| `no_pend` tidak cocok dengan NIM mahasiswa | Tinggi | Mapping manual via `_mig_mahasiswa_mapping` |
| Kode akun lama tidak terpetakan | Sedang | Default mapping sudah disediakan, extend jika perlu |
| Duplikasi kode transaksi | Rendah | Prefix 'TRX-LEGACY-' mencegah tabrakan |
| Data soft-deleted ikut masuk | Rendah | Query export sudah filter `WHERE deleted_at IS NULL` |
| Timeout saat export dari MySQL remote | Sedang | Ekspor per tabel, gunakan batas waktu koneksi yang panjang |

---

## Estimasi Data yang Dapat Dipindahkan

| Kategori | Estimasi | Catatan |
|---|---|---|
| Master biaya & tarif | ~95% | Sangat kompatibel |
| Transaksi pembayaran (2016–sekarang) | ~70–85% | Bergantung kelengkapan NIM di RAG |
| Transaksi pembayaran (2006–2016) | ~50–70% | Banyak data mahasiswa lama tidak ada di SIAKAD baru |
| Transaksi kas | ~80% | Perlu mapping unit kas |
| Dispensasi | ~75% | Header ada, detail perlu join |
| Periode akuntansi | ~90% | Format mudah dikonversi |
| Virtual Account | 0% | Fitur baru, buat fresh |

---

## Kontak & Support

Jika ada pertanyaan terkait proses migrasi, periksa:
1. Log di `storage/logs/laravel.log`
2. Tabel `_mig_run_log` untuk rekap per run
3. Tabel `_mig_unresolved` untuk record yang perlu review manual
