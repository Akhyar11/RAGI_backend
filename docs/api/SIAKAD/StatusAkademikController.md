# StatusAkademikController

> **Modul**: SIAKAD  
> **Base URL**: `/api/v1/siakad`  
> **Autentikasi**: Bearer Token (Sanctum) — kecuali dinyatakan lain  
> **Dibuat**: 2026-10-14  
> **Diperbarui**: 2026-10-14

Cuti mahasiswa, riwayat status akademik, daftar & pengesahan (lock) KHS, dan rekap absensi syarat UAS.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/siakad/status/cuti` | Daftar pengajuan cuti | ✅ `siakad.mahasiswa.read` |
| POST | `/api/v1/siakad/status/cuti` | Buat pengajuan cuti | ✅ `siakad.mahasiswa.manage` |
| PATCH | `/api/v1/siakad/status/cuti/{id}/proses` | Setujui/tolak cuti (otomatis ubah status mahasiswa) | ✅ `siakad.mahasiswa.manage` |
| GET | `/api/v1/siakad/status/log` | Riwayat perubahan status akademik | ✅ `siakad.mahasiswa.read` |
| GET | `/api/v1/siakad/perkuliahan/khs` | Daftar KHS | ✅ `siakad.nilai.read` |
| POST | `/api/v1/siakad/perkuliahan/khs/bulk-lock` | Pengesahan massal KHS per periode | ✅ `siakad.nilai.manage` |
| PATCH | `/api/v1/siakad/perkuliahan/khs/{id}/lock` | Kunci/buka pengesahan KHS (BAAK) | ✅ `siakad.nilai.manage` |
| GET | `/api/v1/siakad/perkuliahan/kelas/{kelasId}/rekap-absensi` | Rekap kehadiran + kelayakan UAS (≥75%) | ✅ `siakad.nilai.read` |

### Headers Baku (semua endpoint authed)

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | Hanya POST/PUT/PATCH |

### Response Error Baku

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

## [GET] /api/v1/siakad/status/cuti

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `status` | string | ❌ | — | `pending`, `disetujui`, `ditolak` |
| `mahasiswa_id` | integer | ❌ | — | Filter mahasiswa |
| `search` | string | ❌ | — | Cari NIM / nama |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `sort_by` | string | ❌ | `created_at` | `created_at`, `updated_at`, `id` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `page` | integer | ❌ | `1` | Halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data cuti mahasiswa berhasil dimuat",
    "data": [{ "id": 1, "mahasiswa_id": 5, "status": "pending", "alasan": "..." }],
    "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1 }
}
```

---

## [POST] /api/v1/siakad/status/cuti

### Request Body

```json
{
    "mahasiswa_id": "integer, required, exists:siakad_mahasiswa,id",
    "tahun_akademik_id": "integer, required, exists:siakad_tahun_akademik,id",
    "alasan": "string, required",
    "file_surat": "string, nullable, max:255"
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Pengajuan cuti berhasil dibuat",
    "data": { "id": 1, "status": "pending" }
}
```

### Response Error

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "alasan": ["Alasan wajib diisi."]
    }
}
```

---

## [PATCH] /api/v1/siakad/status/cuti/{id}/proses

Menyetujui cuti otomatis mengubah `siakad_mahasiswa.status` menjadi `cuti` (KRS terblokir via `assertMahasiswaAktif`).

### Request Body

```json
{
    "status": "disetujui | ditolak (required)",
    "alasan": "string, nullable"
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Pengajuan cuti disetujui.",
    "data": { "id": 1, "status": "disetujui" }
}
```

### Response Error

**404 Not Found**
```json
{
    "status": "error",
    "message": "CutiMahasiswa tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "status": ["Status harus disetujui atau ditolak."]
    }
}
```

---

## [GET] /api/v1/siakad/status/log

Riwayat perubahan status akademik mahasiswa (ditarik otomatis oleh observer saat `siakad_mahasiswa.status` berubah).

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `mahasiswa_id` | integer | ❌ | — | Filter mahasiswa |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `sort_by` | string | ❌ | `created_at` | `created_at`, `updated_at`, `id` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `page` | integer | ❌ | `1` | Halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": [
        { "id": 1, "mahasiswa_id": 5, "status_lama": "aktif", "status_baru": "cuti" }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1 }
}
```

---

## [GET] /api/v1/siakad/perkuliahan/khs

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `mahasiswa_id` | integer | ❌ | — | Filter mahasiswa |
| `tahun_akademik_id` | integer | ❌ | — | Filter periode |
| `program_studi_id` | integer | ❌ | — | Filter program studi |
| `search` | string | ❌ | — | Cari NIM / nama mahasiswa |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `sort_by` | string | ❌ | `created_at` | `created_at`, `updated_at`, `id` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `page` | integer | ❌ | `1` | Halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": [
        { "id": 3, "mahasiswa_id": 5, "ips": 3.5, "ipk": 3.4, "is_locked": false }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1 }
}
```

---

## [PATCH] /api/v1/siakad/perkuliahan/khs/{id}/lock

### Request Body

```json
{
    "is_locked": "boolean, required"
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "KHS dikunci dan disahkan.",
    "data": { "id": 3, "is_locked": true }
}
```

### Response Error

**404 Not Found**
```json
{
    "status": "error",
    "message": "Khs tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "is_locked": ["Status kunci wajib diisi."]
    }
}
```

### Catatan Tambahan

> - KHS yang terkunci tidak dihitung ulang oleh `SiakadAkademikService::hitungKhsDanIpk`.
> - Aksi lock tercatat di `audit_logs` (modul `SIAKAD`, action `approve`/`update`).

---

## [POST] /api/v1/siakad/perkuliahan/khs/bulk-lock

Pengesahan massal seluruh KHS satu periode (+prodi opsional). Tercatat sebagai satu batch audit.

### Request Body

```json
{
    "tahun_akademik_id": "integer, required, exists:siakad_tahun_akademik,id",
    "program_studi_id": "integer, nullable, exists:siakad_program_studi,id",
    "is_locked": "boolean, required"
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Pengesahan massal selesai: 128 KHS dikunci.",
    "data": { "affected": 128 }
}
```

### Response Error

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "tahun_akademik_id": ["Periode akademik wajib dipilih."]
    }
}
```

---

## [GET] /api/v1/siakad/perkuliahan/kelas/{kelasId}/rekap-absensi

Rekap kehadiran per mahasiswa satu kelas + flag kelayakan UAS (hadir ≥ 75%).

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": [
        {
            "mahasiswa": { "id": 5, "nim": "2401001" },
            "total_pertemuan": 14,
            "hadir": 12,
            "persentase_hadir": 85.7,
            "eligible_uas": true
        }
    ]
}
```

### Response Error

**404 Not Found**
```json
{
    "status": "error",
    "message": "Kelas tidak ditemukan."
}
```
