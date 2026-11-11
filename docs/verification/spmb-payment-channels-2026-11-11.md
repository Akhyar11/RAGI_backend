# Laporan Hasil Audit Fungsional CRUD: Kanal Pembayaran SPMB

Tanggal: 2026-11-11 (Asia/Jakarta).

## 1. Ringkasan eksekutif

- Tiga halaman utama diperiksa: tambah komponen biaya, edit komponen biaya, dan pembayaran pribadi SPMB.
- Mapping gateway memakai ID konfigurasi SIKEU; pilihan dinamis Xendit, Midtrans, dan H2H bank berasal dari API. Beberapa kanal dapat aktif bersamaan.
- Setiap tagihan mempertahankan provider/environment awal. Komponen dikelompokkan per gateway dan diskon dialokasikan sekali. Status satu tahap baru lunas ketika semua tagihannya lunas.
- Tidak ada dependency baru. Migrasi hanya dijalankan dalam database tes SQLite; database aplikasi/produksi belum dimigrasikan.

## 2. Matriks pengujian halaman dan API

| Halaman / fitur | Rute | Form / dropdown | Respons API | Status | Keterangan |
|---|---|---|---|---|---|
| Tambah komponen | `/spmb/master/komponen-biaya/create` | AsyncSelect dari opsi server | POST ID gateway | Lulus | Pilihan dicari melalui API dan tersimpan; list serta penghapusan memakai dialog konfirmasi |
| Edit komponen | `/spmb/master/komponen-biaya/{id}/edit` | Label tersimpan di luar halaman pertama | GET detail / PUT mapping | Lulus | Ganti pilihan, simpan, muat ulang, label tetap benar |
| Opsi kosong / API gagal | Form edit komponen | Pilihan lama dipertahankan | 200 kosong / 503 | Lulus | Error terlihat; kegagalan tidak diartikan sebagai pengosongan mapping |
| Pembayaran calon | `/spmb/pembayaran?tahap=...` | Tagihan per kanal | GET daftar pribadi / POST checkout | Lulus | Akun calon tanpa menu admin dapat membuka pembayaran; status tetap belum lunas sesudah memperoleh checkout |
| Midtrans / Xendit | Halaman pembayaran | Tautan provider HTTPS | Descriptor redirect | Lulus | Browser tidak menentukan nominal atau gateway; URL mengikuti respons backend |
| H2H bank | Halaman pembayaran | CUSTID, bank, fee, total | Descriptor billing H2H | Lulus | Instruksi memakai billing bridge, bukan VA pengganti |
| Pagination / gangguan provider | Halaman pembayaran | Halaman server dan error terbaca | GET page 2 / 502 checkout | Lulus | Tagihan lunas tidak dapat dibayar lagi; error tidak menampilkan sukses/VA fiktif |
| Kepemilikan dan permission | Endpoint opsi dan checkout | Role berizin / calon pemilik / calon lain | 200 / 403 | Lulus | Role tes dibuat dinamis; calon lain tidak membaca atau membayar tagihan pemilik |
| Penerbitan tahap nyata | API biodata dan daftar ulang | Komponen multi-gateway | Envelope `tagihan_list[]` | Lulus | Seluruh invoice tampil, retry tidak menerbitkan duplikat |

Browser dijalankan terhadap build produksi lokal dengan API mock. Database/otorisasi/penerbitan/callback diuji melalui Feature Test HTTP pada SQLite. Keduanya memeriksa kontrak yang sama; pengujian ini bukan transaksi bank/provider langsung.

## 3. Hasil pemeriksaan

| Pemeriksaan | Hasil |
|---|---|
| Suite integrasi pembayaran/notifikasi/IAM/keuangan terkait | 113 tes lulus, 707 assertion |
| Pemeriksaan akhir allocator dan `SpmbPaymentChannelsTest` sesudah perbaikan scope, rollback migrasi, return URL, dan default kosong | 21 tes lulus, 145 assertion |
| Pemeriksaan terfokus H2H serta fitur kanal sebelum penambahan tes rollback/default | 24 tes lulus, 171 assertion |
| Playwright `tests/spmb-payment-channels.spec.js` | 7 skenario lulus |
| Regresi Playwright inbox sistem/LMS dan mapping admin IAM | 9 skenario lulus (4 + 5) |
| `npm run build` | Lulus, termasuk TypeScript dan route pembayaran |
| `npx tsc --noEmit` | Lulus |
| ESLint pada file frontend baru dan tes browser | Bersih |
| ESLint sembilan file frontend lama yang diubah, dibandingkan `HEAD` | Tetap 47 error dan 26 warning; tidak ada temuan tambahan pada baris perubahan |
| Laravel Pint pada file baru serta request/listener yang diubah | Lulus setelah format |

Jumlah antar-suite tidak dijumlahkan karena beberapa kasus sama dijalankan ulang. Suite global repository memiliki kegagalan lama yang tercatat di [laporan notifikasi sebelumnya](spmb-notifications-2026-11-11.md); suite global tidak diulang untuk fitur kanal ini.

Perintah reproduksi utama:

```bash
php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Services/SpmbBillAllocatorTest.php tests/Feature/SPMB/SpmbPaymentChannelsTest.php
SPMB_PAYMENT_TEST_URL=http://spmb.localhost:3114 npx playwright test tests/spmb-payment-channels.spec.js --workers=1 --reporter=line
NOTIFICATION_TEST_URL=http://127.0.0.1:3114 npx playwright test tests/notifications.spec.js --workers=1 --reporter=line
NOTIFICATION_TEST_URL=http://localhost:3114 npx playwright test tests/spmb-admin-settings.spec.js --workers=1 --reporter=line
npm run build
```

Hostname dibedakan sesuai routing tenant yang sudah ada. Server preview dibuat hanya untuk tes dan dihentikan sesudah selesai.

## 4. Cakupan transaksi dan temuan

Tes mencakup pembagian diskon per komponen dan global hingga sen, pembulatan, nominal besar tanpa overflow integer, grouping gateway, snapshot, pengulangan penerbitan, beberapa kanal aktif/default, penolakan pergantian environment saat masih ada tagihan, serta up/down/reapply migrasi.

Callback diuji dengan signature/token salah, invoice ID salah, nominal salah, pending, retry sukses, pemilihan tagihan yang tepat, pelunasan kas/jurnal, dan deduplikasi inbox. Kegagalan pemetaan COA membatalkan pembayaran, saldo, serta inbox pelunasan dalam transaksi yang sama. H2H diuji dengan penerbitan bridge, penggunaan kembali billing, dan penerapan pembayaran bank. Pelunasan satu kelompok tidak membuka tahap lain atau memicu daftar ulang lunas lebih awal.

Temuan selama pengerjaan diperbaiki: pemilihan gateway global yang saling menonaktifkan, checkout SPMB yang masih berupa simulator Xendit, pembacaan hanya satu tagihan, promosi lunas sebelum semua kelompok selesai, perhitungan total di frontend dari nilai pembayaran pertama, callback URL dari origin frontend, serta pencatatan audit konfigurasi dengan argumen yang tidak sesuai. Tidak ada masalah baru yang masih terbuka dalam cakupan tes di atas.

## 5. Kesiapan integrasi dan deployment

Kode siap diintegrasikan dengan konfigurasi provider yang valid. Sebelum dipakai pada server aplikasi:

1. Terapkan migrasi `2026_11_11_140000_add_spmb_payment_channels.php`.
2. Aktifkan credential/environment dan unit kas/COA kanal melalui SIKEU; pilih kanal pada komponen biaya SPMB atau tentukan default. Default yang belum siap menolak tagihan baru, tanpa membuat VA fiktif. Tagihan lama tanpa mapping tetap memakai VA tersimpan.
3. Atur URL callback backend di dashboard Midtrans/Xendit sesuai `callback_url` dari API konfigurasi. Atur `APP_URL`/frontend URL yang benar dan akses callback publik.
4. Jalankan worker `notifications,default`, konfigurasi SMTP, dan sinkronisasi H2H `php artisan sikeu:sync-h2h` sesuai konfigurasi server.
5. Verifikasi transaksi sandbox provider dan bank sebelum mengaktifkan produksi.

Tidak ada pembayaran eksternal nyata, pengiriman SMTP produksi, migrasi produksi, atau deployment yang dijalankan dalam pekerjaan ini. Commit dan push mengikuti hook audit normal tanpa bypass.
