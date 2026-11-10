# PaymentGatewayConfigController

> **Modul**: SIKEU (Keuangan)  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Laravel Sanctum)  
> **Dibuat**: 2026-08-05  
> **Diperbarui**: 2026-11-10  

Dokumentasi ini menjelaskan antarmuka API pengelolaan konfigurasi Payment Gateway (Midtrans, Xendit, H2H BTN Syariah, Duitku, dan Rekening Manual).

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/payment-gateway` | Daftar semua konfigurasi Payment Gateway | ✅ Admin / Keuangan |
| GET | `/api/v1/sikeu/payment-gateway/active` | Ambil konfigurasi Payment Gateway online yang sedang aktif | ✅ Admin / Keuangan |
| GET | `/api/v1/sikeu/payment-gateway/{gatewayName}/balance` | Cek saldo terkini akun Payment Gateway via API | ✅ Admin / Keuangan |
| PUT | `/api/v1/sikeu/payment-gateway/{gatewayName}` | Simpan / perbarui konfigurasi Payment Gateway & audit log | ✅ Admin / Keuangan |

---

## 1. GET /api/v1/sikeu/payment-gateway

> Mengambil daftar seluruh konfigurasi payment gateway yang terdaftar di sistem.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "gateway_name": "midtrans",
            "environment": "sandbox",
            "is_active": true,
            "api_key_encrypted": "SB-Mid-server-XXXXX",
            "public_key_encrypted": "SB-Mid-client-XXXXX",
            "webhook_token_encrypted": "G123456789",
            "auto_disbursement_enabled": false,
            "account_validation_enabled": true,
            "max_disbursement_limit": 50000000,
            "va_fee": 4000,
            "vat_percent": 11,
            "charge_fee_to_payer": true,
            "created_at": "2026-11-10T08:00:00.000000Z",
            "updated_at": "2026-11-10T08:00:00.000000Z"
        },
        {
            "id": 2,
            "gateway_name": "xendit",
            "environment": "sandbox",
            "is_active": false,
            "api_key_encrypted": "xnd_development_...",
            "public_key_encrypted": "xnd_public_...",
            "webhook_token_encrypted": "xnd_wh_verification_token",
            "auto_disbursement_enabled": false,
            "account_validation_enabled": true,
            "max_disbursement_limit": 50000000,
            "va_fee": 4000,
            "vat_percent": 11,
            "charge_fee_to_payer": true,
            "created_at": "2026-11-10T08:00:00.000000Z",
            "updated_at": "2026-11-10T08:00:00.000000Z"
        },
        {
            "id": 3,
            "gateway_name": "bsn_h2h",
            "environment": "sandbox",
            "is_active": true,
            "base_url": "http://127.0.0.1:3002",
            "server_location": "Server Kampus (Local Bridge)",
            "created_at": "2026-11-10T08:00:00.000000Z",
            "updated_at": "2026-11-10T08:00:00.000000Z"
        }
    ]
}
```

---

## 2. GET /api/v1/sikeu/payment-gateway/active

> Mengambil satu provider Payment Gateway online yang sedang aktif digunakan.

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "data": {
        "id": 1,
        "gateway_name": "midtrans",
        "environment": "sandbox",
        "is_active": true,
        "va_fee": 4000,
        "vat_percent": 11,
        "charge_fee_to_payer": true
    }
}
```

---

## 3. GET /api/v1/sikeu/payment-gateway/{gatewayName}/balance

> Mengambil saldo terkini dari akun Payment Gateway.

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `gatewayName` | string | ✅ | Nama gateway (`midtrans`, `xendit`, `duitku`) |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "data": {
        "available_balance": 13500000,
        "pending_settlement": 0,
        "total_balance": 13500000,
        "currency": "IDR",
        "last_updated": "15:45:00"
    }
}
```

### Response Error (404 Not Found)

```json
{
    "status": "error",
    "message": "Konfigurasi gateway tidak ditemukan."
}
```

### Response Error (422 Unprocessable Entity)

```json
{
    "status": "error",
    "message": "H2H BTN Syariah / Rekening Manual tidak memiliki saldo gateway. Gunakan GET /api/v1/sikeu/h2h/status untuk diagnostik bridge."
}
```

---

## 4. PUT /api/v1/sikeu/payment-gateway/{gatewayName}

> Menyimpan atau memperbarui konfigurasi gateway dan otomatis merekam jejak perubahan ke `audit_logs`.

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `gatewayName` | string | ✅ | `midtrans`, `xendit`, `bsn_h2h`, `duitku` |

### Request Body (Online Gateway: Midtrans, Xendit, Duitku)

```json
{
    "environment": "sandbox",
    "is_active": true,
    "api_key": "SB-Mid-server-XXXXX",
    "public_key": "SB-Mid-client-XXXXX",
    "webhook_token": "G123456789",
    "auto_disbursement_enabled": false,
    "account_validation_enabled": true,
    "max_disbursement_limit": 50000000,
    "va_fee": 4000,
    "vat_percent": 11,
    "charge_fee_to_payer": true
}
```

### Request Body (Bridge Gateway: BSN H2H)

```json
{
    "environment": "sandbox",
    "is_active": true,
    "base_url": "http://127.0.0.1:3002",
    "server_location": "Server Kampus (Local Bridge)"
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Konfigurasi Payment Gateway berhasil disimpan.",
    "data": {
        "id": 1,
        "gateway_name": "midtrans",
        "environment": "sandbox",
        "is_active": true,
        "updated_at": "2026-11-10T08:50:00.000000Z"
    }
}
```

---

### Catatan Tambahan

- **Penyimpanan Kredensial**: Seluruh Secret Key, Public Key, dan Password DB disimpan secara terenkripsi menggunakan Laravel Encrypter (AES-256-CBC).
- **Paralel BSN H2H**: Mengaktifkan gateway `bsn_h2h` tidak menonaktifkan gateway online (Midtrans/Xendit), karena H2H BTN Syariah berfungsi sebagai kanal perbankan inbound paralel.
- **Audit Logging**: Setiap aksi update mencatat nilai lama (`oldData`) dan nilai perubahan (`newData`) ke tabel `audit_logs`.
