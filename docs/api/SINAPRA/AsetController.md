# AsetController

> **Modul**: SINAPRA (Sarana, Prasarana, & Aset)  
> **Base URL**: `/api/sinapra`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-08-19  
> **Diperbarui**: 2026-09-23  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/sinapra/kategori-aset` | Listing kategori aset (termasuk parent/child) | ✅ |
| POST | `/api/sinapra/kategori-aset` | Tambah kategori aset | ✅ |
| GET | `/api/sinapra/kategori-aset/{id}` | Detail kategori aset | ✅ |
| PUT | `/api/sinapra/kategori-aset/{id}` | Update kategori aset | ✅ |
| DELETE | `/api/sinapra/kategori-aset/{id}` | Hapus kategori aset | ✅ |
| GET | `/api/sinapra/aset` | Listing inventaris barang/aset | ✅ |
| POST | `/api/sinapra/aset` | Tambah aset baru | ✅ |
| GET | `/api/sinapra/aset/{id}` | Detail aset & riwayat perbaikan/peminjaman | ✅ |
| GET | `/api/sinapra/aset/{id}/label` | Generate label fisik barcode & QR Code aset | ✅ |
| POST | `/api/sinapra/aset/labels/batch` | Generate batch label fisik barcode & QR Code | ✅ |
| GET | `/api/sinapra/aset/{id}/hitung-penyusutan` | Kalkulasi nilai buku & penyusutan aset | ✅ |
| PUT | `/api/sinapra/aset/{id}` | Update data aset | ✅ |
| DELETE | `/api/sinapra/aset/{id}` | Soft delete aset | ✅ |

---

## Headers Standar
- `Authorization: Bearer {token}`
- `Accept: application/json`
- `Content-Type: application/json`

---

## GET /api/sinapra/aset

Deskripsi: Mengambil daftar inventaris barang/aset dengan filter kategori, ruangan, status, ketersediaan pinjam, aset laboratorium, serta otomatis scoped ke lab binaan jika user adalah Admin Laboratorium.

### Query Parameters
- `search` (string, optional) - Pencarian nama, kode aset, merk, serial number.
- `kategori_id` (integer, optional) - Filter kategori aset.
- `ruangan_id` (integer, optional) - Filter ruangan/lokasi penempatan aset.
- `kondisi` (enum: `baik`, `rusak_ringan`, `rusak_berat`, optional) - Kondisi fisik.
- `status` (enum: `tersedia`, `dipinjam`, `maintenance`, `dihapuskan`, optional) - Status aset.
- `is_borrowable` (boolean, optional) - Filter apakah aset dapat dipinjam.
- `is_lab_asset` (boolean, optional) - Filter aset laboratorium.
- `sort_by` (string, default: `created_at`) - Whitelist: `created_at`, `kode_aset`, `nama`, `harga_perolehan`, `nilai_buku`, `tanggal_perolehan`.
- `sort_order` (enum: `asc`, `desc`, default: `desc`) - Urutan data.
- `per_page` (integer, default: 15, max: 100) - Jumlah data per halaman.
- `page` (integer, default: 1) - Halaman data yang diambil.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Daftar aset berhasil diambil",
    "data": [
        {
            "id": 1,
            "kategori_id": 2,
            "ruangan_id": 5,
            "kode_aset": "AST-LAB-001",
            "nama": "Mikroskop Binokuler Digital",
            "merk": "Olympus",
            "model": "CX23",
            "serial_number": "OLY-2026-991",
            "tanggal_perolehan": "2026-01-15",
            "harga_perolehan": 15000000.0,
            "nilai_buku": 12000000.0,
            "kondisi": "baik",
            "status": "tersedia",
            "is_borrowable": true,
            "is_lab_asset": true,
            "created_at": "2026-01-15T08:00:00.000000Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1,
        "from": 1,
        "to": 1
    }
}
```

---

## POST /api/sinapra/aset

Deskripsi: Menambahkan unit inventaris barang/aset baru ke dalam sistem.

### Request Body
```json
{
    "kategori_id": 2,
    "ruangan_id": 5,
    "kode_aset": "AST-LAB-002",
    "nama": "Spektrofotometer UV-Vis",
    "merk": "Shimadzu",
    "model": "UV-1900i",
    "serial_number": "SHM-2026-004",
    "tanggal_perolehan": "2026-02-10",
    "harga_perolehan": 45000000,
    "kondisi": "baik",
    "status": "tersedia",
    "is_borrowable": true,
    "is_lab_asset": true
}
```

### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Aset berhasil ditambahkan",
    "data": {
        "id": 2,
        "kategori_id": 2,
        "ruangan_id": 5,
        "kode_aset": "AST-LAB-002",
        "nama": "Spektrofotometer UV-Vis",
        "merk": "Shimadzu",
        "model": "UV-1900i",
        "serial_number": "SHM-2026-004",
        "tanggal_perolehan": "2026-02-10",
        "harga_perolehan": 45000000.0,
        "nilai_buku": 45000000.0,
        "kondisi": "baik",
        "status": "tersedia",
        "is_borrowable": true,
        "is_lab_asset": true,
        "created_at": "2026-02-10T10:00:00.000000Z"
    }
}
```

---

## GET /api/sinapra/aset/{id}/hitung-penyusutan

Deskripsi: Menghitung estimasi sisa nilai buku aset berdasarkan umur perolehan barang dan persentase penyusutan kategori.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Estimasi penyusutan nilai buku aset berhasil dihitung",
    "data": {
        "aset_id": 1,
        "kode_aset": "AST-IT-001",
        "nama": "PC Workstation Server",
        "harga_perolehan": 20000000.0,
        "nilai_buku_saat_ini": 10000000.0
    }
}
```

---

## DELETE /api/sinapra/aset/{id}

Deskripsi: Menghapus data inventaris aset secara soft delete.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Aset berhasil dihapus",
    "data": null
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
    "message": "Aset tidak ditemukan."
}
```

### 422 Unprocessable Entity
```json
{
    "status": "error",
    "message": "Validasi gagal.",
    "errors": {
        "nama": [
            "Nama aset wajib diisi."
        ],
        "kategori_id": [
            "Kategori aset yang dipilih tidak valid."
        ]
    }
}
```

---

## GET /api/sinapra/aset/{id}/label

Deskripsi: Mengambil metadata lengkap stiker label inventaris fisik untuk satu aset, termasuk QR Code dalam format SVG resolusi tinggi dan payload verifikasi aset.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Data label barcode & QR code aset berhasil diambil",
    "data": {
        "id": 1,
        "kode_aset": "AST-LAB-001",
        "nama": "Mikroskop Binokuler Digital",
        "merk": "Olympus",
        "model": "CX23",
        "serial_number": "OLY-2026-991",
        "kategori": "Peralatan Laboratorium Biologi",
        "ruangan_id": 5,
        "lokasi_ruangan": "Laboratorium Biologi Terpadu",
        "lokasi_gedung": "Gedung Saintek Lt. 3",
        "tanggal_perolehan": "2026-01-15",
        "kondisi": "baik",
        "status": "tersedia",
        "qr_content": "http://localhost:8000/sinapra/aset/1",
        "qr_code_svg": "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<svg ...>...</svg>",
        "instansi": "SISTEM SARANA & PRASARANA KAMPUS"
    }
}
```

---

## POST /api/sinapra/aset/labels/batch

Deskripsi: Mengambil sekumpulan metadata label stiker barcode & QR Code sekaligus untuk pencetakan massal (lembar cetak A4 / kertas stiker berkelanjutan).

### Request Body
```json
{
    "aset_ids": [1, 2, 3]
}
```

| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `aset_ids` | array | ✅ | Array ID aset yang valid pada database. Min: 1, Max: 100. |
| `aset_ids.*` | integer | ✅ | ID aset fisik `sinapra_aset`. |

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Data label barcode & QR code batch aset berhasil diambil",
    "data": [
        {
            "id": 1,
            "kode_aset": "AST-LAB-001",
            "nama": "Mikroskop Binokuler Digital",
            "qr_code_svg": "<svg ...>...</svg>",
            "lokasi_ruangan": "Laboratorium Biologi Terpadu"
        },
        {
            "id": 2,
            "kode_aset": "AST-LAB-002",
            "nama": "Centrifuge Refrigerated 15000 RPM",
            "qr_code_svg": "<svg ...>...</svg>",
            "lokasi_ruangan": "Laboratorium Kimia Analitik"
        }
    ]
}
```

---

## Catatan
- Penghapusan data aset dan kategori menggunakan mekanisme soft-delete.
- Data kredensial dan password tidak dikembalikan dalam response API.
