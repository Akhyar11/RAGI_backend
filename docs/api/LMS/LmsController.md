<!-- Pindah dari docs/api/SIAKAD/LmsController.md — base URL baru /api/v1/lms, otorisasi tetap Gate siakad.kelas.* -->
# LmsController

> **Modul**: LMS (standalone, pisah dari SIAKAD)  
> **Base URL**: `/api/v1/lms`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat/Diperbarui**: 2026-09-25

Controller untuk menangani seluruh aktivitas Learning Management System (LMS) dan Absensi Terintegrasi Perkuliahan. Meliputi manajemen materi perkuliahan multi-file, penugasan mahasiswa dengan auto-sync ke sistem penilaian OBE (`siakad_nilai_komponen_mhs`), absensi token 6-digit dengan masa aktif dinamis, rekapitulasi kehadiran kelas, pengajuan dan persetujuan izin/sakit mahasiswa, serta pengaturan media penyimpanan (Cloudflare R2 atau storage lokal).

## Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` / `multipart/form-data` | ✅ untuk POST/PUT/PATCH |

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/lms/kelas/my` | Daftar kelas yang diampu (Dosen) atau diikuti (Mahasiswa) | ✅ |
| GET | `/api/v1/lms/tugas/my` | Seluruh daftar tugas mahasiswa di semester aktif | ✅ |
| GET | `/api/v1/lms/kelas/{kelasId}/overview` | Ringkasan 16 pertemuan kelas, silabus, & progres perkuliahan | ✅ |
| GET | `/api/v1/lms/kelas/{kelasId}/rekap-absensi` | Rekap kehadiran (dosen: sekelas; mahasiswa: miliknya saja) | ✅ |
| PUT | `/api/v1/lms/kelas/{kelasId}/setting` | Perbarui konfigurasi LMS kelas (storage, bobot, izin token) | ✅ |
| GET | `/api/v1/lms/pertemuan` | Daftar pertemuan agregat seluruh kelas yang diakses (halaman `/lms/pertemuan`) | ✅ |
| PUT | `/api/v1/lms/pertemuan/{pertemuanId}` | Perbarui data pertemuan | ✅ kelola |
| DELETE | `/api/v1/lms/pertemuan/{pertemuanId}` | Hapus pertemuan (diblokir bila sudah ada data turunan) | ✅ kelola |
| GET | `/api/v1/lms/pengaturan` | Rekap pengaturan LMS seluruh kelas yang diakses (halaman `/lms/pengaturan`) | ✅ |
| GET | `/api/v1/lms/pertemuan/{id}` | Detail pertemuan, materi, tugas, kehadiran, dan pengajuan izin | ✅ |
| POST | `/api/v1/lms/pertemuan/{pertemuanId}/materi` | Tambah materi pembelajaran baru (teks/link/file) | ✅ |
| PUT | `/api/v1/lms/materi/{materiId}` | Perbarui data materi pembelajaran | ✅ |
| DELETE | `/api/v1/lms/materi/{materiId}` | Hapus materi pembelajaran beserta seluruh lampirannya | ✅ |
| POST | `/api/v1/lms/materi/{materiId}/file` | Unggah lampiran berkas materi tambahan | ✅ |
| DELETE | `/api/v1/lms/materi-file/{fileId}` | Hapus berkas lampiran materi | ✅ |
| POST | `/api/v1/lms/pertemuan/{pertemuanId}/tugas` | Buat penugasan baru pada pertemuan tertentu | ✅ |
| PUT | `/api/v1/lms/tugas/{tugasId}` | Perbarui penugasan perkuliahan | ✅ |
| DELETE | `/api/v1/lms/tugas/{tugasId}` | Hapus penugasan perkuliahan | ✅ |
| POST | `/api/v1/lms/tugas/{tugasId}/kumpul` | Mahasiswa mengunggah berkas pengumpulan tugas | ✅ |
| PUT | `/api/v1/lms/pengumpulan/{pengumpulanId}/nilai` | Dosen memberikan nilai tugas dan auto-sync ke nilai OBE | ✅ |
| POST | `/api/v1/lms/pertemuan/{pertemuanId}/token` | Generate 6-digit token absensi realtime (masa aktif berbatas) | ✅ |
| POST | `/api/v1/lms/pertemuan/{pertemuanId}/token/rotate` | Putar ulang token umur pendek (anti titip-hadir) | ✅ |
| POST | `/api/v1/lms/pertemuan/{pertemuanId}/tutup-presensi` | Tutup sesi presensi (input token ditolak setelah ini) | ✅ |
| POST | `/api/v1/lms/pertemuan/{pertemuanId}/input-token` | Mahasiswa input token absensi untuk mencatat kehadiran | ✅ |
| POST | `/api/v1/lms/pertemuan/{pertemuanId}/bulk-absensi` | Dosen menyimpan presensi mahasiswa secara massal/manual | ✅ |
| POST | `/api/v1/lms/pertemuan/{pertemuanId}/izin` | Mahasiswa mengajukan permohonan izin/sakit dengan bukti surat | ✅ |
| PATCH | `/api/v1/lms/izin/{izinId}/proses` | Dosen menyetujui (disetujui) atau menolak (ditolak) surat izin | ✅ |
| GET | `/api/v1/lms/download/{type}/{id}` | Dapatkan tautan unduh aman (signed URL / streaming berkas) | ✅ |

## Query Parameters (berlaku untuk endpoint list)

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tahun_akademik_id` | integer | ❌ | aktif | Filter ID tahun akademik / semester perkuliahan |
| `search` | string | ❌ | — | Pencarian judul materi, nama kelas, atau nama mahasiswa |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan data (`created_at`, `nama_kelas`, `id`) |
| `sort_order` | string | ❌ | `desc` | Arah pengurutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah rekaman data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Nomor halaman yang diminta |

---

## A. Daftar Pertemuan (Agregat Module-Level)

### [GET] `/api/v1/lms/pertemuan`

Daftar pertemuan **agregat level modul** — menggabungkan seluruh kelas yang boleh diakses
user tanpa parameter `kelasId`, untuk halaman `/lms/pertemuan` di sidebar. Padanan
module-level dari daftar pertemuan pada halaman detail kelas.

#### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `page` | integer | ❌ | `1` | Nomor halaman |
| `per_page` | integer | ❌ | `15` | Jumlah rekaman per halaman (maks. 100) |
| `search` | string | ❌ | — | Pencarian pada `materi`, `catatan_pertemuan`, nama/kode kelas, nama/kode mata kuliah |
| `sort_by` | string | ❌ | `tanggal` | `tanggal`, `pertemuan_ke`, `jam_mulai`, `status_pertemuan`, `created_at` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `tahun_akademik_id` | integer | ❌ | — | Filter periode |
| `status_pertemuan` | string | ❌ | — | `belum` / `berlangsung` / `selesai` |

#### Scope Data per Role

| Peran | Kelas yang terlihat |
|---|---|
| `superadmin`, `admin`, `kaprodi`, `wakil_prodi` | Seluruh kelas |
| `dosen` | Kelas yang diampu (`siakad_dosen_pengampu`) |
| `mahasiswa` | Kelas yang diambil lewat KRS |

Peran yang tidak cocok dengan ketiganya (mis. akun tanpa relasi;Dosen/Mahasiswa) mendapat
hasil kosong — bukan error — agar halaman tidak rusak.

#### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar pertemuan LMS berhasil diambil.",
    "data": [
        {
            "id": 4,
            "kelas_id": 14,
            "pertemuan_ke": 1,
            "tanggal": "2026-10-01",
            "materi": "Basis Data Relasional",
            "jam_mulai": "10:45",
            "jam_selesai": "13:15",
            "catatan_pertemuan": null,
            "status_pertemuan": "belum",
            "token_absensi": null,
            "token_expired_at": null,
            "window_menit": 30,
            "token_rotated_at": null,
            "presensi_closed_at": null,
            "created_at": "2026-10-05T07:48:05.000000Z",
            "updated_at": "2026-10-05T07:48:05.000000Z",
            "kelas": {
                "id": 14,
                "kode_kelas": "IF3A-BASDAT",
                "nama_kelas": "Basis Data Relasional Kelas A",
                "mata_kuliah": {
                    "id": 5,
                    "kode_mk": "IF202",
                    "nama": "Basis Data Relasional"
                }
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 1,
        "total": 1,
        "last_page": 1,
        "from": 1,
        "to": 1
    },
    "filters": {
        "search": null,
        "sort_by": "tanggal",
        "sort_order": "desc",
        "tahun_akademik_id": null,
        "status_pertemuan": null
    }
}
```

### [PUT] `/api/v1/lms/pertemuan/{pertemuanId}`

Memperbarui data pertemuan. Wajib permission `siakad.kelas.manage`; perubahan tercatat pada
`audit_logs`.

#### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `pertemuanId` | integer | ✅ | ID pertemuan |

#### Request Body

```json
{
    "pertemuan_ke": 1,
    "tanggal": "2026-10-02",
    "materi": "Basis Data Relasional — Normalisasi",
    "catatan_pertemuan": "Bawa laptop dan installer DBMS.",
    "jam_mulai": "11:00",
    "jam_selesai": "13:30",
    "status_pertemuan": "selesai"
}
```

| Field | Type | Wajib | Validasi |
|---|---|---|---|
| `pertemuan_ke` | integer | ✅ | 1–16, unik per `kelas_id` |
| `tanggal` | date | ✅ | Format `YYYY-MM-DD` |
| `materi` | string | ❌ | Maks. 255 karakter |
| `catatan_pertemuan` | text | ❌ | — |
| `jam_mulai` | string | ❌ | Format `HH:MM` |
| `jam_selesai` | string | ❌ | Format `HH:MM`, harus setelah `jam_mulai` |
| `status_pertemuan` | string | ❌ | `belum` / `berlangsung` / `selesai` |

> `status_pertemuan` berasal dari CHECK constraint kolom `siakad_pertemuan.status_pertemuan`
> dan dideklarasikan sebagai konstanta `Pertemuan::STATUSES` (bukan string literal).
> Nilai di luar daftar tersebut ditolak **422** — bukan error database 500.

#### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Pertemuan berhasil diperbarui.",
    "data": {
        "id": 4,
        "kelas_id": 14,
        "pertemuan_ke": 1,
        "tanggal": "2026-10-02",
        "materi": "Basis Data Relasional — Normalisasi",
        "catatan_pertemuan": "Bawa laptop dan installer DBMS.",
        "jam_mulai": "11:00",
        "jam_selesai": "13:30",
        "status_pertemuan": "selesai"
    }
}
```

#### Response Error

**403 Forbidden** — user tidak memegang `siakad.kelas.manage`.

```json
{ "message": "This action is unauthorized." }
```

**404 Not Found** — `pertemuanId` tidak ada.

```json
{ "message": "No query results for model [App\\Models\\Siakad\\Pertemuan] 999." }
```

**422 Unprocessable Entity** — nomor pertemuan bentrok pada kelas yang sama.

```json
{
    "message": "Pertemuan ke-1 sudah ada pada kelas ini.",
    "errors": { "pertemuan_ke": ["Pertemuan ke-1 sudah ada pada kelas ini."] }
}
```

**422 Unprocessable Entity** — status di luar closed-set database.

```json
{
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "status_pertemuan": ["Status pertemuan tidak valid. Pilihan: Belum Berlangsung (belum), Berlangsung (berlangsung), Selesai (selesai)."]
    }
}
```

### [DELETE] `/api/v1/lms/pertemuan/{pertemuanId}`

Menghapus pertemuan beserta data turunannya (materi, tugas, quiz, izin). Wajib permission
`siakad.kelas.manage`; penghapusan tercatat pada `audit_logs`.

#### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `pertemuanId` | integer | ✅ | ID pertemuan |

#### Guard Penghapusan

Penghapusan **ditolak 422** bila pertemuan sudah memiliki data turunan, supaya riwayat
akademik tidak rusak diam-diam. List turunan yang dipolicy pesan error:

- materi pembelajaran (`lms_materi`)
- penugasan (`lms_tugas`)
- quiz/tryout (`lms_quiz`)
- pengajuan izin absensi (`lms_izin_absensi`)
- rekap absensi mahasiswa (`siakad_absensi_mahasiswa`)

#### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Pertemuan berhasil dihapus.",
    "data": null
}
```

#### Response Error

**422 Unprocessable Entity** — masih ada data turunan.

```json
{
    "message": "Pertemuan tidak dapat dihapus karena sudah memiliki: rekap absensi mahasiswa. Hapus atau pindahkan datanya terlebih dahulu.",
    "errors": { "pertemuan": ["Pertemuan tidak dapat dihapus karena sudah memiliki: rekap absensi mahasiswa. Hapus atau pindahkan datanya terlebih dahulu."] }
}
```

---

## B. Pengaturan LMS (Agregat Module-Level)

### [GET] `/api/v1/lms/pengaturan`

Rekap konfigurasi LMS untuk seluruh kelas yang boleh diakses user, untuk halaman
`/lms/pengaturan` di sidebar. Endpoint ini read-only, sehingga aman dipanggil langsung
dari halaman daftar tanpa perlu membuka tiap kelas satu per satu.

#### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `page` | integer | ❌ | `1` | Nomor halaman |
| `per_page` | integer | ❌ | `15` | Jumlah rekaman per halaman (maks. 100) |
| `search` | string | ❌ | — | Pencarian pada `kode_kelas`, `nama_kelas`, nama/kode mata kuliah |
| `sort_by` | string | ❌ | `kode_kelas` | `kode_kelas`, `nama_kelas`, `kapasitas`, `created_at` |
| `sort_order` | string | ❌ | `asc` | `asc` / `desc` |
| `tahun_akademik_id` | integer | ❌ | — | Filter periode |

#### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar pengaturan LMS berhasil diambil.",
    "data": [
        {
            "id": 14,
            "mata_kuliah_id": 5,
            "tahun_akademik_id": 1,
            "program_studi_id": 1,
            "ruangan_id": 1,
            "kode_kelas": "IF3A-BASDAT",
            "nama_kelas": "Basis Data Relasional Kelas A",
            "kapasitas": 35,
            "kuota_krs": 34,
            "pertemuan_count": 1,
            "mahasiswa_count": 4,
            "mata_kuliah": { "id": 5, "kode_mk": "IF202", "nama": "Basis Data Relasional" },
            "tahun_akademik": { "id": 1, "kode": "20242", "nama": "2024/2025 Ganjil" },
            "program_studi": { "id": 1, "kode_prodi": "IF", "nama": "Informatika" },
            "lms_setting": {
                "id": 1,
                "kelas_id": 14,
                "total_pertemuan": 14,
                "metode_absensi": "keduanya",
                "batas_min_hadir_persen": 80,
                "can_submit_late": true,
                "show_nilai_to_mahasiswa": false,
                "storage_disk": null
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 14,
        "last_page": 1,
        "from": 1,
        "to": 14
    },
    "filters": {
        "search": null,
        "sort_by": "kode_kelas",
        "sort_order": "asc",
        "tahun_akademik_id": null
    }
}
```

> **Kelas yang belum pernah dikonfigurasi** mengirim `lms_setting: null`. Backend sengaja
> tidak membuat baris default saat membaca, agar UI dapat membedakan "belum dikonfigurasi"
> dari "sudah dikonfigurasi dengan nilai tersebut". Nilai bawaan ketika menyimpan pertama
> kali berasal dari default kolom database (`KelasLmsSetting::defaultSetting()`).

#### Response Error

**403 Forbidden** — user tidak memegang `siakad.kelas.read`.

```json
{ "message": "This action is unauthorized." }
```

### [PUT] `/api/v1/lms/kelas/{kelasId}/setting` (validasi `metode_absensi`)

Memperbarui konfigurasi LMS satu kelas. Wajib permission `siakad.kelas.manage`.

#### Request Body

```json
{
    "total_pertemuan": 16,
    "metode_absensi": "keduanya",
    "batas_min_hadir_persen": 75,
    "can_submit_late": true,
    "show_nilai_to_mahasiswa": false,
    "storage_disk": null
}
```

| Field | Type | Wajib | Validasi |
|---|---|---|---|
| `total_pertemuan` | integer | ✅ | 1–32 |
| `metode_absensi` | string | ✅ | `manual_dosen` / `token_mahasiswa` / `keduanya` |
| `batas_min_hadir_persen` | integer | ✅ | 0–100 |
| `can_submit_late` | boolean | ❌ | — |
| `show_nilai_to_mahasiswa` | boolean | ❌ | — |
| `storage_disk` | string | ❌ | Maks. 50 karakter, boleh `null` (pakai default sistem) |

> `metode_absensi` berasal dari CHECK constraint kolom `lms_kelas_setting.metode_absensi`
> dan dideklarasikan sebagai konstanta `KelasLmsSetting::METODE_ABSENSI`.

**422 Unprocessable Entity**

```json
{
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "metode_absensi": ["Metode absensi tidak valid. Pilihan: Absensi Manual oleh Dosen (manual_dosen), Token Absensi Mahasiswa (token_mahasiswa), Keduanya (Manual & Token) (keduanya)."]
    }
}
```

---

---

## 1. Overview Kelas & Pertemuan

### [GET] `/api/v1/lms/kelas/my`

Mengambil daftar kelas yang diampu (untuk Dosen) atau kelas yang diikuti sesuai KRS yang disetujui (untuk Mahasiswa) pada tahun akademik aktif atau tahun akademik tertentu.

#### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tahun_akademik_id` | integer | ❌ | aktif | ID periode tahun akademik (default: periode `is_active = true`) |
| `search` | string | ❌ | — | Pencarian nama kelas atau nama mata kuliah |

#### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Daftar kelas LMS berhasil dimuat.",
    "data": [
        {
            "id": 14,
            "nama_kelas": "TI-3A",
            "kode_kelas": "TI3A-20261",
            "mata_kuliah": {
                "id": 45,
                "kode_mk": "IF301",
                "nama_mk": "Pemrograman Web Lanjut",
                "total_sks": 3
            },
            "dosen": {
                "id": 2,
                "nama_lengkap": "Dr. Budi Santoso, M.Kom."
            },
            "setting": {
                "allow_student_attendance_token": true,
                "default_storage_limit_mb": 25
            },
            "total_pertemuan": 16,
            "pertemuan_terlaksana": 4
        }
    ]
}
```

#### Response Error Standar
**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki akses ke data kelas LMS."
}
```

### [GET] `/api/v1/lms/kelas/{kelasId}/overview`

Mengambil data lengkap kelas perkuliahan, konfigurasi LMS kelas, serta daftar 16 pertemuan beserta status pelaksanaan dan jumlah materi/tugas yang ada.

#### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Overview kelas LMS berhasil diambil.",
    "data": {
        "kelas": {
            "id": 14,
            "nama_kelas": "TI-3A",
            "kode_kelas": "TI3A-20261",
            "mata_kuliah": {
                "id": 45,
                "kode_mk": "IF301",
                "nama_mk": "Pemrograman Web Lanjut",
                "sks_teori": 2,
                "sks_praktek": 1
            },
            "dosen": {
                "id": 8,
                "nama": "Dr. Ir. Hendra Saputra, M.T."
            }
        },
        "setting": {
            "id": 3,
            "kelas_id": 14,
            "storage_disk": "r2",
            "max_file_size_mb": 50,
            "is_auto_sync_obe": true,
            "can_submit_late": true,
            "allow_student_attendance_token": true
        },
        "pertemuan": [
            {
                "id": 101,
                "pertemuan_ke": 1,
                "tanggal_pelaksanaan": "2026-10-05",
                "materi": "Pengenalan Arsitektur Cloud & API Modern",
                "status_pertemuan": "selesai",
                "total_materi": 2,
                "total_tugas": 1,
                "total_hadir": 38,
                "total_mahasiswa": 40
            }
        ]
    }
}
```

---

## 2. Manajemen Materi Pembelajaran

### [POST] `/api/v1/lms/pertemuan/{pertemuanId}/materi`

Dosen mengunggah materi perkuliahan baru. Format dapat berupa dokumen teks, berkas lampiran, atau tautan video conference/YouTube.

#### Request Body (Multipart/form-data)
```json
{
    "judul": "Modul 01 - Pengantar REST API",
    "deskripsi": "Pelajari bab 1 hingga bab 3 sebelum sesi tatap muka.",
    "tipe_konten_id": 2,
    "link_eksternal": null,
    "urutan": 1,
    "is_published": true,
    "file": "(binary file PDF/PPT/ZIP maks 50MB)"
}
```

#### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Materi pembelajaran berhasil ditambahkan.",
    "data": {
        "id": 201,
        "pertemuan_id": 101,
        "tipe_konten_id": 2,
        "judul": "Modul 01 - Pengantar REST API",
        "deskripsi": "Pelajari bab 1 hingga bab 3 sebelum sesi tatap muka.",
        "tipe_konten": "file",
        "link_eksternal": null,
        "urutan": 1,
        "is_published": true,
        "files": [
            {
                "id": 501,
                "materi_id": 201,
                "file_name": "Modul-01-REST-API.pdf",
                "file_size": 2450120,
                "mime_type": "application/pdf"
            }
        ],
        "created_at": "2026-09-25T16:00:00.000000Z",
        "updated_at": "2026-09-25T16:00:00.000000Z"
    }
}
```

---

## 3. Penugasan & Auto-Sync Nilai OBE

### [POST] `/api/v1/lms/pertemuan/{pertemuanId}/tugas`

Membuat tugas baru pada pertemuan tertentu. Jika `komponen_penilaian_id` dipilih, nilai yang diinputkan dosen akan otomatis tersinkronisasi ke sistem OBE (`siakad_nilai_komponen_mhs`).

#### Request Body
```json
{
    "judul": "Tugas 1: Desain Skema Database LMS",
    "deskripsi": "Buatlah ERD lengkap beserta kamus data dalam bentuk PDF.",
    "deadline_at": "2026-10-12 23:59:00",
    "max_file_size_mb": 25,
    "allowed_extensions": "pdf,zip",
    "komponen_penilaian_id": 18,
    "is_published": true
}
```

#### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Tugas perkuliahan berhasil dibuat.",
    "data": {
        "id": 88,
        "pertemuan_id": 101,
        "judul": "Tugas 1: Desain Skema Database LMS",
        "deadline_at": "2026-10-12 23:59:00",
        "komponen_penilaian_id": 18,
        "is_published": true,
        "created_at": "2026-09-25T16:05:00.000000Z"
    }
}
```

### [PUT] `/api/v1/lms/pengumpulan/{pengumpulanId}/nilai`

Dosen memberikan penilaian pada tugas yang dikumpulkan mahasiswa.

#### Request Body
```json
{
    "nilai": 87.5,
    "feedback_dosen": "Struktur database sangat baik dan ternormalisasi dengan benar."
}
```

#### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Nilai tugas berhasil disimpan dan disinkronkan ke sistem OBE.",
    "data": {
        "id": 402,
        "tugas_id": 88,
        "mahasiswa_id": 312,
        "nilai": 87.5,
        "feedback_dosen": "Struktur database sangat baik dan ternormalisasi dengan benar.",
        "dinilai_at": "2026-10-13T10:15:00.000000Z",
        "dinilai_by": 8
    }
}
```

---

## 4. Presensi Token & Rekap Kehadiran

### [POST] `/api/v1/lms/pertemuan/{pertemuanId}/token`

Menghasilkan 6-digit token acak numerik untuk mahasiswa melakukan presensi mandiri saat sesi perkuliahan berlangsung. Membuka/membuka-ulang sesi (reset penanda tutup).

#### Request Body (opsional)
```json
{
    "window_menit": 45
}
```
`window_menit` 5–180; kosong = `lms_presensi_window_menit` (default 30).

#### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Token absensi berhasil digenerate.",
    "data": {
        "pertemuan_id": 101,
        "token": "729140",
        "ttl_menit": 15,
        "window_menit": 45,
        "expired_at": "2026-10-05T10:15:00+07:00",
        "sisa_detik": 900
    }
}
```

### [POST] `/api/v1/lms/pertemuan/{pertemuanId}/token/rotate`

> Anti titip-hadir (ala Project/lms): putar ulang token dengan umur pendek (`lms_token_rotate_seconds`, default 120 detik) selama dashboard dosen terbuka. Token lama langsung gugur.

Wajib permission `siakad.kelas.manage`. Tidak ada request body.

#### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `pertemuanId` | integer | ✅ | ID pertemuan |

#### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Token absensi berhasil diputar ulang.",
    "data": {
        "pertemuan_id": 101,
        "token": "481027",
        "ttl_detik": 120,
        "expired_at": "2026-10-05T10:02:00+07:00",
        "sisa_detik": 120
    }
}
```

#### Response Error

**403 Forbidden** — tidak memegang `siakad.kelas.manage`.
```json
{
    "message": "This action is unauthorized."
}
```

**404 Not Found** — pertemuan tidak ada.
```json
{
    "message": "No query results for model [App\\Models\\Siakad\\Pertemuan] 999."
}
```

**422 Unprocessable Entity** — sesi presensi sudah ditutup.
```json
{
    "message": "Sesi presensi sudah ditutup. Token tidak dapat diputar ulang.",
    "errors": { "pertemuan": ["Sesi presensi sudah ditutup. Token tidak dapat diputar ulang."] }
}
```

### [POST] `/api/v1/lms/pertemuan/{pertemuanId}/tutup-presensi`

Dosen menutup sesi presensi. Setelah ditutup: input token mahasiswa ditolak, generate/rotate token ditolak (422). Input manual dosen (`bulk-absensi`) tetap bisa untuk koreksi susulan.

Wajib permission `siakad.kelas.manage`. Tidak ada request body.

#### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `pertemuanId` | integer | ✅ | ID pertemuan |

#### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Sesi presensi berhasil ditutup.",
    "data": {
        "id": 101,
        "kelas_id": 14,
        "pertemuan_ke": 1,
        "status_pertemuan": "selesai",
        "presensi_closed_at": "2026-10-05T10:30:00+07:00",
        "token_absensi": null,
        "token_expired_at": null
    }
}
```

#### Response Error

**403 Forbidden** — tidak memegang `siakad.kelas.manage`.
```json
{
    "message": "This action is unauthorized."
}
```

**404 Not Found** — pertemuan tidak ada.
```json
{
    "message": "No query results for model [App\\Models\\Siakad\\Pertemuan] 999."
}
```

### [POST] `/api/v1/lms/pertemuan/{pertemuanId}/input-token`

Mahasiswa memasukkan kode 6 digit untuk validasi presensi. Syarat: sesi belum ditutup + token cocok + belum kedaluwarsa + mahasiswa terdaftar di kelas via KRS (mahasiswa luar kelas ditolak 422).

#### Request Body
```json
{
    "token": "729140"
}
```

#### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Kehadiran Anda berhasil dicatat via token LMS."
}
```

### [POST] `/api/v1/lms/pertemuan/{pertemuanId}/bulk-absensi`

Dosen mengisi presensi mahasiswa secara massal/manual untuk satu pertemuan.

#### Request Body
```json
{
    "absensi": [
        {
            "mahasiswa_id": 312,
            "status_id": 15,
            "catatan": "Hadir tepat waktu"
        }
    ]
}
```

#### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Presensi massal berhasil disimpan."
}
```

---

## 5. Pengajuan & Pemrosesan Izin/Sakit

### [POST] `/api/v1/lms/pertemuan/{pertemuanId}/izin`

Mahasiswa mengajukan permohonan izin atau surat keterangan sakit disertai bukti berkas pendukung.

#### Request Body (Multipart/form-data)
```json
{
    "tipe_izin_id": 12,
    "alasan": "Demam tinggi dan disarankan dokter istirahat selama 2 hari.",
    "file_surat": "(binary file surat keterangan dokter JPEG/PNG/PDF maks 5MB)"
}
```

#### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Pengajuan izin/sakit berhasil dikirimkan ke dosen pengampu.",
    "data": {
        "id": 61,
        "pertemuan_id": 101,
        "mahasiswa_id": 312,
        "tipe_izin_id": 12,
        "tipe_izin": "sakit",
        "alasan": "Demam tinggi dan disarankan dokter istirahat selama 2 hari.",
        "status": "pending",
        "created_at": "2026-10-05T08:00:00.000000Z"
    }
}
```

### [PATCH] `/api/v1/lms/izin/{izinId}/proses`

Dosen pengampu memproses status persetujuan surat izin. Jika disetujui, kehadiran mahasiswa di tabel absensi perkuliahan akan otomatis diubah menjadi `I` (Izin) atau `S` (Sakit).

#### Request Body
```json
{
    "status_id": 21,
    "catatan_dosen": "Semoga lekas sembuh, pelajari materi bab 1 di LMS."
}
```

#### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Pengajuan izin berhasil di-disetujui.",
    "data": {
        "id": 61,
        "status": "disetujui",
        "catatan_dosen": "Semoga lekas sembuh, pelajari materi bab 1 di LMS.",
        "diproses_by": 8,
        "diproses_at": "2026-10-05T11:00:00.000000Z"
    }
}
```

---

## Response Error Standar

### 401 Unauthorized
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

### 403 Forbidden
```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak akses untuk mengelola perkuliahan ini."
}
```

### 404 Not Found
```json
{
    "status": "error",
    "message": "Data materi pembelajaran tidak ditemukan."
}
```

### 422 Unprocessable Entity
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "token": ["Token absensi tidak valid atau telah kedaluwarsa."],
        "nilai": ["Nilai tugas harus berada di rentang 0 hingga 100."]
    }
}
```

---

## Catatan Tambahan

- **Penyimpanan Berkas (Cloudflare R2 & Lokal)**: Pemilihan disk penyimpanan disesuaikan secara dinamis melalui pengaturan sistem dan kelas (`lms_storage_disk`), tanpa melakukan *hardcoding* jalur berkas. Bila disk cloud (r2/r2-private/s3) terpilih namun belum dikonfigurasi (bucket/endpoint kosong, mis. env lokal), sistem otomatis fallback ke disk lokal agar upload tetap berjalan.
- **Integritas Penilaian OBE**: Sinkronisasi nilai tugas ke `siakad_nilai_komponen_mhs` dilakukan secara transaksional dengan menjaga relasi antara mahasiswa, kelas, dan komponen penilaian terkait.
- **Keamanan Berkas**: Tautan pengunduhan materi dan pengumpulan tugas menggunakan signed URL berbatas waktu atau *streamed response* terproteksi sehingga berkas tidak dapat diakses secara publik tanpa autentikasi yang sah.
- **Privasi Data Mahasiswa**: Pemanggil ber-role mahasiswa hanya menerima data kehadiran miliknya sendiri pada endpoint rekap (`rekapitulasi` 1 baris) dan detail pertemuan (`absensi_list`, `izin_list`, dan `pengumpulan` tiap tugas difilter ke miliknya). Agregat hitungan (`absensi_summary`) tetap ditampilkan.
- **Cakupan Daftar Kelas (`kelas/my`)**: dosen hanya melihat kelas yang diampunya (`dosen_pengampu`), mahasiswa hanya kelas di KRS-nya. Akun tanpa relasi dosen/mahasiswa tidak menerima data apa pun kecuali istimewa (admin/kaprodi). Tanpa parameter `tahun_akademik_id`, daftar dibatasi ke periode aktif (`is_active`); kirim ID periode lain untuk melihat riwayat semester lalu.
- **Kerahasiaan Data**: Field sensitif seperti token akses storage, credential S3, dan password pengguna tidak akan dikembalikan dalam response API apapun.
- **Penghapusan Berkas**: Saat materi atau tugas dihapus, berkas fisik yang tersimpan di disk storage akan dibersihkan secara otomatis.
