# KelulusanController

> **Modul**: SIAKAD  
> **Base URL**: `/api/v1/siakad/kelulusan`  
> **Autentikasi**: Bearer Token (Sanctum) — kecuali dinyatakan lain  
> **Dibuat**: 2026-10-14  
> **Diperbarui**: 2026-10-14

Yudisium/kelulusan (syarat min. IPK 2.00 & 144 SKS; penetapan otomatis mengubah status mahasiswa menjadi `lulus`) dan sensing status bermasalah untuk dashboard BAAK.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/siakad/kelulusan` | Daftar kelulusan | ✅ `siakad.mahasiswa.read` |
| GET | `/api/v1/siakad/kelulusan/sensing` | Hitung mangkir/cuti/dropout/aktif-tanpa-KRS | ✅ `siakad.mahasiswa.read` |
| GET | `/api/v1/siakad/kelulusan/cek-syarat/{mahasiswaId}` | Cek IPK & SKS vs syarat | ✅ `siakad.mahasiswa.read` |
| POST | `/api/v1/siakad/kelulusan` | Tetapkan yudisium | ✅ `siakad.mahasiswa.manage` |

---

## [GET] /api/v1/siakad/kelulusan

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari NIM / nama |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": [
        { "id": 1, "mahasiswa_id": 5, "ipk_akhir": 3.4, "total_sks": 150 }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1 }
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

---

## [GET] /api/v1/siakad/kelulusan/sensing

Hitung status bermasalah untuk dashboard BAAK (tanpa parameter).

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
    "data": {
        "mangkir": 3,
        "cuti": 5,
        "dropout": 1,
        "aktif_tanpa_krs_TA_aktif": 12
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

---

## [GET] /api/v1/siakad/kelulusan/cek-syarat/{mahasiswaId}

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
    "data": {
        "mahasiswa": { "id": 5, "nim": "2401001" },
        "ipk": 3.4,
        "total_sks": 150,
        "syarat_sks": 144,
        "syarat_ipk": 2,
        "memenuhi": true
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
    "message": "Mahasiswa tidak ditemukan."
}
```

---

## [POST] /api/v1/siakad/kelulusan

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "mahasiswa_id": "integer, required, exists:siakad_mahasiswa,id",
    "tahun_akademik_id": "integer, required, exists:siakad_tahun_akademik,id",
    "tanggal_sidang": "date, nullable",
    "ipk_akhir": "numeric, required, 0-4 (min 2.00 enforced)",
    "total_sks": "integer, required, min 100 (min 144 enforced)",
    "masa_studi_semester": "integer, required, 1-28",
    "predikat": "string, nullable (master referensi predikat_kelulusan)",
    "nomor_ijazah": "string, nullable, unique",
    "tanggal_ijazah": "date, nullable"
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Yudisium/kelulusan berhasil ditetapkan.",
    "data": { "id": 1, "mahasiswa_id": 5, "ipk_akhir": 3.4, "total_sks": 150 }
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

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Syarat yudisium belum terpenuhi (min IPK 2.00 & 144 SKS)."
}
```

```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "ipk_akhir": ["IPK minimal 2.00 untuk yudisium."]
    }
}
```

### Catatan Tambahan

> - Penetapan yudisium tercatat di `audit_logs` (modul `SIAKAD`, action `approve`).
