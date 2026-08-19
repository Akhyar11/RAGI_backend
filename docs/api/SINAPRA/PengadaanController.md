# PengadaanController

> **Modul**: SINAPRA (Sarana, Prasarana, & Aset)  
> **Base URL**: `/api/sinapra`  
> **Autentikasi**: Bearer Token (`auth:api` / Passport)  
> **Dibuat**: 2026-08-19  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth | Permission |
|---|---|---|---|---|
| GET | `/api/sinapra/pengadaan` | Listing pengajuan pengadaan barang | ✅ | `sinapra.pengadaan.read` |
| POST | `/api/sinapra/pengadaan` | Buat usulan pengadaan barang baru beserta rincian detail | ✅ | `sinapra.pengadaan.create` |
| GET | `/api/sinapra/pengadaan/{id}` | Detail usulan pengadaan & rincian barang | ✅ | `sinapra.pengadaan.read` |
| PATCH | `/api/sinapra/pengadaan/{id}/status` | Update status persetujuan pengadaan | ✅ | `sinapra.pengadaan.approve` |
| DELETE | `/api/sinapra/pengadaan/{id}` | Soft delete usulan pengadaan | ✅ | `sinapra.pengadaan.delete` |
