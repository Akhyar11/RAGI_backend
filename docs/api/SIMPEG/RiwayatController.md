# RiwayatController

> **Modul**: SIMPEG (Sistem Informasi Manajemen Kepegawaian)  
> **Base URL**: `/api/simpeg/pegawai/{pegawaiId}`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-09-24  
> **Diperbarui**: 2026-09-24  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/simpeg/pegawai/{pegawaiId}/riwayat-jabatan` | Daftar riwayat jabatan struktural/fungsional pegawai | ✅ Staff / Admin SIMPEG |
| POST | `/api/simpeg/pegawai/{pegawaiId}/riwayat-jabatan` | Menambahkan catatan riwayat jabatan baru pegawai | ✅ Admin SIMPEG |
| GET | `/api/simpeg/pegawai/{pegawaiId}/riwayat-pendidikan` | Daftar riwayat pendidikan formal pegawai | ✅ Staff / Admin SIMPEG |
| POST | `/api/simpeg/pegawai/{pegawaiId}/riwayat-pendidikan` | Menambahkan catatan riwayat pendidikan formal pegawai | ✅ Admin SIMPEG |

---

## GET /api/simpeg/pegawai/{pegawaiId}/riwayat-jabatan

> Mengambil daftar seluruh riwayat formasi dan jabatan fungsional yang pernah/sedang diduduki pegawai.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Filter kata kunci SK atau nomor SK |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan |
| `sort_order` | string | ❌ | `desc` | Arah urutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Limit per halaman (default 15, maks 100) |
| `page` | integer | ❌ | `1` | Nomor halaman (default 1) |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "pegawai_id": 5,
            "jabatan_id": 2,
            "jabatan_fungsional_id": 1,
            "mulai_jabatan": "2024-01-01",
            "selesai_jabatan": null,
            "sk_nomor": "SK/2024/001",
            "sk_tanggal": "2023-12-20",
            "file_sk": "uploads/simpeg/sk/sk_001.pdf",
            "is_active": true,
            "created_at": "2026-09-24T10:00:00.000000Z",
            "updated_at": "2026-09-24T10:00:00.000000Z",
            "jabatan": {
                "id": 2,
                "nama": "Kepala Pusat Bahasa"
            },
            "jabatan_fungsional": {
                "id": 1,
                "nama": "Asisten Ahli (100)"
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1,
        "from": 1,
        "to": 1
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data Pegawai tidak ditemukan."
}
```

---

## POST /api/simpeg/pegawai/{pegawaiId}/riwayat-jabatan

> Menambahkan riwayat penugasan atau penetapan jabatan baru untuk pegawai.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "jabatan_id": 2,
    "jabatan_fungsional_id": 1,
    "mulai_jabatan": "2024-01-01",
    "selesai_jabatan": null,
    "sk_nomor": "SK/2024/001",
    "sk_tanggal": "2023-12-20",
    "file_sk": "uploads/simpeg/sk/sk_001.pdf",
    "is_active": true
}
```

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Riwayat Jabatan berhasil ditambahkan.",
    "data": {
        "id": 1,
        "pegawai_id": 5,
        "jabatan_id": 2,
        "jabatan_fungsional_id": 1,
        "mulai_jabatan": "2024-01-01",
        "selesai_jabatan": null,
        "sk_nomor": "SK/2024/001",
        "sk_tanggal": "2023-12-20",
        "file_sk": "uploads/simpeg/sk/sk_001.pdf",
        "is_active": true,
        "created_at": "2026-09-24T10:05:00.000000Z",
        "updated_at": "2026-09-24T10:05:00.000000Z"
    }
}
```

### Response Error

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
    "message": "Anda tidak memiliki hak akses (permission) untuk menambah Riwayat Jabatan."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data Pegawai tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "message": "The selected jabatan fungsional id is invalid.",
    "errors": {
        "jabatan_fungsional_id": [
            "The selected jabatan fungsional id is invalid."
        ]
    }
}
```

---

## GET /api/simpeg/pegawai/{pegawaiId}/riwayat-pendidikan

> Mengambil daftar riwayat jenjang pendidikan formal pegawai.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nama institusi atau prodi |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan |
| `sort_order` | string | ❌ | `desc` | Arah urutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Limit per halaman (default 15, maks 100) |
| `page` | integer | ❌ | `1` | Nomor halaman (default 1) |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "pegawai_id": 5,
            "jenjang": "s2",
            "nama_institusi": "Institut Teknologi Bandung",
            "program_studi": "Teknik Informatika",
            "bidang_ilmu": "Sistem Informasi",
            "tahun_masuk": 2012,
            "tahun_lulus": 2014,
            "nomor_ijazah": "12345/ITB/S2/2014",
            "file_ijazah": "uploads/simpeg/ijazah/ijazah_s2.pdf",
            "is_pendidikan_terakhir": true,
            "created_at": "2026-09-24T10:00:00.000000Z",
            "updated_at": "2026-09-24T10:00:00.000000Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1,
        "from": 1,
        "to": 1
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data Pegawai tidak ditemukan."
}
```

---

## POST /api/simpeg/pegawai/{pegawaiId}/riwayat-pendidikan

> Menambahkan data riwayat pendidikan formal baru pegawai.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "jenjang": "s3",
    "nama_institusi": "Universitas Indonesia",
    "program_studi": "Ilmu Komputer",
    "bidang_ilmu": "Kecerdasan Buatan",
    "tahun_masuk": 2018,
    "tahun_lulus": 2022,
    "nomor_ijazah": "98765/UI/S3/2022",
    "file_ijazah": "uploads/simpeg/ijazah/ijazah_s3.pdf",
    "is_pendidikan_terakhir": true
}
```

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Riwayat Pendidikan berhasil ditambahkan.",
    "data": {
        "id": 2,
        "pegawai_id": 5,
        "jenjang": "s3",
        "nama_institusi": "Universitas Indonesia",
        "program_studi": "Ilmu Komputer",
        "bidang_ilmu": "Kecerdasan Buatan",
        "tahun_masuk": 2018,
        "tahun_lulus": 2022,
        "nomor_ijazah": "98765/UI/S3/2022",
        "file_ijazah": "uploads/simpeg/ijazah/ijazah_s3.pdf",
        "is_pendidikan_terakhir": true,
        "created_at": "2026-09-24T10:10:00.000000Z",
        "updated_at": "2026-09-24T10:10:00.000000Z"
    }
}
```

### Response Error

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
    "message": "Anda tidak memiliki hak akses (permission) untuk menambah Riwayat Pendidikan."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data Pegawai tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "jenjang": [
            "The selected jenjang is invalid."
        ]
    }
}
```

> **Catatan**: Data riwayat jabatan dan riwayat pendidikan pegawai mereferensikan master jabatan (`simpeg_jabatan`) dan jabatan fungsional (`simpeg_jabatan_fungsional_akademik`). Penghapusan data riwayat dijaga dengan referensi foreign key yang aman. Tidak ada password atau data sensitif yang di-expose.
