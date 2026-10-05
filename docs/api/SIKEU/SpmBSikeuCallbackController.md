# SpmBSikeuCallbackController

> **Modul**: SIKEU (Keuangan) & SPMB  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: ❌ Publik + **Callback Token** (`x-callback-token`) — kecuali simulasi  
> **Dibuat**: 2026-08-05  
> **Diperbarui**: 2026-09-24

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| POST | `/api/v1/sikeu/callback/spmb/{calonMahasiswaId}` | Webhook pelunasan biaya pendaftaran SPMB | ❌ Publik + token |
| POST | `/api/v1/sikeu/callback/spmb/{calonMahasiswaId}/simulate` | Simulasi pembayaran (local/testing) | ✅ Bearer (pemilik/admin) |
| POST | `/api/v1/sikeu/callback/xendit` | Adapter webhook native Xendit (Fixed VA) → proses pembayaran SPMB | ❌ Publik + token |
| GET | `/api/v1/sikeu/checkout/lookup-va` | Cari detail tagihan dari nomor VA | ✅ Bearer |

> **Keamanan webhook**: endpoint callback berada di luar `auth:api` agar dapat dipanggil payment gateway. Middleware `payment.callback` memverifikasi header `x-callback-token` terhadap `PaymentGatewayConfig.webhook_token_encrypted` (gateway aktif) atau env `SIKEU_CALLBACK_TOKEN`. Bila token belum dikonfigurasi, callback hanya diizinkan di environment `local`/`testing`.

> **Pembayaran sebagian**: callback menghitung status secara proporsional. `nominal` yang lebih kecil dari sisa tagihan menghasilkan status `sebagian` (bukan langsung `lunas`), dan `PendaftaranCalonMhs.status` hanya naik `draft → submitted` saat tagihan benar-benar lunas.

> **Tagihan hantu dihapus**: bila tidak ada tagihan SPMB yang belum lunas untuk pendaftar, callback mengembalikan `404` (tidak lagi membuat tagihan `lunas` baru).

---

## POST /api/v1/sikeu/callback/spmb/{calonMahasiswaId}

> Menangani notifikasi status pembayaran biaya pendaftaran pendaftar SPMB. Menghitung status tagihan (`lunas`/`sebagian`), mencatat entri `pembayaran`, dan entri otomatis `jurnal_umum`. Event `App\Events\Sikeu\PembayaranSpmbLunas` hanya di-dispatch saat tagihan lunas. Untuk tagihan `spmb_daftar_ulang` yang lunas, status `spmb_hasil_seleksi.status_daftar_ulang` diset `lunas` dan event `MahasiswaDiterima` (konversi ke SIAKAD) di-dispatch.

### Headers

| Key | Value | Required |
|---|---|---|
| `x-callback-token` | Token webhook gateway | ✅ (bila token dikonfigurasi) |
| `Accept` | `application/json` | ✅ |

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `calonMahasiswaId` | integer/string | ✅ | ID Pendaftaran/Calon Mahasiswa pendaftar SPMB |

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

---

## POST /api/v1/sikeu/callback/xendit

> Adapter **webhook native Xendit** (Fixed Virtual Account). Memetakan payload standar Xendit ke proses pembayaran SPMB. Dilindungi middleware `payment.callback` (header `x-callback-token`).

### Request Body (format Xendit)

```json
{
    "external_id": "INV-SPMB-20261005-QOBWK",
    "paid_amount": 11750000,
    "payment_id": "xnd_payment_123",
    "bank_code": "BNI",
    "account_number": "8808999986253194"
}
```

> Pencarian tagihan: prioritas `external_id` (nomor tagihan), fallback `account_number` (VA).

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Pembayaran SPMB lunas dan dicatat ke jurnal keuangan.",
    "is_spmb_unlocked": true,
    "data": {
        "tagihan": { "id": 6, "status": "lunas" },
        "pembayaran": { "id": 45, "fee_amount": "9990.00" }
    }
}
```

### Response Error

**404 Not Found** (tagihan SPMB tidak ditemukan)
```json
{ "status": "error", "message": "Tagihan SPMB untuk callback Xendit tidak ditemukan." }
```

**422 Unprocessable Entity** (nominal tidak valid)
```json
{
    "status": "error",
    "message": "Nominal callback Xendit tidak valid.",
    "errors": { "nominal": ["Nominal pembayaran minimal Rp 1.000."] }
}
```
