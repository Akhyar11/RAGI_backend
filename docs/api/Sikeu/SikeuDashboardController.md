# SikeuDashboardController

> **Modul**: SIKEU (Keuangan)  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum / JWT)  
> **Dibuat**: 2026-08-18  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/dashboard-summary` | Mengambil data analitik metrik penerimaan, pengeluaran, saldo kas utama/unit, pajak terutang, live balance Xendit/Payment Gateway, dan feed jurnal | ✅ Keuangan / Super Admin |

### Response Schema: `GET /api/v1/sikeu/dashboard-summary`
```json
{
  "status": "success",
  "message": "Ringkasan finansial SIKEU berhasil dimuat",
  "data": {
    "metrics": {
      "total_penerimaan": 525000000,
      "penerimaan_mahasiswa": 450000000,
      "penerimaan_eksternal": 75000000,
      "total_pengeluaran": 142000000,
      "saldo_kas_utama": 383000000,
      "saldo_total_kas": 412000000,
      "pajak_terutang": 12500000,
      "tagihan_pending_approval": 3,
      "dispensasi_pending": 2,
      "pengajuan_kas_pending": 1,
      "total_pending_approval": 3
    },
    "payment_gateway": {
      "gateway_name": "xendit",
      "is_active": true,
      "environment": "sandbox",
      "available_balance": 15000000,
      "status_koneksi": "connected",
      "last_updated": "13:10:00 18-08-2026",
      "error_message": null
    },
    "recent_jurnals": [...]
  }
}
```
