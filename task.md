# Task: Migrasi Data SIAKAD dari Neo Feeder PDDIKTI

Dibuat: 2026-11-25. Urutan mengikuti dependensi data. Halaman = halaman di
`docs/User-Guide-Web-Service-Neo-Feeder-2.0.0e745e17 (1).pdf`.

> Pemetaan action → tabel di bawah adalah rencana dari nama action dan nama tabel.
> Kolom nyata dan bentuk respons WAJIB diverifikasi sebelum implementasi.

## Sudah selesai (acuan)

| Tabel                          | Isi   | Sumber                         |
| ------------------------------ | ----- | ------------------------------ |
| `siakad_program_studi`       | 14    | sync `siakad_program_studi`  |
| `siakad_tahun_akademik`      | 168   | sync `siakad_tahun_akademik` |
| `siakad_kurikulum`           | 36    | sync `siakad_kurikulum`      |
| `siakad_mata_kuliah`         | 1.183 | sync `siakad_mata_kuliah`    |
| `siakad_dosen`               | 182   | sync `siakad_dosen`          |
| `siakad_mahasiswa`           | 5.994 | sync `siakad_mahasiswa`      |
| `siakad_master_kelas`        | 259   | sync `siakad_kelas` fase 1   |
| `siakad_distribusi_mengajar` | 3.131 | sync `siakad_kelas` fase 2   |

## Keputusan (2026-11-25): `siakad_jadwal_kelas` TIDAK akan diisi

Pemilik proyek memutuskan `siakad_jadwal_kelas` tidak diisi dari Feeder maupun manual. Konsekuensi (diverifikasi dari skema):

| Tabel                      | Kolom                                                | Status                    |
| -------------------------- | ---------------------------------------------------- | ------------------------- |
| `siakad_krs_detail`      | `kelas_id` NOT NULL, FK → `siakad_jadwal_kelas` | Tidak bisa diisi          |
| `siakad_dosen_pengampu`  | `kelas_id` NOT NULL, FK → `siakad_jadwal_kelas` | Tidak bisa diisi          |
| `siakad_nilai_mahasiswa` | `krs_detail_id` NOT NULL → `siakad_krs_detail`  | Tidak bisa diisi (rantai) |
| `siakad_krs` (header)    | hanya mahasiswa + tahun akademik                     | Bisa, tidak butuh kelas   |
| `siakad_khs`             | hanya mahasiswa + tahun akademik (ips, ipk, sks)     | Bisa, tidak butuh kelas   |

Akibatnya tugas 3 DIBATALKAN. Tugas 4 dan 5a dibuka kembali lewat jalur 1 (krs_detail berbasis mata kuliah, `kelas_id` nullable). Dosen pengampu per mata kuliah sudah terwakili
oleh `siakad_distribusi_mengajar` (3.131 baris). Mengubah `kelas_id` menjadi nullable BUKAN opsi yang dipilih
(berdampak ke KRS/LMS); jangan dilakukan tanpa persetujuan baru.

## Aturan umum tiap tugas

Ikuti `.agent/skills/pddikti-sync-standard/SKILL.md`:

- [ ] Daftarkan di `IamApiIntegrationSeeder` (idempoten, kredensial terjaga).
- [ ] Uji Koneksi read-only; sync aktual lewat queue Job (`ShouldQueue`) + lock + progress.
- [ ] Periksa action count (`GetCount...`) secara live; bila tidak ada/timeout, progress indeterminate.
- [ ] Batch tepat 500, persist per batch, batas halaman eksplisit, tidak menghapus data yang tidak hadir.
- [ ] Retry timeout/jaringan (pola `requestWithRetry` di `KelasPddiktiIntegrationSyncService`).
- [ ] Feature test (mock HTTP Feeder), dokumentasi `docs/api/` + `docs/specs/api-contract.md` + `docs/README.md`.
- [ ] Commit normal tanpa `--no-verify`; auditor harus PASS.

---

## 1. Lengkapi pemetaan mata kuliah & prodi (3.109 kelas terlewat)

Dampak: 2.454 baris dosen ikut terlewat; semua tugas lain bergantung padanya.

### Hasil analisis read-only (2026-11-25, `GetListKelasKuliah` 11.935 kelas / 24 halaman)

Lolos 8.826; terlewat 3.109 (satu kelas bisa kena beberapa penyebab, jadi jumlah di bawah tumpang tindih):

| Penyebab                                                       | Kelas | Rincian                                                                                                                                 |
| -------------------------------------------------------------- | ----- | --------------------------------------------------------------------------------------------------------------------------------------- |
| Mata kuliah Feeder tidak ada di lokal                          | 2.838 | 568 mata kuliah berbeda; 78 di antaranya hanya 1 kelas                                                                                  |
| `id_feeder` prodi ganda di lokal                             | 316   | `5e76d67c…` dipakai prodi id 2 (`A` / "Teknologi Rekayasa Otomotif", nonaktif) dan id 14 ("D4 Teknologi Rekayasa Otomotif", aktif) |
| Prodi Feeder tidak ada di lokal                                | 198   | "D3 Teknologi Otomotif" (`67c27953…`)                                                                                                |
| `nama_kelas_kuliah` kosong                                   | 21    | data Feeder tidak lengkap                                                                                                               |
| Semester tidak terpetakan /`id_feeder` matkul-semester ganda | 0     | —                                                                                                                                      |

Mata kuliah hilang per prodi (kelas terdampak): D3 Manajemen Informatika 631, D3 Farmasi 627, D3 Komunikasi Massa 469,
D3 Mesin Otomotif 380, D3 Perhotelan 336, D3 Teknologi Otomotif 199, D3 Sistem Informasi 118, D4 Teknologi Rekayasa Otomotif 48,
D4 TLM 12, D4 Produksi Media 8, D4 MIK 7, D4 TRPL 3.
Sync mata kuliah memakai `GetMatkulKurikulum` (mata kuliah di dalam kurikulum yang sudah ada, 36 kurikulum lokal);
DUGAAN (belum diverifikasi): mata kuliah hilang berasal dari kurikulum lama yang tidak ada di lokal atau tidak terhubung ke kurikulum.

### Temuan cacat sync (2026-11-25, dibuktikan lewat simulasi read-only)

Feeder: 2.132 mata kuliah; lokal 1.183. Penyebab sync membuang data:

- `siakad_mata_kuliah.kode_mk` unik global, padahal Feeder boleh memakai kode sama untuk `id_matkul` berbeda (62 kode lintas prodi; 236 dari 568 mata kuliah hilang terhalang ini).
- Satu mata kuliah hanya boleh satu `kurikulum_id`, padahal `GetMatkulKurikulum` memuat pasangan mata kuliah x kurikulum (63 mata kuliah di >1 kurikulum).
- 285 mata kuliah hanya ada di `GetListMataKuliah` (tidak di kurikulum mana pun) sehingga tidak pernah ditarik (`kurikulum_id` wajib).
- `siakad_program_studi.kode_prodi` unik padahal Feeder punya dua prodi berkode 21403 ("D3 Mesin Otomotif" dan "D3 Teknologi Otomotif").
- Sync memakai KODE sebagai identitas; identitas stabil di Feeder adalah UUID (`id_prodi`, `id_matkul`).

### Migration identitas unik (SUDAH dijalankan di MySQL lokal 2026-11-25; belum di-commit)

`2026_11_25_110000_add_unique_id_feeder_identity_columns`: UNIQUE `id_feeder` pada prodi, mata kuliah, dosen,
mahasiswa (`id_feeder`, `id_feeder_biodata`), jadwal_kelas, dosen_pengampu; duplikat digabung (rujukan dipindah, lalu dihapus).
Tidak unik (referensi): `dosen_penugasan.id_feeder`, `mahasiswa.id_feeder_riwayat`, `feeder_mappings.feeder_id`.
Data nyata saat ini: satu grup duplikat (prodi id 2 `A` nonaktif dengan 191 mahasiswa + 1 kurikulum + 7 dosen, dan id 14 `21304` aktif tanpa rujukan);
hasil yang diharapkan: id 14 bertahan, semua rujukan dipindah ke id 14, `source_id` id 2 dibawa ke id 14, id 2 dihapus.
Tes: `tests/Feature/SiakadFeederIdentityUniqueMigrationTest.php` (7 tes). Regresi: 388 tes SIAKAD/Mobile/IamApi; 48 error + 1 gagal,
semuanya terbukti sudah ada di baseline (constraint `status` `siakad_mahasiswa`; `test_ineligible_duplicate_does_not_block_later_eligible_student_record`).

- [X] Dijalankan di MySQL lokal: prodi id 2 digabung ke id 14 (191 mahasiswa, 1 kurikulum, 7 dosen dipindah; `source_id` dibawa); 13 prodi tersisa; 7 unique index terbentuk. Cadangan baris sebelum migrasi: scratchpad `pre_migration_backup.json`; isi baris yang dihapus juga ada di `storage/logs/laravel.log` ("duplikat digabung").
- [ ] Setelah unique ada: ubah sync prodi/mata kuliah agar mencocokkan lewat `id_feeder` (UUID), bukan kode; longgarkan unique `kode_prodi` dan `kode_mk`.

### Perbaikan sync PDDIKTI prodi & mata kuliah (2026-11-25, KODE SIAP; migration `..._120000` BELUM dijalankan di MySQL)

Ditemukan juga: Feeder mengirim `sks_tatap_muka`/`sks_praktek` = `null` untuk nilai 0 sehingga sync membuang **154 baris** (71 teori + 83 praktik).

- [X] Prodi: identitas = UUID; UUID baru dengan kode terpakai dibuat sebagai prodi baru (`{kode}-{8 UUID}`, kode resmi di `kode_prodi_dikti`).
- [X] Mata kuliah: satu `id_matkul` = satu baris (multi-kurikulum digabung, kurikulum terbaru), kode unik per kurikulum, SKS `null` = 0,
  adopsi baris lama tanpa `id_feeder` pada kurikulum+kode sama, hasil memuat `distinct_courses` dan `multi_curriculum`.
- [X] Migration `2026_11_25_120000_scope_kode_mk_unique_to_kurikulum` (unique global `kode_mk` diganti `(kurikulum_id, kode_mk)`).
- [X] `StoreMataKuliahRequest` dan sync legacy dibatasi per kurikulum. Tes: `PddiktiProdiMataKuliahSyncIdentityTest` (10 tes); regresi 398 tes, sisa 48 error + 1 gagal = baseline.
- [X] Migration `..._120000` dijalankan di MySQL lokal (2026-11-25).
- [X] Dry-run (transaksi di-rollback, DB nyata tidak berubah) dengan Feeder nyata:
  prodi 14 sumber -> 1 dibuat ("D3 Teknologi Otomotif"), 13 diperbarui, 0 dilewati;
  kurikulum 37 -> 1 dibuat ("KURIKULUM TO 2020"), 36 diperbarui, 0 dilewati;
  mata kuliah 1.762 baris / 1.476 `id_matkul` -> 282 dibuat, 1.183 diperbarui, 11 dilewati (kode sama dipakai mata kuliah Feeder lain pada kurikulum yang sama), 266 mata kuliah ada di >1 kurikulum.
  Jumlah mata kuliah 1.183 -> 1.465. Dari 568 mata kuliah yang tadinya hilang, 276 kini terpetakan (1.734 kelas terbuka); 292 masih hilang (1.104 kelas):
  285 tidak ada di kurikulum mana pun + sisa karena kode ganda dalam satu kurikulum.
- [X] Retry timeout/jaringan ditambahkan pada fetch `GetMatkulKurikulum` (percobaan dry-run pertama gagal timeout 120 dtk tanpa retry).
- [X] Sync prodi dan mata kuliah PDDIKTI dipindah ke queue worker (diwajibkan auditor 03): `SyncPddiktiProgramStudiJob`, `SyncPddiktiMataKuliahJob`
  (basis `AbstractSyncPddiktiJob`), trait `QueuesPddiktiSync`, respons `202` + `progress_id`, lock per integrasi (409), `QUEUE_CONNECTION=sync` ditolak (503),
  batch 500 + persist per batch + progress setelah commit, total dari `GetCountProdi` / `GetCountMatkulKurikulum` (diverifikasi live; fallback indeterminate),
  mata kuliah diurut `id_matkul` dengan carry-over di batas batch. UI Pengaturan API sudah generik (polling `progress_id`), tidak diubah.
  Tes: `PddiktiCatalogQueuedSyncTest` (8 tes); regresi 408 tes, sisa 48 error + 1 gagal = baseline.
- [X] Sync ulang lewat UI berurutan: prodi -> kurikulum -> mata kuliah -> kelas. WAJIB restart worker queue (kode job baru) sebelum menekan sync prodi/mata kuliah; kurikulum masih sinkron di request HTTP (kecil, 37 baris).
- [X] DITUNDA (diamkan oleh pemilik, 2026-11-25; tugas 1 dianggap selesai): 285 mata kuliah tanpa kurikulum (hanya di `GetListMataKuliah`). Prodi mata kuliah diturunkan dari `kurikulum->program_studi_id`
  di banyak fitur (OBE, RPS, nilai, akses prodi). Pilihan: (a) `kurikulum_id` nullable + tambah `program_studi_id` pada mata kuliah;
  (b) tempatkan di kurikulum terbaru prodi (mengotori daftar kurikulum). Belum dikerjakan.

### Tindakan

- [X] Daftar mata kuliah/prodi Feeder tanpa padanan lokal (read-only).
- [X] Cek `id_feeder` ganda (prodi: 1; mata kuliah: 0; semester: 0).
- [ ] Prodi ganda: putuskan untuk prodi id 2 (`A`, nonaktif) — kosongkan `id_feeder`, gabung, atau hapus (perubahan data, butuh persetujuan).
- [ ] Prodi "D3 Teknologi Otomotif": jalankan sync prodi (`GetProdi`) atau tambahkan; periksa apakah itu nama baru "D3 Mesin Otomotif" (id 10).
- [ ] Mata kuliah hilang: verifikasi apakah ada di `GetListMataKuliah` tetapi tidak di `GetMatkulKurikulum`/kurikulum lokal; tentukan sumber penarikan.
- [ ] Kelas dengan `nama_kelas_kuliah` kosong (21): tentukan kebijakan (lewati atau beri label).
- [ ] Jalankan ulang sync kelas; target: jumlah skip turun, `assignments_synced` naik.

- **Selesai bila:** sisa skip hanya yang disengaja dan terdokumentasi.

## 2. Skala nilai (KODE SIAP 2026-11-25; migration `..._130000` BELUM dijalankan di MySQL)

- Action: `GetListSkalaNilaiProdi` (hlm. 95), count `GetCountSkalaNilaiProdi` (hlm. 93; diverifikasi live, `data` = "64").
- Target: `siakad_skala_nilai`. Field live: `id_bobot_nilai`, `id_prodi`, `nilai_huruf`, `nilai_indeks`, `bobot_nilai_min`, `bobot_nilai_maks`, tanggal efektif (PDF salah menyebut `bobot_minimum/maksimum`).
- Data Feeder kampus: 64 baris, 12 prodi, semua berlaku; huruf A-E plus `T` (0-0) di 4 prodi.

- [X] Migration: `id_feeder` unik + `tanggal_mulai_efektif`/`tanggal_akhir_efektif`. [x] `SkalaNilaiIntegrationSyncService` + `SyncPddiktiSkalaNilaiJob` (queue, batch 500, persist per batch, grup prodi di batas batch ditahan).
- [X] Registry `siakad_skala_nilai` di seeder (idempoten) dan sudah ter-seed di MySQL lokal (tanpa mengubah setelan koneksi). [x] Controller: enqueue 202, Uji Koneksi read-only, progres.
- [X] Aturan data: rentang bulat dijadikan kontinu hanya bila selisih 1; celah/tumpang tindih dilaporkan sebagai peringatan (tidak ditebak); skala berakhir/versi lama dan huruf berentang kosong (`T`) nonaktif;
  `is_lulus` mengikuti skala standar untuk huruf yang sama saat create; skala standar lokal tidak disentuh.
- [X] Konsumen: `SkalaNilai::aktifUntukProdi` dipakai daftar `skala_nilai` di nilai OBE kelas. Catatan: `DokumenService::mutuHuruf` dan konversi transfer di `PerkuliahanController` mencari bobot menurut huruf tanpa prodi dan mengurutkan baris khusus prodi lebih dulu; saat ini bobot huruf sama di semua prodi sehingga hasilnya sama, tetapi ini rawan bila skala prodi berbeda.
- [X] Tes `PddiktiSkalaNilaiSyncTest` (12 tes). Validasi dengan 64 baris Feeder asli (sqlite sementara): 64 dibuat, 4 nonaktif, 6 peringatan, sync ulang 0 dibuat / 64 diperbarui.

- PERINGATAN DATA FEEDER (perlu dibereskan di Feeder, bukan di SIAKAD): celah rentang 55-59 (D 45-54 lalu C mulai 60) di D3 Farmasi, D3 Komunikasi Massa, D3 Sistem Informasi, D4 Manajemen Informasi Kesehatan;
  E 0-45 berimpit dengan D 45-54 di D3 Farmasi dan D3 Komunikasi Massa. Nilai di celah dikonversi memakai skala standar lokal.

- [ ] Jalankan migration `..._130000` di MySQL lokal; restart worker; jalankan sync skala nilai dari UI (setelah prodi terpetakan).

- **Selesai bila:** skala nilai per prodi tersedia sebelum nilai ditarik (tugas 5).

## 3. ~~Kelas kuliah → `siakad_jadwal_kelas` + `siakad_dosen_pengampu`~~ DIBATALKAN

Alasan: `siakad_jadwal_kelas` tidak diisi (lihat Keputusan). Kebutuhan "siapa mengajar apa" sudah dipenuhi
`siakad_distribusi_mengajar`. `siakad_dosen_penugasan` juga tidak dipakai.

- [X] Tidak ada pekerjaan; jangan menambah sync ke `siakad_jadwal_kelas`/`siakad_dosen_pengampu`.

## 4. KRS historis berbasis mata kuliah (DIBUKA KEMBALI, jalur 1)

Keputusan 2026-11-25: `siakad_jadwal_kelas` tetap tidak diisi. KRS historis disimpan per mata kuliah.
Skema dicatat di `docs/specs/api-contract.md` ("Skema: KRS historis berbasis mata kuliah").

### 4a. Migration + model + pembaca (SELESAI 2026-11-25, belum di-commit)

- [X] Migration aditif: `siakad_krs_detail.kelas_id` nullable; tambah `mata_kuliah_id`, `master_kelas_id` (nullable, FK, index).
- [X] `KrsDetail`: relasi `mataKuliah()`, `masterKelas()`, dan accessor mata kuliah efektif (`kelas?->mataKuliah ?? mataKuliah`).
- [X] Perbaiki pembaca yang rusak/salah bila `kelas` NULL (hasil audit 2026-11-25):

  - [X] `SiakadAkademikService.php:39,70` — `$detail->kelas->mataKuliah->total_sks` TIDAK null-safe: akan error (hitung IPS/IPK).
  - [X] `KrsService.php:128` — cek lulus prasyarat via `whereHas('krsDetail.kelas', matkul)`: riwayat impor tak terlihat.
  - [X] `KrsService.php:354-355` — total SKS dihitung 0 untuk baris tanpa kelas.
  - [X] `PerkuliahanController.php:769,787` dan `DokumenService.php:92-98` — fallback `?? 3` SKS menghasilkan SKS salah.
  - [X] `NilaiKoreksiService.php:171-211` — `mode_penilaian` dari `kelas->tahunAkademik`, fallback `krs->tahunAkademik`; pencarian ikut `krsDetail.mataKuliah`.
  - [X] `MobileMahasiswaService.php:341-346`, `KartuUjianController.php:58-60`, `MahasiswaController.php:126` — kolom MK jadi kosong; pakai mata kuliah efektif.

  - Aman/tidak relevan (berbasis kelas): LMS, Quiz, Forum, Presensi, Notifikasi, Export, NilaiKomponenSync, Tridharma, Payroll, Sippm.
- [X] Tes: baris krs_detail tanpa kelas masuk perhitungan IPS/IPK, transkrip, dan prasyarat; baris dengan kelas tidak berubah.

- Tes: `tests/Feature/SiakadKrsHistorisMataKuliahTest.php` (6 tes). Catatan: 50 tes SIAKAD/Mobile lain GAGAL SEBELUM perubahan ini (CHECK constraint `siakad_mahasiswa.status` menolak `aktif`; tabel `audit_logs` tidak ada di sqlite tes) — dibuktikan identik pada kode baseline; perlu diperbaiki terpisah.

### 4b. Impor KRS dari Feeder (KODE SIAP 2026-11-25; registri `siakad_krs` BELUM di-seed di MySQL)
- Sumber: `GetKRSMahasiswa`, dibaca PER PERIODE (`filter id_periode`, `order id_registrasi_mahasiswa,id_periode,id_matkul,id_kelas` = kunci unik, diverifikasi live), batch 500, maks. 200 batch/periode.
- `GetCountKRSMahasiswa` timeout live (juga dengan filter) -> progres indeterminate + posisi periode di pesan. Ukuran referensi: `GetCountPesertaKelasKuliah` = 286.780 (~575 halaman, ~25 menit).
- [x] `KrsHistorisIntegrationSyncService` + `SyncPddiktiKrsJob`, registri seeder `siakad_krs`, controller (enqueue 202, Uji Koneksi read-only, progres), `requestPddiktiWithRetry` di trait bersama.
- [x] Aturan: KRS per (mahasiswa, TA); detail per (KRS, mata kuliah) dengan `kelas_id` NULL, `mata_kuliah_id`, `master_kelas_id` (rombel hanya dicari); status baru `disetujui` (periode berjalan) / `dikunci` (lampau);
  KRS dan detail yang sudah ada tidak ditimpa; `total_sks_diambil` dihitung dari `total_sks` mata kuliah lokal; `jadwal_kelas` dan `nilai_mahasiswa` tidak disentuh.
- [x] Tes `PddiktiKrsHistorisSyncTest` (16 tes).
- [x] Dry-run data nyata satu periode (20211, transaksi di-rollback): 14.400 baris -> 1.048 KRS + 10.088 detail (100% berombel, rata-rata 19,9 SKS/KRS), 4.312 dilewati (3.874 registrasi mahasiswa tak terpetakan, 438 mata kuliah tak terpetakan), 75 detik.
- TEMUAN (perlu tindakan di sync lain, bukan di KRS):
  - Dari 1.550 registrasi di periode 20211, 435 tidak terpetakan: **315 NIM tidak ada di lokal** (D3 Teknologi Otomotif 133, D3 SI 91, Perhotelan 70, Komunikasi Massa 60, MIK 42, Farmasi 39 untuk keseluruhan 435) -> jalankan ulang **sync mahasiswa**
    (prodi Teknologi Otomotif baru terbentuk setelah sync mahasiswa terakhir; sisanya perlu diperiksa mengapa sync mahasiswa tidak memuatnya).
  - **120 registrasi lama** dari orang yang punya registrasi lain (mis. NIM C21006: D3 Komunikasi Massa 2021 "Peserta didik baru" lalu D4 Produksi Media 2023 "Pindahan Alih Bentuk"). Sync mahasiswa menyimpan satu baris per orang (registrasi terbaru),
    jadi KRS lama sengaja TIDAK ditempel (prodi berbeda pada 120/120 kasus; IPK akan tercampur). Dilaporkan dengan alasan tersendiri. KEPUTUSAN DESAIN bila riwayat registrasi lama ingin ikut: satu baris mahasiswa per registrasi.
  - 438 baris KRS mengacu mata kuliah yang belum terpetakan (antara lain 285 mata kuliah tanpa kurikulum yang ditunda).
- [ ] Seed registri (`IamApiIntegrationSeeder`), restart worker, jalankan sync KRS dari UI (setelah sync mahasiswa ulang). Perkiraan ~25 menit.
- **Selesai bila:** KRS per mahasiswa per semester terbentuk dan jumlah baris per periode cocok dengan Feeder untuk mahasiswa yang terpetakan.

## 5. Nilai dan KHS

### 5a. Nilai per mata kuliah (DIBUKA KEMBALI, setelah 4b)

- Action: `GetDetailNilaiPerkuliahanKelas` (hlm. 91), `GetListNilaiPerkuliahanKelas` (hlm. 95), count `GetCountNilaiPerkuliahanKelas` (hlm. 94),
  `GetRiwayatNilaiMahasiswa` (hlm. 97) — verifikasi field live.
- Target: `siakad_nilai_mahasiswa` (lewat `krs_detail` hasil 4b). Dependensi: skala nilai (tugas 2), 4b.
- Nilai komponen OBE tetap lokal; tidak ditimpa.

### 5b. KHS per semester (DIKERJAKAN, sumber: AKM, bukan nilai)

- Action: `GetListPerkuliahanMahasiswa` (hlm. 110, memuat IPS/IPK/SKS per semester; verifikasi nama field live),
  count `GetCountPerkuliahanMahasiswa` (hlm. 111). Alternatif: `GetRekapKHSMahasiswa` (hlm. 229).
- Target: `siakad_khs` (`mahasiswa_id`, `tahun_akademik_id`, `ips`, `total_sks_semester`, `sks_kumulatif`, `ipk`).
- Dependensi: mahasiswa (`id_registrasi_mahasiswa` → `id_feeder_riwayat`), tahun akademik (`id_feeder`).

- [ ] Verifikasi field AKM yang memuat ips/ipk/sks_semester/sks_total sebelum rancang pemetaan.
- [ ] `is_locked` ditentukan eksplisit (mis. true untuk semester lampau); dokumentasikan.
- [ ] Idempoten per (mahasiswa, tahun akademik); tidak menimpa KHS hasil hitung lokal tanpa aturan.

- **Selesai bila:** KHS per mahasiswa per semester terisi dan IPK sampel cocok dengan Feeder.

## 6. Status akademik dan lulus/DO (AKM untuk KHS ada di 5b)

- Action AKM/status: `GetListPerkuliahanMahasiswa` (hlm. 110), `GetCountPerkuliahanMahasiswa` (hlm. 111) (juga dipakai 5b; satu pembacaan boleh dipakai bersama).
- Action lulus/DO: `GetListMahasiswaLulusDO` (hlm. 195), count `GetCountMahasiswaLulusDO` (hlm. 199).
- Target: `siakad_kelulusan` (lulus/DO); `siakad_status_akademik_log` hanya bila ada perubahan status (kolom `status_lama`/`status_baru`, BUKAN data AKM per semester); evaluasi `siakad_cuti_mahasiswa`.
- Dependensi: mahasiswa, tahun akademik.

- [ ] Jangan mengubah `status` mahasiswa lokal tanpa aturan eksplisit; catat sebagai log.

- **Selesai bila:** riwayat status per semester dan data kelulusan/DO terisi, idempoten.

## 7. Transfer nilai

- Action: `GetNilaiTransferPendidikanMahasiswa` (hlm. 155), count `GetCountNilaiTransferPendidikanMahasiswa` (hlm. 193).
- Target: `siakad_konversi_transfer`, `siakad_konversi_transfer_detail`.
- Dependensi: mahasiswa (registrasi), mata kuliah.

- [ ] Verifikasi struktur header/detail vs satu daftar datar dari Feeder.

- **Selesai bila:** konversi per mahasiswa beserta detail matkul asal/tujuan tersimpan.

## 8. Aktivitas mahasiswa (tugas akhir, PKL, bimbingan, penguji)

- Action: `GetListAktivitasMahasiswa` (hlm. 170), count `GetCountAktivitasMahasiswa` (hlm. 192), `GetListAnggotaAktivitasMahasiswa` (hlm. 172), `GetListBimbingMahasiswa` (hlm. 174), `GetListUjiMahasiswa` (hlm. 179), `GetJenisAktivitasMahasiswa` (hlm. 165).
- Target: `siakad_tugas_akhir`, `siakad_pkl`, `siakad_ta_bimbingan`, `siakad_pkl_bimbingan`, `siakad_ta_penguji`.
- Dependensi: mahasiswa, dosen, jenis aktivitas.

- [ ] Petakan jenis aktivitas ke tabel tujuan (TA vs PKL) lewat ID jenis, bukan string nama.
- [ ] Nilai sidang/penilaian (`siakad_ta_nilai`, `siakad_pkl_penilaian`) cek ketersediaan di Feeder dulu.

- **Selesai bila:** aktivitas TA/PKL beserta pembimbing dan penguji terisi untuk mahasiswa yang terpetakan.

---

## Di luar lingkup saat ini

- Fakultas (`GetFakultas`) dan riwayat pendidikan mahasiswa (prioritas rendah, belum perlu).
- Tabel OBE (CPL, CPMK, RPS, rubrik, dll.): tidak ada di Feeder, diisi lokal.
- `siakad_dosen_penugasan`: tidak dipakai sync distribusi/kelas; UI Feeder Sync "Penugasan PT" masih membingungkan (tidak ada menu kelola).
