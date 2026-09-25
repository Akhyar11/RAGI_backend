# JabatanFungsionalController

> **Modul**: SIMPEG (Sistem Informasi Manajemen Kepegawaian)  
> **Base URL**: `/api/simpeg/jabatan-fungsional`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-09-24  
> **Diperbarui**: 2026-09-25  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/simpeg/jabatan-fungsional` | Daftar seluruh jenjang jabatan fungsional dosen dengan pagination & filter | ✅ Staff / Admin SIMPEG |
| POST | `/api/simpeg/jabatan-fungsional` | Menambahkan master jabatan fungsional dosen baru | ✅ Admin SIMPEG |
| GET | `/api/simpeg/jabatan-fungsional/master/golongan` | Daftar distinct golongan jabatan fungsional akademik | ✅ Staff / Admin SIMPEG |

---

## GET /api/simpeg/jabatan-fungsional

> Mengambil daftar master jenjang jabatan fungsional akademik (Jafung) dosen.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nama jafung atau nama golongan |
| `golongan` | string | ❌ | — | Filter golongan (`tenaga_pengajar`, `asisten_ahli`, `lektor`, `lektor_kepala`, `guru_besar`) |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan (`nama`, `golongan`, `angka_kredit_min`, `created_at`) |
| `sort_order` | string | ❌ | `desc` | Arah urutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses

**200 OK (dengan Pagination)**
```json
{
    "status": "success",
    "message": "Data retrieved successfully",
    "data": [
        {
            "id": 1,
            "nama": "Asisten Ahli (100)",
            "golongan": "asisten_ahli",
            "angka_kredit_min": 100,
            "angka_kredit_max": 150,
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
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data jabatan fungsional tidak ditemukan."
}
```

---

## POST /api/simpeg/jabatan-fungsional

> Menambahkan data master jabatan fungsional dosen baru.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "nama": "Tenaga Pengajar",
    "angka_kredit_min": 0,
    "angka_kredit_max": 100,
    "golongan": "tenaga_pengajar"
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Jabatan Fungsional berhasil dibuat.",
    "data": {
        "id": 2,
        "nama": "Tenaga Pengajar",
        "angka_kredit_min": 0,
        "angka_kredit_max": 100,
        "golongan": "tenaga_pengajar",
        "created_at": "2026-09-25T12:00:00.000000Z",
        "updated_at": "2026-09-25T12:00:00.000000Z"
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak akses (permission) untuk menambah Jabatan Fungsional."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data jabatan fungsional tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "nama": [
            "Nama jabatan fungsional sudah digunakan."
        ],
        "golongan": [
            "Golongan wajib diisi."
        ]
    }
}
```

---

## GET /api/simpeg/jabatan-fungsional/master/golongan

> Mengambil daftar unik opsi golongan jabatan fungsional akademik untuk dropdown form.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nama atau kode golongan |
| `sort_by` | string | ❌ | `urutan` | Kolom pengurutan (`kode`, `nama`, `urutan`, `created_at`) |
| `sort_order` | string | ❌ | `asc` | Arah urutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses

**200 OK (dengan Pagination)**
```json
{
    "status": "success",
    "message": "Daftar master golongan jabatan fungsional berhasil dimuat.",
    "data": [
        {
            "id": 1,
            "value": "tenaga_pengajar",
            "label": "Tenaga Pengajar",
            "pangkat": "Penata Muda",
            "ruang": "III/a"
        },
        {
            "id": 2,
            "value": "asisten_ahli",
            "label": "Asisten Ahli",
            "pangkat": "Penata Muda / Penata Muda Tingkat I",
            "ruang": "III/a - III/b"
        },
        {
            "id": 3,
            "value": "lektor",
            "label": "Lektor",
            "pangkat": "Penata / Penata Tingkat I",
            "ruang": "III/c - III/d"
        },
        {
            "id": 4,
            "value": "lektor_kepala",
            "label": "Lektor Kepala",
            "pangkat": "Pembina / Pembina Tingkat I / Pembina Utama Muda",
            "ruang": "IV/a - IV/c"
        },
        {
            "id": 5,
            "value": "guru_besar",
            "label": "Guru Besar",
            "pangkat": "Pembina Utama Madya / Pembina Utama",
            "ruang": "IV/d - IV/e"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 5,
        "last_page": 1,
        "from": 1,
        "to": 5
    },
    "filters": {
        "search": "",
        "sort_by": "urutan",
        "sort_order": "asc"
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

### Catatan Tambahan

> - Endpoint ini mendukung soft-delete untuk menjaga integritas relasi data riwayat kepangkatan dan usulan jafung dosen.
> - Field `password` dan data kredensial tidak pernah dikembalikan dalam response endpoint ini.
