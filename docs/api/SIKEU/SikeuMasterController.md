# SikeuMasterController

> **Modul**: SIKEU (Keuangan)  
> **Base URL**: `/api/v1/sikeu`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-08-05  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/master/tarif-ukt` | List master Tarif UKT per angkatan, prodi & jalur | ✅ Bearer |
| POST | `/api/v1/sikeu/master/tarif-ukt` | Buat Tarif UKT baru | ✅ Admin |
| PUT | `/api/v1/sikeu/master/tarif-ukt/{id}` | Update Tarif UKT | ✅ Admin |
| DELETE | `/api/v1/sikeu/master/tarif-ukt/{id}` | Hapus Tarif UKT | ✅ Admin |
| GET/POST/PUT/DELETE | `/api/v1/sikeu/master/tarif-spmb` | Deprecated; mengembalikan `410 Gone` | ✅ Admin |
| GET | `/api/v1/sikeu/spmb/tarif` | Deprecated; mengembalikan `410 Gone` | ✅ Bearer |
| GET | `/api/v1/sikeu/master/jalur-kelas` | List master jalur kelas (Reguler, Karyawan, dll.) | ✅ Bearer |
| POST | `/api/v1/sikeu/master/jalur-kelas` | Tambah jalur kelas baru | ✅ Admin |
| PUT | `/api/v1/sikeu/master/jalur-kelas/{id}` | Update jalur kelas | ✅ Admin |
| DELETE | `/api/v1/sikeu/master/jalur-kelas/{id}` | Hapus jalur kelas | ✅ Admin |
| GET | `/api/v1/sikeu/master/jenis-biaya` | List jenis biaya pendidikan | ✅ Bearer |
| POST | `/api/v1/sikeu/master/jenis-biaya` | Tambah jenis biaya | ✅ Admin |
| PUT | `/api/v1/sikeu/master/jenis-biaya/{id}` | Update jenis biaya | ✅ Admin |
| GET | `/api/v1/sikeu/master/beasiswa` | List master beasiswa | ✅ Bearer |
| POST | `/api/v1/sikeu/master/beasiswa` | Buat master beasiswa | ✅ Admin |
| PUT | `/api/v1/sikeu/master/beasiswa/{id}` | Update master beasiswa | ✅ Admin |
| GET | `/api/v1/sikeu/master/mahasiswa-beasiswa` | List pemetaan beasiswa mahasiswa | ✅ Bearer |
| POST | `/api/v1/sikeu/master/mahasiswa-beasiswa` | Tetapkan beasiswa ke mahasiswa | ✅ Admin |
| GET | `/api/v1/sikeu/mahasiswa-search` | Autocomplete pencarian mahasiswa (NIM/Nama) | ✅ Bearer |

---

Endpoint tarif SPMB legacy mengembalikan `410 Gone`. Semua nominal dan komponen biaya SPMB dikelola melalui Master Biaya SPMB, bukan di Master Tarif SIKEU.

---

## GET /api/v1/sikeu/mahasiswa-search

> Autocomplete pencarian mahasiswa (NIM/Nama, limit 20). Wajib diketik dulu (query kosong = hasil kosong); opsional persempit via prodi + angkatan ala legacy agar tidak memuat seluruh data.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `q` | string | ❌ | — | Kata kunci NIM / nama / NIK / telepon / no tagihan |
| `program_studi_id` | integer | ❌ | — | Batasi lingkup prodi (SIAKAD) |
| `angkatan` | integer | ❌ | — | Batasi lingkup tahun angkatan (SIAKAD) |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": [
        {
            "id": 12,
            "mahasiswa_id": 12,
            "calon_mahasiswa_id": null,
            "tipe_referensi": "mahasiswa",
            "is_calon_mahasiswa": false,
            "nim": "2401001",
            "no_pendaftaran": null,
            "nama_mahasiswa": "Ahmad Dahlan",
            "prodi": "Teknik Informatika",
            "tahun_angkatan": 2024,
            "jalur_kelas": "Reguler",
            "unpaid_bills_count": 1,
            "total_unpaid_amount": 2500000,
            "tagihans": [
                { "id": 7, "nomor_tagihan": "INV-2026-0007", "sisa": 2500000, "status": "belum_bayar", "jatuh_tempo": "2026-11-30" }
            ]
        }
    ]
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```
