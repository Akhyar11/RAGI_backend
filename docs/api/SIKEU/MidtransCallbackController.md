# MidtransCallbackController

> **Modul**: SIKEU (Sistem Informasi Keuangan Kampus)  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Signature Key SHA512 (Webhook) / Bearer Token Admin (Trial Simulator)  
> **Dibuat**: 10 November 2026  
> **Status**: Aktif

## Deskripsi Singkat

Controller ini mengelola integrasi pembayaran via **Midtrans Payment Gateway** di SIKEU RAG. Menangani penerimaan webhook notifikasi realtime untuk pembayaran e-wallet (GoPay, ShopeePay), QRIS, dan Virtual Account Midtrans, serta menyediakan endpoint simulasi / trial bagi Super Admin.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| POST | `/api/v1/sikeu/callback/midtrans` | Webhook notifikasi pembayaran dari Midtrans | ✅ Signature SHA512 |
| POST | `/api/v1/sikeu/payment-gateway/midtrans/trial` | Simulasi & trial pembayaran untuk Super Admin | ✅ Super Admin (`sikeu.paymentgateway.manage`) |
| POST | `/api/v1/sikeu/midtrans/snap-token` | Menerbitkan Snap token untuk checkout popup mahasiswa / SPMB | ✅ Mahasiswa / Staff Keuangan |

---

## 1. POST /api/v1/sikeu/callback/midtrans

Menerima webhook callback status transaksi dari Midtrans secara realtime.

### Headers

| Key | Value | Required |
|---|---|---|
| `Content-Type` | `application/json` | ✅ |
| `Accept` | `application/json` | ✅ |

### Request Body

```json
{
  "order_id": "TAG-2026111001",
  "status_code": "200",
  "gross_amount": "1500000.00",
  "signature_key": "9d90ec6e0bf3e6d3023e100e42823126f96fa53127a561bc699cfd7b7c25d88f6154b73b5ee0c9610f4d36ef554a938c3539bc213b28b7617b4458d63c5d648f",
  "transaction_status": "settlement",
  "transaction_id": "921867c8-7c87-4309-8438-bbd31215bb41",
  "payment_type": "gopay",
  "fraud_status": "accept",
  "transaction_time": "2026-11-10 10:25:00",
  "settlement_time": "2026-11-10 10:25:10"
}
```

*Formula Signature Midtrans*:  
`SHA512(order_id + status_code + gross_amount + ServerKey)`

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Pembayaran Midtrans berhasil diproses.",
  "data": {
    "pembayaran_id": 88,
    "kode_transaksi": "921867c8-7c87-4309-8438-bbd31215bb41",
    "order_id": "TAG-2026111001",
    "total_bayar": 1500000,
    "status_tagihan": "lunas"
  }
}
```

---

## 2. POST /api/v1/sikeu/payment-gateway/midtrans/trial

Digunakan oleh Super Admin pada halaman konfigurasi Payment Gateway untuk menguji coba pembayaran sandbox secara menyeluruh (verifikasi mutasi saldo kas Midtrans, update status tagihan, dan posting jurnal umum akuntansi).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Content-Type` | `application/json` | ✅ |
| `Accept` | `application/json` | ✅ |

### Request Body

```json
{
  "tagihan_id": 15,
  "nominal": 1500000,
  "payment_type": "qris",
  "fraud_status": "accept"
}
```

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Trial / Simulasi Pembayaran Midtrans Berhasil Dieksekusi.",
  "data": {
    "simulated_order_id": "TAG-2026111001",
    "transaction_id": "TRIAL-MID-K89LX12AA5",
    "nominal_bayar": 1500000,
    "channel_bayar": "MIDTRANS_QRIS",
    "saldo_kas_midtrans": {
      "sebelum": 0,
      "sesudah": 1500000,
      "mutasi": "+1,500,000.00"
    },
    "status_tagihan_akhir": "lunas",
    "total_terbayar": 1500000,
    "pembayaran": {
      "id": 89,
      "tagihan_id": 15,
      "kode_transaksi": "TRIAL-MID-K89LX12AA5",
      "jumlah_bayar": 1500000,
      "channel_bayar": "MIDTRANS_QRIS",
      "status": "success"
    },
    "callback_response": {
      "status": "success",
      "message": "Pembayaran Midtrans berhasil diproses."
    }
  }
}
```

---

## 3. Respon Error & HTTP Status Codes

### Response Error (400 Bad Request / Signature Invalid)

```json
{
  "status": "error",
  "message": "Signature key tidak valid."
}
```

### Response Error (401 Unauthorized / Token Admin Tidak Valid)

```json
{
  "status": "error",
  "message": "Token tidak valid atau sesi telah berakhir."
}
```

---

## 3. POST /api/v1/sikeu/midtrans/snap-token

Menerbitkan transaksi Midtrans Snap dan mengembalikan Snap token serta Client Key untuk menginisialisasi popup checkout `window.snap.pay(token)` pada portal mahasiswa atau pendaftaran calon mahasiswa.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Content-Type` | `application/json` | ✅ |
| `Accept` | `application/json` | ✅ |

### Request Body

```json
{
  "tagihan_id": 105,
  "first_name": "Budi Santoso",
  "email": "budi@kampus.ac.id",
  "phone": "081234567890"
}
```

### Response Sukses (201 Created)

```json
{
  "status": "success",
  "message": "Token Midtrans Snap berhasil diterbitkan.",
  "data": {
    "token": "d5045437-768c-4a37-b715-f5b244d2d480",
    "redirect_url": "https://app.sandbox.midtrans.com/snap/v2/vtweb/d5045437-768c-4a37-b715-f5b244d2d480",
    "order_id": "TAG-105-1762747200",
    "client_key": "SB-Mid-client-0123456789",
    "gross_amount": 1500000,
    "environment": "sandbox"
  }
}
```

### Response Error (401 Unauthorized / Token Tidak Valid)

```json
{
  "message": "Unauthenticated."
}
```

### Response Error (403 Forbidden / Tanpa Hak Akses)

```json
{
  "status": "error",
  "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

### Response Error (404 Not Found / Tagihan Tidak Ditemukan)

```json
{
  "status": "error",
  "message": "Tagihan mahasiswa tidak ditemukan."
}
```

### Response Error (422 Unprocessable Entity / Tagihan Sudah Lunas)

```json
{
  "status": "error",
  "message": "Tagihan ini sudah lunas."
}
```

### Response Error (422 Unprocessable Entity / Validasi Gagal)

```json
{
  "status": "error",
  "message": "Data yang diberikan tidak valid.",
  "errors": {
    "order_id": [
      "Field order_id wajib diisi oleh webhook Midtrans."
    ],
    "signature_key": [
      "Field signature_key wajib disertakan untuk verifikasi keamanan."
    ]
  }
}
```

### Response Error (500 Internal Server Error / Database Problem)

```json
{
  "status": "error",
  "message": "Terjadi kesalahan sistem saat memproses transaksi Midtrans."
}
```
