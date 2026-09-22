# PaymentGatewayConfigController

> **Modul**: SIKEU (Keuangan)  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-08-05  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/payment-gateway` | List semua provider Payment Gateway (Midtrans, Xendit) | ✅ Admin |
| GET | `/api/v1/sikeu/payment-gateway/active` | Get provider Payment Gateway yang sedang aktif | ✅ Admin |
| GET | `/api/v1/sikeu/payment-gateway/{gatewayName}/balance` | Cek saldo terkini akun Payment Gateway via API | ✅ Admin |
| PUT | `/api/v1/sikeu/payment-gateway/{gatewayName}` | Update API Key & status aktif Payment Gateway | ✅ Admin |
