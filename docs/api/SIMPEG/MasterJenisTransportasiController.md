# MasterJenisTransportasiController

> **Modul**: SIMPEG / **Base URL**: `/api/simpeg/master/jenis-transportasi` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-09-27

Dokumentasi API CRUD data master moda dan jenis transportasi penugasan dinas luar pada modul SIMPEG. Mendukung pembedaan kepemilikan armada dinas kampus (memerlukan driver & nomor polisi) vs transportasi umum / pribadi.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/simpeg/master/jenis-transportasi` | Daftar master jenis transportasi berpaginasi | ✅ User / Admin SIMPEG |
| POST | `/api/simpeg/master/jenis-transportasi` | Menambah moda transportasi baru | ✅ Admin SIMPEG |
| GET | `/api/simpeg/master/jenis-transportasi/{id}` | Detail moda transportasi | ✅ User / Admin SIMPEG |
| PUT | `/api/simpeg/master/jenis-transportasi/{id}` | Memperbarui moda transportasi | ✅ Admin SIMPEG |
| DELETE | `/api/simpeg/master/jenis-transportasi/{id}` | Menghapus moda transportasi | ✅ Admin SIMPEG |

---

## Headers Standar

| Header | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ⚠️ (POST / PUT) |

---

## 1. GET /api/simpeg/master/jenis-transportasi

Mengambil daftar master moda transportasi dengan dukungan filter kepemilikan armada, pencarian kata kunci, pengurutan, dan paginasi.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | - | Pencarian berdasarkan nama, kode, atau deskripsi |
| `is_active` | boolean | ❌ | - | Filter status keaktifan (`true` / `false` / `1` / `0`) |
| `is_kendaraan_kampus` | boolean | ❌ | - | Filter kepemilikan armada kampus (`true`=Armada Kampus, `false`=Umum/Pribadi) |
| `sort_by` | string | ❌ | `urutan` | Kolom pengurutan (`id`, `nama`, `kode`, `urutan`, `is_kendaraan_kampus`, `created_at`) |
| `sort_dir` | string | ❌ | `asc` | Arah urutan (`asc` / `desc`) |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maksimal 100) |
| `page` | integer | ❌ | `1` | Nomor halaman paginasi |
| `all` | boolean | ❌ | `false` | Ambil seluruh data tanpa paginasi |

### Response 200 OK (Paginated)
```json
{
  "status": "success",
  "message": "Data master jenis transportasi berhasil diambil",
  "data": [
    {
      "id": 1,
      "kode": "TRN-MOBIL-DINAS",
      "nama": "Mobil Operasional Kampus",
      "is_kendaraan_kampus": true,
      "deskripsi": "Armada mobil operasional kampus dengan pengemudi",
      "urutan": 1,
      "is_active": true,
      "created_at": "2026-09-24T16:07:28.000000Z",
      "updated_at": "2026-09-27T22:45:00.000000Z"
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

## 2. POST /api/simpeg/master/jenis-transportasi

Menambahkan data moda transportasi dinas baru.

### Request Body
```json
{
  "nama": "Mobil Dinas Kampus",
  "kode": "MOBIL_KAMPUS",
  "is_kendaraan_kampus": true,
  "deskripsi": "Armada resmi operasional dinas kampus",
  "urutan": 1,
  "is_active": true
}
```

### Response 201 Created
```json
{
  "status": "success",
  "message": "Master moda transportasi berhasil ditambahkan",
  "data": {
    "id": 7,
    "nama": "Mobil Dinas Kampus",
    "kode": "MOBIL_KAMPUS",
    "is_kendaraan_kampus": true,
    "deskripsi": "Armada resmi operasional dinas kampus",
    "urutan": 1,
    "is_active": true,
    "created_at": "2026-09-27T22:45:00.000000Z",
    "updated_at": "2026-09-27T22:45:00.000000Z"
  }
}
```

### Response 422 Unprocessable Entity
```json
{
  "status": "error",
  "message": "Data yang diberikan tidak valid.",
  "errors": {
    "kode": [
      "Kode transportasi ini sudah digunakan."
    ]
  }
}
```

---

## 3. GET /api/simpeg/master/jenis-transportasi/{id}

Mengambil detail data satu moda transportasi.

### Response 200 OK
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "nama": "Mobil Operasional Kampus",
    "kode": "TRN-MOBIL-DINAS",
    "is_kendaraan_kampus": true,
    "deskripsi": "Armada mobil operasional kampus dengan pengemudi",
    "urutan": 1,
    "is_active": true,
    "created_at": "2026-09-24T16:07:28.000000Z",
    "updated_at": "2026-09-27T22:45:00.000000Z"
  }
}
```

### Response 404 Not Found
```json
{
  "status": "error",
  "message": "Data moda transportasi tidak ditemukan."
}
```

---

## 4. PUT /api/simpeg/master/jenis-transportasi/{id}

Memperbarui data moda transportasi dinas.

### Request Body
```json
{
  "nama": "Mobil Operasional Kampus Terpadu",
  "kode": "TRN-MOBIL-DINAS",
  "is_kendaraan_kampus": true,
  "deskripsi": "Armada mobil operasional kampus pusat",
  "urutan": 1,
  "is_active": true
}
```

### Response 200 OK
```json
{
  "status": "success",
  "message": "Master moda transportasi berhasil diperbarui",
  "data": {
    "id": 1,
    "nama": "Mobil Operasional Kampus Terpadu",
    "kode": "TRN-MOBIL-DINAS",
    "is_kendaraan_kampus": true,
    "deskripsi": "Armada mobil operasional kampus pusat",
    "urutan": 1,
    "is_active": true,
    "created_at": "2026-09-24T16:07:28.000000Z",
    "updated_at": "2026-09-27T22:45:00.000000Z"
  }
}
```

---

## 5. DELETE /api/simpeg/master/jenis-transportasi/{id}

Menghapus data master moda transportasi. Terlindungi oleh foreign key check terhadap tabel pengajuan surat tugas dinas (`simpeg_surat_tugas`).

### Response 200 OK
```json
{
  "status": "success",
  "message": "Moda transportasi berhasil dihapus"
}
```

### Response 422 Unprocessable Entity
```json
{
  "status": "error",
  "message": "Moda transportasi ini tidak dapat dihapus karena sudah digunakan pada surat tugas dinas."
}
```

### Response 403 Forbidden
```json
{
  "status": "error",
  "message": "Anda tidak memiliki hak akses untuk menghapus master jenis transportasi dinas."
}
```

---

## Catatan Integritas & Keamanan
- **Proteksi Relasi**: Master jenis transportasi tidak menggunakan *soft-delete*, penghapusan dicegah jika terdapat surat tugas dinas yang merujuk entitas ini (`exists()` guard).
- **Kepemilikan Armada**: Nilai boolean `is_kendaraan_kampus = true` menandai bahwa penugasan menggunakan armada resmi kampus sehingga form pengajuan surat tugas akan mewajibkan/membuka pilihan driver kampus dan nopol kendaraan.
