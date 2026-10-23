# Analisis Gap & Implementation Plan — Modul LMS
### Porting Fitur `Project/lms` → Modul LMS RAG

| | |
|---|---|
| **Tanggal** | 22 Oktober 2026 |
| **Status** | FINAL v1 — **Fase 0 DONE** ✅ · **Fase 1A (Export Laporan) DONE** ✅ · **Fase 1B (Import/Bulk Bank Soal) DONE** ✅ · **Fase 1C (Integrasi PresensiV2) DONE** ✅ · **Fase 3A (Notifikasi LMS) DONE** ✅ · **Fase 3B (Forum Lanjutan) DONE** ✅ · **Fase 4B (Tryout Lifecycle) DONE** ✅ · **Fase 5 (Dashboard & Pengaturan) DONE** ✅ · Fase 2 (AI Grading — DIHAPUS) & seterusnya pending |
| **Sumber (donor)** | `/Users/it/Project/lms` (Laravel 13.8 API + Next.js 16 web + microservice AI Node) |
| **Target (penerima)** | `/Users/it/Project/RAG` (Laravel 13.8 backend + Next.js 16 frontend) |
| **Ruang lingkup** | Modul LMS RAG (prefix `/api/v1/lms`, 57 route, 14 tabel `lms_*`, 14 halaman FE) |

> **Catatan ruang lingkup (perubahan atas rencana awal)**: **Fase 2 — AI Grading Esai DIHAPUS** atas instruksi user
> (tidak ada tabel/kolom AI grading, tidak ada service, tidak ada toggle FE).
> Semua fase lain tetap sesuai rencana.
>
> **Keputusan teknis yang sudah diambil saat eksekusi:**
>
> | ID | Keputusan |
> |---|---|
> | TD-1 | Permission `lms.kelas.manage` **TIDAK dibuat**. `siakad.kelas.manage` sudah kanonik → check FE yang mati dihapus, bukan permission baru. |
> | TD-2 | Backend `getDownloadUrl` jadi sumber kebenaran: key response `nama_file`, `download_url`, `disk`; tipe FE `'materi' \| 'pengumpulan'`. |
> | TD-3 | Topik default forum dibuat lewat aksi eksplisit idempoten `POST /api/v1/lms/kelas/{kelasId}/forum/umum` (bukan auto-create di GET), dilindungi kolom `slug` + unique `(kelas_id, slug)`. |
> | TD-4 | Definisi menu LMS dipindah ke `database/seeders/IAM/menu_definitions.php` + key baru `is_active` & `role_slugs` (menggantikan blok hardcode "semua menu LMS → dosen + mahasiswa"). |
> | TD-5 | `PermissionSeeder` jadi sumber kebenaran tunggal; grant `siakad.kelas.*`, `siakad.nilai.*`, `lms.forum.*` untuk `kaprodi` & `wakil_prodi` di dalamnya. |
> | TD-7 | Export laporan: satu `LmsExportService` (writer + data builder); format dipilih `?format=csv\|xlsx` (default CSV); CSV memakai BOM UTF-8 + `\r\n`; XLSX kolom angka ditulis numerik; setiap export dicatat di `core_audit_logs` (`module=LMS`, `action=export`). |
> | TD-22 | Mapping skema donor → RAG: `LmsPresensiSesi` → `siakad_pertemuan`; `LmsPresensiRekap` → `siakad_absensi_mahasiswa`; `lms_kelas.siakad_xid_kls` → `siakad_kelas.kode_kelas`; `id_ptk` → `siakad_dosen.nidn`; `lms_kelas_dosen` → `siakad_dosen_pengampu`; `lms_kelas_mahasiswa` → KRS (`siakad_krs` + `siakad_krs_detail`). |
> | TD-23 | RAG **tidak** membuat tabel `lms_presensi_sesi`/`lms_presensi_rekap`; cukup tabel `lms_presensi_sync_log` + kolom sync (`sync_status`, `sync_error`, `synced_at`, `id_jurnal_presensiv2`) pada `siakad_pertemuan` & `siakad_absensi_mahasiswa`. |
> | TD-24 | `sync_status` adalah *closed-set domain* dinormalisasi di trait `App\Models\Concerns\HasPresensiSyncStatus`: `local` (default), `pending`, `synced`, `failed`. Default `local` (bukan `pending`) agar perintah retry tidak mencoba ribuan record yang memang tidak pernah diintegrasikan. |
> | TD-25 | Integrasi bersifat **opt-in & fail-safe**: `PresensiV2SyncService::isConfigured()` false bila `PRESENSI_API_URL` kosong **dan** koneksi `presensiv2` tidak dikonfigurasi → seluruh aksi sync berstatus "skipped" tanpa mengubah data lokal. |
> | TD-26 | Transport `auto\|api\|db`. `api` → `POST {PRESENSI_API_URL}/api/sync.php` header `X-Api-Key`, body `action` ∈ `buka_jurnal\|hadir\|hapus\|reset\|tarik`. `db` → koneksi MySQL `presensiv2`, tabel `presensi_jurnal_perkuliahan` (kunci `id_jurnal`) & `presensi_rekap` (hadir = row ada, **tidak ada** kolom `status`). |
> | TD-27 | Sumber `id_ptk` dikonfigurabel via `PRESENSI_ID_PTK_SOURCE` (`nidn` default \| `nip`); `id_smt` = `siakad_tahun_akademik.kode`; `id_prodi` = `siakad_program_studi.kode_prodi`; `ruang` = `siakad_kelas.ruangan.nama` atau `PRESENSI_RUANG_DEFAULT`. |
> | TD-28 | Feature flag disimpan di `core_system_settings` key `lms_fitur_presensi` (default aktif), dibaca via `App\Models\Lms\LmsSetting::fiturPresensiAktif()` (cache 120 detik). Non-admin → `403` saat flag off; admin (pemegang `lms.presensi.manage`) tetap boleh. |
> | TD-29 | Penentuan mahasiswa terdaftar pada kelas untuk tarik riwayat memakai `siakad_krs_detail` JOIN `siakad_krs` → `siakad_krs.mahasiswa_id` (model `Mahasiswa` **tidak** punya relasi `krsDetails`). |
> | TD-41 | Notifikasi LMS **disimpan** pada tabel `lms_notifikasi` (in-app inbox), bukan dihitung on-the-fly seperti donor — supaya status baca konsisten antar perangkat. `core_users` **tidak** punya kolom notifikasi (sudah diverifikasi), jadi tidak ada duplikasi dengan mekanisme global. |
> | TD-42 | Notifikasi bersifat **personal** (per `user_id`) → endpoint **tidak** memakai permission modul terpisah; setiap user hanya bisa baca & menandai notifikasi miliknya sendiri (notifikasi orang lain → `404`). |
> | TD-43 | Pengingat deadline bersifat **idempoten** lewat kolom `dedup_key` (unique per user) berisi `deadline_tugas:{id}:{YYYYMMDD}` / `deadline_quiz:{id}:{YYYYMMDD}` → command harian aman dijalankan berkali-kali. Kanal outbound (email/WA) **opsional** (`config/lms.php`, default nonaktif) dan memakai `CampusNotificationService` yang sudah ada — tanpa membuat kanal baru. |
> | TD-44 | Skema `lms_notifikasi`: `jenis` closed-set (50), `prioritas` ∈ `rendah\|normal\|tinggi`, `rute` (rute FE), `kelas_id` nullable (`nullOnDelete`), `entitas_type`/`entitas_id` polymorphic, `is_read` + `read_at`, `dedup_key` (191, nullable) dengan unique `(user_id, dedup_key)`; index `(user_id,is_read)`, `(user_id,created_at)`, `(entitas_type,entitas_id)`. `dedup_key` **disembunyikan** dari response (`$hidden`). |
> | TD-45 | Endpoint notifikasi: `GET /lms/notifikasi` (filter `search`, `jenis`, `is_read`, `sort_by`, `sort_order`, `per_page`), `GET /lms/notifikasi/unread-count`, `POST /lms/notifikasi/read-all`, `POST /lms/notifikasi/{id}/read`. |
> | TD-46 | Penerima pengingat tugas = mahasiswa kelas yang **belum** punya `PengumpulanTugas`; pengingat kuis = peserta kelas (`tipe=kuis`) / `lms_tryout_peserta` (`tipe=tryout`) dikurangi attempt berstatus `selesai`. Hanya tugas/kuis `is_published = true` (kuis juga `is_archived = false`). |
> | TD-47 | Menu LMS `/lms/notifikasi` ("Notifikasi", icon `FaBell`, permission `siakad.kelas.read`, order_index 8, semua role LMS) — ditambahkan di `menu_definitions.php`; `'FaBell'` dipetakan ke ikon Lucide `Bell` di `Sidebar.tsx`. |
> | TD-48 | FE: lonceng navbar (`NotifikasiBell.tsx`) melakukan polling 60 detik + badge unread, sedangkan halaman `/lms/notifikasi` memakai Filter Drawer + `DataTable` (tanpa tombol Refresh / search inline) dan aksi baris lewat `DropdownMenu`. |
> | TD-49 | FE halaman `/lms/notifikasi` hanya menampilkan 2 `StatCard` dari data nyata (`meta.total` & endpoint `unread-count`) — tidak mengarang statistik. `EMPTY_META` = `{ current_page: 1, per_page: 15, total: 0, last_page: 1 }` (tanpa `from`/`to`). Filter status baca memakai konstanta `LMS_NOTIFIKASI_READ_OPTIONS` (`'false'`/`'true'`) yang dikonversi ke `is_read?: boolean` saat memanggil service. |
> | TD-50 | Penanda pesan forum "diedit" memakai atribut terhitung `is_edited` (= `updated_at > created_at`) pada `ForumPost` — tanpa kolom baru. Konsekuensi: edit pada detik yang sama dengan pembuatan (presisi detik) tidak tertandai. |
> | TD-51 | Hak edit pesan = pemilik pesan (`lms.forum.create`) **atau** pengelola kelas (`lms.forum.manage`), selaras dengan hak hapus. Route `PUT /forum-post/{postId}` sengaja tanpa middleware `can:` agar pemilik pesan tidak butuh `manage`; otorisasi di `ForumPostPolicy::update()`. |
> | TD-52 | `PUT /forum/{topikId}` hanya mengubah `judul` + `is_pinned`; `pertemuan_id` tidak dapat dipindah lewat endpoint ini (menghindari topik berpindah kelas/pertemuan tanpa validasi konteks). |
> | TD-53 | Rename topik & edit pesan dicatat ke `core_audit_logs` (`module=LMS`, `action=update`) dengan `old_values` snapshot sebelum update, dibungkus try/catch agar kegagalan audit tidak menggagalkan request. |
> | TD-54 | Tryout lifecycle **tidak** menambah kolom `tipe_peserta`: peserta tetap gabungan mahasiswa KRS kelas + `lms_tryout_peserta` eksplisit yang dihitung runtime (satu sumber kebenaran, tanpa state ganda). Hanya `passing_score` yang ditambah ke `lms_quiz` (decimal 5,2 nullable, skala 0–100). |
> | TD-55 | Status kelulusan **tidak disimpan**: `Quiz::isLulus()` + accessor `QuizAttempt::is_lulus` menghitung `nilai_akhir >= passing_score` saat serialisasi. `null` = ambang belum ditetapkan/belum dinilai. Mengubah ambang langsung berlaku untuk attempt lama. |
> | TD-56 | `POST /lms/quiz/{quizId}/peserta-copy` hanya mengizinkan sumber & tujuan bertipe `tryout` pada **kelas yang sama**, idempoten (`added`/`skipped`), dan mencatat audit `create` pada `lms_tryout_peserta`. |
> | TD-57 | `POST /lms/quiz/{quizId}/soal/bulk-delete` bersifat atomik: seluruh ID wajib milik quiz path (422 bila ada yang tidak cocok), terkunci bila attempt sudah ada, dan audit `delete` mencatat snapshot `old_values`. `GET /lms/mahasiswa/search` memakai permission `siakad.kelas.manage` dengan `q` parsial NIM/nama (maks. 50) sebagai sumber `AsyncSelect` peserta. |
> | TD-58 | Dashboard LMS: satu endpoint `GET /lms/dashboard/overview` dengan split role ditentukan dari profil (mahasiswa → `mahasiswa`, dosen → `dosen`, lainnya → `pengelola`), scoping kelas memakai `resolveAccessibleKelasIds`, cache 5 menit per user. Tren 7 hari dihitung di PHP (bukan fungsi tanggal SQL) agar portabel SQLite/MySQL. |
> | TD-59 | Pengaturan level modul LMS dikelola `LmsModuleSettingService` (definisi terpusat: label/tipe/min/max/default/public). Nilai disimpan sebagai string pada `core_system_settings` (tabel existing), di-cast saat dibaca. Permission baru `lms.pengaturan.read/manage` hanya untuk admin/superadmin; perubahan diaudit (`module=LMS`, `action=update`, `core_system_settings`). |
> | TD-60 | `GET /lms/public/settings` sengaja berada di file route terpisah (`routes/lms_public.php`, tanpa `auth:api`) karena seluruh `routes/lms.php` berada di grup auth. Hanya key `public: true` yang diekspos (batas upload + feature flag), cache 24 jam dan di-invalidate saat pengaturan disimpan. Status kelulusan tetap tidak termasuk data publik. |

---

## 1. Ringkasan Eksekutif

### 1.1 Metodologi

1. **Inventarisasi penuh donor** — `Project/lms/api` (22 migrasi, 29 tabel `lms_*`, ~110 endpoint, 15 controller, 5 service, 2 command), `Project/lms/web` (4 route page + tab role), `Project/lms/api-pencocokan-jawabab-isai` (microservice AI).
2. **Inventarisasi penuh target** — `RAG/backend` (16 migrasi LMS, 14 tabel `lms_*`, 57 route, 3 controller, 3 service, 2 policy, 23 FormRequest, 6 file test), `RAG/frontend` (14 halaman, 17 komponen, `lms.service.ts` 63 method, `lms.types.ts` 687 baris).
3. **Mapping domain-per-domain** dengan status: `✅ Lengkap` · `🟡 Parsial` · `❌ Tidak Ada` · `⛔ Tidak Relevan` (sudah diselesaikan oleh arsitektur RAG).
4. **Verifikasi manual** terhadap source code (bukan hanya rely pada dokumen) untuk setiap klaim gap.

### 1.2 Angka Ringkas

| Metrik | `Project/lms` (donor) | RAG LMS (target) | Catatan |
|---|---|---|---|
| Tabel domain | 28 (`lms_*` self-contained) | 14 `lms_*` + reuse 9 tabel `siakad_*` | Donor duplikasi; target memakai FK ke SIAKAD native ✅ |
| Endpoint | ~110 | 57 | Gap utama ada di **reporting/export**, bukan di CRUD inti |
| Controller | 15 | 3 (1.872 baris) | Target sudah mengonsolidasikan (service layer) |
| Service | 5 (+2 command) | 3 (88 method) | |
| Halaman FE | 4 route (mega-dashboard, tab-based) | 14 route | Target **lebih baik** (deep-link, RBAC per halaman) |
| Auth | Sanctum + login sendiri + Google OAuth | Passport + SSO IAM | ⛔ Target sudah superior |
| Master data | Sync dari SIAKAD via REST + SSE | Native di DB yang sama | ⛔ Tidak perlu diporting |
| Storage | S3/MinIO + proxy Range sendiri | R2 + `FileStorageService` + signed URL | ⛔ Mostly solved, ⚠️ Range perlu verifikasi |
| OBE | REST (`get_cpmk.php`) + tulis DB langsung | Native `siakad_obe_*` + `ObeController` | ⛔ Mostly solved, 🟡 ada yang perlu双向 sync |

### 1.3 Verdict

> **Modul LMS di RAG sudah ~70% setara secara fungsional inti** — CRUD meet, materi, tugas, absensi token, izin, quiz/tryout, forum, bank soal, ketercapaian OBE semuanya **sudah ada dan matang**. Yang **belum ada** secara spesifik:
>
> 1. **Integrasi PresensiV2** (donor sudah read-only mirror ke PresensiV2 dengan retry) — **P0**
> 2. **AI grading esai** (cosine similarity + mikroservice embedding) — **P0**
> 3. **Export/Import laporan & bank soal** (CSV/XLSX) — **P0**
> 4. **Notifikasi & deadline reminder** — **P1**
> 5. **Forum lanjutan** (edit, balasan bertingkat, @"mention") — **P1**
> 6. **Bulk operation bank soal + import XLSX** — **P1**
> 7. **Dashboard analitik LMS + halaman pengaturan modul** — **P2**
> 8. **Penyempurnaan tryout** (copy peserta, publish lifecycle, passing score) — **P1/P2**
>
> Plus **12 item tech debt** di RAG yang sebaiknya dibereskan lebih dulu (Fase 0).

---

## 2. Perbedaan Arsitektur Fundamental

Ini poin **paling penting** sebelum porting: ketiga area berikut di donor **tidak boleh** di-port mentah-metah karena RAG sudah menyelesaikannya dengan cara yang lebih baik.

| Aspek | `Project/lms` (donor) | RAG (target) | Dampak Porting |
|---|---|---|---|
| **Master data** | Milik sendiri (`lms_users`, `lms_kelas`, `lms_kelas_mahasiswa`, `lms_kelas_dosen`) + sync dari SIAKAD REST via SSE (`SiakadSyncService`, `SyncController`, `siakad:sync` cmd) + pruning KRS | SIAKAD native di DB yang sama (`siakad_kelas`, `siakad_dosen_pengampu`, `siakad_krs_detail`) | ⛔ **Jangan port.** Justru perlu *cleanup*: donor menyimpan user/role sendiri — RAG memakai `core_users` + `core_roles` |
| **Autentikasi** | Sanctum + `AuthController` (login NIM/NIDN, admin-login terpisah, Google OAuth via Socialite) | Passport + SSO IAM (`SsoController`, `core_users`, `core_roles`, `core_permissions`, `Gate::before`) + 2FA | ⛔ **Jangan port.** Donor's `lms_users` + `role` string = anti-pattern RBAC di RAG |
| **Storage** | S3/MinIO sendiri + `StorageProxyController` (Range 206 manual, 8KB chunk) | R2 + `FileStorageService` (signed URL, disk privat/publik terpisah, `GET /api/files/view`) | ⛔ Jangan buat proxy baru. ⚠️ Verifikasi Range di `FileStreamController` |
| **OBE** | REST read (`get_cpmk.php`, `get_sesi.php` + `X-Api-Key`) + tulis **langsung ke DB OBE** (`mengajar/makul/rps/sesi/cpmk/dosen/mhs/nilai_cpmk_detail`) | Native `siakad_obe_*` + `ObeController` + `syncNilaiKeOBE()` | 🟡 **Partial port**: yang perlu di-port adalah *Two-way sync Pertemuan↔RPS Mingguan*, bukan mechanism-nya |
| **Presensi** | Milik sendiri (`lms_presensi_sesi`, `lms_presensi_rekap`) → **read-only mirror** PresensiV2 (dual transport `api|db`) | Token sendiri + `siakad_absensi_mahasiswa` | 🔴 **Gap utama.** RAG belum punya integrasi PresensiV2 sama sekali |
| **Standar proyek** | Tidak ada AGENTS.md,(api CRUD seen adhoc) | `AGENTS.md` + 17 skill wajib + auditor git hook | ⚠️ Semua kode baru **wajib** follow skill RAG |

### 2.1 Konsekuensi untuk Skema Data

 Donor memakai `lms_quiz_soal` **menyalin** soal (duplikasi isi). RAG memakai `lms_quiz_soal.bank_soal_id` → **FK live** ke `siakad_bank_soal` (sudah benar & lebih baik, lihat `2026_10_05_050000_create_lms_quiz_tables.php`).

> ⚠️ **Jangan** port `lms_quiz_soal.pertanyaan/gambar/poin` standalone — akan merusak model referensi-live RAG.
> ✅ Yang perlu di-port dari donor adalah **fitur** (AI grading flag, batch, publish, dsb.), bukan **struktur**.

---

## 3. Matriks Gap Per Domain

| # | Domain | Donor | RAG | Status | Prioritas | Fase |
|---|---|---|---|---|---|---|
| A | **Presensi → PresensiV2** | `PresensivSyncService` (917 baris), dual `api\|db`, jurnal+rekap, retry | Token + bulk manual + tutup + izin | 🔴 **Hilang** | **P0** | 1 |
| B | **AI grading esai** | `calculateCosineSimilarity` + `finalizeAttempt` (`scale`/`threshold`) + mikroservice USE/ChromaDB | Manual penuh | 🔴 **Hilang** | **P0** | 2 |
| C | **Export laporan** | CSV: tugas, quiz, presensi rekap (BOM) | — | 🔴 **Hilang** | **P0** | 1 |
| C2 | **Import XLSX bank soal** | `SoalImportService` + template + bulk delete/move | — (CRUD di `ObeController`) | 🔴 **Hilang** | **P0** | 1 |
| D | **Notifikasi deadline** | `NotificationController@index` | — | 🔴 **Hilang** | P1 | 3 |
| E | **Forum lanjutan** | edit/delete topik, `channels`/`subtopics` | create/read/delete, 1 level | 🟡 Parsial | P1 | 3 |
| F | **Tryout lifecycle** | publish/archive, copy peserta, bulk hapus soal, `passing_score`, `tipe_peserta`, AI grade | `tipe`, `kode_akses`, `is_archived`, peserta | 🟡 Parsial | P1 | 4 |
| G | **Dashboard analitik LMS** | `LmsDashboardController@getOverviewStats` + admin console (api/tryout/presensi/system/health) | `/lms` = daftar kelas saja | 🔴 **Hilang** | P2 | 5 |
| H | **Pengaturan modul LMS** | `lms_settings` 14 key + admin CRUD + logo + public settings + cache | 9 key `lms_*` di `core_system_settings` (tanpa halaman LMS khusus) | 🟡 Parsial | P2 | 5 |
| I | **Feature flag** | `fitur_presensi` (cache 120s, gate 403 non-admin) | — | 🔴 **Hilang** | P1 | 1 |
| J | **Video/materi** | `link_url`, `file_name`, video embed, Range streaming | `tipe_konten_id` master referensi (teks/file/link/video_embed) ✅, `MateriFile` ✅ | 🟡 Parsial (minor) | P3 | 6 |
| K | **OBE ↔ RPS two-way** | `syncToObe` + baca topic RPS per pertemuan | `siakad_rps_mingguan` native, `syncNilaiKeOBE` | 🟡 Parsial | P1 | 4 |
| L | **SIAKAD master sync** | REST + SSE + KRS pruning | Native DB | ⛔ N/A | — | — |
| M | **Auth / user / role** | `lms_users`, Sanctum, Google OAuth | Passport + SSO IAM | ⛔ N/A (lebih baik) | — | — |
| N | **Storage** | Proxy Range sendiri | `FileStorageService` signed URL | ⛔ N/A / ⚠️ verifikasi Range | P3 | 6 |
| O | **Ketercapaian CPMK** | `getKetercapaian` + tab progress | `ketercapaian` + tab mahasiswa ✅ | ✅ Lengkap | — | — |
| P | **Kolaborator dosen** | — | `lms_quiz_kolaborator` (pengawas/pemantau/penginput_soal) | ✅ Lebih baik | — | — |
| Q | **Anti-cheat quiz** | batch 10, hide `is_benar`, timer server, autosave, publish gate | ✅ Semua ada (batch_size, `effectiveBatchSize`, hide kunci, `dibuka_at`/`ditutup_at`, autosave) | ✅ Lengkap | — | — |

---

## 4. Detail Gap & Desain Solusi

### 4.A 🔴 P0-1 — Integrasi PresensiV2

**Kondisi donor**
- `api/app/Services/PresensivSyncService.php` (917 baris) — dual transport via `config/lms_integration.php` `presensi.mode = auto|api|db`:
  - `api` → REST ke app PresensiV2
  - `db` → koneksi MySQL `presensiv2` (read-write)
- Sync **jurnal** (sesi) + **rekap** (kehadiran mahasiswa), retry on failure, bookkeeping ke `lms_sync_log`.
- `api/app/Console/Commands/RetryPresensiSync.php` + schedule `->hourly()->withoutOverlapping()`.
- `PresensiController::ensureAbsenViaPresensiV2()` → **403 non-admin** di semua write endpoint.
- Feature flag `fitur_presensi` → `LmsSetting::fiturPresensiAktif()` (cache 120s), non-admin dapat 403 saat flag off.
- `PresensiDevController` — rekap JSON + **export CSV 13 kolom dengan UTF-8 BOM**.

**Kondisi RAG**
- Absensi token 6-digit (`lms_tugas`…`: GenerateTokenRequest`, `InputTokenAbsensiRequest`), TTL + rotate dari `lms_token_ttl_minutes`/`lms_token_rotate_seconds`.
- `bulk-absensi` manual, `tutup-presensi` manual + `lms:tutup-presensi-otomatis` (hourly).
- `lms_izin_absensi` +approve dosen.
- **Tidak ada** koneksi ke PresensiV2, **tidak ada** retry sync, **tidak ada** flag, **tidak ada** export.

**Desain solusi RAG**

| Item | Specifikasi |
|---|---|
| Config | `config/lms_integration.php` → blok `presensi`: `mode` (`auto`/`api`/`db`), `api_url`, `api_key`, `timeout`, `db_connection`, `jurnal_table`, `rekap_table`, `id_ptk_source`, `ruang_default` (semua via `env()`) |
| Koneksi DB | Koneksi `presensiv2` ditambahkan di `config/database.php` (RW) |
| Service | `app/Services/Siakad/PresensiV2SyncService.php` — `syncJurnal()`, `syncKehadiran()`, `syncAbsensi()`, `hapusAbsensi()`, `resetRekap()`, `tarikRiwayat()`, `status()` |
| Service (panel) | `app/Services/Siakad/LmsPresensiService.php` — `status()`, `daftarKelas()`, `rekap()`, `barisRekap()`, `logSinkronisasi()`, `syncUlang()`, `tarikRiwayat()` |
| Model/tabel | Tabel `lms_presensi_sync_log` + kolom sync (`sync_status`, `sync_error`, `synced_at`, `id_jurnal_presensiv2`) pada `siakad_pertemuan` & `siakad_absensi_mahasiswa` (migrasi `2026_10_22_000002`) |
| Command | `app/Console/Commands/LmsRetryPresensiSync.php` → `lms:retry-presensi-sync --limit=100 --dry-run` |
| Schedule | `routes/console.php` → `Schedule::command('lms:retry-presensi-sync')->hourly()->withoutOverlapping()` |
| Flag | Key `lms_fitur_presensi` di `core_system_settings` (default aktif) + `App\Models\Lms\LmsSetting::fiturPresensiAktif()` (cache 120 detik) |
| Gate | `LmsService::assertPresensiFiturAktif()` → 403 bila flag off & bukan-holder `lms.presensi.manage` |
| Controller | `app/Http/Controllers/API/Siakad/LmsPresensiController.php` — permission `lms.presensi.read` (baca) & `lms.presensi.manage` (tulis/export) |
| Endpoint | `GET /lms/admin/presensi/status`, `/kelas`, `/rekap`, `/log`, `/export` + `POST /lms/admin/presensi/sync`, `/tarik` |
| Menu | `/lms/presensi` ("Presensi & Integrasi") — `role_slugs`: `superadmin`, `admin` |

**Perhatian**: jangan buat tabel `lms_presensi_sesi`/`lms_presensi_rekap` — RAG memakai `siakad_pertemuan` + `siakad_absensi_mahasiswa` yang sudah ada.

**Status**: ✅ **SELESAI (Fase 1C)** — seluruh item di atas terimplementasi, terdokumentasi di
[`docs/api/LMS/LmsPresensiController.md`](api/LMS/LmsPresensiController.md), dan teruji pada
`tests/Feature/SiakadLmsPresensiTest.php` (14 test). Integrasi bersifat **fail-safe**: seluruh
alur presensi LMS tetap berfungsi normal ketika PresensiV2 belum dikonfigurasi.

---

### 4.B 🔴 P0-2 — AI Grading Esai

**Kondisi donor**
- Kolom di `lms_bank_soal` & `lms_tryout_soal`: `grade_by_ai` (bool), `ai_grading_method` (`scale|threshold`), `ai_similarity_threshold` (0–1, default 0.70).
- `TryoutController::calculateCosineSimilarity()` — **bag-of-words frequency cosine similarity** (纯 PHP, tanpa dependency).
- `TryoutController::finalizeAttempt()` — `scale` → partial credit = similarity × poin; `threshold` → biner di atas ambang.
- Mikroservice terpisah `api-pencocokan-jawabab-isai/` — Universal Sentence Encoder (TensorFlow.js, **offline** via `local_model_v2/`) + ChromaDB + MySQL `hasil_penilaian`. Endpoint `POST /api/cek-jawaban`, `GET /api/riwayat`.

**Kondisi RAG**
- `QuizService::submit` → auto-grade hanya pilihan ganda; esai/uraian → `butuh_penilaian_manual = true` → `QuizController@beriNilaiManual` (dosen input manual + `feedback_dosen`).

**Desain solusi RAG**

| Fase | Cakupan |
|---|---|
| 2A (P0) | Kolom `grade_by_ai`, `ai_grading_method`, `ai_similarity_threshold` di `siakad_bank_soal` (migrasi). **Service** `app/Services/Siakad/AiGradingService.php` dengan `cosineSimilarity()` (port dari donor,算法 sama) + `grade()` (mode `scale`/`threshold`). Integrasi di `QuizService::submit()` —填空 soal bernilai `sim × poin`, tandai `butuh_penilaian_manual` hanya bila similarity di bawah ambang. |
| 2B (P1) | Kontrak adapter ke mikroservice: config `lms.ai_grading.service_url` + `AiGradingService::gradeViaService()` (fallback ke cosine bila service mati). Tabel `lms_ai_grading_log`. |
| 2C (P2) | Embedding offline (USE) bilajieksplorasi — **rekomendasi: skip**, gunakan provider eksternal / cosine dulu. |

**Catatan**: `ai_grading_method` adalah *closed-set domain* (bukan entitas master) → boleh `enum`/`const` terpusat sesuai aturan `no_hardcode_definition`. Pastikan nilainya sinkron dengan kolom DB.

---

### 4.C 🔴 P0-3 — Export & Import

**Kondisi donor**

| Jenis | File | Detail |
|---|---|---|
| Export CSV | `TugasController@exportSubmissions` | `fputcsv`, submissions tugas |
| Export CSV | `QuizController@exportResults` | `fputcsv`, hasil quiz |
| Export CSV | `PresensiDevController@export` | 13 kolom + **UTF-8 BOM** (Excel Windows) |
| Import XLSX | `SoalImportService` | Parser + generator template, shared: bank soal / quiz / tryout |
| Template | `LmsBankSoalController@downloadTemplate` | `GET /bank-soal/import/template` |
| Limit | `lms_settings.quiz_max_soal_import` | 500 soal per import |

**Kondisi RAG**
- **Export LMS: ✅ SUDAH SELESAI (Fase 1B, 2026-10-22)** — `LmsExportService` + 6 endpoint export (lihat tabel di bawah).
- **Import/Bulk Bank Soal: ✅ SUDAH SELESAI (Fase 1B, 2026-10-22)** — `SoalImportService` + 6 endpoint baru di `/api/v1/siakad/obe/soal` (lihat tabel di bawah). FE: modal import + bulk action bar di `/lms/bank-soal`.

**Desain solusi RAG** — leverage `phpoffice/phpspreadsheet ^5.10` (sudah ada) + `exceljs`/`xlsx` (sudah ada di FE)

| Endpoint baru | Method | Keterangan |
|---|---|---|
| `/api/v1/lms/kelas/{kelasId}/export/nilai` | GET | Rekap nilai (tugas + quiz) per mahasiswa |
| `/api/v1/lms/kelas/{kelasId}/export/presensi` | GET | Rekap absensi (13 kolom) CSV + BOM |
| `/api/v1/lms/kelas/{kelasId}/export/absensi-izin` | GET | Rekap izin |
| `/api/v1/lms/quiz/{quizId}/export/hasil` | GET | Hasil quiz per mahasiswa |
| `/api/v1/lms/tugas/{tugasId}/export/pengumpulan` | GET | Status & nilai pengumpulan |
| `/api/v1/lms/tryout/{quizId}/export/hasil` | GET | Hasil tryout |
| `/api/v1/siakad/obe/soal/import` | POST | Import XLSX bank soal |
| `/api/v1/siakad/obe/soal/import/template` | GET | Template XLSX |
| `/api/v1/siakad/obe/soal/bulk-delete` | POST | Bulk delete (dengan konfirmasi) |
| `/api/v1/siakad/obe/soal/bulk-move` | POST | Bulk pindah kategori |
| `PUT /api/v1/siakad/obe/soal/{id}` | PUT | **Update soal** (saat ini tidak ada!) |

> ⚠️ **Perhatikan**: endpoint bank soal berada di prefix `/api/v1/siakad/obe/*` (milik modul SIAKAD), bukan `/api/v1/lms/*`. Pertahankan lokasi ini agar tidak menduplikasi master.

**Service**: `app/Services/Siakad/LmsExportService.php` (CSV/XLSX writer terpusat, semua output pakai BOM + `\r\n`).
**Service**: `app/Services/Siakad/SoalImportService.php` (port dari donor, sesuaikan ke `siakad_bank_soal`).

---

### 4.D 🟡 P1-1 — Notifikasi & Deadline Reminder

**Kondisi donor**: `NotificationController@index` → `GET /api/v1/notifikasi/deadline` — agregasi deadline tugas + quiz mendekati batas.

**Kondisi RAG**: tidak ada tabel/API notifikasi **in-app** LMS. Yang ada: `app/Services/Notification/CampusNotificationService.php` — **outbound only** (WhatsApp via Fonnte + Email, masih mode simulasi/log, tanpa penyimpanan inbox).

**Desain solusi RAG**

```
Tabel: lms_notifikasi  (in-app inbox)  — migrasi 2026_10_22_000003_create_lms_notifikasi_table.php
  id, user_id (core_users), jenis (deadline_tugas|deadline_quiz|tugas_baru|quiz_baru|forum|info),
  judul, pesan, rute (route FE), kelas_id (siakad_kelas), entitas_type, entitas_id,
  prioritas (rendah|normal|tinggi), is_read, read_at, dedup_key, timestamps
  index(user_id, is_read), index(user_id, created_at), unique(user_id, dedup_key)
```

- Service `LmsNotificationService` — `index()`, `unreadCount()`, `markAsRead()`, `markAllAsRead()`, `push()`, `pushMany()`, `pushDeadlineTugas()`, `pushDeadlineQuiz()`.
- **Reuse kanal outbound**: panggil `CampusNotificationService::sendEmail()` / `sendWhatsApp()` (static) untuk kanal email/WA — **jangan** buat kanal baru. Kanal bersifat opsional (`config/lms.php` → `lms.notifikasi.kirim_email` / `kirim_whatsapp`, default nonaktif). In-app inbox tetap butuh tabel sendiri karena `CampusNotificationService` tidak menyimpan state.
- Trigger: approaching deadline → scheduled command harian `lms:notifikasi-deadline` (07:00). Pengingat tugas hanya untuk mahasiswa yang **belum mengumpulkan**; pengingat kuis hanya untuk peserta yang **belum menyelesaikan** (`lms_quiz_attempt.status = 'selesai'`).
- Endpoint `GET /api/v1/lms/notifikasi`, `GET /api/v1/lms/notifikasi/unread-count`, `POST /api/v1/lms/notifikasi/{id}/read`, `POST /api/v1/lms/notifikasi/read-all`.
- FE: `NotifikasiBell.tsx` di navbar + halaman `/lms/notifikasi`.

> **Cek juga (sudah diverifikasi)**: `core_users` **tidak** punya kolom/relasi notifikasi (mis. `unread_notifications_count`) → tidak ada duplikasi dengan mekanisme global, tabel baru `lms_notifikasi` memang dibutuhkan.

---

### 4.E 🟡 P1-2 — Forum Lanjutan

| Fitur | Donor | RAG | Rencana |
|---|---|---|---|
| Topik: create | ✅ | ✅ | — |
| Topik: edit/rename | ✅ (implied by model) | ❌ | Tambah `PUT /forum/{topikId}` |
| Topik: delete | ✅ | ✅ | — |
| Topik: pin | ❌ | ✅ (`is_pinned`) | ✅ RAG lebih baik |
| Post: create | ✅ | ✅ | — |
| Post: edit | ❌ | ❌ | Tambah `PUT /forum/post/{postId}` (penulis sendiri / `lms.forum.manage`) |
| Post: delete | ❌ | ✅ | — |
| **Balasan >1 level** | ❌ (donor juga 1 level!) | ❌ | ⚠️ Keduanya sama. **Rekomendasi: skip** (kompleksitas tinggi, nilai rendah) — atau ubah `parent_id` jadi `reply_to_post_id` + satu level reply-to-reply opsional |
| `@mention` | ❌ | ❌ | P2, bisa ditumpukan di notifikasi (4.D) |
| Lampiran file | ❌ | ❌ | P2 |
| Reaksi/like | ❌ | ❌ | P3 |
| Auto-create "Diskusi Umum" | ✅ | ✅ | — |

**Tech debt terkait (lihat 5)**: `ForumController@listTopik` (GET) **menulis data** (auto-create topik) → race condition saat banyak tab. Fix: jadikan auto-create hanya di `storeTopik`/first-write, atau pakai `firstOrCreate` + unique constraint.

---

### 4.F 🟡 P1-3 — Tryout Lifecycle

| Fitur | Donor | RAG | Rencana |
|---|---|---|---|
| Tipe kuis/tryout | ✅ | ✅ (`tipe`) | — |
| Jendela buka/tutup | `mulai_at`/`selesai_at` | `dibuka_at`/`ditutup_at` ✅ | — |
| Durasi (menit) | ✅ | ✅ (`durasi_menit`) | — |
| Max attempt | ✅ | ✅ | — |
| Publish | ✅ `is_published` | ✅ | — |
| Archive | ✅ `status` | ✅ `is_archived` | — |
| **Passing score** | ✅ `passing_score` | ❌ | Tambah kolom + tampilkan status Lulus/Tidak Lulus |
| **Copy peserta** dari tryout lain | ✅ `copyPeserta` | ❌ | Tambah `POST /quiz/{quizId}/peserta-copy` |
| **Bulk hapus soal** | ✅ `bulkRemoveSoal` | ❌ | Tambah `POST /quiz/{quizId}/soal/bulk-delete` |
| Peserta per kelas | ❌ | ✅ `addPesertaByKelas` | ✅ RAG lebih baik |
| Kode akses | ❌ | ✅ `kode_akses` | ✅ RAG lebih baik |
| AI grading | ✅ | ❌ | Lihat 4.B |
| Timer server-side | ✅ | ✅ (`dimulai_at`) | — |
| Enroll dari search user | ✅ `searchUsers` | ⚠️ tidak ada endpoint search | Tambah `GET /lms/mahasiswa/search?q=` (reuse `siakad_mahasiswa`) |

---

### 4.G 🟡 P2-1 — Dashboard Analitik LMS

**Kondisi donor**: `LmsDashboardController@getOverviewStats` → `GET /api/v1/dashboard/overview`, split role:
- Mahasiswa: jumlah kelas, tryout aktif, kuis tersedia
- Dosen/Admin: jumlah kelas, tryout, bank soal

Plus admin console dengan tab `api | tryout | presensi | system | health` (cek koneksi DB, statistik sync).

**Kondisi RAG**: `/lms` hanya daftar kelas. `/lms/[kelasId]` punya tab rekap & ketercapaian (sudah baik).

**Desain**
- `GET /api/v1/lms/dashboard/overview` — split role (mhs/dosen/admin),uzzle cache 5 menit.
- Halaman FE `/lms/dashboard` — kartu statistik + grafik tren (per重用 chart yang sudah ada di repo bila ada; kalau tidak, pakai CSS bar sederhana agar tidak menambah dependency).
- Health check: reuse `GET /api/v1/siakad/...` yang sudah ada bila tersedia.

---

### 4.H 🟡 P2-2 — Pengaturan Modul LMS & Feature Flag

**Kondisi donor**: tabel `lms_settings` (14 key) + CRUD admin + upload logo + `GET /api/v1/public/settings` (cache 24h, `Cache-Control: public, max-age=120`).

**Kondisi RAG**: 9 key `lms_*` di `core_system_settings` (`SystemSettingController`), **tanpa** halaman pengaturan khusus LMS.

**Desain**
- Halaman FE `/lms/pengaturan/modul` (permission `lms.kelas.manage` / `siakad.kelas.manage`).
- Backend: `GET/PUT /api/v1/lms/pengaturan/modul` yang membaca/menulis key berawalan `lms_` di `core_system_settings` (jangan buat tabel `lms_settings` baru).
- Tambahkan key yang hilang dari donor: `lms_max_soal_import`, `lms_upload_max_size_mb`, `lms_video_max_size_mb`, `lms_fitur_presensi`, `lms_system_name`, `lms_system_logo`.
- `GET /api/v1/lms/public/settings` — endpoint publik untuk branding (cache 24h), ditembak `useSettings()` di FE.

---

### 4.I 🟡 P1-4 — Two-way Sync Pertemuan ↔ RPS Mingguan

**Kondisi donor**
- `LmsDashboardController::getMeetings` → baca OBE (`mengajar→makul→rps→sesi`) untuk **topic asli per pertemuan** (cache 10 menit).
- `LmsDashboardController::syncToObe` → tulis topic pertemuan ke OBE (`rps`, `sesi`, `cpmk`, `nilai_cpmk_detail`).
- `ObeNilaiSyncService` → push nilai tugas & quiz ke OBE.

**Kondisi RAG**
- `siakad_rps_mingguan` native (ada migrasi `2026_10_10_040000_add_jenis_pertemuan_and_sub_cpmk_ids_to_rps_mingguan`).
- `LmsService::syncNilaiKeOBE()` ✅ sudah ada.
- **Belum ada**: push/pull antara `siakad_pertemuan` (LMS) dan `siakad_rps_mingguan` (OBE).

**Desain**
- Service `RpsSyncService` (di modul SIAKAD, bukan LMS — karena RPS milik SIAKAD): `pushLmsKeRps()`, `pullRpsKeLms()`.
- Kolom linking: `siakad_pertemuan.rps_mingguan_id` (nullable, FK).
- Gate: hanya `siakad.kurikulum.manage` / OBE admin (bukan dosen biasa) untuk sync massal; dosen boleh syncMeeting individual.

> **Penting**: Putuskan **sumber kebenaran** — RPS Mingguan (OBE) adalah master, LMS adalah mirror. Jika RAG/Fase berikutnya akan menyatukan OBE ke RAG, cukup syn单向.

---

### 4.J 🟡 P3 — Materi & Video

**Kondisi donor**: `lms_materi.link_url`, `file_name`, `file_size`; tipe `file|link|materi`; video player dengan seek; `StorageProxyController` Range 206.
**Kondisi RAG**: `MateriPertemuan` + `MateriFile` + `tipe_konten_id` master referensi (teks/file/link_eksternal/video_embed) ✅; link eksternal ✅.

**Rencana (P3)**
1. Verifikasi `FileStreamController` → `FileStorageService::streamInlineAny()` memakai `Storage::disk()->response()` yang **sudah mendukung Range** via `BinaryFileResponse`; uji seek video 500MB. Bila R2 tidak mendukung range pada `response()`, tambahkan fallback `readStream` + header `Accept-Ranges`/`Content-Range`.
2. `file_name` asli pada `lms_materi_file` — RAG sudah punya `nama_file` + `nama_file_asli` di pengumpulan ✅; ensure `MateriFile` punya `nama_file_asli` bila belum.
3. Download endpoint: RAG sudah ada `GET /lms/download/{type}/{id}` ✅ (perbaiki tipe mismatch — lihat tech debt #2).

---

## 5. Tech Debt RAG (Pra-rekondisi — Fase 0)

| # | Temuan | Lokasi | Perbaikan |
|---|---|---|---|
| TD-1 | Permission `lms.kelas.manage` di-check FE tapi **tidak pernah dibuat** di DB → dead branch | `frontend/app/(main)/lms/[kelasId]/page.tsx:122`, `.../pertemuan/[pertemuanId]/page.tsx:141` | ✅ **SELESAI** — permission **tidak dibuat**; `siakad.kelas.manage` sudah kanonik → check FE yang mati dihapus. **Penting**: `PermissionSeeder` adalah sumber kebenaran (bukan migrasi) — lihat TD-5 |
| TD-2 | `getDownloadUrl` tidak selaras **di 2 aspek**: (a) tipe — FE `'materi'\|'tugas'\|'izin'` vs BE hanya menerima `'materi'\|'pengumpulan'` (`'tugas'`/`'izin'` → `ValidationException`); (b) **nama field response** — FEektipe `{ url, file_name }` vs BE mengembalikan `{ nama_file, download_url, disk }` | `frontend/services/lms.service.ts:280` vs `backend/app/Services/Siakad/LmsService.php@getDownloadUrl` | Selaraskan tipe FE → `'materi'\|'pengumpulan'`; samakan nama field (rekomendasi: **samakan FE ke BE** — `nama_file`, `download_url`, `disk`); atau vice versa via kontrak bersama |
| TD-3 | `GET /lms/kelas/{id}/forum` **menulis** (auto-create topik) → race condition | `backend/app/Http/Controllers/API/Siakad/ForumController.php@listTopik` | Auto-create hanya saat POST, atau `firstOrCreate` + unique index |
| TD-4 | Menu LMS dibuat via **migrasi**, tapi `MenuSeeder` melakukan `Menu::truncate()` → menu hilang saat re-seed | `backend/database/migrations/2026_10_05_080000_*`, `2026_10_07_020000_*` | Pindahkan definisi menu LMS ke `menu_definitions.php` + `MenuSeeder` (idempotent) |
| TD-5 | Dua sumber kebenaran permission (`PermissionSeeder` + migrasi `2026_10_05_073000`) | `backend/database/seeders/PermissionSeeder.php`, `migrations/2026_10_05_073000_*` | Jadikan `PermissionSeeder` tunggal; migrasi cukup `insert-if-missing` |
| TD-6 | Typo kapitalisasi role: `'admin_siAkad'.toLowerCase()` | `frontend/app/(main)/lms/[kelasId]/page.tsx:122` | `'admin_siakad'` |
| TD-7 | Tidak ada `lms/layout.tsx` (modul lain punya) | `frontend/app/(main)/lms/` | Buat layout dengan tema `var(--module-primary)` + breadcrumb |
| TD-8 | `/lms` tidak ada di `baseAllowed` → akses bergantung penuh pada mapping menu role | `frontend/app/(main)/layout.tsx` | Tambahkan `/lms` ke `baseAllowed` |
| TD-9 | Menu `/lms/pertemuan` nonaktif tapi halaman tetap ada | `migrations/2026_10_06_090000_lms_alur_baru.php` | Tentukan: aktifkan kembali atau hapus halaman |
| TD-10 | Tidak ada endpoint `GET /lms/kelas/{id}/setting` (hanya `PUT`) | `backend/routes/lms.php` | Tambah GET agar halaman edit tidak perlu round-trip |
| TD-11 | Mahasiswa tidak bisa update/unsubmit pengumpulan tugas | `backend/routes/lms.php` | Tambah `PUT/DELETE /lms/tugas/{tugasId}/kumpulan` (hanya sebelum dinilai) |
| TD-12 | Tidak ada dokumentasi API Bank Soal di `docs/api/LMS/` | `backend/docs/api/LMS/` | Lengkapi di Fase 1 (skill `api-documentation`) |

> ⚠️ Semua perbaikan TD wajib mengikuti skill `rbac-authorization`, `api-crud-standard`, `seeder-standard`, dan **tidak boleh** memakai `--no-verify` saat commit (ada git-hook auditor).

---

## 6. Implementation Plan

Estimasi dalam **hari kerja** (1 dev). Jalur kritis ditandai ⛔.

### Fase 0 — Rekondisi & Tech Debt (2–3 hari) ⛔ *prasyarat semua fase* — ✅ **DONE**

**Objective**: Hilangkan tech debt & samakan standar agar fase berikutnya bersih.

| Deliverable | Detail |
|---|---|
| BE | TD-1, TD-2, TD-3, TD-5, TD-10, TD-11 ✅ |
| FE | TD-1, TD-6, TD-7, TD-8 ✅ |
| SEEDER | TD-4, TD-5 — pindahkan menu LMS ke `menu_definitions.php` ✅ |
| TEST | Regression: `php artisan test --filter="SiakadLms\|SiakadForum\|SiakadTryout\|SiakadBankSoal"` → 48 test hijau ✅ |
| DOC | Update `docs/api/LMS/*.md` bila ada perubahan endpoint ✅ |

**Acceptance**: seluruh test LMS existing hijau; `MenuSeeder` tidak menghapus menu LMS; permission `lms.kelas.manage` ada di DB bila diputuskan ada.
→ **Terpenuhi**, kecuali tidak ada permission `lms.kelas.manage` (keputusan **TD-1**: tidak dibuat; `siakad.kelas.manage` yang dipakai).

---

### Fase 1 — PresensiV2 + Export/Import (5–7 hari) ⛔ *P0* — ✅ **1A/1B/1C SELESAI**

**Objective**: Tutup gap P0-A dan P0-C.

**1A — Integrasi PresensiV2** (3 hari) — ✅ **SELESAI**
- [x] `config/lms_integration.php` → blok `presensi`
- [x] `config/database.php` → koneksi `presensiv2`
- [x] Migrasi `lms_presensi_sync_log` + kolom sync status di `siakad_pertemuan` & `siakad_absensi_mahasiswa`
- [x] `app/Services/Siakad/PresensiV2SyncService.php` (dual transport `api`/`db`, mode `auto`)
- [x] `app/Services/Siakad/LmsPresensiService.php` (panel administrasi: status, kelas, rekap, log, sinkron, tarik)
- [x] `app/Console/Commands/LmsRetryPresensiSync.php` + schedule hourly (`--limit`, `--dry-run`)
- [x] `LmsService::assertPresensiFiturAktif()` + flag `lms_fitur_presensi` (cache 120 detik)
- [x] `GET /lms/admin/presensi/status|kelas|rekap|log|export` + `POST /lms/admin/presensi/sync|tarik`
- [x] Permission `lms.presensi.read` / `lms.presensi.manage` + menu `/lms/presensi`
- [x] FE: `/lms/presensi` — panel status integrasi + rekap + tombol sinkron ulang/tarik riwayat + export
- [x] Docs: `docs/api/LMS/LmsPresensiController.md` + indeks `docs/README.md`
- [x] Test: `tests/Feature/SiakadLmsPresensiTest.php` (14 test)

**1B — Export & Import** (3–4 hari) — ✅ **SELESAI** (export laporan + import/bulk bank soal)
- [x] `app/Services/Siakad/LmsExportService.php` (CSV + XLSX writer, BOM) — plus data builder `rekapNilaiKelas`, `rekapPresensiKelas`, `rekapIzinKelas`, `hasilQuiz`, `pengumpulanTugas`
- [x] `app/Services/Siakad/SoalImportService.php` (port dari donor, adaptasi `siakad_bank_soal`) — parser XLSX/XLS/CSV + generator template + `importRows()` bertransaksi
- [x] Endpoint export (6 buah, tabel 4.C) — ✅ selesai
- [x] Endpoint import/template bank soal + bulk delete/move + `PUT /obe/soal/{id}` — ✅ `GET /soal/{id}`, `PUT /soal/{id}`, `GET /soal/import/template`, `POST /soal/import`, `POST /soal/bulk-delete`, `POST /soal/bulk-move`
- [x] FE: tombol export di halaman rekap/tryout/nilai — ✅ `ExportLaporanModal` (kelas/pertemuan/tryout)
- [x] FE: dialog import di `/lms/bank-soal` + download template — ✅ `ImportSoalModal` + `BulkMoveSoalModal` + bulk action bar
- [x] Audit log (`module = LMS`) untuk aksi export — ✅ `action=export`; import/bulk bank soal → `core_audit_logs` module `SIAKAD`

**Acceptance**
- Export CSV presensi dibuka di Excel Windows tanpa Character Acak (BOM ✅)
- Import 100 soal XLSX berhasil, 1 soal salah → laporan baris error
- `lms:retry-presensi-sync` idempotent, tidak duplikasi rekap ✅
- Alur presensi LMS tetap normal saat PresensiV2 belum dikonfigurasi (fail-safe) ✅
- Test feature: `SiakadLmsExportTest.php` (10 test) + `SiakadLmsPresensiTest.php` (14 test) ✅

---

### Fase 2 — AI Grading Esai (4–5 hari) ⛔ *P0*

| Deliverable | Detail |
|---|---|
| Migrasi | `siakad_bank_soal` += `grade_by_ai` (bool default false), `ai_grading_method` (enum `scale\|threshold`), `ai_similarity_threshold` (decimal 3 default 0.70) |
| Service | `app/Services/Siakad/AiGradingService.php` — `cosineSimilarity()`, `grade()`, `gradeViaService()` (adapter opsional) |
| Integrasi | `QuizService::submit()` → auto-grade esai bila flag aktif; `butuh_penilaian_manual` hanya bila di bawah ambang |
| Feedback | `lms_quiz_attempt_jawaban.feedback_dosen` auto: "Dinilai otomatis (similarity 0.82)" |
| FE | `bank-soal/BankSoalForm.tsx` → toggle AI grading + slider threshold + pilih metode |
| FE | `QuizManagePanel` → daftar soal yang perlu penilaian manual (filter) |
| Test | `AiGradingServiceTest.php` (fixture similarity) + `SiakadLmsE2ETest` alur esai auto-grade |

**Acceptance**: esai dengan jawaban identik → similarity ≥ 0.9 → auto full credit; jawaban nyasar → 0 poin & ditandai manual.

---

### Fase 3 — Notifikasi + Forum Lanjutan (4–5 hari)

**3A — Notifikasi** (2–3 hari) — **DONE** ✅
- [x] Migrasi `lms_notifikasi` — ✅ `2026_10_22_000003_create_lms_notifikasi_table.php` (TD-41)
- [x] `LmsNotificationService` — ✅ `app/Services/Siakad/LmsNotificationService.php`
- [x] `GET /lms/notifikasi`, `POST /lms/notifikasi/{id}/read`, `POST /lms/notifikasi/read-all` — ✅ + `GET /lms/notifikasi/unread-count` (TD-42)
- [x] Command `lms:notifikasi-deadline` (scheduled harian 07:00) → deadline tugas & quiz
  - Sumber data: `lms_tugas.deadline_at` + `lms_tugas.is_published` (filter `deadline_at` antara now dan now+H) dan `lms_quiz.ditutup_at` + `lms_quiz.is_published` (`is_archived = false`)
  - Idempoten via `dedup_key` (TD-43); kanal email/WA opsional via `config/lms.php`
- [x] FE: `NotifikasiBell.tsx` + `/lms/notifikasi`
- [x] Dokumentasi API — ✅ `docs/api/LMS/LmsNotifikasiController.md`
- [x] Test — ✅ `tests/Feature/SiakadLmsNotifikasiTest.php` (11 test)

**3B — Forum** (2 hari) — **DONE** ✅
- [x] `PUT /lms/forum/{topikId}` (rename + pin) — ✅ `ForumController::updateTopik` + `UpdateForumTopikRequest` + `ForumTopikPolicy::update`
- [x] `PUT /lms/forum-post/{postId}` (edit post) — ✅ `ForumController::updatePost` + `UpdateForumPostRequest` + `ForumPostPolicy::update`
- [x] Perbaiki TD-3 (auto-create topik tidak lagi di GET) — ✅ selesai di Fase 0
- [x] FE: tombol edit + modal rename; tampilkan "diedit" + timestamp — ✅ `ForumChat.tsx` (modal edit + penanda `is_edited`) & `KelasForumTab.tsx` (modal rename topik)
- [x] Dokumentasi API — ✅ `docs/api/LMS/ForumController.md` (2 endpoint baru, ability policy, `is_edited`)
- [x] Test — ✅ `tests/Feature/SiakadForumTest.php` (+2 test: rename topik & edit pesan, total 10 test / 107 assertions)

**Acceptance**: tugas deadline H-1 memunculkan notifikasi di hari yang sama ✅ (3A — command `lms:notifikasi-deadline` harian 07:00, idempoten via `dedup_key`; notifikasi mahasiswa yang sudah mengumpulkan otomatis dilewati); edit forum tercatat di audit log ✅ (3B — `core_audit_logs` action `update` untuk `lms_forum_topik` & `lms_forum_post`, terverifikasi test).

---

### Fase 4 — Bank Soal & Tryout (4–5 hari)

**4A — Bank Soal** (2–3 hari)
- [x] Bulk delete / bulk move (dengan konfirmasi & audit log) — ✅ `POST /obe/soal/bulk-delete` & `/bulk-move` + bulk action bar FE
- [x] Import XLSX + template (terpasang di Fase 1B — ini"& UI-nya") — ✅ `SoalImportService` + `GET /obe/soal/import/template` + `ImportSoalModal`
- [ ] Halaman `/lms/bank-soal`: filter kategori ✅, bulk action bar ✅, tampilan CPMK/tingkat kesulitan (tingkat kesulitan ✅, CPMK pending)
- [x] Dokumentasi API — ✅ `docs/api/SIAKAD/ObeController.md` (TD-12 tetap_closed: bank soal berada di modul SIAKAD, bukan `docs/api/LMS/`)

**4B — Tryout** (2 hari) — **DONE** ✅
- [x] Kolom `passing_score` di `lms_quiz` — ✅ migration `2026_10_23_000001` (TD-54; `tipe_peserta` sengaja tidak ditambah, lihat TD-54)
- [x] `POST /lms/quiz/{quizId}/peserta-copy` (copy dari tryout lain) — ✅ `QuizController::copyPeserta` (TD-56)
- [x] `POST /lms/quiz/{quizId}/soal/bulk-delete` — ✅ `QuizController::bulkDeleteSoal` (TD-57)
- [x] `GET /lms/mahasiswa/search?q=` (pencarian peserta) — ✅ `QuizController::searchMahasiswa` (TD-57)
- [x] FE: tab "Salin Peserta", input passing score, badge Lulus/Tidak Lulus di `TryoutNilaiTab` — ✅ `SalinPesertaForm` (Peserta tab), input + badge ambang (Nilai tab), kolom kelulusan (Hasil tab)
- [x] Dokumentasi API — ✅ `docs/api/LMS/QuizController.md` (3 endpoint baru + `passing_score`/`is_lulus`)
- [x] Test — ✅ `tests/Feature/SiakadTryoutTest.php` (+2 test: passing score & salin/bulk/cari; total 5 test / 74 assertions)

---

### Fase 5 — Dashboard & Pengaturan (3–4 hari) — **DONE** ✅

- [x] `GET /lms/dashboard/overview` (split role, cache 5 menit) — ✅ `LmsService::dashboardOverview` (TD-58)
- [x] FE `/lms/dashboard` — kartu statistik + grafik tren — ✅ `app/(main)/lms/dashboard/page.tsx` (5 StatCard + tren 7 hari, tanpa library chart)
- [x] `GET/PUT /lms/pengaturan/modul` (key `lms_*` di `core_system_settings`) — ✅ `LmsModuleSettingService` + `UpdatePengaturanLmsRequest` (TD-59)
- [x] FE `/lms/pengaturan/modul` — form pengaturan LMS — ✅ halaman admin (toggle fitur + 3 batas angka)
- [x] `GET /lms/public/settings` (publik, cache 24h) + hook `useSettings` di FE — ✅ `routes/lms_public.php` (tanpa auth) + `hooks/useLmsSettings.ts` (TD-60)
- [x] Tambah key setting baru: `lms_max_soal_import`, `lms_upload_max_size_mb`, `lms_video_max_size_mb` (key `lms_fitur_presensi` **sudah ada** — dibuat di Fase 1C) — ✅ definisi terpusat `LmsModuleSettingService::definitions()`
- [x] Permission & menu — ✅ `lms.pengaturan.read/manage` (admin/superadmin) + menu `/lms/dashboard` & `/lms/pengaturan/modul` (migration `2026_10_23_000002`)
- [x] Dokumentasi API — ✅ `docs/api/LMS/LmsController.md` (section 8)
- [x] Test — ✅ `tests/Feature/SiakadLmsDashboardSettingTest.php` (7 test / 70 assertions)

---

### Fase 6 — Penyempurnaan, Dokumentasi & Hardening (3 hari)

- [ ] Verifikasi Range streaming video 500MB (TD/4.J)
- [ ] `MateriFile` — pastikan `nama_file_asli` tersimpan
- [ ] Audit keamanan: semua endpoint LMS punya permission gate; tidak ada kebocoran `is_benar` ke mahasiswa (regression test)
- [ ] Dokumentasi API lengkap `docs/api/LMS/*.md` (skill `api-documentation`)
- [ ] Update `plan_lms_absensi.md` → status implementasi
- [ ] Unit test: `LmsNotificationServiceTest`, `AiGradingServiceTest`, `LmsExportServiceTest`
- [ ] Checklist QA manual (dosen / mahasiswa / admin / kaprodi)

---

### 6.1 Timeline Ringkas

```
Fase 0  Rekondisi          ██ 2-3h   ⛔ prasyarat   ✅ DONE
Fase 1  PresensiV2+Export  █████ 5-7h ⛔ P0         ✅ DONE (1A/1B/1C)
Fase 2  AI Grading         ████ 4-5h  ⛔ P0         ⛔ DIHAPUS (per instruksi user)
Fase 3  Notifikasi+Forum   ████ 4-5h  (3A ✅ DONE, 3B pending)
Fase 4  Bank Soal+Tryout   ████ 4-5h
Fase 5  Dashboard+Setting  ███ 3-4h
Fase 6  Polish+Dok         ███ 3h
                        ─────────
                   Total  25-32 hari kerja
```

---

## 7. Backlog Endpoint Baru (Ringkasan)

| Fase | Endpoint | Method |
|---|---|---|
| 1 | `/api/v1/lms/admin/presensi/kelas` · `/rekap` · `/sync` | GET/POST |
| 1 | `/api/v1/lms/kelas/{id}/export/{nilai\|presensi\|izin}` | GET |
| 1 | `/api/v1/lms/quiz/{id}/export/hasil` · `/tugas/{id}/export/pengumpulan` | GET |
| 1 | `/api/v1/siakad/obe/soal/import` · `/import/template` · `/bulk-delete` · `/bulk-move` · `PUT /{id}` | POST/GET/PUT |
| 2 | — (kolom + service, tanpa endpoint baru; `PUT /obe/soal/{id}` sudah ada dari Fase 1) | — |
| 3 | `/api/v1/lms/notifikasi` · `/{id}/read` · `/read-all` · `/unread-count` | GET/POST |
| 3 | `/api/v1/lms/forum/{topikId}` · `/forum/post/{postId}` | PUT |
| 4 | `/api/v1/lms/quiz/{id}/peserta-copy` · `/soal/bulk-delete` · `/mahasiswa/search` | POST/GET |
| 5 | `/api/v1/lms/dashboard/overview` · `/pengaturan/modul` · `/public/settings` | GET/PUT |

---

## 8. Risk & Mitigasi

| # | Risk | Dampak | Mitigasi |
|---|---|---|---|
| R1 | PresensiV2 tidak stabil / schema berubah | Sync gagal, data absensi kosong | Dual transport `api\|db` + retry + **fallback ke bulk manual RAG** (tetap berfungsi tanpa PresensiV2) |
| R2 | AI grading salah menilai esaiSimilar | Nilai mahasiswa tidak adil | `scale`/`threshold` + **always simpan `similarity_score`** + dosen bisa override manual + audit log |
| R3 | Migrasi `siakad_bank_soal`bentrok dengan data OBE existing | Data hilang | Semua kolom baru **nullable/default**; `down()` aman; backup sebelum migrate |
| R4 | Export data sensitif (nilai) bocor | Kebocoran data | Permission gate `siakad.nilai.manage`; **tanpa cache**; audit log tiap export; tidak disimpan di disk publik |
| R5 | Scope creep — donor punya banyak fitur non-kritis (video recording, chat privat, badge) | Proyek molor | **Fase 6+ tidak dijadwalkan**; backlog tetap di dokumen ini sebagai referensi |
| R6 | `MenuSeeder` truncate menghapus menu LMS lagi | Modul LMS hilang dari sidebar | TD-4 di Fase 0 — pindahkan ke seeder |
| R7 | Git-hook auditor menolak commit | Tertahan | Baca skrip auditor di `.githooks/audits/`, perbaiki kode. **DILARANG `--no-verify`** |
| R8 | Variance Naming | API baru tidak konsisten | Ikuti `api-naming-standard`: resource-less + aksi eksplisit, multi-word kebab-case (`bulk-absensi`, `input-token`) |

---

## 9. Checklist Deliverable Final

**Backend**
- [ ] Semua endpoint baru punya FormRequest + validasi
- [ ] Semua endpoint punya permission gate (`can:` / Policy)
- [ ] Semua aksi tulis punya audit log `module = LMS`
- [ ] Semua file privat via `FileStorageService` (signed URL), bukan `Storage::url()`
- [ ] Migrasi punya `down()` + index foreigner key + rollback aman
- [ ] Dokumentasi `docs/api/LMS/*.md` diperbarui
- [ ] Unit/feature test: `php artisan test --filter=Lms`

**Frontend**
- [ ] Form pakai React Hook Form + Zod (`form-validation-standard`)
- [ ] Tabel pakai DataTable + Filter Drawer + 3-dot menu + ConfirmDialog (`table-filter-ui-standard`)
- [ ] Font max 12px, warna `var(--module-primary)`
- [ ] Loading/empty/error state di setiap fetch
- [ ] `pnpm lint` & `tsc --noEmit` bersih

**Dokumentasi**
- [ ] `docs/api/LMS/*.md` lengkap (request, response, error, contoh)
- [ ] `plan_lms_absensi.md` di-update dengan status implementasi
- [ ] Panduan dosen (adaptasi dari `Buku Panduan LMS Indonusa - Dosen.docx`)

---

## 10. Lampiran — Referensi Kode Donor

| Fitur | File Donor |
|---|---|
| PresensiV2 sync | `api/app/Services/PresensivSyncService.php` |
| Retry sync | `api/app/Console/Commands/RetryPresensiSync.php` + `routes/console.php:13` |
| Admin presensi rekap | `api/app/Http/Controllers/Api/Admin/PresensiDevController.php` |
| AI grading | `api/app/Http/Controllers/Api/TryoutController.php:1094` (`calculateCosineSimilarity`), `:1163` (`finalizeAttempt`) |
| Mikroservice AI | `api-pencocokan-jawabab-isai/ai.js`, `db.js`, `index.js` |
| Soal import | `api/app/Services/SoalImportService.php` |
| Bank soal | `api/app/Http/Controllers/Api/LmsBankSoalController.php` |
| Export CSV | `TugasController@exportSubmissions`, `QuizController@exportResults` |
| Storage Range | `api/app/Http/Controllers/Api/StorageProxyController.php` |
| Feature flag | `api/app/Models/LmsSetting.php` (`fiturPresensiAktif`) |
| Settings admin | `api/app/Http/Controllers/Api/Admin/SettingController.php` |
| Dashboard stats | `api/app/Http/Controllers/Api/LmsDashboardController.php:1150` |
| OBE read | `api/app/Services/ObeDataService.php` |
| OBE write | `api/app/Services/ObeNilaiSyncService.php` |
| Kontrak OBE | `obe_integration_api.md` |
| Konfigurasi | `api/config/lms_integration.php` |

---

*Dokumen ini dibuat oleh audit kode langsung pada `Project/lms` dan `RAG`. Semua klaim gap diverifikasi terhadap source code, bukan asumsi.*
