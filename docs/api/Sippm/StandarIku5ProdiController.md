# StandarIku5ProdiController

> **Modul**: SIPPM (Sistem Informasi Penelitian dan Pengabdian Masyarakat)  
> **Base URL**: `/api/sippm/iku5-standards`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-08-01  
> **Diperbarui**: 2026-08-01  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/sippm/iku5-standards` | Mengambil daftar standar target IKU 5 per prodi | ✅ Admin / User |
| POST | `/api/sippm/iku5-standards` | Membuat atau memperbarui (upsert) standar IKU 5 | ✅ Admin LPPM |
| GET | `/api/sippm/iku5-standards/{id}` | Mengambil detail standar IKU 5 prodi | ✅ Admin / User |
| PUT | `/api/sippm/iku5-standards/{id}` | Perbarui data standar IKU 5 | ✅ Admin LPPM |
| DELETE | `/api/sippm/iku5-standards/{id}` | Hapus data standar IKU 5 | ✅ Admin LPPM |

---

## GET /api/sippm/iku5-standards

> Mengambil daftar target IKU 5 (Scopus, Sinta, HKI, Buku) per Program Studi dengan filter dan pengurutan.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari berdasarkan nama prodi / kode prodi |
| `tahun_akademik` | string | ❌ | — | Filter berdasarkan tahun akademik (misal `2025/2026`) |
| `unit_kerja_id` | integer | ❌ | — | Filter berdasarkan ID unit kerja / prodi |
| `sort_by` | string | ❌ | `tahun_akademik` | Whitelist: `created_at`, `tahun_akademik`, `target_publikasi_scopus`, `target_publikasi_sinta` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Data standar IKU 5 prodi berhasil diambil.",
    "data": [
        {
            "id": 1,
            "unit_kerja_id": 5,
            "tahun_akademik": "2025/2026",
            "target_publikasi_scopus": 8,
            "target_publikasi_sinta": 15,
            "target_hki_paten": 6,
            "target_buku_isbn": 4,
            "created_at": "2026-08-01T14:42:10.000000Z",
            "updated_at": "2026-08-01T14:42:10.000000Z",
            "unit_kerja": {
                "id": 5,
                "nama_unit": "S1 Teknik Informatika",
                "kode": "IF"
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 6,
        "last_page": 1,
        "from": 1,
        "to": 6
    },
    "filters": {
        "tahun_akademik": "2025/2026"
    }
}
```

---

## POST /api/sippm/iku5-standards

> Menambahkan atau memperbarui (upsert) standar IKU 5 untuk Program Studi pada tahun akademik tertentu.

### Request Body

```json
{
    "unit_kerja_id": 5,
    "tahun_akademik": "2025/2026",
    "target_publikasi_scopus": 8,
    "target_publikasi_sinta": 15,
    "target_hki_paten": 6,
    "target_buku_isbn": 4
}
```

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Standar IKU 5 prodi berhasil disimpan.",
    "data": {
        "id": 1,
        "unit_kerja_id": 5,
        "tahun_akademik": "2025/2026",
        "target_publikasi_scopus": 8,
        "target_publikasi_sinta": 15,
        "target_hki_paten": 6,
        "target_buku_isbn": 4,
        "created_at": "2026-08-01T14:42:10.000000Z",
        "updated_at": "2026-08-01T14:42:10.000000Z",
        "unit_kerja": {
            "id": 5,
            "nama_unit": "S1 Teknik Informatika",
            "kode": "IF"
        }
    }
}
```
