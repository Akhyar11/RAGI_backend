---
name: pddikti-sync-standard
description: "Wajib digunakan saat menambah atau mengubah integrasi/sinkronisasi import PDDIKTI Neo Feeder di Laravel, termasuk registry IAM, job queue, progress, pagination, dan persistensi batch."
---

# Standar Sinkronisasi PDDIKTI

Gunakan pola sinkronisasi mahasiswa sebagai referensi arsitektur, bukan untuk menyalin pemetaan/domain mahasiswa:

- Registry dan progress/job: `database/seeders/IAM/IamApiIntegrationSeeder.php`, `app/Http/Controllers/IAM/IamApiIntegrationController.php`, `app/Jobs/SyncPddiktiMahasiswaJob.php`.
- Count, pagination, progress, dan commit batch: `app/Services/IAM/SiakadMahasiswaIntegrationSyncService.php`, terutama `syncPddikti`, `fetchPddiktiCount`, `fetchPddiktiBatch`, dan fungsi simpan batch.
- Tes perilaku: `tests/Feature/IamApiIntegrationTest.php` dan tes sync khusus domain mahasiswa.

## Wajib untuk Integrasi Baru

1. **Daftarkan integrasi.** Tambahkan definisi tetap yang idempoten pada `IamApiIntegrationSeeder`: key, `source_type = pddikti`, action sumber, `target_entity`, koneksi Feeder terkelola, kontrak field/response, dan metadata count bila tersedia. Jangan membuat integrasi dinamis tanpa seeder. Reseed harus mempertahankan koneksi/kredensial admin sesuai pola registry.
2. **Uji koneksi read-only.** Uji koneksi hanya mengambil sampel terbatas dan tidak menulis data target atau mengantrekan import.
3. **Semua sync aktual melalui queue.** Buat job khusus yang mengimplementasikan `ShouldQueue`; controller/handler hanya validasi, mengambil lock, membuat ID progres, memperbarui status registry, dan dispatch job setelah transaksi commit. Jangan melakukan paging/import panjang di request HTTP. Job menerima ID integrasi, ID progres, pemilik/actor dan informasi lock yang diperlukan; muat model integrasi di worker, tangani kegagalan, perbarui status/progress, dan lepaskan lock pada sukses maupun gagal. Queue default mengikuti konfigurasi aplikasi; jangan mengasumsikan worker berjalan tanpa menjelaskannya pada dokumentasi.
4. **Periksa count action sebelum merancang progress.** Periksa katalog/manual/action Neo Feeder yang tersedia dan hak akses pada instance untuk action `GetEntitasCount` atau `GetCountEntitas` (nama nyata mengikuti entitas), lalu verifikasi bentuk respons count. Daftarkan action count yang terbukti tersedia pada registry dan gunakan untuk total/progress. Jangan mengarang nama action atau menganggap jumlah dari satu halaman sebagai jumlah keseluruhan.
5. **Jika count action tidak tersedia.** Catat hasil pemeriksaan dan keterbatasan pada kontrak/dokumentasi. Sync tetap wajib di queue; gunakan progress indeterminate berbasis jumlah record yang telah diproses dan jangan menampilkan persentase/ETA palsu. Bila jumlah dapat dihitung dengan aman dari sumber terverifikasi lain, dokumentasikan sumber itu.
6. **Batch tepat 500.** Semua request daftar entitas memakai `limit = 500`, offset/page bergerak deterministik hingga sumber habis atau total terverifikasi tercapai. Hanya batch terakhir yang boleh berisi kurang dari 500. Terapkan batas maksimum halaman untuk mencegah loop tak terbatas; jika batas tercapai tanpa konfirmasi akhir, gagalkan dengan pesan jelas, jangan menyatakan sukses parsial sebagai selesai.
7. **Simpan setiap batch sebelum mengambil halaman berikutnya.** Jangan mengumpulkan seluruh sumber ke array untuk kemudian ditulis. Validasi dan tulis setiap batch segera setelah diterima, dalam transaksi database yang terukur. Gunakan pencocokan/idempotensi dan validasi relasi milik domain; konflik atau relasi tidak valid dilewati/dilaporkan, bukan ditebak. Update progress hanya setelah batch durable/commit, lalu bebaskan referensi batch dan memori sebelum request berikutnya. Batasi rincian skip/error log, jangan simpan seluruh payload mentah, dan jangan log data identitas dosen/pegawai jika tidak dibutuhkan.
8. **Progress dan concurrency.** Progress minimal menyimpan status queued/running/completed/failed, fase, processed, total bila diketahui, progress, pesan aman, timestamp, dan ringkasan hasil. Gunakan cache/store progres sesuai pola mahasiswa, lock per integrasi/domain untuk mencegah sync tumpang tindih, expiry progres, dan cleanup lock di seluruh jalur gagal.
9. **Aman dan terbatas.** Jangan menghapus target yang tidak hadir dalam respons. Detail skip/error dibatasi jumlahnya dan tidak boleh menyimpan token/kredensial. Jangan mencatat payload sensitif secara mentah ke log umum. Audit kegagalan log tidak boleh membatalkan hasil import.
10. **Tes wajib.** Feature test harus membuktikan: definisi seeder idempoten dan konfigurasi kredensial terjaga; Uji Koneksi tidak menulis; sync mengantrekan job; worker/fake queue menerima ID integrasi dan progres; count action benar atau mode indeterminate yang eksplisit; request list menggunakan limit 500 dan offset yang benar; tiap batch langsung tersimpan sebelum batch selanjutnya; sync ulang idempoten; progres bergerak setelah persist; upstream error/worker failure menandai failed dan melepaskan lock; relasi konflik dilewati dengan alasan. Mock seluruh HTTP Neo Feeder.
11. **Dokumentasi.** Perbarui kontrak API bersama `docs/specs/api-contract.md`, dokumentasi controller IAM/domain di `docs/api/`, serta indeks `docs/README.md` bila endpoint atau respons berubah. Jelaskan queue worker, status/progress, count action yang diverifikasi atau ketiadaannya, batas batch/paging, pemetaan, idempotensi, skip, dan contoh error.

## Bentuk Alur yang Diharapkan

```text
HTTP sync -> validasi + lock + progress queued -> dispatch Job -> 202 + progress ID
Worker -> count bila tersedia -> ambil maksimal 500 -> validasi dan persist batch -> update progress setelah commit
       -> ambil batch selanjutnya -> completed / failed -> update registry dan lepaskan lock
```

Untuk count tidak tersedia, bagian count dilewati dengan alasan tercatat dan progress tetap indeterminate; semua batas batch, persistensi per batch, queue, lock, dan status terminal tetap berlaku.
