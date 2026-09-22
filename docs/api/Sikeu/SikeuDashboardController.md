# SikeuDashboardController

> **Modul**: SIKEU (Keuangan)  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum / JWT)  
> **Dibuat**: 2026-08-18  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/dashboard-summary` | Mengambil data analitik metrik penerimaan, pengeluaran, saldo kas utama/unit, piutang, pajak terutang, live balance Xendit/Payment Gateway, dan feed jurnal. Mendukung filter rentang tanggal. | ✅ Keuangan / Super Admin |

### Query Parameters (Opsional)
| Parameter | Tipe | Contoh | Deskripsi |
|---|---|---|---|
| `start_date` | string (YYYY-MM-DD) | `2026-09-01` | Batas awal tanggal pelaporan transaksi |
| `end_date` | string (YYYY-MM-DD) | `2026-09-30` | Batas akhir tanggal pelaporan transaksi |

### Response Schema: `GET /api/v1/sikeu/dashboard-summary?start_date=2026-09-01&end_date=2026-09-30`
```json
{
  "status": "success",
  "message": "Ringkasan finansial SIKEU berhasil dimuat",
  "data": {
    "filter": {
      "start_date": "2026-09-01",
      "end_date": "2026-09-30",
      "has_filter": true
    },
    "metrics": {
      "total_penerimaan": 525000000,
      "penerimaan_mahasiswa": 450000000,
      "penerimaan_eksternal": 75000000,
      "total_pengeluaran": 142000000,
      "pengeluaran_operasional": 100000000,
      "pengeluaran_kas_unit": 42000000,
      "total_piutang_mahasiswa": 85000000,
      "total_piutang_akumulasi": 150000000,
      "saldo_kas_utama": 383000000,
      "saldo_total_kas": 412000000,
      "pajak_terutang": 12500000,
      "tagihan_pending_approval": 3,
      "dispensasi_pending": 2,
      "pengajuan_kas_pending": 1,
      "total_pending_approval": 3,
      "total_transaksi_jurnal": 15
    },
    "payment_gateway": {
      "gateway_name": "xendit",
      "is_active": true,
      "environment": "sandbox",
      "available_balance": 15000000,
      "status_koneksi": "connected",
      "last_updated": "13:10:00 18-08-2026",
      "error_message": null
    },
    "unit_kas": [...],
    "recent_jurnals": [
      {
        "id": 101,
        "nomor_jurnal": "JRN-IN-20260916-00045",
        "tanggal_jurnal": "2026-09-16",
        "keterangan": "Pelunasan Tagihan INV-SIAKAD-001 (SIAKAD) - Mhs ID: 101",
        "total_debet": 5000000,
        "total_kredit": 5000000,
        "status": "posted",
        "status_posting": "posted",
        "jenis_sumber": "SIAKAD_PEMBAYARAN",
        "details": [...]
      }
    ]
  }
}
```

---

### Response Khusus Petugas Kas Kecil (Scoping Role PJ Kas Kecil)
Bila request berasal dari user dengan role `petugas_kas_kecil` (non-admin), backend secara otomatis menyaring data agar hanya menyajikan saldo petty cash dan transaksi unit kas kecil yang dipegang:

```json
{
  "status": "success",
  "message": "Ringkasan kas kecil unit penanggung jawab berhasil dimuat",
  "data": {
    "filter": {
      "start_date": "2026-09-01",
      "end_date": "2026-09-30",
      "has_filter": true
    },
    "is_petugas_kas_kecil": true,
    "metrics": {
      "saldo_saat_ini": 2115000,
      "saldo_awal": 2500000,
      "total_pengeluaran": 385000,
      "total_transaksi": 4,
      "pengajuan_pending": 1,
      "unit_count": 1
    },
    "unit_kas": [
      {
        "id": 8,
        "nama_kas": "Kas Kecil FTI",
        "saldo_awal": "2500000.00",
        "saldo_saat_ini": "2115000.00",
        "penanggung_jawab_id": 56,
        "fakultas": { "id": 1, "nama": "Fakultas Teknologi Informasi" },
        "akun_keuangan": { "id": 2, "kode_akun": "101.02", "nama_akun": "Kas Unit Fakultas / Petty Cash" }
      }
    ],
    "recent_transaksis": [...],
    "recent_pengajuans": [...]
  }
}
```

