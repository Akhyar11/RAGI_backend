# Dokumentasi API Integrasi SIKEU - SPMB

> **Modul**: SIKEU (Keuangan) & SPMB  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum) — kecuali callback/webhook internal  
> **Dibuat**: 2026-08-05  

## Daftar Endpoint Integrasi SPMB

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/spmb/tarif` | Deprecated; mengembalikan `410 Gone` | ✅ Bearer |
| GET/POST/PUT/DELETE | `/api/v1/sikeu/master/tarif-spmb` | Deprecated; mengembalikan `410 Gone` dan tidak mengubah record historis | ✅ Admin |
| POST | `/api/v1/sikeu/callback/spmb/{calonMahasiswaId}` | Webhook Callback pelunasan biaya SPMB | ✅ Bearer |

---

## Endpoint Tarif SPMB Legacy (Deprecated)

Semua endpoint `/api/v1/sikeu/spmb/tarif` dan `/api/v1/sikeu/master/tarif-spmb` mengembalikan **410 Gone**. Tarif SPMB tidak lagi dikelola di SIKEU. Record tarif yang sudah ada dipertahankan untuk arsip, tetapi tidak dipakai untuk invoice. Atur nominal hanya pada item Komponen Biaya di Master Biaya SPMB.

```json
{
    "status": "error",
    "message": "Tarif SPMB tidak lagi dikelola di SIKEU. Atur seluruh komponen dan nominal melalui Master Biaya SPMB."
}
```

## POST /api/v1/sikeu/callback/spmb/{calonMahasiswaId}

> Webhook Callback pelunasan pembayaran pendaftaran SPMB. Otomatis mencatat tagihan lunas, transaksi pembayaran, mutasi kas, auto-journal umum debet-kredit, dan men-dispatch Event Laravel `PembayaranSpmbLunas`.

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `calonMahasiswaId` | integer/string | ✅ | ID Calon Mahasiswa pendaftar SPMB |

### Request Body

```json
{
    "order_id": "ORDER-SPMB-12345",
    "nominal": 250000,
    "status": "paid",
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
        "calon_mahasiswa_id": "123",
        "tagihan_status": "lunas",
        "pembayaran_id": 45
    }
}
```
