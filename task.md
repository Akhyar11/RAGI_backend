# Task: Migrasi Data SIAKAD dari Neo Feeder PDDIKTI

Dibuat: 2026-11-25. Urutan mengikuti dependensi data. Halaman = halaman di
`docs/User-Guide-Web-Service-Neo-Feeder-2.0.0e745e17 (1).pdf`.

> Pemetaan action → tabel di bawah adalah rencana dari nama action dan nama tabel.
> Kolom nyata dan bentuk respons WAJIB diverifikasi sebelum implementasi.

## Sudah selesai (acuan)
| Tabel | Isi | Sumber |
|---|---|---|
| `siakad_program_studi` | 14 | sync `siakad_program_studi` |
| `siakad_tahun_akademik` | 168 | sync `siakad_tahun_akademik` |
| `siakad_kurikulum` | 36 | sync `siakad_kurikulum` |
| `siakad_mata_kuliah` | 1.183 | sync `siakad_mata_kuliah` |
| `siakad_dosen` | 182 | sync `siakad_dosen` |
| `siakad_mahasiswa` | 5.994 | sync `siakad_mahasiswa` |
| `siakad_master_kelas` | 259 | sync `siakad_kelas` fase 1 |
| `siakad_distribusi_mengajar` | 3.131 | sync `siakad_kelas` fase 2 |

## Keputusan (2026-11-25): `siakad_jadwal_kelas` TIDAK akan diisi
Pemilik proyek memutuskan `siakad_jadwal_kelas` tidak diisi dari Feeder maupun manual. Konsekuensi (diverifikasi dari skema):

| Tabel | Kolom | Status |
|---|---|---|
| `siakad_krs_detail` | `kelas_id` NOT NULL, FK → `siakad_jadwal_kelas` | Tidak bisa diisi |
| `siakad_dosen_pengampu` | `kelas_id` NOT NULL, FK → `siakad_jadwal_kelas` | Tidak bisa diisi |
| `siakad_nilai_mahasiswa` | `krs_detail_id` NOT NULL → `siakad_krs_detail` | Tidak bisa diisi (rantai) |
| `siakad_krs` (header) | hanya mahasiswa + tahun akademik | Bisa, tidak butuh kelas |
| `siakad_khs` | hanya mahasiswa + tahun akademik (ips, ipk, sks) | Bisa, tidak butuh kelas |

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
- [ ] Daftar `id_matkul` dan `id_prodi` Feeder dari `GetListKelasKuliah` yang tidak punya padanan lokal (read-only).
- [ ] Cek `id_feeder` ganda di `siakad_mata_kuliah` dan `siakad_program_studi` ("terhubung ke lebih dari satu data lokal").
- [ ] Putuskan penyebab: mata kuliah kurikulum lama / prodi di luar 14 / data ganda; lengkapi lewat sync mata kuliah atau perbaikan data.
- [ ] Jalankan ulang sync kelas; target: jumlah skip turun, `assignments_synced` naik.
- **Selesai bila:** sisa skip hanya yang disengaja dan terdokumentasi.

## 2. Skala nilai
- Action: `GetListSkalaNilaiProdi` (hlm. 95), count `GetCountSkalaNilaiProdi` (hlm. 93).
- Target: `siakad_skala_nilai` (kini 9 baris; jangan menimpa data lokal tanpa aturan merge).
- Dependensi: prodi (`id_feeder`).
- [ ] Verifikasi kolom tabel vs field Feeder (bobot, nilai huruf, rentang).
- [ ] Implementasi sync + tes + docs.
- **Selesai bila:** skala nilai per prodi tersedia sebelum nilai ditarik (tugas 5).

## 3. ~~Kelas kuliah → `siakad_jadwal_kelas` + `siakad_dosen_pengampu`~~ DIBATALKAN
Alasan: `siakad_jadwal_kelas` tidak diisi (lihat Keputusan). Kebutuhan "siapa mengajar apa" sudah dipenuhi
`siakad_distribusi_mengajar`. `siakad_dosen_penugasan` juga tidak dipakai.
- [x] Tidak ada pekerjaan; jangan menambah sync ke `siakad_jadwal_kelas`/`siakad_dosen_pengampu`.

## 4. KRS historis berbasis mata kuliah (DIBUKA KEMBALI, jalur 1)
Keputusan 2026-11-25: `siakad_jadwal_kelas` tetap tidak diisi. KRS historis disimpan per mata kuliah.
Skema dicatat di `docs/specs/api-contract.md` ("Skema: KRS historis berbasis mata kuliah").

### 4a. Migration + model + pembaca (SELESAI 2026-11-25, belum di-commit)
- [x] Migration aditif: `siakad_krs_detail.kelas_id` nullable; tambah `mata_kuliah_id`, `master_kelas_id` (nullable, FK, index).
- [x] `KrsDetail`: relasi `mataKuliah()`, `masterKelas()`, dan accessor mata kuliah efektif (`kelas?->mataKuliah ?? mataKuliah`).
- [x] Perbaiki pembaca yang rusak/salah bila `kelas` NULL (hasil audit 2026-11-25):
  - [x] `SiakadAkademikService.php:39,70` — `$detail->kelas->mataKuliah->total_sks` TIDAK null-safe: akan error (hitung IPS/IPK).
  - [x] `KrsService.php:128` — cek lulus prasyarat via `whereHas('krsDetail.kelas', matkul)`: riwayat impor tak terlihat.
  - [x] `KrsService.php:354-355` — total SKS dihitung 0 untuk baris tanpa kelas.
  - [x] `PerkuliahanController.php:769,787` dan `DokumenService.php:92-98` — fallback `?? 3` SKS menghasilkan SKS salah.
  - [x] `NilaiKoreksiService.php:171-211` — `mode_penilaian` dari `kelas->tahunAkademik`, fallback `krs->tahunAkademik`; pencarian ikut `krsDetail.mataKuliah`.
  - [x] `MobileMahasiswaService.php:341-346`, `KartuUjianController.php:58-60`, `MahasiswaController.php:126` — kolom MK jadi kosong; pakai mata kuliah efektif.
  - Aman/tidak relevan (berbasis kelas): LMS, Quiz, Forum, Presensi, Notifikasi, Export, NilaiKomponenSync, Tridharma, Payroll, Sippm.
- [x] Tes: baris krs_detail tanpa kelas masuk perhitungan IPS/IPK, transkrip, dan prasyarat; baris dengan kelas tidak berubah.

- Tes: `tests/Feature/SiakadKrsHistorisMataKuliahTest.php` (6 tes). Catatan: 50 tes SIAKAD/Mobile lain GAGAL SEBELUM perubahan ini (CHECK constraint `siakad_mahasiswa.status` menolak `aktif`; tabel `audit_logs` tidak ada di sqlite tes) — dibuktikan identik pada kode baseline; perlu diperbaiki terpisah.

### 4b. Impor KRS dari Feeder (setelah 4a)
- Action: `GetKRSMahasiswa` (hlm. 160; field: `id_registrasi_mahasiswa`, `id_periode`, `id_prodi`, `id_matkul`, `id_kelas`,
  `nama_kelas_kuliah`, `sks_mata_kuliah`), atau `GetPesertaKelasKuliah` (hlm. 100, count hlm. 101).
- Target: `siakad_krs` (per mahasiswa per tahun akademik) + `siakad_krs_detail` (`mata_kuliah_id`, `master_kelas_id`, `kelas_id` NULL).
- Dependensi: mahasiswa (`id_feeder_riwayat`), tahun akademik (`id_feeder`), mata kuliah (`id_feeder`), Master Kelas (nama + prodi + angkatan).
- [ ] Tentukan `siakad_krs.status` untuk data historis (mis. `dikunci`) dan `total_sks_diambil` = jumlah `sks_mata_kuliah`; dokumentasikan.
- [ ] Baris yang mahasiswa/matkul/periodenya tidak terpetakan dilewati dengan alasan.

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
