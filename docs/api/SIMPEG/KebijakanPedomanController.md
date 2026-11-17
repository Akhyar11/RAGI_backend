# Dokumentasi API: Kebijakan dan Pedoman Kepegawaian (KebijakanPedomanController)

> **Modul**: SIMPEG (Kepegawaian Terintegrasi)  
> **Base URL**: `/api/simpeg/kebijakan-pedoman`  
> **Autentikasi**: Bearer Token (Passport)  
> **Dibuat**: 2026-11-17  
> **Diperbarui**: 2026-11-17  

Modul ini mengelola kebijakan, pedoman, standar operasional prosedur (SOP), peraturan, dan surat edaran kepegawaian institusi. Modul ini terintegrasi dua arah dengan **Modul ARSIP & Persuratan**:
1. Nomor surat resmi kebijakan divalidasi ke Modul Arsip (`core_arsip_nomor_surat`).
2. Perihal dan tanggal surat ditarik otomatis dari nomor arsip yang diterbitkan.
3. Dokumen scan PDF kebijakan yang diunggah otomatis tersinkronisasi ke data nomor surat pada Modul Arsip (`file_surat_path` & `file_path`), sehingga data persuratan langsung memiliki dokumen digital resmi.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth / Permission |
|---|---|---|---|
| GET | `/api/simpeg/kebijakan-pedoman/masters` | Mengambil data master kategori kebijakan aktif | ✅ `simpeg.kebijakan_pedoman.read` |
| GET | `/api/simpeg/kebijakan-pedoman` | Daftar kebijakan & pedoman dengan pagination dan filter | ✅ `simpeg.kebijakan_pedoman.read` |
| GET | `/api/simpeg/kebijakan-pedoman/{id}` | Rincian detail kebijakan dan pedoman | ✅ `simpeg.kebijakan_pedoman.read` |
| POST | `/api/simpeg/kebijakan-pedoman` | Menambah kebijakan baru & sinkronisasi dokumen ke Arsip | ✅ `simpeg.kebijakan_pedoman.create` |
| POST/PUT | `/api/simpeg/kebijakan-pedoman/{id}` | Memperbarui data/berkas scan kebijakan dan pedoman | ✅ `simpeg.kebijakan_pedoman.update` |
| DELETE | `/api/simpeg/kebijakan-pedoman/{id}` | Menghapus data kebijakan (soft delete) | ✅ `simpeg.kebijakan_pedoman.delete` |

---

## 1. GET /api/simpeg/kebijakan-pedoman/masters

Mengambil daftar kategori kebijakan dan pedoman aktif (Peraturan, SK, Pedoman/Panduan, Surat Edaran, SOP, Instruksi Kerja).

### Headers
| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)
```json
{
  "status": "success",
  "message": "Master kategori kebijakan berhasil diambil",
  "data": [
    {
      "id": 1,
      "kode": "PERATURAN",
      "nama": "Peraturan",
      "deskripsi": "Peraturan Yayasan, Rektor, atau Kebijakan Institusi Tingkat Tertinggi",
      "urutan": 1,
      "is_active": true
    },
    {
      "id": 2,
      "kode": "SK",
      "nama": "Surat Keputusan (SK)",
      "deskripsi": "Surat Keputusan Pimpinan Terkait Kebijakan & Ketentuan Kepegawaian",
      "urutan": 2,
      "is_active": true
    },
    {
      "id": 3,
      "kode": "PEDOMAN",
      "nama": "Pedoman & Panduan",
      "deskripsi": "Buku Pedoman, Panduan Teknis, atau Tata Tertib Kepegawaian",
      "urutan": 3,
      "is_active": true
    }
  ]
}
```

---

## 2. GET /api/simpeg/kebijakan-pedoman

Menampilkan daftar repositori kebijakan dan pedoman kepegawaian dengan filter dan paginasi server-side.

### Headers
| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters
| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `page` | integer | ❌ | `1` | Nomor halaman data |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks: 100) |
| `search` | string | ❌ | — | Pencarian nomor surat, judul, pejabat, atau deskripsi |
| `kategori_kebijakan_id` | integer | ❌ | — | Filter ID kategori kebijakan (`simpeg_master_kategori_kebijakan`) |
| `tahun` | integer | ❌ | — | Filter tahun penetapan dokumen |
| `is_active` | boolean | ❌ | — | Filter status berlaku (`true` / `false`) |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan (`created_at`, `tanggal_penetapan`, `tanggal_berlaku`, `judul`, `nomor_surat`) |
| `sort_order` | string | ❌ | `desc` | Arah pengurutan (`asc` / `desc`) |

### Response Sukses (200 OK)
```json
{
  "status": "success",
  "message": "Daftar kebijakan dan pedoman berhasil diambil",
  "data": [
    {
      "id": 1,
      "nomor_surat_arsip_id": 105,
      "kategori_kebijakan_id": 3,
      "nomor_surat": "012/SK-REK/IT/2026",
      "judul": "Pedoman Tata Tertib dan Jam Kerja Tenaga Kependidikan",
      "deskripsi": "Ketentuan mengenai jam operasional dan tata tertib kehadiran pegawai",
      "pejabat_penetap": "Rektor Institut",
      "tanggal_penetapan": "2026-01-10",
      "tanggal_berlaku": "2026-01-15",
      "tanggal_berakhir": null,
      "file_dokumen_path": "simpeg/kebijakan_pedoman/uuid-file.pdf",
      "file_dokumen_url": "https://api.campus.ac.id/api/files/view?path=simpeg%2Fkebijakan_pedoman%2Fuuid-file.pdf&expires=...",
      "is_active": true,
      "created_by": 1,
      "updated_by": 1,
      "created_at": "2026-01-10T08:30:00.000000Z",
      "updated_at": "2026-01-10T08:30:00.000000Z",
      "kategori": {
        "id": 3,
        "kode": "PEDOMAN",
        "nama": "Pedoman & Panduan"
      },
      "nomor_surat_arsip": {
        "id": 105,
        "nomor_surat": "012/SK-REK/IT/2026",
        "perihal": "Pedoman Tata Tertib Kepegawaian",
        "kode_unit": "REK",
        "kode_klasifikasi": "KP.01",
        "tanggal_surat": "2026-01-10",
        "status": "terpakai"
      },
      "pembuat": {
        "id": 1,
        "name": "Administrator SDM",
        "email": "admin.sdm@kampus.ac.id",
        "username": "adminsdm"
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
  }
}
```

---

## 3. GET /api/simpeg/kebijakan-pedoman/{id}

Menampilkan detail rincian satu dokumen kebijakan dan pedoman.

### Headers
| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)
```json
{
  "status": "success",
  "message": "Rincian kebijakan dan pedoman berhasil diambil",
  "data": {
    "id": 1,
    "nomor_surat_arsip_id": 105,
    "kategori_kebijakan_id": 3,
    "nomor_surat": "012/SK-REK/IT/2026",
    "judul": "Pedoman Tata Tertib dan Jam Kerja Tenaga Kependidikan",
    "deskripsi": "Ketentuan mengenai jam operasional dan tata tertib kehadiran pegawai",
    "pejabat_penetap": "Rektor Institut",
    "tanggal_penetapan": "2026-01-10",
    "tanggal_berlaku": "2026-01-15",
    "tanggal_berakhir": null,
    "file_dokumen_path": "simpeg/kebijakan_pedoman/uuid-file.pdf",
    "file_dokumen_url": "https://api.campus.ac.id/api/files/view?path=simpeg%2Fkebijakan_pedoman%2Fuuid-file.pdf&expires=...",
    "is_active": true,
    "kategori": {
      "id": 3,
      "kode": "PEDOMAN",
      "nama": "Pedoman & Panduan",
      "deskripsi": "Buku Pedoman, Panduan Teknis, atau Tata Tertib Kepegawaian"
    },
    "nomor_surat_arsip": {
      "id": 105,
      "nomor_surat": "012/SK-REK/IT/2026",
      "perihal": "Pedoman Tata Tertib Kepegawaian",
      "kode_unit": "REK",
      "kode_klasifikasi": "KP.01",
      "tanggal_surat": "2026-01-10",
      "status": "terpakai"
    },
    "pembuat": {
      "id": 1,
      "name": "Administrator SDM",
      "email": "admin.sdm@kampus.ac.id",
      "username": "adminsdm"
    }
  }
}
```

---

## 4. POST /api/simpeg/kebijakan-pedoman

Menambahkan kebijakan dan pedoman baru beserta file scan PDF. Endpoint ini secara otomatis:
- Menyimpan file PDF ke disk penyimpanan privat.
- Memperbarui nomor surat di modul Arsip (`core_arsip_nomor_surat`), mengisi `file_surat_path`, mengalihkan status menjadi `'terpakai'`, dan menetapkan polimorfik referensi.

### Headers
| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `multipart/form-data` | ✅ |

### Request Body (Multipart/Form-Data)
| Key | Type | Required | Deskripsi |
|---|---|---|---|
| `nomor_surat` | string | ✅ | Nomor surat resmi yang terbit di Arsip |
| `file_dokumen` | file (pdf) | ✅ | Berkas scan dokumen PDF resmi (maks. 10MB / 10240 KB) |
| `nomor_surat_arsip_id` | integer | ❌ | ID nomor surat dari tabel `core_arsip_nomor_surat` (otomatis jika tidak dikirim) |
| `judul` | string | ❌ | Judul kebijakan / pedoman (otomatis mengambil perihal surat jika kosong) |
| `kategori_kebijakan_id`| integer | ✅ | ID kategori dari `simpeg_master_kategori_kebijakan` |
| `pejabat_penetap` | string | ❌ | Nama atau jabatan penetap (misal: Rektor Institut) |
| `tanggal_penetapan` | date (Y-m-d) | ❌ | Tanggal penetapan surat (otomatis dari tanggal surat arsip) |
| `tanggal_berlaku` | date (Y-m-d) | ❌ | Tanggal mulai berlakunya kebijakan |
| `tanggal_berakhir` | date (Y-m-d) | ❌ | Tanggal berakhirnya masa berlaku kebijakan |
| `deskripsi` | string | ❌ | Catatan ringkas / keterangan perihal kebijakan |
| `is_active` | boolean | ❌ | Status keberlakuan aktif dokumen (default: true) |

### Response Sukses (201 Created)
```json
{
  "status": "success",
  "message": "Kebijakan dan pedoman berhasil ditambahkan dan disinkronkan ke Arsip",
  "data": {
    "id": 1,
    "nomor_surat_arsip_id": 105,
    "kategori_kebijakan_id": 3,
    "nomor_surat": "012/SK-REK/IT/2026",
    "judul": "Pedoman Tata Tertib dan Jam Kerja Tenaga Kependidikan",
    "file_dokumen_url": "https://api.campus.ac.id/api/files/view?path=simpeg%2Fkebijakan_pedoman%2Fuuid-file.pdf&expires=...",
    "is_active": true
  }
}
```

---

## 5. POST/PUT /api/simpeg/kebijakan-pedoman/{id}

Memperbarui data kebijakan dan pedoman, serta opsional mengganti berkas scan PDF. Jika berkas baru diunggah, dokumen di Modul Arsip otomatis terbarukan.

### Headers
| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `multipart/form-data` | Jika unggah file baru |

### Request Body
Sama dengan endpoint POST, dengan `file_dokumen` bersifat opsional (nullable).

### Response Sukses (200 OK)
```json
{
  "status": "success",
  "message": "Kebijakan dan pedoman berhasil diperbarui",
  "data": {
    "id": 1,
    "judul": "Pedoman Tata Tertib dan Jam Kerja Pegawai (Revisi 2026)",
    "is_active": true
  }
}
```

---

## 6. DELETE /api/simpeg/kebijakan-pedoman/{id}

Menghapus dokumen kebijakan dan pedoman (soft delete).

### Headers
| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)
```json
{
  "status": "success",
  "message": "Kebijakan dan pedoman berhasil dihapus",
  "data": null
}
```

---

## Error Responses

### 400 Bad Request
```json
{
  "status": "error",
  "message": "Permintaan tidak valid atau format berkas unggahan tidak sesuai."
}
```

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
  "message": "Anda tidak memiliki hak akses untuk melihat kebijakan dan pedoman."
}
```

### 404 Not Found
```json
{
  "status": "error",
  "message": "No query results for model [App\\Models\\Simpeg\\KebijakanPedoman] 999"
}
```

### 422 Unprocessable Entity
```json
{
  "status": "error",
  "message": "Nomor surat resmi wajib diisi.",
  "errors": {
    "nomor_surat": [
      "Nomor surat resmi wajib diisi."
    ],
    "file_dokumen": [
      "Berkas dokumen scan wajib diunggah."
    ]
  }
}
```
