# Verifikasi Inbox Sistem dan Notifikasi SPMB

Tanggal laporan: 2026-11-11 (Asia/Jakarta).

Implementasi mencakup transisi submit, verifikasi, publikasi hasil, penerbitan VA, pembayaran lunas, dan daftar ulang. Bell menggabungkan feed sistem dan LMS; masing-masing inbox mempertahankan pagination, filter, serta status bacanya. Penyimpanan ulang hasil lulus mempertahankan kuota, waktu publikasi, dan status daftar ulang.

## Hasil pemeriksaan

| Pemeriksaan | Hasil | Waktu / konteks |
|---|---|---|
| `php -d memory_limit=1G -d max_execution_time=0 vendor/bin/phpunit --filter=SpmbSystemNotificationsTest` | 18 tes lulus, 108 assertion | Diulang 2026-11-11 setelah sinkronisasi backend ke `b563ee7` |
| `npm run build` | Lulus, termasuk TypeScript dan pembuatan route | Diulang 2026-11-11 setelah sinkronisasi frontend ke `32357f3` |
| `NOTIFICATION_TEST_URL=http://127.0.0.1:3107 npx playwright test tests/notifications.spec.js --workers=1 --reporter=line` | 4 tes lulus | Diulang 2026-11-11, preview produksi lokal, API mock |
| ESLint pada halaman inbox sistem/LMS, bell, tabs, service, tipe, helper navigasi, dan tes browser | 0 error, 0 warning | Diulang 2026-11-11 |
| `.githooks/audits/01-syntax-hygiene-and-structure.sh` | Lulus | Backend, 2026-11-11 |
| `.githooks/audits/01-typecheck-and-hygiene.sh` | Lulus | Frontend, 2026-11-11 |
| `git diff --cached --check` | Lulus | Kedua repo |
| Seluruh 128 berkas tes backend, dijalankan per berkas dalam proses terpisah | 703 tes: 629 lulus, 45 gagal assertion, 29 error; 4.395 assertion | Eksekusi penuh pada sesi 2026-11-10, basis backend `fc5cb7a` dengan perubahan fitur ini |
| `npm run lint -- --format=json` seluruh frontend | 4.125 error, 949 warning | Eksekusi penuh pada sesi 2026-11-10; masalah repository yang masih tersisa di luar implementasi fitur ini |

Suite backend satu proses terhenti karena kode Feeder lama memanggil `set_time_limit(120)` dan mengubah batas waktu untuk tes sesudahnya. Seluruh berkas kemudian dijalankan dalam proses PHP terpisah. Cara reproduksi tanpa perubahan kode:

```bash
while IFS= read -r spmb_test_file; do
  php -d memory_limit=1G -d max_execution_time=0 vendor/bin/phpunit "$spmb_test_file"
done < <(rg --files tests -g '*Test.php')
```

Eksekusi penuh belum diulang pada commit remote baru `b563ee7`; tes fitur notifikasi serta empat berkas baseline terakhir sudah diuji pada basis tersebut. Build dan tes browser frontend sudah diulang pada basis `32357f3`.

## Cakupan tes fitur

- Pemicu submit, kedua jalur verifikasi, pendaftaran manual, keputusan hasil, VA, callback pembayaran, dan status daftar ulang.
- Penerima adalah pemilik pendaftaran; akun admin pemroses tidak menerima notifikasi calon.
- Deduplikasi inbox/email, callback berulang, publikasi tertunda, dan retry job.
- Kuota saat menyimpan ulang hasil, perpindahan prodi, pencabutan kelulusan, dan rollback ketika prodi tujuan penuh.
- Email setelah commit, rollback tanpa email, serialisasi job, serta pengiriman melalui transport uji ke alamat calon yang benar. Gangguan transport tidak membatalkan transaksi bisnis.
- Autentikasi, isolasi antar-pengguna, pagination/filter, waktu baca yang idempoten, read-all, serta independensi status baca LMS.
- Browser: akses pengguna biasa, ID sama pada dua sumber, kegagalan salah satu sumber, filter server, navigasi inbox, dan sinkronisasi status baca LMS dari bell.

Tes browser memakai API mock. Koneksi SMTP/gateway pembayaran produksi serta pengiriman email eksternal tidak diuji.

## Kegagalan suite yang sudah ada sebelumnya

Ke-22 berkas yang gagal juga gagal dengan hasil yang sama pada snapshot tanpa fitur inbox. Perbandingan mencakup jumlah tes lulus/gagal/error serta nama dan pesan utama kasus gagal; nilai acak pada detail koneksi SQL dinormalisasi.

Sebanyak 18 berkas dibandingkan pada sesi 2026-11-10 terhadap snapshot `fc5cb7a`. Empat berkas SINAPRA terakhir dibandingkan ulang 2026-11-11 terhadap snapshot `b563ee7`, dengan autoloader diarahkan ke source snapshot dan database SQLite terpisah. Keempatnya menghasilkan kegagalan yang sama.

```text
tests/Feature/DynamicDatabaseTest.php
tests/Feature/MahasiswaAuditLogTest.php
tests/Feature/SPMB/MasterBiayaBebanTest.php
tests/Feature/SPMB/MasterBiayaGelombangTest.php
tests/Feature/SPMB/ReferralTest.php
tests/Feature/SPMB/SpmbPembayaranEndToEndTest.php
tests/Feature/SiakadLmsPresensiTest.php
tests/Feature/SiakadMasterReferensiFlowTest.php
tests/Feature/SiakadPaLanjutanTest.php
tests/Feature/SikeuPajakPelaporanTest.php
tests/Feature/Simpeg/SimpegAttendanceAutomationTest.php
tests/Feature/Simpeg/SimpegUnitKerjaDanJabatanTest.php
tests/Feature/SimpegIzinDanSkTest.php
tests/Feature/SimpegKeteranganTest.php
tests/Feature/SimpegKompetensiTest.php
tests/Feature/SimpegPayrollFlexibleTest.php
tests/Feature/SimpegPegawaiRoleTest.php
tests/Feature/SimpegSuratTugasTest.php
tests/Feature/SinapraPeminjamanBerjenjangTest.php
tests/Feature/SinapraProdiRoleTest.php
tests/Feature/SinapraSikeuIntegrationTest.php
tests/Feature/SinapraPengembalianAsetTest.php
```

Pada frontend, dua error dan satu warning di `MainLayoutClient.tsx`, serta dua warning di `proxy.ts`, juga ditemukan pada versi sebelum perubahan fitur. File fitur notifikasi yang diperiksa secara terpisah bersih dari diagnostik lint.

## Status integrasi dan publikasi

Perubahan fitur dipisahkan menjadi commit backend dan frontend, dengan audit wajib pada setiap commit. Referensi commit dan hasil push diperiksa melalui riwayat Git dan remote.

Eksekusi audit sempat ditolak peninjau izin otomatis karena `.githooks/pre-commit` dapat mengirim diff source ke layanan audit AI eksternal (AGY/Gemini atau fallback OpenCode). Pada 2026-11-11 pengguna memberikan persetujuan eksplisit untuk pengiriman diff dan kelanjutan audit, commit, serta push.

[AGENTS.md](../../AGENTS.md) melarang bypass hook, perubahan `core.hooksPath`, atau penggunaan `--no-verify`. Pemeriksaan lokal yang lulus tercatat di atas; audit AI dijalankan oleh hook commit normal dan hasilnya diperiksa sebelum push.

Saat rilis disetujui, terapkan migrasi `2026_11_11_120000_create_core_notifications_table.php` dan jalankan worker yang mendengarkan `notifications,default` sesuai [SERVER_SETUP.md](../SERVER_SETUP.md). Migrasi database produksi dan deployment belum dijalankan dalam pekerjaan ini.

## Tambahan: mapping admin SPMB melalui IAM

IAM → Pengaturan Sistem kini menyediakan bagian **Admin SPMB** untuk memilih beberapa akun penerima inbox sistem dan email. Nilai awal kosong; akun penerima tidak ditentukan melalui nama role, email, ID tetap, ataupun permission SPMB. Mapping tidak menambahkan hak akses modul. Hanya pengguna dengan kewenangan `iam.settings.update` yang dapat membaca/mengubah mapping dan mencari pilihan akun.

| Pemeriksaan tambahan (2026-11-11) | Hasil |
|---|---|
| `php -d memory_limit=1G -d max_execution_time=0 vendor/bin/phpunit tests/Feature/IAM tests/Feature/SPMB/SpmbSystemNotificationsTest.php tests/Feature/SPMB/SpmbAdminNotificationsTest.php tests/Feature/RestrictedRoleTest.php tests/Feature/FeederTokenModeTest.php tests/Feature/SiakadLmsNotifikasiTest.php` | 90 tes lulus, 541 assertion |
| `npx tsc --noEmit` dan `npm run build` | Lulus |
| ESLint pada komponen mapping, service, tipe, skema Zod, dan tes browser baru | 0 error, 0 warning |
| `NOTIFICATION_TEST_URL=http://localhost:3113 npx playwright test tests/spmb-admin-settings.spec.js --workers=1 --reporter=line` | 5 tes lulus, build produksi lokal dengan API mock |
| `NOTIFICATION_TEST_URL=http://127.0.0.1:3113 npx playwright test tests/notifications.spec.js --workers=1 --reporter=line` | 4 tes regresi inbox sistem/LMS lulus |

Tes backend tambahan mencakup validasi mapping sebelum penulisan pengaturan, audit perubahan, pencarian/pagination akun aktif, metadata akun tersimpan di luar halaman pertama, pemicu seluruh tahap SPMB, deduplikasi, perubahan penerima, akun nonaktif/hilang, pengecualian salinan admin untuk calon pemilik pendaftaran, rollback, email antrean yang diserialisasi lalu dikirim melalui transport uji, jadwal publikasi/retry, serta isolasi status baca.

Tes browser mapping mencakup pemuatan pilihan tersimpan, pencarian server, simpan dan muat ulang, pembatalan, pengosongan mapping berisi akun nonaktif, error validasi, dan kegagalan pemuatan opsi tanpa menyimpan mapping kosong. Hostname lokal dipisahkan sesuai routing yang sudah ada: produksi lokal `127.0.0.1` me-rewrite `/admin/settings` sebagai path modul, sedangkan `localhost` membatasi akses path modul `/lms`. Routing tersebut tidak diubah dalam fitur ini.

Suite global backend/frontend yang masih memiliki kegagalan lama dijelaskan pada hasil pemeriksaan sebelumnya; suite global tersebut tidak diulang untuk tambahan mapping. Tidak ada migrasi baru untuk mapping karena menggunakan `core_system_settings`. Penerima nyata perlu dipilih melalui IAM setelah rilis, dan SMTP serta worker antrean tetap menggunakan konfigurasi server yang sudah ada. Pengiriman SMTP produksi belum diuji.
