# Panduan Migrasi & Setup Saldo Awal SIKEU

> **Modul**: SIKEU — Akuntansi  
> **Terakhir diperbarui**: 2026-10-27  
> **Dokumen terkait**: [Migrasi SIKU Legacy](MIGRASI_SIKEU_LEGACY.md) · [API Saldo Awal](api/Sikeu/SaldoAwalController.md)

Panduan ini dipakai saat **cut-over** dari data keuangan lama ke RAG SIKEU. Tujuannya
agar saldo awal dan jurnal legacy tidak saling menimpa sehingga saldo tidak terhitung dua kali.

---

## 1. Konsep Cut-off (Wajib Dipahami)

Saldo awal adalah **ringkasan seluruh transaksi sebelum tanggal cut-over**. Karena itu,
saldo awal harus menjadi **jurnal paling awal** di buku.

```
 ... transaksi lama (sudah diringkas) ... │ CUT-OFF │ transaksi baru (dijurnal normal) ...
                                          │         │
                              tanggal saldo awal    tanggal transaksi pertama
                              (mis. 31/12/2025)     (mis. 01/01/2026)
```

Aturan sistem:

- Posting saldo awal **ditolak** bila sudah ada jurnal `posted` lain pada/sebelum tanggal saldo awal.
- Command migrasi jurnal legacy **menolak** tanggal `<=` tanggal saldo awal yang sudah diposting.
- Jurnal saldo awal memakai `jenis_sumber = penyesuaian`, nomor `JRN-OPEN...`, dan terkunci
  (`referensi_id` = header saldo awal) sehingga tidak bisa diedit/dihapus lewat jurnal biasa.

> **Prinsip**: pilih **salah satu** — ringkas masa lalu dengan saldo awal **atau** impor
> seluruh jurnal historis. Jangan campur keduanya untuk periode yang sama.

---

## 2. Prasyarat & Akses Admin

### 2.1 Akun super admin

Super admin memiliki akses ke seluruh menu, termasuk saldo awal. Buat/atur ulang
akun lewat `.env` (password tidak di-hardcode):

```env
SUPER_ADMIN_NAME="Super Administrator"
SUPER_ADMIN_EMAIL=superadmin@kampus.ac.id
SUPER_ADMIN_USERNAME=superadmin
SUPER_ADMIN_PASSWORD=<password-kuat>
```

Jalankan seeder idempoten (hanya menambah/memperbarui akun ini, **tidak** menghapus
role pengguna lain):

```bash
cd backend
php artisan config:clear
php artisan db:seed --class="Database\Seeders\IAM\SuperAdminSeeder"
```

> Jangan gunakan `AdminUserSeeder` hanya untuk membuat satu akun, karena seeder tersebut
> melakukan `truncate` pada `core_user_roles` dan menghapus seluruh role pengguna lain.

### 2.2 Akun admin akuntansi

Fitur saldo awal hanya terlihat untuk role yang memiliki menu
`/sikeu/akuntansi/saldo-awal` (default: `superadmin`, `admin`, `operator_sikeu`,
`kabag_keuangan`, `admin_keuangan_akuntansi`).

Buat/atur akun admin akuntansi lewat `.env` (jangan hardcode password):

```env
SIKEU_ADMIN_NAME="Admin Keuangan Akuntansi"
SIKEU_ADMIN_EMAIL=admin.keu.akuntansi@kampus.ac.id
SIKEU_ADMIN_USERNAME=admin_keu_akuntansi
SIKEU_ADMIN_PASSWORD=<password-kuat>
```

Lalu jalankan seeder idempoten (tidak menghapus role pengguna lain):

```bash
cd backend
php artisan config:clear
php artisan db:seed --class="Database\Seeders\Sikeu\SikeuAdminSeeder"
```

Seeder hanya berjalan bila `SIKEU_ADMIN_PASSWORD` diisi. Jika role
`admin_keuangan_akuntansi` belum ada, jalankan `RoleSeeder` terlebih dahulu.

### 2.3 Cek akses

1. Login di frontend dengan akun tersebut.
2. Buka **SIKEU → Akuntansi → Saldo Awal Akuntansi** (`/sikeu/akuntansi/saldo-awal`).
3. Pastikan halaman menampilkan daftar COA neraca dan tombol **Simpan Draft**.
4. Jika menu tidak muncul: cek `core_menu_role` untuk menu tersebut dan pastikan role user aktif.

---

## 3. Pilih Mode Migrasi

| Aspek | **Mode A — Saldo Awal saja** | **Mode B — Jurnal historis penuh** |
|---|---|---|
| Data lama | Diringkas ke satu jurnal saldo awal | Seluruh jurnal dimigrasikan apa adanya |
| Saldo awal | **Wajib** diposting | **Tidak perlu** (saldo terbentuk dari jurnal) |
| `sikeu:sync-legacy --only=trkas` | **Jangan** dijalankan | Jalankan untuk seluruh periode |
| Tanggal transaksi baru | Harus **setelah** tanggal saldo awal | Bebas, mengikuti tanggal asli |
| Riwayat laporan | Mulai dari cut-over | Lengkap sejak periode awal |
| Risiko | Saldo awal salah input | Jurnal legacy tidak seimbang/ganda |

> Untuk sebagian besar migrasi, **Mode A lebih aman dan lebih cepat**. Gunakan Mode B
> hanya bila audit membutuhkan riwayat jurnal lengkap dan sumber legacy terbukti seimbang.

---

## 4. Mode A — Saldo Awal Saja (Rekomendasi)

### Langkah 1 — Migrasi data non-jurnal

Ikuti [MIGRASI_SIKEU_LEGACY.md](MIGRASI_SIKEU_LEGACY.md), tetapi **lewati `trkas`**:

```bash
cd backend

# Selalu mulai dari dry-run
php artisan sikeu:sync-legacy --dry-run --only=mahasiswa
php artisan sikeu:sync-legacy --dry-run --only=bayar
php artisan sikeu:sync-legacy --dry-run --only=biayalain
php artisan sikeu:sync-legacy --dry-run --only=datakas
php artisan sikeu:sync-legacy --dry-run --only=rkpbyr
php artisan sikeu:sync-legacy --dry-run --only=trbyr

# Eksekusi setelah dry-run bersih
php artisan sikeu:sync-legacy --only=mahasiswa
php artisan sikeu:sync-legacy --only=bayar
php artisan sikeu:sync-legacy --only=biayalain
php artisan sikeu:sync-legacy --only=datakas
php artisan sikeu:sync-legacy --only=rkpbyr
php artisan sikeu:sync-legacy --only=trbyr
```

Pastikan mapping akun sudah lengkap sebelum lanjut:

```sql
SELECT COUNT(*) AS belum_map FROM _mig_akun_mapping WHERE akun_id_baru IS NULL;
```

### Langkah 2 — Pastikan belum ada jurnal baru

Saldo awal hanya bisa diposting bila belum ada jurnal `posted` pada/selewat tanggal saldo awal.
Jika database tujuan masih kosong untuk jurnal, lanjut ke Langkah 3.

### Langkah 3 — Input saldo awal di UI

1. Buka **SIKEU → Akuntansi → Saldo Awal Akuntansi**.
2. Isi **tanggal saldo awal** = tanggal terakhir periode lama (contoh `2025-12-31`).
3. Isi saldo **aset**, **liabilitas**, dan **ekuitas** dari neraca legacy:
   - Aset/liabilitas: isi sesuai saldo normal (debet/kredit).
   - Ekuitas: boleh dikosongkan; selisih akan otomatis masuk `301.03`.
4. Perhatikan panel **Validasi Cut-off**:
   - Hijau: `siap_posting = true` → aman diposting.
   - Kuning: ada jurnal lain pada/sebelum tanggal → **jangan posting**; lihat bagian 7.
5. Klik **Simpan Draft**, review total debet/kredit, lalu **Posting ke Jurnal**.

Sistem otomatis:

- Membuat jurnal seimbang (selalu balance).
- Membukukan selisih ke **`301.03` — Ekuitas Saldo Awal**.
- Mengunci jurnal saldo awal dari edit/hapus biasa.

### Langkah 4 — Mulai transaksi baru

Semua transaksi setelah cut-over harus bertanggal **lebih besar** dari tanggal saldo awal
(contoh: mulai `2026-01-01`). Jangan memigrasikan `trkas` lama setelah saldo awal diposting.

### Langkah 5 — Audit

```bash
php artisan sikeu:saldo-awal:audit
```

Lihat bagian 6 untuk cara membaca hasilnya.

---

## 5. Mode B — Jurnal Historis Penuh

1. Siapkan mapping akun (`_mig_akun_mapping`) sampai 0 baris `akun_id_baru IS NULL`.
2. Impor **seluruh** jurnal legacy, termasuk `trkas`:

   ```bash
   php artisan sikeu:sync-legacy --dry-run --only=trkas
   php artisan sikeu:sync-legacy --only=trkas
   ```

   Command ini sekarang membentuk header + detail jurnal yang seimbang, memakai
   `jenis_sumber` yang valid dan `nomor_jurnal` yang benar.

3. **Jangan** memposting saldo awal. Saldo akun akan terbentuk dari jurnal historis.
4. Jalankan audit:

   ```bash
   php artisan sikeu:saldo-awal:audit
   ```

   Pada Mode B, `saldo_awal` bernilai `null` dan pemeriksaan fokus ke keseimbangan buku,
   jurnal tanpa detail, serta rekonsiliasi kas unit.

> Jika sumber legacy memiliki jurnal yang tidak seimbang, perbaiki di sumber atau
> gunakan Mode A agar buku RAG dimulai bersih dari saldo awal.

---

## 6. Audit Pasca Cut-over

```bash
php artisan sikeu:saldo-awal:audit          # ringkasan manusia
php artisan sikeu:saldo-awal:audit --json   # untuk otomasi/monitoring
```

Yang diperiksa:

| Pemeriksaan | Arti | Tindakan |
|---|---|---|
| `cut_off` | Status cut-off terhadap jurnal posted | `siap_posting` harus `true` |
| `jurnal_sebelum_cut_off` | Jurnal posted pada/sebelum tanggal saldo awal | Kosongkan/geser tanggal; jangan posting saldo awal |
| `jurnal_tanpa_detail` | Header posted tanpa baris detail | Perbaiki/lengkapi jurnal tersebut |
| `jurnal_tidak_seimbang` | Total debet ≠ kredit | Koreksi jurnal di sumber/DB |
| `neraca_saldo.selisih` | Selisih total debet vs kredit seluruh buku | Harus `0` |
| `rekonsiliasi_kas_unit` | `sikeu_unit_kas.saldo_saat_ini` vs saldo GL akun kas | Selisih harus `0`; jika tidak, sesuaikan saldo unit kas |

Contoh membaca output:

```json
{
  "saldo_awal": { "tanggal": "2025-12-31", "total_debet": 150000000, "total_kredit": 150000000 },
  "cut_off": { "siap_posting": true, "jumlah_jurnal_sebelum": 0 },
  "neraca_saldo": { "total_debet": 150000000, "total_kredit": 150000000, "selisih": 0 }
}
```

> `rekonsiliasi_kas_unit` membandingkan saldo unit kas operasional dengan akun kas di GL.
> Saldo awal **tidak** mengubah `sikeu_unit_kas`; selisih di sini biasanya berasal dari
> data kas lama, bukan dari saldo awal.

---

## 7. Troubleshooting

| Gejala | Penyebab | Solusi |
|---|---|---|
| Tombol **Posting** ditolak: “Ada N jurnal posted pada/sebelum …” | Sudah ada jurnal legacy pada/sebelum tanggal saldo awal | Pilih salah satu: (a) pindahkan tanggal saldo awal **setelah** jurnal terakhir, atau (b) hapus jurnal legacy yang sudah tercakup saldo awal, atau (c) jangan pakai saldo awal (Mode B) |
| Command migrasi error “berada pada/sebelum cut-off saldo awal” | Mencoba impor jurnal lama setelah saldo awal diposting | Batalkan posting saldo awal bila memang ingin impor historis, atau ubah tanggal jurnal |
| “Tidak ada draft saldo awal yang bisa diposting” | Belum menyimpan draft | Klik **Simpan Draft** terlebih dahulu |
| “Saldo awal belum memiliki baris akun” | Draft kosong | Tambahkan minimal satu akun neraca |
| Pembatalan posting ditolak | Ada jurnal lain setelah tanggal saldo awal | Hapus/ubah jurnal tersebut lebih dulu, atau batalkan lewat prosedur koreksi jurnal |
| Akun tidak muncul di pilihan | Akun bukan kelompok aset/liabilitas/ekuitas, nonaktif, atau akun `301.03` | Gunakan akun neraca aktif; `301.03` memang dikunci untuk penyeimbang otomatis |
| Saldo terlihat dobel | Saldo awal diposting **dan** jurnal lama ikut diimpor | Hapus salah satu: batalkan saldo awal, atau hapus jurnal lama yang tercakup |
| Selisih kas unit vs GL | Saldo `sikeu_unit_kas` tidak mengikuti jurnal | Koreksi saldo unit kas atau telusuri mutasi kas yang belum dijurnal |
| Menu saldo awal tidak muncul | Role belum dipetakan ke menu | Cek `core_menu_role`; jalankan migrasi menu `2026_10_27_000002_add_sikeu_saldo_awal_menu.php` |

---

## 8. Checklist Go-live

- [ ] Mapping akun legacy lengkap (`_mig_akun_mapping` tanpa `NULL`).
- [ ] Data master, tagihan, dan pembayaran sudah dimigrasikan.
- [ ] Mode migrasi dipilih (A atau B) dan didokumentasikan.
- [ ] Mode A: saldo awal diinput, direview, dan **diposting**; transaksi baru bertanggal setelah cut-off.
- [ ] Mode B: seluruh jurnal historis diimpor; **tidak** ada saldo awal.
- [ ] `php artisan sikeu:saldo-awal:audit` bersih: tidak ada jurnal sebelum cut-off,
      tidak ada jurnal tanpa detail, neraca seimbang, rekonsiliasi kas unit `0`.
- [ ] Laporan **Neraca** dan **Laba Rugi** menampilkan angka yang masuk akal.
- [ ] Akses role diverifikasi (admin akuntansi bisa, auditor tetap read-only).
- [ ] Backup database sebelum dan sesudah cut-over.

---

## 9. Referensi Cepat

| Kebutuhan | Perintah / Lokasi |
|---|---|
| Buat super admin | `php artisan db:seed --class="Database\Seeders\IAM\SuperAdminSeeder"` |
| Buat admin akuntansi | `php artisan db:seed --class="Database\Seeders\Sikeu\SikeuAdminSeeder"` |
| Migrasi legacy (semua) | `php artisan sikeu:sync-legacy --dry-run` lalu tanpa `--dry-run` |
| Migrasi SQL dump | `php artisan migrate:sikeu-legacy-sql --sql-file=... [--dry-run]` |
| Migrasi kas JSON | `php artisan migrate:sikeu-kas [--dry-run]` |
| Audit cut-over | `php artisan sikeu:saldo-awal:audit [--json]` |
| Halaman UI | `/sikeu/akuntansi/saldo-awal` |
| API | `GET/PUT/POST/DELETE /api/v1/sikeu/akuntansi/saldo-awal` |
| Logika inti | `backend/app/Services/Sikeu/SaldoAwalService.php` |
| Tabel | `sikeu_saldo_awal`, `sikeu_saldo_awal_detail` |
| Akun penyeimbang | `301.03` — Ekuitas Saldo Awal |
