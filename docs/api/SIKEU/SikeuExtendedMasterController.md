# SikeuExtendedMasterController

> **Modul**: SIKEU — Keuangan  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-10-06  
> **Diperbarui**: 2026-10-06

Controller master tambahan SIKEU (jalur kelas, tarif UKT, beasiswa, potongan
mahasiswa) sekaligus daftar & detail tagihan mahasiswa.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/tagihan` | Daftar tagihan mahasiswa (filter status/angkatan/prodi/jalur/kelas + pencarian) | ✅ |
| GET | `/api/v1/sikeu/tagihan/{id}` | Detail invoice tagihan (rincian modul, riwayat bayar, VA) | ✅ |

---

## GET /api/v1/sikeu/tagihan

> Daftar tagihan mahasiswa dengan pagination. Mendukung filter `kelas`
> (mis. `25A`) yang mencocokkan kolom `siakad_mahasiswa.kelas`
> (dinormalisasi uppercase di server).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nomor tagihan, NIM/nama/NIK mahasiswa, atau no. pendaftaran calon |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan |
| `sort_order` | string | ❌ | `desc` | Arah urutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `20` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |
| `status` | string | ❌ | — | Filter status tagihan (`all` = semua) |
| `tahun_angkatan` | integer | ❌ | — | Filter angkatan (`all` = semua) |
| `program_studi_id` | integer | ❌ | — | Filter program studi (`all` = semua) |
| `jalur_kelas` | string | ❌ | — | Filter jalur kelas (`all` = semua) |
| `kelas` | string | ❌ | — | Filter kelas mahasiswa, cth `25A` (`all` = semua; dinormalisasi uppercase) |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "nomor": "INV-SIAKAD-2025-SMT3-00007",
            "nim": "2025010007",
            "nama": "Budi Santoso",
            "angkatan": 2025,
            "jalur": "Reguler",
            "kelompok_ukt": "Level 3",
            "prodi": "Informatika",
            "program_studi_id": 1,
            "total": 4250000,
            "total_potongan": 0,
            "total_bayar": 0,
            "sisa": 4250000,
            "status": "belum_bayar",
            "jatuhTempo": "2026-09-30",
            "source": "SIAKAD"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 45,
        "last_page": 3
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
    "message": "Data tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "kelas": ["Format kelas tidak valid. Gunakan 2 digit angkatan + huruf, cth: 25A."]
    }
}
```

---

## GET /api/v1/sikeu/tagihan/{id}

> Detail invoice tagihan: info mahasiswa, rincian per komponen yang
> dikelompokkan per modul, riwayat pembayaran, dispensasi, dan virtual account.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Detail tagihan berhasil diambil.",
    "data": {
        "id": 1,
        "nomor_tagihan": "INV-SIAKAD-2025-SMT3-00007",
        "total_tagihan": 4250000,
        "total_potongan": 0,
        "total_bayar": 0,
        "sisa": 4250000,
        "status": "belum_bayar"
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
    "message": "Tagihan dengan ID tersebut tidak ditemukan."
}
```

### Catatan Tambahan

> - Filter `kelas` hanya mencocokkan mahasiswa SIAKAD (`siakad_mahasiswa.kelas`); tagihan milik calon mahasiswa SPMB tidak ikut tersaring filter ini.
> - Nilai `kelas` dinormalisasi ke uppercase sebelum dibandingkan, sehingga `?kelas=25a` sama dengan `?kelas=25A`.
