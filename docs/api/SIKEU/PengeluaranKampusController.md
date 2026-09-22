# PengeluaranKampusController

> **Modul**: SIKEU (Keuangan)  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum / JWT)  
> **Dibuat**: 2026-08-18  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/pengeluaran` | List transaksi pengeluaran kampus dengan filter & pagination | ✅ Staf Keuangan |
| POST | `/api/v1/sikeu/pengeluaran` | Catat pengeluaran baru (debet kas & auto-posting balanced journal) | ✅ Staf Keuangan |
| GET | `/api/v1/sikeu/pengeluaran/{id}` | Detail transaksi pengeluaran spesifik | ✅ Staf Keuangan |
