# API Contract: Nomor SK SPMB melalui Arsip

## Sinkronisasi Dosen PDDIKTI

- Integrasi `siakad_dosen` memakai `GetListDosen` dan count action terverifikasi `GetCountDosen`. Sync aktual didispatch ke `SyncPddiktiDosenJob` pada queue `default`, mengembalikan `202 Accepted` dengan `progress_id`, dan dipoll lewat endpoint IAM progress. Uji koneksi tetap read-only; preview dosen belum didukung.
- Daftar dosen diambil dengan `limit=500`, offset bertambah deterministik, dan batas 100 halaman. Jika `GetCountDosen` tersedia, jumlah list harus mencapai total tersebut; list yang berakhir lebih awal atau cap tercapai menyebabkan kegagalan, bukan sukses parsial. Jika count action tidak tersedia/ditolak, progress `total` dan persentase tetap `null` sampai terminal.
- Data homebase dan `GetRiwayatPendidikanDosen` diambil untuk ID dosen di halaman saat ini saja; keduanya memakai filter ID batch, limit 500, dan batas 100 halaman. Setiap halaman persist dalam satu transaksi; setiap dosen memakai savepoint agar satu row gagal tidak membatalkan row valid lainnya. Log dan progress diperbarui sesudah transaksi halaman commit dan sebelum halaman berikutnya diminta. Rincian log dibatasi maksimal 100.
- Match dosen mengikuti NIDN, lalu NUPTK; bila keduanya tidak ada, UUID `id_dosen` hanya fallback idempotensi. NIP, NIK, dan nama tidak menyatukan record secara otomatis. Conflicts dan invalid row dilaporkan; data lokal yang tidak muncul di sumber tidak dihapus.
- Worker memperbarui status integrasi/progress serta melepaskan lock pada sukses maupun gagal. Proses juga dapat menyelaraskan SIMPEG, riwayat pendidikan, dan akun SSO dosen aktif.

## Integrasi Dosen Neo Feeder melalui IAM

- Integrasi tetap `siakad_dosen` terdaftar pada `IamApiIntegrationSeeder`, memakai `source_type = pddikti`, action `GetListDosen`, dan koneksi Neo Feeder terkelola dari Pengaturan Sistem. Uji koneksi hanya meminta satu record dan tidak mengubah target.
- Sync aktual membuat lock dan progres queued, mendispatch `SyncPddiktiDosenJob` pada queue `default` setelah commit, dan merespons `202 Accepted` dengan `progress_id`. Progress dibaca melalui `GET /api/admin/api-integrations/student-sync-progress/{progressId}`; akses hanya untuk pemilik request atau superadmin. Driver queue `sync` ditolak agar impor panjang tidak berjalan di request.
- Action `GetCountDosen` telah diverifikasi read-only pada Feeder kampus dan mengembalikan total `181`. Action ini dipakai untuk total/progress. Jika action gagal atau tak tersedia, sync tetap memproses halaman tetapi progress `total` dan persentase bernilai `null` (indeterminate).
- `GetListDosen` menggunakan `limit=500`, offset deterministik, dan batas 100 halaman. Setiap halaman diproses sebelum offset berikutnya: homebase dan riwayat pendidikan hanya diminta untuk ID dosen dalam batch tersebut; seluruh persist batch berjalan dalam transaksi database sebelum log/progress diperbarui. Jika response list berakhir sebelum `GetCountDosen` terpenuhi, job gagal dan tidak menandai hasil parsial sebagai sukses.
- `GetListPenugasanDosen` untuk homebase dan `GetRiwayatPendidikanDosen` juga memakai limit 500 serta batas 100 halaman. Filter `id_dosen in (...)` untuk riwayat telah diverifikasi pada instance Feeder kampus; data relasi tidak dipreload untuk populasi penuh.
- Matching target lokal: NIDN lebih dulu, NUPTK fallback; bila keduanya tidak tersedia, `id_dosen` dipakai untuk idempotensi. NIP, NIK, dan nama tidak digunakan untuk merge otomatis. Konflik identifier ditangani per-record, dicatat maksimal 100 detail tanpa payload mentah, dan tidak menghapus target yang absen dari sumber.
- Nilai NIDN, NUPTK, dan NIP yang disimpan pada SIAKAD/SIMPEG/username SSO mempertahankan digit dan nol awal seperti dari PDDIKTI; normalisasi nol awal hanya untuk pencocokan duplikasi. Username SSO dosen aktif dibuat atau diperbarui dari prioritas NIDN, NUPTK, lalu NIP. Dosen nonaktif tidak memperoleh akun baru, tetapi akun lama yang tertaut atau cocok dengan identifier dapat diperbarui.
- Progress terminal dan `siakad_feeder_sync_logs` melaporkan `created`, `updated`, `sso_created`, dan `sso_updated` sebagai hitungan record/akun unik yang berhasil diproses. `synced` tetap berarti jumlah row sumber yang berhasil, bukan jumlah row yang berubah. Empat kolom counter pada log memerlukan migration `2026_11_23_130000_add_record_and_sso_counts_to_feeder_sync_logs` sebelum deployment.
- Worker memperbarui `siakad_feeder_sync_logs`, status integrasi, dan progress setelah setiap halaman berhasil disimpan; lock dibersihkan pada sukses maupun gagal. Sync juga dapat menyelaraskan `simpeg_pegawai`, riwayat pendidikan SIMPEG, dan akun SSO dosen aktif, sehingga operator harus meninjau efeknya sebelum run produksi.

## Integrasi Kelas Kuliah PDDIKTI

- Integrasi tetap `siakad_kelas` memakai `GetListKelasKuliah`. `GetCountKelasKuliahWs` tercatat di User Guide Neo Feeder 2.0, tetapi request live pada koneksi kampus timeout; worker tidak memanggilnya dan menggunakan progress indeterminate tanpa total/persentase palsu.
- Sync tulis berjalan melalui `SyncPddiktiKelasJob` pada queue `default`, mengembalikan `202 Accepted` dan `progress_id`. Uji koneksi mengambil maksimal satu record secara read-only dan tidak mengubah status target. Progress dapat dipoll lewat endpoint progress IAM oleh pemilik request atau superadmin.
- `GetListKelasKuliah` dipanggil dengan `limit=500`, `order=id_kelas_kuliah`, dan offset deterministik. Request halaman diulang maksimal 2 kali (jeda 3 dan 10 detik) hanya untuk timeout/kegagalan jaringan, `error_code` bukan 0 tidak diulang. Setiap batch dipersist dalam transaksi sebelum batch berikutnya diminta; progress jumlah terbaca diperbarui setelah commit. Batch terakhir yang kurang dari 500 menandai akhir. Jika 100 batch (50.000 record) penuh tanpa batch akhir, job gagal eksplisit.
- `id_prodi`, `id_semester`, dan `id_matkul` harus cocok unik dan aktif dengan `id_feeder` pada `siakad_program_studi`, `siakad_tahun_akademik`, dan `siakad_mata_kuliah`. Mapping yang hilang/ambigu/terhapus, label kelas yang tidak valid, duplikasi, atau konflik identifier menjadi item skip dengan alasan (maksimal 100 detail); sistem tidak menebak relasi atau menghapus target yang tidak ada di sumber.
- Satu `siakad_master_kelas` dicari/dibuat berdasarkan program studi + label + angkatan, lintas semester; contoh `26A` menjadi tahun angkatan 2026 (label lain seperti `B` diterima dengan angkatan null; unik per prodi + nama + angkatan, NULL dicocokkan eksplisit) dan `dosen_pa_id` null. `siakad_jadwal_kelas` (sebelumnya `siakad_kelas`) TIDAK ditulis karena merupakan tabel penjadwalan. Pemetaan `id_kelas_kuliah` ke prodi, semester, mata kuliah, dan Master Kelas hanya ditahan di cache sementara (2 jam) selama job untuk fase dosen pengajar.
- Counter `created`/`updated` pada fase kelas menghitung Master Kelas baru/yang sudah ada. Cakupan penulisan hanya `siakad_master_kelas` (fase kelas) dan `siakad_distribusi_mengajar` (fase dosen); `siakad_jadwal_kelas`, peserta, mahasiswa, KRS, nilai, dosen pengampu, dan pivot kelas-rombel tidak diimpor.
- Ringkasan progress/hasil memuat jumlah baris dibaca, created, updated, synced, skipped, pages, dan maksimal 100 alasan skip. Kegagalan upstream menandai job/progress failed dan melepaskan lock.
- Setelah impor kelas selesai, worker mengambil `GetDosenPengajarKelasKuliah` dengan batch 500, offset deterministik, batas 100 batch, dan persist per batch. Count action `GetCountDosenPengajarKelasKuliah` belum diverifikasi live; progress assignment indeterminate dan batch terakhir kurang dari 500 menandai akhir.
- Mapping wajib: `id_kelas_kuliah` ke hasil `GetListKelasKuliah` pada job yang sama; `id_dosen` ke `siakad_dosen.id_feeder`; sedangkan `id_registrasi_dosen` TIDAK dicocokkan ke `siakad_dosen_penugasan` (tabel itu tidak diisi sync PDDIKTI); nilainya hanya disimpan sebagai `registrasi_ids` pada `dosen_anggota_details`. SKS berasal dari `sks_substansi_total`; Master Kelas ditentukan dari baris kelas Feeder yang sama. Mapping kosong/ambigu atau kelas/rombel tak ditemukan dilewati dengan alasan.
- Assignment valid digabung ke satu `siakad_distribusi_mengajar` per prodi + tahun akademik + mata kuliah + semester anjuran. Dosen disimpan di `dosen_anggota_ids`/`dosen_anggota_details`, rombel di `kelas_ids`. Detail Feeder berlabel `source=pddikti`; anggota/SKS manual dipertahankan, dan anggota Feeder yang tidak lagi muncul tidak dihapus.
- `dosen_koordinator_id` tidak disimpulkan dari urutan Feeder. Distribusi baru memakai koordinator null, koordinator existing tidak ditimpa. Distribusi target yang duplikat dilewati. Hasil mencakup `assignment_source_total`, `distributions_created`, `distributions_updated`, jumlah assignment `assignments_synced`, `assignment_skipped`, dan maksimal 100 `assignment_skipped_items`. Mahasiswa/peserta/KRS/nilai tidak diimpor.

## Sync KRS historis PDDIKTI (2026-11-25)

Integrasi `siakad_krs` (action `GetKRSMahasiswa`) berjalan lewat queue worker dengan kontrak yang sama seperti sync PDDIKTI lain (202 + `progress_id`, lock, batch 500, persist per batch).
Tidak ada perubahan skema: memakai kolom `siakad_krs_detail.mata_kuliah_id`/`master_kelas_id` dan `kelas_id` nullable dari "Skema: KRS historis berbasis mata kuliah".
Pembacaan per periode (`filter id_periode`, urutan majemuk unik `id_registrasi_mahasiswa,id_periode,id_matkul,id_kelas`, offset deterministik, maks. 200 batch per periode). `GetCountKRSMahasiswa` timeout live (juga dengan filter) sehingga progres indeterminate; posisi periode ada di pesan.
Aturan: satu KRS per (mahasiswa, tahun akademik) dan satu detail per (KRS, mata kuliah); status baru `disetujui` untuk periode berjalan, `dikunci` untuk periode lampau; KRS/detail yang sudah ada tidak ditimpa (kecuali `master_kelas_id` NULL yang dapat diisi);
rombel hanya dicari (tidak dibuat); registrasi lama dari orang yang memiliki registrasi lain (alih bentuk/pindah prodi) tidak ditempel ke baris mahasiswa lokal dan dilaporkan dengan alasan tersendiri.
`siakad_jadwal_kelas` dan `siakad_nilai_mahasiswa` tidak disentuh (nilai ditangani terpisah).

## Sync skala nilai PDDIKTI (2026-11-25)

Integrasi `siakad_skala_nilai` (action `GetListSkalaNilaiProdi`, count `GetCountSkalaNilaiProdi` diverifikasi live) berjalan lewat queue worker dengan kontrak yang sama seperti prodi/mata kuliah
(202 + `progress_id`, lock, batch 500 urut `id_prodi`, persist per batch, progres indeterminate bila count tidak tersedia; grup prodi yang terpotong batas batch ditahan).
Skema (`2026_11_25_130000_add_pddikti_identity_and_period_to_siakad_skala_nilai`): `siakad_skala_nilai.id_feeder` (unik, = `id_bobot_nilai`), `tanggal_mulai_efektif`, `tanggal_akhir_efektif`.
Aturan: skala standar lokal (`program_studi_id` NULL) tidak diubah/dihapus; rentang bulat Feeder dijadikan kontinu hanya bila selisih tepat 1 (84 -> 84.99), celah/tumpang tindih dilaporkan sebagai peringatan dan tidak ditebak;
skala berakhir dan versi huruf lama diimpor nonaktif; huruf berentang kosong yang bertabrakan dengan huruf berbobot sama nonaktif; `is_lulus` mengikuti skala standar lokal untuk huruf yang sama saat membuat baris.
Nama field live `bobot_nilai_min`/`bobot_nilai_maks` (PDF manual menyebut `bobot_minimum`/`bobot_maksimum`).
Konsumen: daftar `skala_nilai` pada respons nilai OBE kelas memakai `SkalaNilai::aktifUntukProdi` (skala khusus prodi bila ada, selain itu skala standar), bukan seluruh skala aktif.

## Sync PDDIKTI prodi dan mata kuliah memakai UUID sebagai identitas (2026-11-25)

**Eksekusi sync (diperbarui 2026-11-25):** sync PDDIKTI prodi dan mata kuliah berjalan lewat queue worker, bukan di request HTTP.
`POST /api-integrations/{id}/sync` memvalidasi, mengambil lock per integrasi, mencatat progres `queued`, dispatch job setelah commit,
dan menjawab `202` + `progress_id` (`409` bila sedang berjalan, `503` bila `QUEUE_CONNECTION=sync`, `422` untuk preview).
Job membaca `GetProdi` / `GetMatkulKurikulum` per batch 500 (offset deterministik, maks. 100 batch, gagal jelas bila tanpa penanda akhir),
menyimpan tiap batch dalam transaksi sebelum batch berikutnya, memperbarui progres setelah commit, dan melepas lock pada semua jalur terminal.
Action count terverifikasi live: `GetCountProdi` (`data` = jumlah) dan `GetCountMatkulKurikulum` (`data` = jumlah string); bila action ditolak atau timeout,
progres indeterminate (tanpa persentase/ETA). Mata kuliah diurut `id_matkul`; kemunculan `id_matkul` yang terpotong batas batch ditahan dan diproses bersama batch berikutnya.
Tidak ada record target yang dihapus karena absen di sumber. Sumber `custom_api` tetap sinkron.

Cacat yang diperbaiki (dibuktikan dengan data Feeder nyata): sync memakai kode sebagai identitas padahal kode Feeder tidak unik.
- **Prodi (`GetProdi`)**: baris lokal dengan `id_feeder` lain bukan kandidat adopsi; UUID baru dengan kode terpakai dibuat sebagai prodi baru,
  `kode_prodi` lokal `{kode}-{8 karakter UUID}` (tetap unik), kode resmi di `kode_prodi_dikti`. Baris lama tanpa identitas tetap diadopsi lewat kode;
  baris legacy ber-`source_id` berbeda tetap dilewati untuk rekonsiliasi manual. Baris yang sudah ada mempertahankan kode lokalnya bila kode Feeder dipakai prodi lain.
- **Mata kuliah (`GetMatkulKurikulum`)**: satu `id_matkul` = satu baris lokal (pasangan di banyak kurikulum digabung; kurikulum berlaku terbaru dipakai,
  penempatan lokal yang sudah ada dipertahankan). Pencocokan: `id_feeder`, `source_id`, lalu adopsi baris tanpa `id_feeder` pada kurikulum dan kode sama.
  Komponen SKS `null` dari Feeder dianggap 0; `sks_praktik` = praktek + praktek lapangan + simulasi.
- Skema: `siakad_mata_kuliah.kode_mk` unik per kurikulum (`kurikulum_id`, `kode_mk`) menggantikan unique global
  (migration `2026_11_25_120000_scope_kode_mk_unique_to_kurikulum`). `StoreMataKuliahRequest` memvalidasi keunikan kode per kurikulum.
  Sync legacy `SiakadMataKuliahSyncService` mencocokkan kode hanya dalam kurikulum tujuan.
- Belum dikerjakan: mata kuliah yang tidak ada di kurikulum mana pun (hanya di `GetListMataKuliah`); prodi mata kuliah diturunkan dari
  `kurikulum->program_studi_id` di banyak fitur sehingga `kurikulum_id` NULL perlu keputusan desain tersendiri.

## Skema: `id_feeder` sebagai identitas unik (2026-11-25)

Kolom `id_feeder` yang berfungsi sebagai **identitas** entitas Neo Feeder wajib unik. Kolom yang hanya **referensi/atribut**
tidak dibuat unik.

| Tabel | Kolom | Status | Arti di Feeder |
|---|---|---|---|
| `siakad_program_studi` | `id_feeder` | UNIQUE (baru) | `id_prodi` |
| `siakad_mata_kuliah` | `id_feeder` | UNIQUE (baru) | `id_matkul` |
| `siakad_dosen` | `id_feeder` | UNIQUE (baru) | `id_dosen` |
| `siakad_mahasiswa` | `id_feeder`, `id_feeder_biodata` | UNIQUE (baru) | `id_mahasiswa`; kunci pencocokan sync mahasiswa |
| `siakad_jadwal_kelas` | `id_feeder` | UNIQUE (baru) | `id_kelas_kuliah` |
| `siakad_dosen_pengampu` | `id_feeder` | UNIQUE (baru) | `id_aktivitas_mengajar` |
| `siakad_kurikulum`, `siakad_tahun_akademik` | `id_feeder` | sudah UNIQUE | — |
| `siakad_dosen_penugasan` | `id_feeder` | TIDAK unik (referensi) | `id_registrasi_dosen`, berulang per tahun akademik |
| `siakad_mahasiswa` | `id_feeder_riwayat` | TIDAK unik (atribut) | `id_registrasi_mahasiswa` aktif |
| `siakad_feeder_mappings` | `feeder_id` | TIDAK unik (tabel pemetaan) | — |

Migration `2026_11_25_110000_add_unique_id_feeder_identity_columns` (data dahulu, lalu indeks):
1. `''` pada kolom identitas diubah menjadi NULL (NULL boleh berulang pada unique index).
2. Grup baris dengan nilai sama dideduplikasi. Baris bertahan: tidak soft-delete, lalu `is_active` aktif, lalu `id` terkecil.
3. Seluruh rujukan (foreign key, plus kolom `program_studi_id`/`prodi_id` tanpa FK pada prodi) dipindah ke baris bertahan sebelum baris duplikat dihapus permanen.
   Nilai `source_id`/`source_kode_prodi` baris duplikat dibawa ke baris bertahan bila kosong (prodi).
4. Setiap grup diproses dalam transaksi; konflik unique saat memindahkan rujukan menggagalkan migration dengan pesan jelas tanpa mengubah grup itu.
5. Baris yang dihapus dicatat di log (`Log::warning`) sebagai JSON. Penghapusan tidak dapat dibatalkan oleh `down()`.

## Skema: KRS historis berbasis mata kuliah (2026-11-25) — migration + pembaca DIIMPLEMENTASI, impor Feeder belum

Latar: `siakad_jadwal_kelas` tidak diisi dari Neo Feeder. KRS Feeder (`GetKRSMahasiswa`) berisi mahasiswa x periode x
mata kuliah (+ label rombel `nama_kelas_kuliah`), tanpa kelas konkret. Pendekatan: tambahan skema tanpa membongkar alur kelas.

Perubahan `siakad_krs_detail` (migration `2026_11_25_100000_add_mata_kuliah_and_rombel_to_siakad_krs_detail_table`, aditif):
- `kelas_id` menjadi **nullable** (FK ke `siakad_jadwal_kelas` tetap ada).
- Tambah `mata_kuliah_id` (nullable, FK `siakad_mata_kuliah`, indexed) dan `master_kelas_id` (nullable, FK `siakad_master_kelas`, rombel).
- Aturan: baris dari alur KRS online tetap mengisi `kelas_id`; baris impor Feeder mengisi `mata_kuliah_id` (+ `master_kelas_id`) dan `kelas_id` NULL.
- Pembaca nilai/IPK memakai mata kuliah efektif: `krs_detail.kelas.mataKuliah` bila ada, jika tidak `krs_detail.mata_kuliah_id`.
  Periode (tahun akademik) selalu dari `siakad_krs.tahun_akademik_id`, bukan dari kelas.
- Baris lama/KRS online mengisi `mata_kuliah_id` dari kelasnya (backfill migration + `KrsService::addClass`).
- Tanpa unique baru pada `(krs_id, mata_kuliah_id)`: satu MK dapat diambil pada kelas teori dan praktik; idempotensi impor dijaga di importer.
- Fitur berbasis kelas (LMS, kuis, forum, presensi, notifikasi, kuota/bentrok KRS online) tidak berlaku untuk baris tanpa kelas.

Tidak berubah: `siakad_dosen_pengampu.kelas_id` tetap wajib (pengampu per mata kuliah ada di `siakad_distribusi_mengajar`).

## Kode diskon pendaftaran SPMB (2026-11-16)

- `POST /api/spmb/pendaftaran/biodata` hanya menyimpan/memperbarui draft biodata dan pilihan `kode_diskon`; endpoint ini tidak menerbitkan invoice dan tidak mengklaim kuota kode.
- `POST /api/spmb/pendaftaran/terbitkan-tagihan` menjadi satu-satunya langkah yang mengunci klaim kode dan menerbitkan invoice. Request menerima `kode_diskon` opsional; bila tidak dikirim server menggunakan pilihan tersimpan di draft. Klaim kode, nominal snapshot, dan penerbitan invoice berjalan dalam transaksi; retry memakai invoice/klaim yang sudah ada dan tidak menghabiskan kuota ulang.
- Kode diskon mendukung tipe `persen`, `nominal` (besar potongan), dan `total_tetap` (target total beban pendaftaran setelah potongan kode). Target total tetap tidak pernah menaikkan tagihan; potongan adalah maksimum sisa biaya eligible setelah potongan calon lain. `maksimum_potongan` dapat membatasi potongan untuk tipe persen/nominal dan tidak berlaku pada `total_tetap`.
- Admin dapat membatasi kode ke beberapa `jalur_masuk_ids[]` dan `master_tipe_jalur_ids[]`, seluruhnya FK ke master. Array kosong berarti semua. Validasi preview dan penerbitan memeriksa jalur dari gelombang yang dipilih serta jenis pendaftaran aktual; browser tidak dipercaya untuk menentukan jalur.
- Basis diskon hanya komponen Master Biaya positif yang ditandai sebagai beban pendaftaran. Tarif fallback dan daftar ulang tidak eligible. Jenis `total_tetap` menargetkan total komponen eligible tersebut, setelah potongan per calon yang sudah berlaku; biaya gateway berada di luar target.
- `POST /api/spmb/kode-diskon/validate` hanya pratinjau, tidak mengunci kuota. Penggunaan hanya tercatat setelah invoice berhasil diterbitkan. Jika penerbitan gagal, transaksi mengembalikan klaim/kuota.
- Pendaftaran yang belum memiliki invoice dapat memperbarui atau menghapus kode pada draft. Setelah invoice terbit kode, pilihan jalur, dan jenis pendaftaran terkunci; perubahan memerlukan alur pembatalan/reissue eksplisit.

## Kanal pembayaran komponen biaya SPMB (2026-11-14)

- `spmb_master_komponen_biaya.payment_gateway_name` menyimpan kanal pilihan walau konfigurasi belum dibuat. `payment_gateway_config_id` tetap FK nullable untuk mengaitkan record konfigurasi SIKEU yang tersedia. API CRUD menerima kanal yang dapat dikonfigurasi (`xendit`, `midtrans`, `duitku`, `bsn_h2h`, `rekening_manual`); nama null berarti memakai kanal default SIKEU pada penerbitan baru.
- `GET /api/spmb/master/payment-gateway-options` memerlukan `spmb.manage`, menyediakan pagination `page/per_page` (15, maksimum 100), `search`, `sort_by` (`gateway_name`/`id`), `sort_order`, dan daftar kanal yang dapat dipilih walau belum memiliki record konfigurasi. Metadata opsi hanya memuat `id` nullable, `gateway_name`, `environment` nullable, `is_active`, dan `is_configured`; credential tidak dikembalikan.
- Pemilihan nama kanal belum mengaktifkan atau mengonfigurasi provider. Penerbitan tagihan tetap menolak kanal yang belum aktif; alur pembayaran hanya boleh memakai adapter yang didukung SPMB (Midtrans, Xendit, H2H). Kanal lain yang tampil di daftar SIKEU belum otomatis berarti adapter SPMB tersedia.
- Konfigurasi gateway dapat aktif bersamaan; `is_default` menentukan pilihan untuk pemanggil lama yang tidak memilih kanal. Pemilihan default SPMB hanya mempertimbangkan kanal yang didukung (`midtrans`, `xendit`, `bsn_h2h`), sehingga default global modul lain tidak pernah terpilih sebagai kanal SPMB. Memilih kanal SPMB tidak mengubah default modul lain. Kredensial tetap dikelola di SIKEU dengan `sikeu.paymentgateway.manage`.
- Tagihan menyimpan `payment_gateway_config_id`, `payment_gateway_name`, dan `payment_gateway_environment` ketika diterbitkan. Perubahan master komponen berlaku untuk tagihan berikutnya. Provider/environment tagihan berjalan tidak dialihkan diam-diam ketika konfigurasi berubah atau nonaktif.
- Komponen dikelompokkan per gateway pada tahap pendaftaran/daftar ulang. Potongan dialokasikan sekali dengan batas nilai komponen; total antar-tagihan sama dengan total beban sesudah potongan. Biaya gateway dihitung memakai konfigurasi kanal masing-masing. Penerbitan ulang tahap yang sama menggunakan tagihan yang sudah ada.
- Respons lama `tagihan/virtual_account` tetap tersedia untuk kompatibilitas, ditambah `tagihan_list[]` yang memuat seluruh tagihan. Halaman pembayaran baru mengambil daftar lengkap dan tidak menyimpulkan lunas dari satu tagihan saja.
- `GET /api/spmb/pembayaran` (Bearer Passport) hanya menampilkan tagihan milik calon yang login; petugas dengan `spmb.manage`/`spmb.pendaftaran.read` boleh membuka endpoint, sedangkan pengguna tanpa pendaftaran dan tanpa permission menerima 403. Filter `tahap=pendaftaran|daftar_ulang`, pencarian, sorting, dan pagination standar memakai envelope `status/message/data/meta` tanpa key `filters` di root. Data mencakup rincian nominal/potongan/sisa, kanal, status, dan informasi pembayaran yang aman.
- `POST /api/spmb/pembayaran/{id}/checkout` hanya untuk pemilik tagihan atau petugas berizin `sikeu.paymentgateway.manage`/`sikeu.pembayaran.create`. Tidak menerima nominal/gateway dari browser. Midtrans menghasilkan token Snap, Client Key publik, dan environment untuk membuka popup tanpa tab baru; Xendit menghasilkan URL invoice; H2H menghasilkan billing/CUSTID dan instruksi bank. Tagihan lama tanpa mapping dapat menampilkan VA lama. Tagihan lunas/nonaktif/kanal belum siap menghasilkan 422, milik pengguna lain 403, tidak ditemukan 404, gangguan provider 502.
- Checkout online dicatat di `sikeu_payment_checkouts` (order/provider/nominal/environment/status dan URL/token checkout), digunakan ulang selama masih valid. Callback mencocokkan order, provider, nominal, dan tanda tangan/token server; callback tertunda/kedaluwarsa tidak melunasi tagihan, retry sukses tidak menggandakan kas/jurnal/notifikasi. Tidak ada fallback VA fiktif untuk kanal yang dipilih eksplisit.
- Callback Midtrans/Xendit serta sinkronisasi H2H memperbarui tagihan yang tepat. Agregasi status pendaftaran/daftar ulang memeriksa seluruh tagihan tahap terkait. Penyelesaian satu tahap tidak melunasi tahap lain. Konversi mahasiswa dan notifikasi tetap mengikuti status agregat; seluruh pengiriman email setelah commit.
- Frontend komponen biaya memakai AsyncSelect yang mengambil opsi API. Registrasi dan daftar ulang membuka `/spmb/pembayaran?tahap=...`; halaman tersebut menampilkan tiap tagihan dan menjalankan checkout sesuai respons backend. Browser tidak dapat mensimulasikan pembayaran untuk tagihan dengan kanal eksplisit.

## Inbox Sistem dan Transisi SPMB (2026-11-10)

### Mapping penerima admin SPMB melalui IAM (2026-11-11)

- IAM → Pengaturan Sistem menyediakan mapping beberapa akun penerima admin SPMB. Sumber penerima adalah daftar ID akun yang dipilih admin IAM, tanpa pencarian akun melalui ID tetap, email, nama role, atau inferensi permission SPMB.
- Key `spmb_admin_notification_user_ids` pada `core_system_settings` menyimpan JSON array ID integer positif dari `core_users`. Default `[]` menonaktifkan pengiriman admin. Mapping tidak memberikan hak akses modul kepada akun terpilih.
- `GET /api/admin/system-settings` menyertakan record key tersebut dengan `value` JSON string serta `users[]` berisi `id`, `name`, `username`, `email`, dan `is_active` untuk menampilkan pilihan tersimpan, termasuk akun yang kemudian nonaktif.
- `POST /api/admin/system-settings` menerima key tersebut melalui envelope `settings[]` yang sudah ada. Server menolak JSON bukan array, ID tidak valid/tidak ditemukan/nonaktif, atau lebih dari 100 entri dengan 422 sebelum menulis pengaturan. ID ganda dinormalisasi. `[]` menghapus seluruh penerima. Perubahan mapping dicatat pada audit log.
- Pembacaan/perubahan pengaturan dan `GET /api/admin/system-settings/spmb-admin-options` memerlukan autentikasi serta kewenangan IAM `iam.settings.update`. Endpoint opsi hanya menampilkan akun aktif, mendukung pencarian nama/username/email, `page`, `per_page` (default 15, maksimum 100), `sort_by` (name/username/email/id), dan `sort_order` (asc/desc).
- Admin yang dipetakan menerima inbox dan email untuk transisi SPMB yang sama dengan calon: submit, verifikasi, publikasi hasil, penerbitan VA, pelunasan, dan daftar ulang. Pesan admin menyebut nama/nomor pendaftar dengan URL `/spmb/pendaftaran/{id}`, type `admin_{type_calon}`, dan kunci deduplikasi per pengguna/event.
- Penerima nonaktif/hilang dilewati pada pengiriman. Akun calon pemilik pendaftaran dikecualikan dari salinan admin untuk event miliknya sendiri. Perubahan mapping berlaku untuk event selanjutnya; tidak mengirim ulang riwayat. Notifikasi calon tetap berjalan ketika mapping kosong atau rusak.
- Email admin memakai antrean `notifications` setelah transaksi berhasil. Rollback tidak meninggalkan inbox/email admin; kegagalan antrean tidak membatalkan proses utama.

- Tabel `core_notifications` menyimpan notifikasi personal: `user_id` (FK core_users), `module_id` (FK nullable core_modules), `type`, `title`, `message`, `url`, `entity_type`, `entity_id`, `priority`, `is_read`, `read_at`, `dedup_key`, timestamps. Unique `(user_id, dedup_key)` mencegah duplikasi pada retry; ini data transaksi, bukan master.
- `GET /api/notifications` memerlukan `auth:api` dan hanya mengambil milik pengguna aktif. Filter: `module_id`, `is_read`, `search`, `sort_by` (created_at/read_at/priority), `sort_order` (asc/desc), `per_page` (default 15, maksimum 100), `page`. Respons envelope standar dengan `data[]`, `meta`, `filters`, dan relasi `module` (id/name/code/primary_color).
- `GET /api/notifications/unread-count` mengembalikan `data.unread_count`. `POST /api/notifications/{id}/read` mengembalikan notifikasi milik pengguna atau 404; timestamp baca tidak berubah pada pengulangan. `POST /api/notifications/read-all` hanya memperbarui notifikasi pengguna yang belum dibaca dan mengembalikan `data.updated`. Kedua POST tanpa body. Tidak ada permission modul yang diperlukan untuk inbox pribadi.
- Penerima calon SPMB adalah `pendaftaran.user_id`, termasuk pendaftaran manual. Penerima admin ditetapkan melalui mapping IAM di atas. Pemicu: submitted, keputusan verifikasi, hasil yang dipublikasikan, VA aktif terbit/diperbarui, tagihan lunas, serta daftar ulang menunggu pembayaran/lunas. Perubahan data tanpa transisi tidak mengirim ulang. Tagihan sebagian/pending tidak mengirim notifikasi lunas.
- Inbox dicatat dalam transaksi pemicu. Email diantrikan setelah commit ke queue `notifications`; rollback tidak mengirim email. Kegagalan transport email dicatat tanpa membatalkan bisnis. Jalankan worker queue untuk pengiriman email. Hasil bertanggal masa depan dijadwalkan melalui job dan diperiksa ulang sebelum publikasi.
- Penyimpanan ulang hasil lulus pada prodi sama tidak menambah kuota dan mempertahankan status daftar ulang serta waktu publikasi. Perubahan prodi/status merekonsiliasi kuota dalam transaksi dengan lock pendaftaran dan kuota. Prodi tujuan penuh menghasilkan 422 tanpa mengubah hasil/kuota asal.
- Frontend bell mengambil feed sistem dan LMS secara independen, menjumlah unread, mengurutkan preview terbaru, dan menandai baca pada sumber yang tepat. Inbox `/notifikasi` untuk sistem; inbox `/lms/notifikasi` tetap tersedia dengan navigasi antar-inbox. Kegagalan satu sumber tidak menghapus sumber lain.

## Keputusan

Nomor SK kelulusan SPMB hanya diterbitkan melalui alur permohonan dan persetujuan Arsip. SPMB tidak membuat nomor sendiri. Format nomor mengikuti generator Arsip:

`{nomor_urut}/{kode_klasifikasi}/{kode_unit}/{bulan_romawi}/{tahun}`

Konfigurasi template SPMB mengambil referensi modul, klasifikasi, dan unit melalui ID dari master. Admin mengonfigurasi template dengan record unit penerbit `INDO` yang tersedia di master; ID unit pada template menjadi sumber untuk permohonan. Arsip tidak boleh mengganti unit yang diminta ketika menyetujui permohonan. `format_nomor_surat` dipertahankan untuk kompatibilitas data lama, tetapi tidak lagi menentukan nomor SK.

## Konfigurasi Role Pihak Ketiga SPMB

Pengaturan IAM menyimpan `third_party_registration_role` sebagai slug role akun pihak ketiga. Akun yang dibuat dari formulir manual mendapat role ini secara otomatis. Dropdown pihak ketiga hanya mengembalikan akun aktif dengan role tersebut. POST manual menerima `pihak_ketiga_user_ids[]` dan menautkan akun terpilih ke pendaftaran; nama lama tetap disimpan sebagai snapshot.

## Endpoint

### `POST /api/spmb/pendaftaran/{id}/terbitkan-sk`

- Jika belum ada permohonan aktif, buat permohonan nomor melalui Arsip dan kembalikan `200 OK` dengan status `menunggu_verifikasi`. Belum ada nomor final atau PDF pada tahap ini.
- Jika permohonan telah disetujui Arsip, gunakan nomor resmi yang tersimpan di Arsip untuk membuat dan menyimpan PDF, lalu kembalikan `200 OK`.
- Jika template atau referensi master Arsip tidak valid, kembalikan `422 Unprocessable Entity` dan jangan membuat nomor atau PDF.
- Jika permohonan sebelumnya ditolak, pengiriman ulang endpoint ini membuat permohonan baru.
- Jika permohonan sudah disetujui tetapi nomor untuk unit yang diminta tidak ditemukan, endpoint mengembalikan `422` untuk rekonsiliasi oleh Arsip; SPMB tidak membuat permohonan pengganti otomatis.

### `GET /api/spmb/pendaftaran/{id}/sk-lulus`

- Hanya menyajikan PDF yang telah dibuat dengan nomor SK resmi Arsip.
- Selama permohonan belum disetujui atau nomor resmi belum tersedia, kembalikan `409 Conflict`; endpoint tidak membuat nomor internal atau PDF sementara.
