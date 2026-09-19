# TagihanApprovalController

> **Modul**: SIKEU (Keuangan)  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum) — Role Pimpinan / Kabag Keuangan  
> **Dibuat**: 2026-08-05  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/approvals` | List antrean persetujuan tagihan & dispensasi | ✅ Pimpinan |
| POST | `/api/v1/sikeu/approvals/tagihan/{id}/approve` | Setujui penerbitan tagihan khusus | ✅ Pimpinan |
| POST | `/api/v1/sikeu/approvals/tagihan/{id}/reject` | Tolak penerbitan tagihan khusus | ✅ Pimpinan |
| POST | `/api/v1/sikeu/approvals/dispensasi/{id}/approve` | Setujui pengajuan dispensasi | ✅ Pimpinan |
| POST | `/api/v1/sikeu/approvals/dispensasi/{id}/reject` | Tolak pengajuan dispensasi | ✅ Pimpinan |
