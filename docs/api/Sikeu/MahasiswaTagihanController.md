# MahasiswaTagihanController

> **Modul**: SIKEU (Keuangan)  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-08-05  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/mahasiswa/tagihan` | Portal rincian tagihan semester mahasiswa aktif | ✅ Mahasiswa |
| GET | `/api/v1/sikeu/mahasiswa/invoice/{id}` | Unduh / Generate Surat Invoice Tagihan Resmi | ✅ Mahasiswa |

---

## GET /api/v1/sikeu/mahasiswa/tagihan

> Menampilkan daftar tagihan semester berjalan untuk mahasiswa yang sedang terautentikasi.

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "nomor_tagihan": "INV-SIAKAD-20260801-001",
            "total_tagihan": 3500000.00,
            "total_potongan": 500000.00,
            "total_bayar": 0.00,
            "sisa_tagihan": 3000000.00,
            "status": "belum_bayar",
            "jatuh_tempo": "2026-09-01",
            "virtual_account": {
                "va_number": "8882026080001",
                "bank_nama": "Bank BNI",
                "status": "aktif"
            }
        }
    ]
}
```
