# PegawaiController

> **Modul**: SIMPEG (Sistem Informasi Kepegawaian)  
> **Base URL**: `/api/simpeg/pegawai`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-09-14  
> **Diperbarui**: 2026-09-22  

Controller ini mengelola master data pegawai di lingkungan universitas/institusi, mencakup pendaftaran pegawai baru, penetapan multi-role jenis pegawai (`core_roles`), pembuatan akun login SSO otomatis (`core_users`), pengunduhan template import berkas, import pegawai massal via CSV/Excel, serta manajemen biometrik presensi.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/simpeg/pegawai/roles` | Daftar opsi role aktif SSO untuk jenis pegawai | ✅ |
| GET | `/api/simpeg/pegawai/template` | Mengunduh template file CSV impor pegawai | ✅ |
| POST | `/api/simpeg/pegawai/import` | Mengimpor berkas data pegawai massal (.csv, .xlsx) | ✅ |
| GET | `/api/simpeg/pegawai/me` | Profil data pegawai dari user yang sedang login | ✅ |
| GET | `/api/simpeg/pegawai` | Daftar data pegawai (dengan pagination & filter) | ✅ |
| POST | `/api/simpeg/pegawai` | Tambah data pegawai baru (multi-role SSO & shift) | ✅ |
| GET | `/api/simpeg/pegawai/{id}` | Detail profil lengkap pegawai (roles, unit, shift) | ✅ |
| PUT | `/api/simpeg/pegawai/{id}` | Memperbarui data pegawai & sinkronisasi akun SSO | ✅ |
| DELETE | `/api/simpeg/pegawai/{id}` | Menghapus data pegawai (Soft-delete) | ✅ |
| POST | `/api/simpeg/pegawai/{id}/reset-face` | Mereset data biometrik wajah pegawai | ✅ |

---

## GET /api/simpeg/pegawai/roles

> Mengambil daftar seluruh role aktif dari SSO (`core_roles`) untuk digunakan sebagai opsi dinamis pilihan jenis pegawai / peran sistem.

### Headers
```http
Authorization: Bearer <token>
Accept: application/json
```

### Response Success (200 OK)
```json
{
  "status": "success",
  "message": "Daftar role berhasil diambil",
  "data": [
    {
      "id": 5,
      "name": "Dosen Pengajar",
      "slug": "dosen",
      "description": "Tenaga pendidik akademik"
    },
    {
      "id": 6,
      "name": "Tenaga Kependidikan",
      "slug": "tendik",
      "description": "Staf administrasi dan operasional"
    }
  ]
}
```

### Response Error (401 Unauthorized)
```json
{
  "status": "error",
  "message": "Unauthenticated."
}
```

---

## GET /api/simpeg/pegawai/template

> Mengunduh berkas template format CSV (UTF-8 BOM) untuk impor data pegawai secara massal.

### Headers
```http
Authorization: Bearer <token>
```

### Response
- Header: `Content-Type: text/csv; charset=UTF-8`
- Header: `Content-Disposition: attachment; filename="template_import_pegawai.csv"`
- Body: Berkas CSV mentah dengan kolom header:
  `nip,nik,nama_lengkap,email,telepon,jenis_kelamin,tempat_lahir,tanggal_lahir,jenis_pegawai,status_kepegawaian,unit_kerja,jabatan,tanggal_masuk,alamat`

---

## POST /api/simpeg/pegawai/import

> Mengunggah dan memproses data pegawai secara massal dari berkas CSV atau XLSX.

### Headers
```http
Authorization: Bearer <token>
Content-Type: multipart/form-data
```

### Request Body (Form Data)
| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `file` | file | ✅ | Berkas `.csv`, `.txt`, `.xlsx`, atau `.xls` (Maksimal 10 MB) |

### Response Success (200 OK)
```json
{
  "status": "success",
  "message": "Proses impor selesai: 2 berhasil, 0 gagal.",
  "data": {
    "total": 2,
    "success": 2,
    "failed": 0,
    "errors": [],
    "data": [
      {
        "id": 12,
        "nama": "Dr. Ahmad Fadhil, M.Kom.",
        "nip": "198501152010121001",
        "email": "ahmad.fadhil@campus.ac.id",
        "username": "198501152010121001"
      }
    ]
  }
}
```

### Response Error (422 Unprocessable Content)
```json
{
  "status": "error",
  "message": "Berkas yang diunggah tidak valid atau kolom wajib tidak lengkap.",
  "errors": {
    "file": ["Format berkas tidak didukung. Harap unggah berkas .csv atau .xlsx."]
  }
}
```

---

## GET /api/simpeg/pegawai/me

> Mengambil data profil pegawai milik pengguna yang sedang terautentikasi (login).

### Headers
```http
Authorization: Bearer <token>
Accept: application/json
```

### Response Success (200 OK)
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "user_id": 2,
    "unit_kerja_id": 3,
    "nip": "199208152020011002",
    "nidn": "0415018501",
    "nuptk": "3560763664230001",
    "nik": "3271012345670001",
    "nama_lengkap": "Wasis Utama",
    "gelar_depan": "Dr.",
    "gelar_belakang": "M.T.",
    "nama_gelar": "Dr. Wasis Utama, M.T.",
    "jenis_kelamin": "L",
    "status_kepegawaian": "tetap_yayasan",
    "status": "aktif",
    "tanggal_masuk": "2020-01-02",
    "shift_template_id": 1,
    "telepon": "081234567890",
    "alamat": "Jl. Merdeka No. 45, Bandung"
  }
}
```

---

## GET /api/simpeg/pegawai

> Mengambil daftar pegawai dengan dukungan pencarian teks, whitelist sorting, dan server-side pagination.

### Headers
```http
Authorization: Bearer <token>
Accept: application/json
```

### Query Params
| Parameter | Tipe | Default | Wajib | Keterangan |
|---|---|---|---|---|
| `search` | string | - | ❌ | Pencarian pada kolom nama, nip, nidn, nuptk, atau nik |
| `sort_by` | string | `created_at` | ❌ | Kolom pengurutan (whitelist: `created_at`, `updated_at`, `nama_lengkap`, `nip`) |
| `sort_order` | string | `desc` | ❌ | Arah pengurutan: `asc` atau `desc` |
| `page` | integer | `1` | ❌ | Halaman data yang dituju |
| `per_page` | integer | `15` | ❌ | Jumlah data per halaman (default 15, maks 100) |
| `unit_kerja_id` | integer | - | ❌ | Filter berdasarkan ID unit kerja |
| `role_id` | integer | - | ❌ | Filter berdasarkan ID role SSO |
| `status` | string | - | ❌ | Filter status keaktifan (`aktif`, `non_aktif`, `pensiun`) |
| `shift_template_id` | integer | - | ❌ | Filter berdasarkan penugasan shift kerja |

### Response Success (200 OK)
```json
{
  "status": "success",
  "message": "Data retrieved successfully",
  "data": [
    {
      "id": 1,
      "user_id": 2,
      "unit_kerja_id": 3,
      "shift_template_id": 1,
      "nip": "199001012022011001",
      "nidn": "0415018501",
      "nuptk": "3560763664230001",
      "nik": "3271012345670001",
      "nama_lengkap": "Wasis Utama",
      "gelar_depan": "Dr.",
      "gelar_belakang": "M.Kom.",
      "nama_gelar": "Dr. Wasis Utama, M.Kom.",
      "jenis_kelamin": "L",
      "status_kepegawaian": "tetap_yayasan",
      "tanggal_masuk": "2024-01-15",
      "status": "aktif",
      "telepon": "081234567890",
      "alamat": "Jl. Pendidikan No. 45, Bandung",
      "created_at": "2026-09-22T22:15:00.000000Z",
      "updated_at": "2026-09-22T22:15:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 1,
    "last_page": 1,
    "from": 1,
    "to": 1
  },
  "filters": {
    "search": "",
    "sort_by": "created_at",
    "sort_order": "desc"
  }
}
```

### Response Error (403 Forbidden)
```json
{
  "status": "error",
  "message": "Anda tidak memiliki hak akses (permission) untuk melihat Data Pegawai."
}
```

---

## POST /api/simpeg/pegawai

> Menambahkan pegawai baru ke SIMPEG, mengaitkan jenis pegawai (role SSO), dan otomatis membuatkan akun login SSO jika belum tersedia.

### Headers
```http
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

### Request Body
```json
{
  "nama_lengkap": "Wasis Utama",
  "gelar_depan": "Dr.",
  "gelar_belakang": "M.Kom.",
  "nip": "199001012022011001",
  "nidn": "0415018501",
  "nuptk": "3560763664230001",
  "nik": "3271012345670001",
  "email": "wasis.utama@campus.ac.id",
  "tanggal_masuk": "2024-01-15",
  "shift_template_id": 1,
  "unit_kerja_id": 2,
  "role_ids": [5],
  "status_kepegawaian": "tetap_yayasan",
  "status": "aktif",
  "jenis_kelamin": "L",
  "tempat_lahir": "Bandung",
  "tanggal_lahir": "1990-01-01",
  "telepon": "081234567890",
  "alamat": "Jl. Pendidikan No. 45, Bandung"
}
```

### Response Success (201 Created)
```json
{
  "status": "success",
  "message": "Data Pegawai berhasil ditambahkan.",
  "data": {
    "id": 15,
    "user_id": 35,
    "unit_kerja_id": 2,
    "shift_template_id": 1,
    "nip": "199001012022011001",
    "nidn": "0415018501",
    "nuptk": "3560763664230001",
    "nik": "3271012345670001",
    "nama_lengkap": "Wasis Utama",
    "gelar_depan": "Dr.",
    "gelar_belakang": "M.Kom.",
    "nama_gelar": "Dr. Wasis Utama, M.Kom.",
    "jenis_kelamin": "L",
    "status_kepegawaian": "tetap_yayasan",
    "tanggal_masuk": "2024-01-15",
    "status": "aktif",
    "telepon": "081234567890",
    "alamat": "Jl. Pendidikan No. 45, Bandung",
    "created_at": "2026-09-22T22:15:00.000000Z",
    "updated_at": "2026-09-22T22:15:00.000000Z"
  }
}
```

### Response Error (422 Unprocessable Content)
```json
{
  "status": "error",
  "message": "Validasi gagal.",
  "errors": {
    "nip": ["NIP wajib diisi."],
    "nidn": ["NIDN wajib diisi."],
    "nuptk": ["NUPTK wajib diisi."],
    "tanggal_masuk": ["Tanggal masuk wajib diisi."],
    "shift_template_id": ["Shift Kerja (Jadwal Presensi) wajib dipilih."]
  }
}
```

### Response Error (403 Forbidden)
```json
{
  "status": "error",
  "message": "Anda tidak memiliki hak akses (permission) untuk menambah Data Pegawai."
}
```

---

## GET /api/simpeg/pegawai/{id}

> Mengambil detail profil lengkap pegawai berdasarkan ID entitas.

### Headers
```http
Authorization: Bearer <token>
Accept: application/json
```

### URL Parameters
| Parameter | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `id` | integer | ✅ | ID entitas pegawai (`simpeg_pegawai.id`) |

### Response Success (200 OK)
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "user_id": 2,
    "unit_kerja_id": 3,
    "nip": "199001012022011001",
    "nidn": "0415018501",
    "nuptk": "3560763664230001",
    "nik": "3271012345670001",
    "nama_lengkap": "Wasis Utama",
    "gelar_depan": "Dr.",
    "gelar_belakang": "M.Kom.",
    "nama_gelar": "Dr. Wasis Utama, M.Kom.",
    "jenis_kelamin": "L",
    "status_kepegawaian": "tetap_yayasan",
    "status": "aktif",
    "tanggal_masuk": "2024-01-15",
    "shift_template_id": 1,
    "unit_kerja": {
      "id": 3,
      "kode": "FTI",
      "nama": "Fakultas Teknologi Informasi"
    },
    "shift_template": {
      "id": 1,
      "name": "Shift Reguler Kampus"
    }
  }
}
```

### Response Error (404 Not Found)
```json
{
  "status": "error",
  "message": "Pegawai tidak ditemukan."
}
```

---

## PUT /api/simpeg/pegawai/{id}

> Memperbarui data pegawai di SIMPEG beserta perubahan gelar, tanggal masuk, penugasan shift kerja, dan sinkronisasi role SSO.

### Headers
```http
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

### URL Parameters
| Parameter | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `id` | integer | ✅ | ID entitas pegawai (`simpeg_pegawai.id`) |

### Request Body
```json
{
  "nama_lengkap": "Wasis Utama",
  "gelar_depan": "Dr.",
  "gelar_belakang": "M.Kom.",
  "nip": "199001012022011001",
  "nidn": "0415018501",
  "nuptk": "3560763664230001",
  "tanggal_masuk": "2024-01-15",
  "shift_template_id": 1,
  "unit_kerja_id": 2,
  "role_ids": [5],
  "status_kepegawaian": "tetap_yayasan",
  "status": "aktif",
  "jenis_kelamin": "L"
}
```

### Response Success (200 OK)
```json
{
  "status": "success",
  "message": "Data Pegawai berhasil diperbarui.",
  "data": {
    "id": 15,
    "nama_lengkap": "Wasis Utama",
    "gelar_depan": "Dr.",
    "gelar_belakang": "M.Kom.",
    "nama_gelar": "Dr. Wasis Utama, M.Kom.",
    "nip": "199001012022011001",
    "nidn": "0415018501",
    "nuptk": "3560763664230001",
    "tanggal_masuk": "2024-01-15",
    "shift_template_id": 1,
    "status": "aktif",
    "updated_at": "2026-09-22T22:16:00.000000Z"
  }
}
```

### Response Error (404 Not Found)
```json
{
  "status": "error",
  "message": "Pegawai tidak ditemukan."
}
```

---

## DELETE /api/simpeg/pegawai/{id}

> Menghapus data pegawai secara lembut (*soft-delete*). Data diproteksi dengan kolom `deleted_at` dan tidak dihapus secara permanen dari database.

### Headers
```http
Authorization: Bearer <token>
Accept: application/json
```

### URL Parameters
| Parameter | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `id` | integer | ✅ | ID entitas pegawai (`simpeg_pegawai.id`) |

### Response Success (200 OK)
```json
{
  "status": "success",
  "message": "Data Pegawai berhasil dinonaktifkan (soft-delete)."
}
```

### Response Error (404 Not Found)
```json
{
  "status": "error",
  "message": "Pegawai tidak ditemukan."
}
```

---

## POST /api/simpeg/pegawai/{id}/reset-face

> Mereset data biometrik wajah pegawai (`face_embedding` dan `face_enrolled_at`). Digunakan oleh Admin/HR ketika pegawai berganti perangkat atau gagal mengenali wajah sehingga perlu melakukan enrollment biometrik wajah ulang melalui aplikasi mobile presensi.

### Headers
```http
Authorization: Bearer <token>
Accept: application/json
```

### URL Parameters
| Parameter | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `id` | integer | ✅ | ID entitas pegawai (`simpeg_pegawai.id`) |

### Response Success (200 OK)
```json
{
  "status": "success",
  "message": "Data biometrik wajah pegawai berhasil direset. Pegawai dapat mendaftarkan ulang wajahnya via aplikasi mobile presensi.",
  "data": {
    "id": 1,
    "nama_lengkap": "Dr. Wasis Utama, M.T.",
    "is_face_enrolled": false,
    "face_enrolled_at": null
  }
}
```

### Response Error (404 Not Found)
```json
{
  "status": "error",
  "message": "Pegawai tidak ditemukan."
}
```

---

## Catatan Keamanan & Sistem
1. **Penerapan Soft-Delete**: Model `Pegawai` menggunakan trait `Illuminate\Database\Eloquent\SoftDeletes`. Menghapus data pegawai melalui endpoint `DELETE` hanya akan mengisi kolom `deleted_at` untuk menjamin integritas riwayat presensi, gaji, dan riwayat jabatan.
2. **Proteksi Password Akun SSO**: Password default (`indonusa`) yang di-generate untuk akun login SSO (`core_users`) disimpan dalam bentuk hash terenkripsi Bcrypt dan **TIDAK PERNAH dikembalikan** pada respons JSON endpoint API manapun demi privasi dan keamanan sistem.
