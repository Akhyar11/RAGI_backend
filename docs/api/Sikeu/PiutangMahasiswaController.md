# PiutangMahasiswaController

> **Modul**: SIKEU (Sistem Informasi Keuangan Kampus)  
> **Base URL**: `/api/v1/sikeu/piutang`  
> **Autentikasi**: Bearer Token (Sanctum / OAuth2)  
> **Dibuat**: 2026-08-27  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/piutang` | Daftar piutang mahasiswa (filter angkatan, periode, prodi, status) | ✅ Admin / Keuangan |
| GET | `/api/v1/sikeu/piutang/export-excel` | Download laporan piutang mahasiswa dalam format Excel/CSV | ✅ Admin / Keuangan |

---

## GET /api/v1/sikeu/piutang

> Mengambil daftar piutang / tunggakan tagihan mahasiswa beserta ringkasan metrik total piutang.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari NIM atau Nama Mahasiswa |
| `angkatan` | integer | ❌ | — | Filter tahun angkatan mahasiswa (misal 2024) |
| `tahun_akademik_id` | integer | ❌ | — | Filter ID Tahun Akademik / Periode |
| `program_studi_id` | integer | ❌ | — | Filter ID Program Studi |
| `status` | string | ❌ | `piutang` | `piutang` (belum lunas), `belum_bayar`, `sebagian`, `dispensasi`, `lunas`, `all` |
| `sort_by` | string | ❌ | `id` | Kolom pengurutan (`id`, `created_at`, `total_tagihan`, `total_bayar`, `jatuh_tempo`, `status`) |
| `sort_order` | string | ❌ | `desc` | `asc` atau `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman data yang diminta |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Data piutang mahasiswa berhasil dimuat",
    "data": [
        {
            "id": 1,
            "nomor_tagihan": "INV-2026-0001",
            "mahasiswa_id": 5,
            "nim": "20240005",
            "nama_mahasiswa": "Ahmad Rizky",
            "angkatan": 2024,
            "program_studi": "Teknik Informatika",
            "tahun_akademik_id": 1,
            "tahun_akademik": "2025/2026 Ganjil",
            "total_tagihan": 5000000,
            "total_potongan": 500000,
            "total_denda": 0,
            "total_bayar": 1500000,
            "sisa_piutang": 3000000,
            "status": "sebagian",
            "jatuh_tempo": "2026-09-30",
            "created_at": "2026-08-01 10:00:00",
            "has_dispensasi": false
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1,
        "from": 1,
        "to": 1
    },
    "summary": {
        "total_tagihan": 5000000,
        "total_potongan": 500000,
        "total_denda": 0,
        "total_bayar": 1500000,
        "total_piutang": 3000000,
        "total_mahasiswa_tunggakan": 1,
        "total_record_dispensasi": 0
    },
    "filters": {
        "search": null,
        "angkatan": "2024",
        "tahun_akademik_id": null,
        "program_studi_id": null,
        "status": "piutang",
        "sort_by": "id",
        "sort_order": "desc"
    }
}
```

---

## GET /api/v1/sikeu/piutang/export-excel

> Mengunduh file spreadsheet Excel/CSV laporan piutang mahasiswa sesuai filter aktif.

### Query Parameters
Sama seperti endpoint `GET /api/v1/sikeu/piutang`.

### Response Header
`Content-Type: text/csv; charset=UTF-8`  
`Content-Disposition: attachment; filename="Laporan_Piutang_Mahasiswa_YYYYMMDD_HHMMSS.csv"`
