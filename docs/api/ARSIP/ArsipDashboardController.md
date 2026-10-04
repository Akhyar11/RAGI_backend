# ArsipDashboardController

> **Modul**: ARSIP  
> **Base URL**: `/api/arsip/dashboard`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-10-02  
> **Diperbarui**: 2026-10-05  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/arsip/dashboard` | Mengambil statistik dan ringkasan metrik dashboard arsip | ✅ Authenticated |

---

## GET /api/arsip/dashboard

> Mengambil metrik agregat statistik persuratan, status permohonan nomor surat, status kop surat, dan daftar nomor/permohonan surat terbaru.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

*Tidak ada parameter khusus.*

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Data statistik dashboard arsip berhasil dimuat.",
    "data": {
        "current_year": 2026,
        "total_nomor_surat": 15,
        "nomor_surat_tahun_ini": 15,
        "nomor_surat_terpakai": 12,
        "nomor_surat_direservasi": 3,
        "request_pending": 1,
        "request_disetujui": 4,
        "total_kop_surat": 2,
        "kop_status": {
            "baru_aktif": true,
            "lama_aktif": true
        },
        "recent_nomor": [
            {
                "id": 1,
                "nomor_surat": "1/DI/BAAK/II/2026",
                "perihal": "Pemberitahuan Praktikum",
                "tujuan": "Seluruh Mahasiswa",
                "status": "terpakai",
                "pembuat": {
                    "id": 1,
                    "name": "Super Administrator",
                    "email": "admin@campus.ac.id"
                }
            }
        ],
        "recent_requests": [
            {
                "id": 1,
                "kode_request": "REQ-2026-0001",
                "perihal": "Permohonan Nomor Surat Tugas",
                "status": "disetujui",
                "user": {
                    "id": 2,
                    "name": "Staff Akademik",
                    "email": "staff@campus.ac.id"
                }
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
    "message": "Unauthenticated."
}
```
