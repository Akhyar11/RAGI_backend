# PajakKampusController

> **Modul**: SIKEU (Keuangan)  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum / JWT)  
> **Dibuat**: 2026-08-18  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/pajak` | List kewajiban pajak terutang & sudah disetor (PPh 21/23/PPN) | ✅ Staf Keuangan |
| POST | `/api/v1/sikeu/pajak/{id}/setor` | Catat bukti setor pajak resmi dengan nomor NTPN & auto-posting jurnal | ✅ Staf Keuangan |
