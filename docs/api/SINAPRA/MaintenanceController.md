# MaintenanceController

> **Modul**: SINAPRA (Sarana, Prasarana, & Aset)  
> **Base URL**: `/api/sinapra`  
> **Autentikasi**: Bearer Token (`auth:api` / Passport)  
> **Dibuat**: 2026-08-19  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth | Permission |
|---|---|---|---|---|
| GET | `/api/sinapra/maintenance` | Listing log maintenance/perawatan | ✅ | `sinapra.maintenance.read` |
| POST | `/api/sinapra/maintenance` | Buat tiket perbaikan/perawatan baru | ✅ | `sinapra.maintenance.create` |
| GET | `/api/sinapra/maintenance/{id}` | Detail tiket perbaikan | ✅ | `sinapra.maintenance.read` |
| PUT | `/api/sinapra/maintenance/{id}` | Update status perbaikan & biaya | ✅ | `sinapra.maintenance.update` |
| DELETE | `/api/sinapra/maintenance/{id}` | Soft delete tiket perbaikan | ✅ | `sinapra.maintenance.delete` |
