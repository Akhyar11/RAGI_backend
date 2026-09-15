# SimpegDashboardController

> **Modul**: SIMPEG (Sistem Informasi Kepegawaian)  
> **Base URL**: `/api/simpeg/dashboard-stats`  
> **Autentikasi**: Bearer Token (Passport)  
> **Dibuat**: 2026-09-15  
> **Diperbarui**: 2026-09-15  

Controller ini menyajikan metrik statistik dan ringkasan data kepegawaian secara *real-time* langsung dari database untuk panel Dashboard SIMPEG Admin (jumlah pegawai, jumlah dosen, jumlah tendik, jumlah unit kerja, serta 5 pegawai terkini).

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth / Permission |
|---|---|---|---|
| GET | `/api/simpeg/dashboard-stats` | Statistik real-time KPI kepegawaian & pegawai terkini | ✅ Login User (`auth:api`) |

---

## GET /api/simpeg/dashboard-stats

> Mengambil metrik data kepegawaian terkini langsung dari query database secara dinamis.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Success (200 OK)

```json
{
  "status": "success",
  "data": {
    "total_pegawai": 180,
    "total_dosen": 180,
    "total_tendik": 0,
    "total_unit_kerja": 12,
    "recent_pegawai": [
      {
        "id": 511,
        "nama_lengkap": "YULITA MAULANI",
        "nip": null,
        "nidn": "0601079801",
        "jenis_pegawai": "dosen",
        "unit_kerja": {
          "id": 5,
          "nama": "S1 Teknik Informatika",
          "kode": "IF"
        },
        "roles": [
          {
            "id": 5,
            "name": "Dosen Pengajar",
            "slug": "dosen"
          }
        ]
      }
    ]
  }
}
```
