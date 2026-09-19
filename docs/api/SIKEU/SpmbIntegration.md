# Dokumentasi API Integrasi SIKEU - SPMB

> **Modul**: SIKEU (Keuangan) & SPMB  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum) — kecuali callback/webhook internal  
> **Dibuat**: 2026-08-05  

## Daftar Endpoint Integrasi SPMB

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/spmb/tarif` | Get tarif pendaftaran SPMB dinamis per jalur & gelombang | ✅ Bearer |
| GET | `/api/v1/sikeu/master/tarif-spmb` | List master tarif SPMB | ✅ Admin |
| POST | `/api/v1/sikeu/master/tarif-spmb` | Tambah master tarif SPMB baru | ✅ Admin |
| PUT | `/api/v1/sikeu/master/tarif-spmb/{id}` | Update master tarif SPMB | ✅ Admin |
| DELETE | `/api/v1/sikeu/master/tarif-spmb/{id}` | Hapus master tarif SPMB | ✅ Admin |
| POST | `/api/v1/sikeu/callback/spmb/{calonMahasiswaId}` | Webhook Callback pelunasan biaya SPMB | ✅ Bearer |

---

## GET /api/v1/sikeu/spmb/tarif

> Mengambil nominal biaya pendaftaran SPMB berdasarkan `jalur_id` dan `gelombang_id` secara real-time. Jika kombinasi tidak ditemukan, mengembalikan `nominal_standar` dari `jenis_biaya` tipe `spmb_adm`.

### Query Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `jalur_id` | string/integer | ✅ | ID Jalur Masuk SPMB |
| `gelombang_id` | string/integer | ✅ | ID Gelombang SPMB |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "data": {
        "jalur_id": "1",
        "gelombang_id": "2",
        "nominal": 250000.00
    }
}
```

---

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
