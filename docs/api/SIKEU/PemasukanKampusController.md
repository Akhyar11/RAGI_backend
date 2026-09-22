# PemasukanKampusController

> **Modul**: SIKEU (Keuangan)  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-08-05  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/pemasukan` | List transaksi pemasukan non-UKT (Hibah, Donasi) | ✅ Staf Keuangan |
| POST | `/api/v1/sikeu/pemasukan/external` | Catat pemasukan baru dari hibah/donatur | ✅ Staf Keuangan |
