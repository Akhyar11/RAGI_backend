# Verifikasi Fase 5 — LMS & Absensi (E2E, Regresi, Storage, Dokumentasi)

> **Tanggal**: 2026-09-29
> **Cakupan**: Modul LMS backend (`LmsController`, `LmsService`), frontend (`/siakad/lms`, `lms.service.ts`), storage R2/lokal, dokumentasi API.

## 1. Uji Alur Penuh (E2E) — `tests/Feature/SiakadLmsE2ETest.php`

4 skenario sesuai plan, **4 passed (46 assertions)**:

| # | Alur | Hasil |
|---|---|---|
| 1 | Dosen input materi + file → Mahasiswa download via `GET download/materi/{id}` (assert `nama_file`, `download_url`, path DB relatif) | ✅ |
| 2 | Generate token → mahasiswa input → absensi `hadir` tersimpan; token salah → 422; token kedaluwarsa → 422 | ✅ |
| 3 | Tugas link OBE → submit → nilai 80 → `siakad_nilai_komponen_mhs` = 80; revisi 95 → OBE overwrite 95; tugas standalone → nilai TIDAK masuk OBE | ✅ |
| 4 | Ajukan izin → approve → absensi otomatis `sakit`; skenario tolak → absensi TIDAK berubah (tidak ada `sakit`/`izin`) | ✅ |

## 2. Regresi Modul SIAKAD Existing

```
SiakadLmsTest + SiakadLmsE2ETest + SiakadObeTest + SiakadMasterReferensiFlowTest
+ SiakadMahasiswaBeasiswaTest + SiakadFeederDosenSyncTest → 24 passed (219 assertions)
```

2 gagal pada filter `Siakad` (`SikeuPembayaranMahasiswaPotonganTest`, `SimpegPegawaiRoleTest`)
adalah **pre-existing, di luar modul LMS/SIAKAD** (BindingResolutionException Sikeu;
validasi `tanggal_masuk`/`shift_template_id` Simpeg) — tidak tersentuh perubahan Fase 5.

## 3. Storage R2 Optimal

- `config/filesystems.php` mendukung disk `r2` (publik) + `r2-private` (privat); env lokal memakai `local` (R2 memang belum dikonfigurasi di lokal — sesuai desain).
- **Perbaikan Fase 5** (`LmsService::resolveDisk`): bila kandidat disk cloud belum dikonfigurasi,
  otomatis fallback ke kandidat berikutnya (setting kelas → `lms_storage_disk` → default).
  Terverifikasi: `setting=r2` + env lokal → `local`; `setting=local` → `local`.
- Upload materi/tugas/surat memakai nama UUID + path relatif `siakad/lms/.../Y/m` (sesuai standar file-upload).
- Download via `temporaryUrl` R2 (30 mnt) / URL lokal, di balik `auth` + Gate `siakad.kelas.read`.

## 4. Dokumentasi & Frontend

- `docs/api/SIAKAD/LmsController.md` (405 baris): 23 endpoint + error + catatan (termasuk perilaku fallback baru); terindeks di `docs/README.md`.
- Frontend: halaman daftar kelas, detail kelas, detail pertemuan + `services/lms.service.ts` mencakup semua alur E2E (nilai, token, izin, kumpul, download).
- **Temuan non-blokir**: tipe param `getDownloadUrl` di frontend (`'materi' | 'tugas' | 'izin'`)
  belum selaras dengan backend (`'materi' | 'pengumpulan'`); fungsi tsb belum dipakai di halaman mana pun,
  selaraskan saat halaman download dikerjakan.
