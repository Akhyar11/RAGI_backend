# AkuntansiController

> **Modul**: SIKEU (Keuangan & Akuntansi)  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-08-05  
> **Diperbarui**: 2026-09-21

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/akuntansi/coa` | Chart of Accounts (Daftar Akun Keuangan) | ✅ Staf Keuangan |
| POST | `/api/v1/sikeu/akuntansi/coa` | Tambah Akun Keuangan (COA) Baru | ✅ Staf Keuangan |
| GET | `/api/v1/sikeu/akuntansi/jurnal` | List Jurnal Umum (envelope `data` + `meta`) | ✅ Staf Keuangan |
| POST | `/api/v1/sikeu/akuntansi/jurnal` | Buat Entri Jurnal Umum Manual | ✅ Staf Keuangan |
| GET | `/api/v1/sikeu/akuntansi/jurnal/{id}` | Detail jurnal + rincian akun | ✅ Staf Keuangan |
| PUT | `/api/v1/sikeu/akuntansi/jurnal/{id}` | Edit jurnal MANUAL (kunci: otomatis, penutup, periode tutup) | ✅ Staf Keuangan |
| DELETE | `/api/v1/sikeu/akuntansi/jurnal/{id}` | Hapus jurnal MANUAL (kunci sama) | ✅ Staf Keuangan |
| GET | `/api/v1/sikeu/akuntansi/buku-besar` | Laporan Buku Besar per Akun (envelope `data` + `meta`) | ✅ Staf Keuangan |
| GET | `/api/v1/sikeu/akuntansi/laporan?dari=&sampai=` | 4 laporan dari buku (neto, posted only) | ✅ Staf Keuangan |
| GET | `/api/v1/sikeu/periode` | Daftar periode akuntansi | ✅ Staf Keuangan |
| POST | `/api/v1/sikeu/periode` | Buat periode (tolak rentang bertabrakan) | ✅ Staf Keuangan |
| POST | `/api/v1/sikeu/periode/{id}/tutup` | Tutup buku: kunci + jurnal `JRN-TUTUP` ke 301.02 | ✅ Staf Keuangan |
| GET | `/api/v1/sikeu/pengaturan-jurnal` | Daftar prefix nomor jurnal per jenis | ✅ Staf Keuangan |
| PUT | `/api/v1/sikeu/pengaturan-jurnal` | Simpan prefix (A-Z/0-9/-, maks 12) | ✅ Staf Keuangan |

> UI pengaturan: `/sikeu/akuntansi/pengaturan` (menu Pengaturan Akuntansi, grup AKUNTANSI & LAPORAN).

> UI: `/sikeu/akuntansi/pengaturan` (menu Pengaturan Akuntansi di grup AKUNTANSI & LAPORAN).

---

## Basis akrual & konvensi akun

> Penerbitan tagihan `Dr 103.01 / Cr 401.01 (UKT) atau 401.02 (SPMB)`; pembayaran `Dr Kas/Bank / Cr 103.01`; koreksi sebaliknya; hapus tagihan membalik penerbitan; potongan `Dr 504.01 / Cr 103.01` (+ sebaliknya saat batal); dispensasi ACC = memo `JRN-DSP` nol. Bucket laporan: pendapatan `401/402/403`, beban operasional `502.01+505.01`, pemeliharaan `502.02`, lab `502.03`, honorarium `501`, lainnya `503+504`; kas-bank `101+102`, piutang `103`, utang pajak `202`. Filter `dari/sampai` membatasi arus; posisi kumulatif.
