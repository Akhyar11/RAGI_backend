<RULE[agent_skills]>
# Agent Directive: Selalu Periksa dan Gunakan Skill yang Relevan

Sebelum mengeksekusi tugas apapun, Anda WAJIB memeriksa daftar skill di bawah ini dan membaca SKILL.md yang relevan menggunakan `view_file` sebelum mulai coding.

## Daftar Skill yang Tersedia

| Skill                           | Path                                                    | Aktifkan Ketika                                                          |
| ------------------------------- | ------------------------------------------------------- | ------------------------------------------------------------------------ |
| `unit-testing`                | `.agent/skills/unit_testing/SKILL.md`                | Diminta membuat atau memperbaiki file test (`.test.php`, `Test.php`) |
| `api-crud-standard`           | `.agent/skills/api_crud_standard/SKILL.md`           | Membuat atau memodifikasi endpoint API CRUD (Controller, Route, Request) |
| `service-layer-pattern`       | `.agent/skills/service_layer_pattern/SKILL.md`       | Membuat Service class atau memindahkan logika dari Controller            |
| `rbac-authorization`          | `.agent/skills/rbac_authorization/SKILL.md`          | Mengimplementasikan pengecekan role/permission, Gate, Policy             |
| `audit-log-standard`          | `.agent/skills/audit_log_standard/SKILL.md`          | Menambahkan pencatatan jejak (audit log) pada aksi user                  |
| `api-error-handling`          | `.agent/skills/api_error_handling/SKILL.md`          | Mengkonfigurasi Exception Handler atau menangani error di API            |
| `file-upload-standard`        | `.agent/skills/file_upload_standard/SKILL.md`        | Mengimplementasikan fitur upload file (dokumen, foto, dll.)              |
| `database-migration-standard` | `.agent/skills/database_migration_standard/SKILL.md` | Membuat atau memodifikasi file migrasi database                          |
| `seeder-standard`             | `.agent/skills/seeder_standard/SKILL.md`             | Membuat atau memodifikasi file Seeder                                    |
| `event-listener-standard`     | `.agent/skills/event_listener_standard/SKILL.md`     | Membuat Event, Listener, atau alur reaktif antar-modul                   |
| `api-documentation`           | `.agent/skills/api_documentation/SKILL.md`           | Membuat Controller baru ATAU memodifikasi endpoint yang sudah ada        |
| `rbac-refactoring-standard`   | `.agent/skills/rbac_refactoring_standard/SKILL.md`   | Melakukan refaktor controller, model, migration, atau merancang pengecekan akses (RBAC) tanpa mengandalkan field statis. |
| `module-management-standard`| `.agent/skills/module_management_standard/SKILL.md`  | Merancang, menambah, atau memodifikasi modul aplikasi (Master Modul) di ekosistem kampus terintegrasi. |
| `audit-fungsional-crud`       | `../RAGIFrontend/.agent/skills/audit_fungsional_crud/SKILL.md` | Diminta menguji, mengaudit, atau memverifikasi fungsi operasional halaman/form CRUD, dropdown data, integrasi API, dan alur bisnis modul. |
| `unfinishedtodo`            | `.agent/skills/unfinishedtodo/SKILL.md`            | Memulai, melanjutkan, atau menutup pekerjaan multi-langkah via `.agent/unfinished_todo/TODO.md` agar tahan terhadap sesi terputus |

## Aturan Wajib

1. **BACA** SKILL.md yang relevan sebelum mulai coding. Jangan asumsikan — baca dulu.
2. **PATUHI SEMUA** ketentuan di dalam SKILL.md tanpa pengecualian.
3. Jika sebuah tugas melibatkan **lebih dari satu skill** (misalnya: membuat API CRUD + unit test-nya), baca **semua** SKILL.md yang relevan terlebih dahulu.
4. **JANGAN** menyimpang dari standar yang sudah ditetapkan di skill tanpa persetujuan eksplisit dari user.
   </RULE[agent_skills]>

<RULE[github_push]>
# Git Push Policy
Agent **DILARANG KERAS** melakukan eksekusi perintah `git push` secara otomatis setelah menyelesaikan tugas atau setelah melakukan commit. Perintah `git push` HANYA boleh dieksekusi jika User memintanya secara eksplisit (misalnya: "push ke github").
</RULE[github_push]>

<RULE[no_hardcode_definition]>
# Zero Hardcode & Dynamic Entity Reference Policy

## Definisi Hardcode
Hardcode adalah suatu metode atau cara pengambilan data, pengiriman data, atau pengaturan data dengan **menyebutkan/mengetik nama atau label string secara langsung** (misalnya menyebutkan `'spmb'`, `'sikeu'`, atau string nama spesifik lainnya) alih-alih merujuk pada identitas entitas database.
Termasuk juga merespons API dengan **enum string statis** (seperti `'REGULER'`, `'KARYAWAN'`) jika data tersebut merujuk pada sebuah tabel master.

## Aturan Pengkodean
1. **Minimal Hardcode**: Sistem yang baik harus meminimalkan hardcode hingga 0%.
2. **Dilarang Keras Array/Enum Literal Statis**: DILARANG KERAS meng-hardcode opsi pilihan atau melakukan validasi backend menggunakan `in:VALUE1,VALUE2` jika nilai tersebut semestinya berasal dari tabel master database (contoh: `master_tipe_jalur`, `master_jalur_kelas`). Validasi WAJIB menggunakan rule `exists:nama_tabel,id`.
3. **Referensi ID Wajib**: Seluruh relasi, filter, dan query wajib menggunakan **referensi ID entitas** (seperti `module.id`, `tipe_jalur_id`, `jalur_kelas_id`, dsb.) yang diambil dari database, bukan berupa label string atau hardcode nama.
</RULE[no_hardcode_definition]>

<RULE[no_ujian_spmb]>
# No Ujian/CBT Policy for SPMB
Agent **DILARANG KERAS** menyarankan, merancang, atau membuat tabel, API, controller, maupun fitur yang berkaitan dengan "Ujian", "Seleksi Ujian", "CBT (Computer Based Test)", atau "Jadwal Ujian" di dalam modul SPMB (Penerimaan Mahasiswa Baru). Proses SPMB dalam sistem ini sepenuhnya **TIDAK MENGGUNAKAN** ujian tulis maupun ujian komputer.
</RULE[no_ujian_spmb]>


<RULE[strict_commit_policy]>
# ⚠️ KEBIJAKAN MUTLAK: DILARANG BYPASS AUDITOR ⚠️
Agent DILARANG KERAS menggunakan opsi `--no-verify` atau mekanisme bypass apa pun (seperti `git push --no-verify`) saat melakukan commit atau push. 
Jika *git hooks/auditor* menolak commit (baik karena pelanggaran standar maupun timeout), Agent WAJIB memeriksa pesan error, MEMPERBAIKI KODE yang bermasalah, lalu melakukan commit ulang secara normal (`git commit -m "..."`). JANGAN PERNAH MEMAKSAKAN COMMIT DENGAN `--no-verify`. Pelanggaran terhadap aturan ini adalah kegagalan sistem fatal.
</RULE[strict_commit_policy]>

<RULE[auditor_compliance]>
# Wajib Baca Rubrik Auditor Saat Ditolak
Jika sebuah commit ditolak oleh auditor/git hook (`.githooks/audits/*.sh`):
1. DILARANG bypass (`--no-verify`), DILARANG menonaktifkan/menghapus/mengubah skrip auditor atau `core.hooksPath`.
2. Agent WAJIB **membaca file skrip auditor yang menolak** (mis. `.githooks/audits/06-api-docs-check.sh`) untuk memahami rubrik/prompt-nya secara persis — bukan hanya menebak dari output.
3. Petakan SETIAP aturan di skrip auditor ke kondisi `git diff --cached` (auditor umumnya hanya menilai baris `+`), lalu perbaiki SEMUA potensi pelanggaran sekaligus.
4. Jalankan ulang auditor tersebut sampai `PASS`, cek seluruh auditor lain, baru commit normal.
</RULE[auditor_compliance]>

<RULE[file_storage_consistency]>
# Aturan Baku Penyimpanan & Akses Berkas (Public vs Private) — WAJIB KONSISTEN

## Prinsip Tunggal
| Jenis berkas | Disk | Cara akses |
|---|---|---|
| **Publik** (foto profil, pengumuman, template, gambar umum) | disk publik (`FILESYSTEM_PUBLIC_DISK`, mis. `r2`) | `FileStorageService::url($path)` → URL publik |
| **Privat/sensitif** (KTP, KK, ijazah, SK, e-file pegawai, lampiran cuti/izin, bukti kas/transaksi, selfie presensi) | disk privat (`FILESYSTEM_PRIVATE_DISK`, mis. `r2-private`) | **Signed URL** saja (otomatis via `FileStorageService::url()`/`signedUrl()`) — **DILARANG** URL storage langsung |

## Aturan Wajib
1. **Upload WAJIB lewat `FileStorageService`**:
   - Privat: `$this->files->store($file, '<baseDir>', private: true)`
   - Publik: `$this->files->store($file, '<baseDir>')`
   - DILARANG menyimpan berkas langsung dengan `Storage::disk(...)->put()` di controller/service.
2. **Ambil URL WAJIB lewat `FileStorageService`**:
   - `FileStorageService::url($path)` otomatis mengembalikan **Signed URL** bila berkas ada di disk privat, dan URL publik bila tidak.
   - Khusus berkas privat, boleh langsung `FileStorageService::signedUrl($path)`.
3. **DILARANG KERAS** memakai URL storage langsung untuk berkas privat:
   - SALAH: `Storage::disk('public')->url($path)`, `Storage::url($path)`, atau `R2_PRIVATE_URL`/`r2.dev` untuk dokumen sensitif.
   - BENAR: `FileStorageService::url($path)` / `signedUrl($path)` → mengarah ke endpoint stream generik `GET /api/files/view?path=…` (bertanda-tangan, berlaku 15 menit).
4. **Endpoint stream generik**: `GET /api/files/view?path=<path relatif>` dilindungi middleware `signed`, mencari berkas di semua disk (`r2-private → r2 → public → local`) sehingga berkas lama (masa migrasi) tetap tampil. Jangan membuat URL storage langsung untuk berkas privat.
5. **Konfigurasi server** (jangan keliru):
   - `FILESYSTEM_PRIVATE_DISK` = disk privat (mis. `r2-private`, bucket terpisah).
   - **JANGAN** mengisi `R2_PRIVATE_URL` dengan domain publik (`pub-….r2.dev`) dan **matikan Public Access** pada bucket privat. Berkas privat hanya boleh diakses via Signed URL.
6. **Path DB selalu relatif** (tanpa domain, tanpa prefix `storage/`), nama file UUID + ekstensi asli. Ekstensi executable (`.php`, `.sh`, `.exe`, dll.) ditolak.
7. Setiap penambahan endpoint pengelola berkas WAJIB disertai dokumentasi API (`docs/api/…`) sesuai Aturan auditor dokumentasi.

## Larangan Ringkas
- ❌ `Storage::disk('public')->url()` untuk dokumen sensitif.
- ❌ Menyimpan berkas privat di bucket publik.
- ❌ Mengekspos URL R2/storage langsung untuk berkas privat.
- ✅ `FileStorageService` sebagai satu-satunya pintu upload & pembuatan URL berkas.
</RULE[file_storage_consistency]>
