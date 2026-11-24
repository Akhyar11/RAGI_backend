# Matriks Kapabilitas & Checklist QA — Modul LMS RAG

> **Tanggal**: 2026-11-24  
> **Ruang lingkup**: 6 area hardening — quiz/tryout, bank soal, forum, notifikasi, dashboard, pengaturan.  
> **Sumber kebenaran otorisasi**: permission di `PermissionSeeder`/migrasi IAM + Gate/Policy di controller.  
> **Prinsip RBAC**: role ≠ profil. Super Admin/admin adalah kapasitas administratif; **jangan** diberi role `dosen`/`mahasiswa` atau profil akademik otomatis hanya agar fitur LMS bisa dipakai.

---

## 1. Matriks Kapabilitas

Legenda: ✅ tersedia sesuai permission & scope · ⚠️ tersedia hanya dengan scope/kepemilikan eksplisit · 👁 monitoring/audit administratif · ❌ tidak tersedia.

| Area | Dosen pengampu | Kaprodi / Wakil Prodi | Mahasiswa | Admin / Super Admin |
|---|---|---|---|---|
| **Quiz & tryout** | ✅ Buat, publish, atur batch/peserta, reset attempt, koreksi manual, export hasil kelasnya | ✅ Monitoring & kelola lintas kelas prodi; koreksi dengan `siakad.nilai.manage` | ⚠️ Start/batch/autosave/submit hanya attempt sendiri; lihat hasil sendiri, tanpa kunci | 👁 Monitoring agregat + audit export; aksi dosen tetap butuh permission & scope kelas, bukan auto-profile |
| **Bank soal** | ✅ CRUD soal/opsi, import XLSX, template, bulk delete/move pada referensi yang diizinkan | ✅ Kelola bank soal prodi | ❌ Tidak ada endpoint tulis; soal tidak pernah dikirim ke mahasiswa melalui endpoint bank soal | 👁 Audit perubahan/import; konfigurasi referensi melalui permission sistem |
| **Forum diskusi** | ✅ `lms.forum.read/create/manage`; buat topik, moderasi, hapus pesan pada kelas yang diampu | ✅ `lms.forum.read/create/manage` untuk kelas dalam prodi | ✅ `lms.forum.read/create`; edit/hapus **pesan sendiri** via `ForumPostPolicy` | 👁 Audit moderasi; menu Diskusi memakai `lms.forum.read` (bukan `siakad.kelas.read`) |
| **Notifikasi in-app** | ✅ Inbox personal + tandai baca | ✅ Inbox personal + tandai baca | ✅ Inbox personal + tandai baca | ✅ Inbox personal + tandai baca; tidak ada endpoint baca notifikasi orang lain |
| **Dashboard LMS** | ✅ Scope kelas yang diampu + ringkasan pengumpulan/attempt belum dinilai | ✅ Scope seluruh kelas (pengelola) | ✅ Scope KRS valid + tugas belum dikumpulkan & tryout selesai | ✅ Scope seluruh kelas (pengelola), cache 5 menit per user/periode |
| **Pengaturan modul** | ❌ Hanya `GET /pengaturan/modul` bila diberi `lms.pengaturan.read` | ⚠️ `lms.pengaturan.read` bila diberi eksplisit | ❌ Hanya `GET /lms/public/settings` (publik) | ✅ `lms.pengaturan.read/update` termasuk pengaturan `lms_*` |

### Catatan kebocoran data

- Endpoint mahasiswa untuk pengerjaan quiz (`batch`, `autosave`, `submit`) tidak mengembalikan `kunci_jawaban`, `pembahasan`, `is_benar`, `poin_didapat`, maupun nested `bankSoal`/`opsi`.
- Endpoint dosen (`/quiz/{id}/manage`, `/attempt/{id}/detail`, koreksi) tetap dapat mengakses kunci dan pembahasan untuk keperluan mengajar.
- `LmsExportService::FORMATS` tetap `csv|xlsx`. `?format=pdf` ditolak `ExportLmsRequest` dengan 422; laporan PDF memakai route cetak terpisah.

---

## 2. Checklist QA per Role

### 2.1 Dosen Pengampu

- [ ] Login sebagai dosen dengan profil pengampu kelas uji (jangan Tambahkan role Super Admin ke akun dosen).
- [ ] Buka `/lms` → kelas yang diampu tampil; kelas lain tidak tampil.
- [ ] Buat kuis/tryout, tambah soal dari bank soal, publish, lalu buka panel kelola: `kunci_jawaban`, `pembahasan`, dan `is_benar` masih terlihat.
- [ ] Buka Batch soal sebagai mahasiswa pada attempt aktif: tidak ada field kunci/pembahasan/`is_benar`.
- [ ] Submit attempt mahasiswa uji: response hanya berisi `id`, `quiz_id`, `mahasiswa_id`, `attempt_ke`, `status`, `dimulai_at`, `disubmit_at`, `nilai_akhir`, `is_lulus`, `butuh_penilaian_manual`.
- [ ] Koreksi soal uraian: `butuh_penilaian_manual` berubah setelah dosen memberi nilai & feedback; nilai tersinkron OBE bila quiz terhubung komponen penilaian.
- [ ] Forum kelas: buat topik, kirim pesan, edit/hapus pesan sendiri, lalu hapus pesan mahasiswa sebagai moderator; audit `module=LMS` tercatat.
- [ ] Buka dashboard: ringkasan hanya kelas yang diampu; daftar `belum_dinilai` sesuai data uji.
- [ ] Buka notifikasi: hanya notifikasi milik sendiri; klik read-all → unread count 0.
- [ ] Ekspor rekap kelas CSV → file dibuka di Excel Windows tanpa karakter acak (BOM UTF-8), format `pdf` ditolak 422.

### 2.2 Mahasiswa

- [ ] Login sebagai mahasiswa dengan KRS aktif; hanya kelas dari KRS yang tampil di `/lms`.
- [ ] Kerjakan tryout publish: batch soal tidak membocorkan kunci, autosave berhasil, submit menghasilkan nilai.
- [ ] Cari di response submit untuk string `kunci_jawaban`, `pembahasan`, `is_benar`, `bank_soal` → tidak ditemukan.
- [ ] Kirim balasan forum, lalu edit balasan sendiri; tombol edit/hapus tidak muncul untuk pesan orang lain.
- [ ] Menu **Diskusi** muncul karena role memiliki `lms.forum.read`; bukan karena `siakad.kelas.read` saja.
- [ ] Dashboard: `tugas_belum_dikumpul`/`tryout_selesai` sesuai kondisi nyata, jadwal hari ini sesuai KRS.
- [ ] Inbox notifikasi: notifikasi orang lain tidak bisa dibuka (404), read-all hanya menandai milik sendiri.
- [ ] Coba endpoint pengaturan modul & export kelas → 403.

### 2.3 Kaprodi / Wakil Prodi

- [ ] Login dengan role kaprodi/wakil prodi (tanpa auto-role Dosen).
- [ ] Dashboard menampilkan scope seluruh kelas yang diizinkan, bukan hanya kelas yang diampu.
- [ ] Quiz/tryout dan forum lintas kelas prodi dapat dipantau/dikelola sesuai `siakad.kelas.manage` + `lms.forum.manage`.
- [ ] Bank soal prodi dapat dikelola; perubahan tercatat di audit.
- [ ] Coba `PUT /lms/pengaturan/modul` tanpa grant `lms.pengaturan.update` → 403.

### 2.4 Admin / Super Admin

- [ ] Akun admin tidak otomatis memiliki profil dosen/mahasiswa dan tidak otomatis menjadi peserta quiz.
- [ ] Monitoring agregat & audit log LMS dapat dibuka melalui permission admin yang sudah ada.
- [ ] Menu Master Modul/Menu/Permission menjadi sumber kebenaran; tidak ada hardcode daftar modul di FE.
- [ ] `GET/PUT /lms/pengaturan/modul` mengikuti `lms.pengaturan.read/update`; perubahan tercatat.
- [ ] `GET /lms/public/settings` tetap dapat dibuka tanpa Bearer dan hanya berisi key publik.
- [ ] Export/audit: unduh CSV/XLSX memicu `core_audit_logs` `module=LMS`, `action=export`.

---

## 3. Otorisasi & Audit yang Diharapkan

| Aksi | Permission / guard | Audit |
|---|---|---|
| Submit quiz mahasiswa | `siakad.kelas.read` + attempt milik caller | `update` pada `lms_quiz_attempt` |
| Koreksi manual dosen | `siakad.kelas.manage` + scope kelas | `update` pada `lms_quiz_attempt_jawaban` |
| Import/bulk bank soal | permission SIAKAD/OBE yang berlaku | `module=SIAKAD` |
| Moderasi forum | `lms.forum.manage` / `ForumPostPolicy` | `module=LMS`, `action=update/delete` |
| Baca/tandai notifikasi | personal, tanpa permission modul | tidak perlu audit read |
| Ubah pengaturan modul | `lms.pengaturan.update` | `module=LMS`, `action=update` |
| Export CSV/XLSX | `siakad.kelas.manage` + `assertKelasAccess` | `module=LMS`, `action=export` |

---

## 4. Referensi Pengujian Otomatis

```bash
cd backend
php artisan test tests/Feature/SiakadTryoutTest.php \
  tests/Feature/SiakadLmsExportTest.php \
  tests/Feature/SiakadLmsNotifikasiTest.php \
  tests/Feature/SiakadForumTest.php \
  tests/Feature/IAM/MenuVisibilityTest.php \
  tests/Unit/Services/Siakad/LmsNotificationServiceTest.php \
  tests/Unit/Services/Siakad/LmsExportServiceTest.php

cd ../frontend
npx tsc --noEmit
```

Regresi kunci jawaban ada di `SiakadTryoutTest::test_tryout_engine_batch_autosave_submit_dan_obe_sync`.
Konsistensi permission menu Diskusi ada di `MenuVisibilityTest::test_diskusi_menu_permission_matches_forum_api_permission`.
