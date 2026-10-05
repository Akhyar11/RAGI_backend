# SinapraImportController

> **Modul**: SINAPRA (Sarana, Prasarana, & Aset)  
> **Base URL**: `/api/sinapra/import`  
> **Autentikasi**: Bearer Token (Passport / Sanctum)  
> **Dibuat**: 2026-10-05  
> **Diperbarui**: 2026-10-05  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| POST | `/api/sinapra/import/{entity}` | Import berkas Excel/CSV ke entitas data SINAPRA | ✅ |
| GET | `/api/sinapra/import/template/{entity}` | Unduh berkas template Excel (.xlsx) resmi untuk import | ✅ |

### Daftar Entitas (`{entity}`) yang Didukung:
- `gedung` — Gedung Kampus
- `tipe-ruangan` — Master Tipe Ruangan
- `ruangan` — Ruangan Kampus
- `kategori-aset` — Master Kategori Aset
- `kategori-bhp` — Master Kategori Bahan Habis Pakai
- `satuan` — Master Satuan Pengukuran
- `vendor` — Master Vendor & Mitra Rekanan
- `aset` — Inventaris Aset & Sarana Kampus

---

## Headers Standar
- `Authorization: Bearer {token}`
- `Accept: application/json`

---

## POST /api/sinapra/import/{entity}

Deskripsi: Mengunggah berkas Excel (`.xlsx`, `.xls`) atau CSV untuk mengimpor dan memperbarui data entitas SINAPRA secara otomatis. Mendukung file tunggal maupun file workbook multi-sheet (seperti `DATA_MASTER_SINAPRA.xlsx`).

### URL Parameters
- `entity` (string, required) - Nama entitas target (`gedung`, `tipe-ruangan`, `ruangan`, `kategori-aset`, `kategori-bhp`, `satuan`, `vendor`, `aset`).

### Request Body (`multipart/form-data`)
| Field | Tipe | Wajib | Deskripsi |
|---|---|---|---|
| `file` | file | Ya | File Excel / CSV (mimes: `xlsx`, `xls`, `csv`, `txt`, maks 10MB) |

### Response Sukses (201 Created / 200 OK)
```json
{
    "status": "success",
    "message": "Proses import selesai. Berhasil dibuat: 10, diperbarui: 2, gagal: 0.",
    "data": {
        "created": 10,
        "updated": 2,
        "failed": 0,
        "total_processed": 12,
        "errors": []
    }
}
```

### Response Error Berkas / Precondition Gagal (400 Bad Request)
```json
{
    "status": "error",
    "message": "Berkas Excel kosong atau tidak memiliki baris data setelah header."
}
```

### Response Validasi / Error (422 Unprocessable Entity)
```json
{
    "status": "error",
    "message": "Format file harus berupa .xlsx, .xls, atau .csv.",
    "errors": {
        "file": [
            "Format file harus berupa .xlsx, .xls, atau .csv."
        ]
    }
}
```

### Response Autentikasi & Izin Akses (401 Unauthorized / 403 Forbidden)
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```
```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak akses untuk melakukan operasi ini pada entitas gedung."
}
```

---

## GET /api/sinapra/import/template/{entity}

Deskripsi: Menghasilkan dan mengunduh berkas template Excel `.xlsx` terstandarisasi lengkap dengan styling header dan contoh baris data pengisian.

### URL Parameters
- `entity` (string, required) - Nama entitas target (`gedung`, `tipe-ruangan`, `ruangan`, `kategori-aset`, `kategori-bhp`, `satuan`, `vendor`, `aset`).

### Response (200 OK)
- **Content-Type**: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
- **Content-Disposition**: `attachment; filename="Template_Import_Sinapra_{entity}.xlsx"`
- File binary stream Excel.
