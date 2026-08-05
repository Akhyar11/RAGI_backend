# UnitKasController

> **Modul**: SIKEU (Keuangan)  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-08-05  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/master/unit-kas` | List unit kas & saldo saat ini | ✅ Bearer |
| POST | `/api/v1/sikeu/master/unit-kas` | Tambah unit kas baru | ✅ Admin |
| PUT | `/api/v1/sikeu/master/unit-kas/{id}` | Update unit kas | ✅ Admin |
| DELETE | `/api/v1/sikeu/master/unit-kas/{id}` | Hapus unit kas | ✅ Admin |
