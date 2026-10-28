# PembayaranMahasiswaPotonganController

> **Modul**: SIKEU (Keuangan)  
> **Base URL**: `/api/v1/sikeu/pembayaran-mahasiswa`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-10-28  
> **Diperbarui**: 2026-10-28

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/pembayaran-mahasiswa/potongan` | Daftar potongan mahasiswa | ✅ |
| GET | `/api/v1/sikeu/pembayaran-mahasiswa/potongan/{id}` | Detail potongan mahasiswa | ✅ |
| POST | `/api/v1/sikeu/pembayaran-mahasiswa/potongan` | Tambah potongan dan aplikasikan ke tagihan | ✅ |
| DELETE | `/api/v1/sikeu/pembayaran-mahasiswa/potongan/{id}` | Batalkan potongan | ✅ |

---

## POST /api/v1/sikeu/pembayaran-mahasiswa/potongan

> Menambahkan potongan wajib dan mengaplikasikannya langsung ke tagihan terpilih. Nomor surat dari Modul Arsip wajib diisi.

### Headers
| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Query Parameters
| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Pencarian |
| `status` | string | ❌ | all | Filter status |
| `tipe_referensi` | string | ❌ | all | Filter tipe referensi |
| `sort_by` | string | ❌ | `id` | Kolom pengurutan |
| `sort_order` | string | ❌ | `desc` | Arah urutan (`asc`/`desc`) |
| `per_page` | integer | ❌ | `15` | Jumlah per halaman (maks. 100) |

### Request Body
```json
{
    "mahasiswa_id": 10,
    "calon_mahasiswa_id": null,
    "is_calon_mahasiswa": false,
    "tipe_referensi": "mahasiswa",
    "nama_potongan": "Potongan Wajib",
    "nomor_sk": null,
    "nomor_surat_arsip_id": 123,
    "nomor_surat_arsip": "B/123/UNIV/X/2026",
    "keterangan": "Berdasarkan surat keputusan",
    "status": "aktif",
    "target_bills": [
        {
            "tagihan_id": 456,
            "nominal_potongan": 1000000,
            "mode_potongan": "nominal"
        }
    ]
}
```

### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Potongan \"Potongan Wajib\" berhasil disimpan dan diterapkan pada 1 tagihan.",
    "data": {
        "potongan": {},
        "total_nominal_potongan": 1000000,
        "impacted_bills": []
    }
}
```

### Response Error
**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Validasi data potongan mahasiswa gagal.",
    "errors": {
        "nomor_surat_arsip_id": ["Nomor surat dari Modul Arsip wajib diisi untuk potongan wajib."]
    }
}
```

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
    "message": "Data potongan mahasiswa tidak ditemukan."
}
```
