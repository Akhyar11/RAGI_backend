# SpmBSikeuCallbackController

> **Modul**: SIKEU (Keuangan) & SPMB  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-08-05  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| POST | `/api/v1/sikeu/callback/spmb/{calonMahasiswaId}` | Webhook Callback pelunasan pembayaran biaya pendaftaran SPMB | ✅ Bearer |

---

## POST /api/v1/sikeu/callback/spmb/{calonMahasiswaId}

> Menangani notifikasi status pembayaran biaya pendaftaran pendaftar SPMB. Mengubah status tagihan menjadi `lunas`, mencatat entri `pembayaran`, menambahkan saldo `unit_kas`, melakukan entri otomatis pada `jurnal_umum` (Debet Kas, Kredit Pendapatan SPMB `401.03`), serta men-dispatch Laravel Event `App\Events\Sikeu\PembayaranSpmbLunas`.

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `calonMahasiswaId` | integer/string | ✅ | ID Calon Mahasiswa pendaftar SPMB |

### Request Body

```json
{
    "order_id": "TRX-SPMB-777-01",
    "nominal": 250000,
    "status": "settlement",
    "bank_kode": "BNI",
    "channel": "VA_BNI"
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Pembayaran SPMB berhasil diproses, saldo kas diperbarui, dan status pendaftaran SPMB dibuka (unlocked).",
    "spmb_unlock": true,
    "data": {
        "calon_mahasiswa_id": "777",
        "tagihan_status": "lunas",
        "pembayaran_id": 45
    }
}
```
