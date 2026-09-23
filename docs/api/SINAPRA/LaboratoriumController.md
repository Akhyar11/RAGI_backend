# LaboratoriumController

> **Modul**: SINAPRA (Sarana, Prasarana, & Aset)  
> **Base URL**: `/api/sinapra`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-09-23  
> **Diperbarui**: 2026-09-23  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/sinapra/lab-bhp` | Listing inventaris bahan habis pakai (BHP) lab | ✅ |
| POST | `/api/sinapra/lab-bhp` | Tambah data BHP lab baru | ✅ |
| GET | `/api/sinapra/lab-bhp/{id}` | Detail item BHP lab & riwayat mutasi stok | ✅ |
| PUT | `/api/sinapra/lab-bhp/{id}` | Update data master BHP lab | ✅ |
| DELETE | `/api/sinapra/lab-bhp/{id}` | Soft delete data BHP lab | ✅ |
| POST | `/api/sinapra/lab-bhp/{id}/transaksi` | Catat mutasi stok BHP (masuk/restock & keluar/pemakaian) | ✅ |
| GET | `/api/sinapra/bebas-tanggungan` | Listing permohonan surat bebas tanggungan lab | ✅ |
| POST | `/api/sinapra/bebas-tanggungan` | Mahasiswa mengajukan permohonan bebas tanggungan | ✅ |
| GET | `/api/sinapra/bebas-tanggungan/{id}` | Detail permohonan & status kelayakan tanggungan lab | ✅ |
| POST | `/api/sinapra/bebas-tanggungan/{id}/approve` | Persetujuan/penolakan surat bebas tanggungan oleh petugas | ✅ |
| GET | `/api/sinapra/alat-kalibrasi` | Listing jadwal & sertifikat kalibrasi alat presisi | ✅ |
| POST | `/api/sinapra/alat-kalibrasi` | Catat riwayat kalibrasi alat presisi lab | ✅ |
| GET | `/api/sinapra/alat-kalibrasi/{id}` | Detail rekaman kalibrasi alat presisi | ✅ |
| PUT | `/api/sinapra/alat-kalibrasi/{id}` | Update rekaman kalibrasi alat presisi | ✅ |
| DELETE | `/api/sinapra/alat-kalibrasi/{id}` | Soft delete rekaman kalibrasi alat | ✅ |

---

## Headers Standar
- `Authorization: Bearer {token}`
- `Accept: application/json`
- `Content-Type: application/json`

---

## GET /api/sinapra/lab-bhp

Deskripsi: Mengambil daftar bahan habis pakai (BHP) laboratorium. Otomatis difilter hanya ruangan lab binaan laboran saat diakses oleh akun berstatus `admin_laboratorium`.

### Query Parameters
- `search` (string, optional) - Pencarian nama BHP, kode BHP, atau kategori.
- `ruangan_id` (integer, optional) - Filter ID ruangan lab.
- `kategori` (string, optional) - Filter kategori BHP.
- `sort_by` (string, default: `created_at`) - Whitelist: `created_at`, `nama_bhp`, `kode_bhp`, `stok_saat_ini`, `stok_minimum`.
- `sort_order` (enum: `asc`, `desc`, default: `desc`) - Urutan data.
- `per_page` (integer, default: 15, max: 100) - Jumlah data per halaman.
- `page` (integer, default: 1) - Halaman data yang diambil.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Daftar BHP laboratorium berhasil diambil",
    "data": [
        {
            "id": 1,
            "ruangan_id": 2,
            "kode_bhp": "BHP-RJ45-001",
            "nama_bhp": "Konektor RJ45 Cat6",
            "kategori": "komponen_elektronik",
            "stok_saat_ini": 120.0,
            "stok_minimum": 20.0,
            "satuan": "Pcs",
            "spesifikasi": "Gold plated 50u, support Gigabit",
            "lokasi_penyimpanan": "Lemari Komponen Rak B-2",
            "created_at": "2026-09-23T08:00:00.000000Z",
            "ruangan": {
                "id": 2,
                "kode": "LAB-01",
                "nama": "Laboratorium Rekayasa Perangkat Lunak",
                "gedung": {
                    "id": 1,
                    "nama": "Gedung Teori & Lab"
                }
            }
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
    "filters": {
        "search": null,
        "ruangan_id": null,
        "kategori": null,
        "sort_by": "created_at",
        "sort_order": "desc"
    }
}
```

---

## POST /api/sinapra/lab-bhp

Deskripsi: Menambahkan data item bahan habis pakai laboratorium baru.

### Request Body
```json
{
    "ruangan_id": 2,
    "kode_bhp": "BHP-RJ45-001",
    "nama_bhp": "Konektor RJ45 Cat6",
    "kategori": "komponen_elektronik",
    "stok_saat_ini": 100,
    "stok_minimum": 20,
    "satuan": "Pcs",
    "spesifikasi": "Gold plated 50u, support Gigabit",
    "lokasi_penyimpanan": "Lemari Komponen Rak B-2"
}
```

### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Data BHP lab berhasil ditambahkan",
    "data": {
        "id": 1,
        "ruangan_id": 2,
        "kode_bhp": "BHP-RJ45-001",
        "nama_bhp": "Konektor RJ45 Cat6",
        "kategori": "komponen_elektronik",
        "stok_saat_ini": 100.0,
        "stok_minimum": 20.0,
        "satuan": "Pcs",
        "created_at": "2026-09-23T08:00:00.000000Z"
    }
}
```

---

## POST /api/sinapra/lab-bhp/{id}/transaksi

Deskripsi: Mencatat transaksi mutasi stok BHP (masuk untuk restock, keluar untuk pemakaian praktikum). Sistem otomatis memvalidasi ketersediaan stok agar tidak bernilai negatif saat pemakaian keluar.

### Request Body
```json
{
    "jenis_transaksi": "keluar",
    "jumlah": 30,
    "tanggal": "2026-09-23",
    "keterangan": "Praktikum Jaringan Komputer Kelas 2A"
}
```

### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Transaksi mutasi BHP lab berhasil dicatat",
    "data": {
        "transaksi": {
            "id": 1,
            "bhp_id": 1,
            "user_id": 5,
            "jenis_transaksi": "keluar",
            "jumlah": 30.0,
            "tanggal": "2026-09-23",
            "keterangan": "Praktikum Jaringan Komputer Kelas 2A"
        },
        "stok_terkini": 70.0
    }
}
```

---

## GET /api/sinapra/bebas-tanggungan

Deskripsi: Mengambil daftar permohonan surat bebas tanggungan lab. Mahasiswa hanya dapat melihat permohonannya sendiri, sedangkan Laboran dan Admin Sarpras dapat melihat permohonan seluruh mahasiswa.

### Query Parameters
- `search` (string, optional) - Pencarian nomor surat atau nama/NIM/email pemohon.
- `status` (enum: `diajukan`, `disetujui`, `ditolak`, optional) - Filter status.
- `sort_by` (string, default: `created_at`) - Whitelist: `created_at`, `tanggal_pengajuan`, `status`.
- `sort_order` (enum: `asc`, `desc`, default: `desc`) - Urutan data.
- `per_page` (integer, default: 15, max: 100) - Jumlah data per halaman.
- `page` (integer, default: 1) - Halaman data yang diambil.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Daftar permohonan bebas tanggungan lab berhasil diambil",
    "data": [
        {
            "id": 1,
            "user_id": 10,
            "nomor_surat": "SBT/2026/09/00001",
            "tanggal_pengajuan": "2026-09-23",
            "tanggal_disetujui": "2026-09-23",
            "disetujui_oleh": 5,
            "status": "disetujui",
            "catatan": "Verifikasi bersih dari tanggungan lab",
            "created_at": "2026-09-23T08:00:00.000000Z",
            "mahasiswa": {
                "id": 10,
                "username": "mahasiswa1",
                "name": "Bambang Pamungkas"
            },
            "approver": {
                "id": 5,
                "username": "laboran_trpl",
                "name": "Bayu Laboran"
            }
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
    "filters": {
        "search": null,
        "status": null,
        "sort_by": "created_at",
        "sort_order": "desc"
    }
}
```

---

## POST /api/sinapra/bebas-tanggungan

Deskripsi: Mahasiswa mengajukan permohonan surat bebas tanggungan laboratorium.

### Request Body
```json
{
    "catatan": "Mohon penerbitan surat bebas lab untuk yudisium program studi TRPL."
}
```

### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Permohonan surat bebas tanggungan lab berhasil diajukan",
    "data": {
        "id": 1,
        "user_id": 10,
        "tanggal_pengajuan": "2026-09-23",
        "status": "diajukan",
        "catatan": "Mohon penerbitan surat bebas lab untuk yudisium program studi TRPL."
    }
}
```

---

## GET /api/sinapra/bebas-tanggungan/{id}

Deskripsi: Mengambil rincian permohonan surat bebas tanggungan lab beserta pengecekan otomatis kelayakan laboratorium pemohon (apakah masih ada peminjaman aktif/belum kembali).

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Detail permohonan bebas tanggungan lab berhasil diambil",
    "data": {
        "id": 1,
        "user_id": 10,
        "nomor_surat": "SBT/2026/09/00001",
        "tanggal_pengajuan": "2026-09-23",
        "tanggal_disetujui": "2026-09-23",
        "disetujui_oleh": 5,
        "status": "disetujui",
        "catatan": "Mohon penerbitan surat bebas lab untuk yudisium program studi TRPL.",
        "created_at": "2026-09-23T08:00:00.000000Z",
        "mahasiswa": {
            "id": 10,
            "username": "mahasiswa1",
            "name": "Bambang Pamungkas"
        },
        "approver": {
            "id": 5,
            "username": "laboran_trpl",
            "name": "Bayu Laboran"
        },
        "kelayakan_lab": {
            "is_layak": true,
            "tanggungan_aktif_count": 0,
            "catatan": "Mahasiswa tidak memiliki tanggungan peminjaman laboratorium."
        }
    }
}
```

---

## POST /api/sinapra/bebas-tanggungan/{id}/approve

Deskripsi: Verifikasi dan persetujuan surat bebas tanggungan oleh petugas laboratorium atau Admin SINAPRA. Sistem otomatis mengecek apakah mahasiswa masih memiliki tanggungan peminjaman alat laboratorium aktif yang belum dikembalikan. Jika ada tanggungan, persetujuan otomatis ditolak (422). Jika bersih, nomor surat otomatis digenerate.

### Request Body
```json
{
    "is_approved": true,
    "catatan": "Verifikasi bersih dari seluruh tanggungan fasilitas lab."
}
```

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Status surat bebas tanggungan lab berhasil diproses",
    "data": {
        "id": 1,
        "nomor_surat": "SBT/2026/09/00001",
        "status": "disetujui",
        "tanggal_disetujui": "2026-09-23",
        "disetujui_oleh": 5
    }
}
```

---

## GET /api/sinapra/alat-kalibrasi

Deskripsi: Mengambil daftar instrumen presisi laboratorium beserta status laik dan masa kedaluwarsa kalibrasi.

### Query Parameters
- `search` (string, optional) - Pencarian nama instrumen, kode aset, institusi penguji, atau sertifikat.
- `status_kelayakan` (enum: `laik`, `tidak_laik`, `butuh_perbaikan`, optional) - Filter status fisik kelayakan.
- `mendekati_kadaluarsa` (boolean, optional) - Filter alat yang masa berlaku kalibrasinya habis dalam 30 hari ke depan.
- `sort_by` (string, default: `created_at`) - Whitelist: `created_at`, `tanggal_kalibrasi`, `tanggal_kadaluarsa`, `status_kelayakan`.
- `sort_order` (enum: `asc`, `desc`, default: `desc`) - Urutan data.
- `per_page` (integer, default: 15, max: 100) - Jumlah data per halaman.
- `page` (integer, default: 1) - Halaman data yang diambil.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Daftar kalibrasi alat presisi berhasil diambil",
    "data": [
        {
            "id": 1,
            "aset_id": 5,
            "institusi_kalibrasi": "Balai Pengujian & Sertifikasi Instrumen Presisi",
            "nomor_sertifikat": "CERT-KAL-2026-001",
            "tanggal_kalibrasi": "2025-10-15",
            "tanggal_kadaluarsa": "2026-10-15",
            "status_kelayakan": "laik",
            "catatan": "Akurasi magnifikasi terkalibrasi 99.8%",
            "created_at": "2026-09-23T08:00:00.000000Z",
            "aset": {
                "id": 5,
                "kode_aset": "AST-MIC-001",
                "nama": "Mikroskop Digital Olympus"
            }
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
    "filters": {
        "search": null,
        "status_kelayakan": null,
        "mendekati_kadaluarsa": false,
        "sort_by": "tanggal_kadaluarsa",
        "sort_order": "asc"
    }
}
```

---

## Response Error Standar

### 401 Unauthorized
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

### 403 Forbidden
```json
{
    "status": "error",
    "message": "Anda tidak memiliki kewenangan untuk mengakses sumber daya ini."
}
```

### 404 Not Found
```json
{
    "status": "error",
    "message": "Data tidak ditemukan."
}
```

### 422 Unprocessable Entity
```json
{
    "status": "error",
    "message": "Validasi gagal.",
    "errors": {
        "status": [
            "Persetujuan ditolak: Mahasiswa bersangkutan masih memiliki 1 tanggungan peminjaman alat laboratorium yang belum dikembalikan."
        ]
    }
}
```

---

## Catatan
- Operasi penghapusan data BHP, Bebas Tanggungan, dan Kalibrasi menggunakan mekanisme soft-delete.
- Data kredensial dan password pengguna tidak dikembalikan dalam response API.
- Akun berstatus `admin_laboratorium` hanya dapat mengelola BHP dan alat laboratorium di ruangan yang menjadi tanggung jawabnya.
