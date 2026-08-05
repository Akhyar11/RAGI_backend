# PengajuanKasController

> **Modul**: SIKEU (Keuangan)  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-08-05  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/pengajuan-kas` | List pengajuan pencairan kas operasional unit | ✅ Bearer |
| POST | `/api/v1/sikeu/pengajuan-kas` | Buat pengajuan pencairan kas baru | ✅ Bearer |
| POST | `/api/v1/sikeu/pengajuan-kas/{id}/approve` | Persetujuan & pencairan kas unit | ✅ Pimpinan |
