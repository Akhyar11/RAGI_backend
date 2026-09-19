# AkuntansiController

> **Modul**: SIKEU (Keuangan & Akuntansi)  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-08-05  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/akuntansi/coa` | Chart of Accounts (Daftar Akun Keuangan) | ✅ Staf Keuangan |
| POST | `/api/v1/sikeu/akuntansi/coa` | Tambah Akun Keuangan (COA) Baru | ✅ Staf Keuangan |
| GET | `/api/v1/sikeu/akuntansi/jurnal` | List Jurnal Umum (Balanced Journal) | ✅ Staf Keuangan |
| POST | `/api/v1/sikeu/akuntansi/jurnal` | Buat Entri Jurnal Umum Manual | ✅ Staf Keuangan |
| GET | `/api/v1/sikeu/akuntansi/buku-besar` | Laporan Buku Besar per Akun | ✅ Staf Keuangan |
