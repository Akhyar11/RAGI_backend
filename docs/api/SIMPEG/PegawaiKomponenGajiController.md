# PegawaiKomponenGajiController

> **Modul**: SIMPEG / **Base URL**: /api/simpeg/payroll / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-09-19

Modul ini mengelola konfigurasi kustomisasi komponen gaji per pegawai secara nested sub-resource. Admin dan pengelola kepegawaian dapat menyesuaikan nominal khusus, mengaktifkan/menonaktifkan komponen tertentu untuk pegawai spesifik, serta menambahkan catatan SK/insentif.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/simpeg/payroll/pegawai/{pegawaiId}/komponen` | Ambil konfigurasi komponen gaji milik pegawai | ✅ Bearer |
| PUT | `/api/simpeg/payroll/pegawai/{pegawaiId}/komponen` | Simpan / perbarui konfigurasi komponen gaji pegawai | ✅ Bearer |

---

## 1. GET /api/simpeg/payroll/pegawai/{pegawaiId}/komponen

> Mengambil daftar konfigurasi komponen gaji fleksibel milik seorang pegawai tertentu.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters
*Tidak memerlukan query parameters.*

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Data konfigurasi komponen gaji pegawai berhasil diambil",
    "data": {
        "pegawai": {
            "id": 1,
            "nama_lengkap": "Dr. Budi Santoso",
            "nip": "198001012005011001"
        },
        "komponen": [
            {
                "komponen_gaji_id": 1,
                "kode": "GP",
                "nama": "Gaji Pokok",
                "jenis": "pendapatan",
                "tipe_nilai": "nominal_tetap",
                "nilai_default": 4500000,
                "nominal_kustom": 5000000,
                "is_active": true,
                "catatan": "Kenaikan berkala"
            }
        ]
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
    "message": "Anda tidak memiliki hak akses melihat konfigurasi komponen pegawai."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Pegawai tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "pegawaiId": [
            "ID pegawai tidak valid."
        ]
    }
}
```

---

## 2. PUT /api/simpeg/payroll/pegawai/{pegawaiId}/komponen

> Menyimpan atau memperbarui konfigurasi kustomisasi komponen gaji seorang pegawai.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Query Parameters
*Tidak memerlukan query parameters.*

### Request Body

| Field | Type | Required | Validasi | Deskripsi |
|---|---|---|---|---|
| `komponen` | array | ✅ | `required|array` | Daftar komponen kustom |
| `komponen.*.komponen_gaji_id` | integer | ✅ | `required|exists:simpeg_master_komponen_gaji,id` | ID master komponen gaji |
| `komponen.*.nominal_kustom` | numeric | ❌ | `nullable|numeric|min:0` | Nominal kustom khusus pegawai |
| `komponen.*.is_active` | boolean | ❌ | `boolean` | Status keaktifan komponen |
| `komponen.*.catatan` | string | ❌ | `nullable|string` | Catatan penyesuaian |

```json
{
    "komponen": [
        {
            "komponen_gaji_id": 1,
            "nominal_kustom": 5000000,
            "is_active": true,
            "catatan": "Kenaikan berkala"
        }
    ]
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Konfigurasi komponen gaji untuk Dr. Budi Santoso berhasil disimpan.",
    "data": {
        "id": 1,
        "nama_lengkap": "Dr. Budi Santoso",
        "nip": "198001012005011001",
        "email": "budi.santoso@kampus.ac.id",
        "status_pegawai": "tetap",
        "created_at": "2026-07-28T14:00:00.000000Z",
        "updated_at": "2026-09-19T08:15:00.000000Z"
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
    "message": "Anda tidak memiliki hak akses mengatur komponen gaji pegawai."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Pegawai tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "komponen": [
            "Bidang komponen wajib diisi."
        ]
    }
}
```

---

## Catatan Khusus & Integritas Data
- Konfigurasi komponen tersimpan pada tabel `simpeg_pegawai_komponen_gaji`.
- Perubahan komponen gaji dipantau oleh `PegawaiKomponenGajiObserver` untuk pencatatan audit log perubahan nilai sensitif.
- **Soft Delete**: Data riwayat komponen dan relasi master dilindungi untuk menjamin jejak audit keuangan/kepegawaian.
- **Password & Token**: Password, hashed password, dan token autentikasi tidak pernah dikembalikan dalam response API ini.
