# SinapraDashboardController

> **Modul**: SINAPRA (Sarana, Prasarana, & Aset)  
> **Base URL**: `/api/sinapra`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-09-29  
> **Diperbarui**: 2026-09-29  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/sinapra/dashboard-summary` | Ringkasan metrik eksekutif, ketersediaan ruangan, nilai aset, dan early warning | ✅ |

---

## Headers Standar
- `Authorization: Bearer {token}`
- `Accept: application/json`

---

## GET /api/sinapra/dashboard-summary

Deskripsi: Mengambil ringkasan data metrik eksekutif sarana dan prasarana kampus secara real-time, meliputi statistik fasilitas (gedung/ruangan/kapasitas), nilai perolehan dan nilai buku inventaris aset (terkoneksi akuntansi SIKEU), peminjaman aktif, pemeliharaan/pengadaan barang, peringatan dini (*early warning*) stok BHP dan kalibrasi laboratorium, serta log aktivitas terbaru.

### Query Parameters
Tidak memerlukan query parameters.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Ringkasan dashboard SINAPRA berhasil dimuat",
    "data": {
        "metrics": {
            "total_gedung": 8,
            "total_ruangan": 42,
            "ruangan_tersedia": 38,
            "total_kapasitas_ruangan": 1650,
            "total_aset": 350,
            "total_harga_perolehan": 1500000000,
            "total_nilai_buku": 1125000000,
            "total_akumulasi_penyusutan": 375000000,
            "total_aset_ada_pic": 120,
            "peminjaman_ruangan_aktif": 5,
            "peminjaman_aset_aktif": 12,
            "peminjaman_pending": 3,
            "maintenance_aktif": 4,
            "pengadaan_pending": 2,
            "pengadaan_disetujui": 6
        },
        "breakdown_aset": {
            "status": {
                "tersedia": 310,
                "dipinjam": 18,
                "maintenance": 12,
                "rusak": 8,
                "dihapus": 2
            },
            "kondisi": {
                "baik": 320,
                "rusak_ringan": 22,
                "rusak_berat": 8
            }
        },
        "early_warnings": {
            "bhp_kritis_count": 3,
            "bhp_kritis_list": [
                {
                    "id": 1,
                    "ruangan_id": 2,
                    "kode_bhp": "BHP-001",
                    "nama_bhp": "Etanol 96%",
                    "stok_saat_ini": 2,
                    "stok_minimum": 5,
                    "satuan": "Liter",
                    "ruangan": {
                        "id": 2,
                        "nama": "Lab Kimia Dasar",
                        "kode": "LAB-KIM-01"
                    }
                }
            ],
            "kalibrasi_urgent_count": 2,
            "kalibrasi_urgent_list": [
                {
                    "id": 1,
                    "aset_id": 10,
                    "nomor_sertifikat": "KAL/2026/089",
                    "tanggal_kadaluarsa": "2026-10-15",
                    "status_kelayakan": "layak",
                    "aset": {
                        "id": 10,
                        "nama": "Spektrofotometer UV-Vis",
                        "kode_aset": "AST-LAB-010"
                    }
                }
            ]
        },
        "recent_activities": {
            "peminjaman_ruangan": [],
            "peminjaman_aset": [],
            "aset_terbaru": []
        }
    }
}
```

### Response Error (500 Internal Server Error)
```json
{
    "status": "error",
    "message": "Gagal memuat ringkasan dashboard SINAPRA: Database error description"
}
```
