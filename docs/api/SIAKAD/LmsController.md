# LmsController

> **Modul**: SIAKAD  
> **Base URL**: `/api/v1/siakad/lms`  
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
| GET | `/api/v1/siakad/lms/kelas/my` | Daftar kelas yang diampu (Dosen) atau diikuti (Mahasiswa) | ✅ |
| GET | `/api/v1/siakad/lms/tugas/my` | Seluruh daftar tugas mahasiswa di semester aktif | ✅ |
| GET | `/api/v1/siakad/lms/kelas/{kelasId}/overview` | Ringkasan 16 pertemuan kelas, silabus, & progres perkuliahan | ✅ |
| GET | `/api/v1/siakad/lms/kelas/{kelasId}/rekap-absensi` | Matriks rekapitulasi kehadiran seluruh mahasiswa per pertemuan | ✅ |
| PUT | `/api/v1/siakad/lms/kelas/{kelasId}/setting` | Perbarui konfigurasi LMS kelas (storage, bobot, izin token) | ✅ |
| GET | `/api/v1/siakad/lms/pertemuan/{id}` | Detail pertemuan, materi, tugas, kehadiran, dan pengajuan izin | ✅ |
| POST | `/api/v1/siakad/lms/pertemuan/{pertemuanId}/materi` | Tambah materi pembelajaran baru (teks/link/file) | ✅ |
| PUT | `/api/v1/siakad/lms/materi/{materiId}` | Perbarui data materi pembelajaran | ✅ |
| DELETE | `/api/v1/siakad/lms/materi/{materiId}` | Hapus materi pembelajaran beserta seluruh lampirannya | ✅ |
| POST | `/api/v1/siakad/lms/materi/{materiId}/file` | Unggah lampiran berkas materi tambahan | ✅ |
| DELETE | `/api/v1/siakad/lms/materi-file/{fileId}` | Hapus berkas lampiran materi | ✅ |
| POST | `/api/v1/siakad/lms/pertemuan/{pertemuanId}/tugas` | Buat penugasan baru pada pertemuan tertentu | ✅ |
| PUT | `/api/v1/siakad/lms/tugas/{tugasId}` | Perbarui penugasan perkuliahan | ✅ |
| DELETE | `/api/v1/siakad/lms/tugas/{tugasId}` | Hapus penugasan perkuliahan | ✅ |
| POST | `/api/v1/siakad/lms/tugas/{tugasId}/kumpul` | Mahasiswa mengunggah berkas pengumpulan tugas | ✅ |
| PUT | `/api/v1/siakad/lms/pengumpulan/{pengumpulanId}/nilai` | Dosen memberikan nilai tugas dan auto-sync ke nilai OBE | ✅ |
| POST | `/api/v1/siakad/lms/pertemuan/{pertemuanId}/token` | Generate 6-digit token absensi realtime (masa aktif berbatas) | ✅ |
| POST | `/api/v1/siakad/lms/pertemuan/{pertemuanId}/input-token` | Mahasiswa input token absensi untuk mencatat kehadiran | ✅ |
| POST | `/api/v1/siakad/lms/pertemuan/{pertemuanId}/bulk-absensi` | Dosen menyimpan presensi mahasiswa secara massal/manual | ✅ |
| POST | `/api/v1/siakad/lms/pertemuan/{pertemuanId}/izin` | Mahasiswa mengajukan permohonan izin/sakit dengan bukti surat | ✅ |
| PATCH | `/api/v1/siakad/lms/izin/{izinId}/proses` | Dosen menyetujui (disetujui) atau menolak (ditolak) surat izin | ✅ |
| GET | `/api/v1/siakad/lms/download/{type}/{id}` | Dapatkan tautan unduh aman (signed URL / streaming berkas) | ✅ |

## Query Parameters (berlaku untuk endpoint list)

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Pencarian judul materi, nama kelas, atau nama mahasiswa |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan data (`created_at`, `nama_kelas`, `id`) |
| `sort_order` | string | ❌ | `desc` | Arah pengurutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah rekaman data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Nomor halaman yang diminta |

---

## 1. Overview Kelas & Pertemuan

### [GET] `/api/v1/siakad/lms/kelas/{kelasId}/overview`

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

### [POST] `/api/v1/siakad/lms/pertemuan/{pertemuanId}/materi`

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

### [POST] `/api/v1/siakad/lms/pertemuan/{pertemuanId}/tugas`

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

### [PUT] `/api/v1/siakad/lms/pengumpulan/{pengumpulanId}/nilai`

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

### [POST] `/api/v1/siakad/lms/pertemuan/{pertemuanId}/token`

Menghasilkan 6-digit token acak numerik untuk mahasiswa melakukan presensi mandiri saat sesi perkuliahan berlangsung.

#### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Token absensi berhasil digenerate.",
    "data": {
        "pertemuan_id": 101,
        "token": "729140",
        "token_expired_at": "2026-10-05 10:15:00",
        "ttl_minutes": 15
    }
}
```

### [POST] `/api/v1/siakad/lms/pertemuan/{pertemuanId}/input-token`

Mahasiswa memasukkan kode 6 digit untuk validasi presensi.

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

### [POST] `/api/v1/siakad/lms/pertemuan/{pertemuanId}/bulk-absensi`

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

### [POST] `/api/v1/siakad/lms/pertemuan/{pertemuanId}/izin`

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

### [PATCH] `/api/v1/siakad/lms/izin/{izinId}/proses`

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

- **Penyimpanan Berkas (Cloudflare R2 & Lokal)**: Pemilihan disk penyimpanan disesuaikan secara dinamis melalui pengaturan sistem dan kelas (`lms_storage_disk`), tanpa melakukan *hardcoding* jalur berkas.
- **Integritas Penilaian OBE**: Sinkronisasi nilai tugas ke `siakad_nilai_komponen_mhs` dilakukan secara transaksional dengan menjaga relasi antara mahasiswa, kelas, dan komponen penilaian terkait.
- **Keamanan Berkas**: Tautan pengunduhan materi dan pengumpulan tugas menggunakan signed URL berbatas waktu atau *streamed response* terproteksi sehingga berkas tidak dapat diakses secara publik tanpa autentikasi yang sah.
- **Kerahasiaan Data**: Field sensitif seperti token akses storage, credential S3, dan password pengguna tidak akan dikembalikan dalam response API apapun.
- **Penghapusan Berkas**: Saat materi atau tugas dihapus, berkas fisik yang tersimpan di disk storage akan dibersihkan secara otomatis.
