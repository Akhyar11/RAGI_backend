# MahasiswaBeasiswaController

> **Modul**: SIAKAD (Akademik & BAAK)  
> **Base URL**: `/api/v1/siakad`  
> **Autentikasi**: Bearer Token (Sanctum / Passport)  
> **Dibuat**: 2026-09-16  
> **Diperbarui**: 2026-09-16

Controller ini mengelola penetapan penerima program beasiswa mahasiswa oleh Administrator BAAK. BAAK bertugas memilih mahasiswa dan program beasiswanya, sementara skema dan besaran pemotongan biaya beasiswa tetap dikonfigurasi oleh Keuangan (SIKEU).

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/siakad/civitas/beasiswa` | Daftar mahasiswa penerima beasiswa | ✅ BAAK / Admin |
| GET | `/api/v1/siakad/civitas/beasiswa/options` | Daftar pilihan program beasiswa aktif (dari SIKEU) | ✅ BAAK / Admin |
| POST | `/api/v1/siakad/civitas/beasiswa` | Tetapkan mahasiswa penerima beasiswa baru | ✅ BAAK / Admin |
| GET | `/api/v1/siakad/civitas/beasiswa/{id}` | Rincian penetapan beasiswa mahasiswa | ✅ BAAK / Admin |
| PUT | `/api/v1/siakad/civitas/beasiswa/{id}` | Perbarui data penetapan beasiswa mahasiswa | ✅ BAAK / Admin |
| DELETE | `/api/v1/siakad/civitas/beasiswa/{id}` | Hapus penetapan beasiswa mahasiswa | ✅ BAAK / Admin |

---

## GET /api/v1/siakad/civitas/beasiswa

Mengambil daftar mahasiswa penerima beasiswa dengan filter, sorting, dan server-side pagination.

### Query Parameters
- `search` / `q` (string, opsional): Pencarian nama mahasiswa, NIM, atau nama beasiswa.
- `status` (string, opsional): Filter status (`aktif`, `nonaktif`, `selesai`).
- `beasiswa_id` (integer, opsional): Filter berdasarkan ID program beasiswa.
- `sort_by` (string, opsional): Kolom pengurutan (`nama_mahasiswa`, `nim`, `status`, `berlaku_mulai`, `id`). Default: `nama_mahasiswa`.
- `sort_order` (string, opsional): Arah pengurutan (`asc` atau `desc`). Default: `asc`.
- `per_page` (integer, opsional): Jumlah per halaman (default: 15, max: 100).
- `page` (integer, opsional): Nomor halaman.

### Response Sukses (200 OK)
```json
{
  "status": "success",
  "message": "Daftar penerima beasiswa berhasil dimuat",
  "data": [
    {
      "id": 1,
      "mahasiswa_id": 105,
      "nim": "202401001",
      "nama_mahasiswa": "Ahmad Dani",
      "prodi": "Teknik Informatika",
      "angkatan": 2024,
      "beasiswa_id": 2,
      "kode_beasiswa": "KIP_KULIAH",
      "nama_beasiswa": "KIP Kuliah Merdeka",
      "sumber_beasiswa": "pemerintah",
      "tipe_potongan": "persen",
      "nilai_potongan": 100,
      "potongan_text": "100%",
      "berlaku_mulai": "2024-09-01",
      "berlaku_sampai": "2025-08-31",
      "status": "aktif",
      "created_at": "2026-09-16 10:00:00"
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
    "beasiswa_id": null,
    "sort_by": "nama_mahasiswa",
    "sort_order": "asc"
  }
}
```

---

## GET /api/v1/siakad/civitas/beasiswa/options

Mengambil daftar master beasiswa aktif dari SIKEU yang siap dipilih oleh BAAK. Menampilkan ringkasan potongan sebagai informasi read-only.

### Response Sukses (200 OK)
```json
{
  "status": "success",
  "message": "Daftar pilihan program beasiswa berhasil dimuat",
  "data": [
    {
      "id": 1,
      "kode": "BEASISWA_PRESTASI",
      "nama": "Beasiswa Prestasi Akademik",
      "sumber": "yayasan",
      "tipe_potongan": "persen",
      "nilai_potongan": 50,
      "potongan_text": "50%",
      "deskripsi": "Potongan 50% UKT semester untuk mahasiswa berprestasi"
    }
  ]
}
```

---

## POST /api/v1/siakad/civitas/beasiswa

Menetapkan mahasiswa tertentu untuk memperoleh program beasiswa.

### Request Body
```json
{
  "mahasiswa_id": 105,
  "beasiswa_id": 2,
  "berlaku_mulai": "2026-09-01",
  "berlaku_sampai": "2027-08-31",
  "status": "aktif"
}
```

### Response Sukses (201 Created)
```json
{
  "status": "success",
  "message": "Mahasiswa berhasil ditetapkan sebagai penerima beasiswa",
  "data": {
    "id": 1,
    "mahasiswa_id": 105,
    "beasiswa_id": 2,
    "nim": "202401001",
    "nama_mahasiswa": "Ahmad Dani",
    "berlaku_mulai": "2026-09-01",
    "berlaku_sampai": "2027-08-31",
    "status": "aktif"
  }
}
```

---

## PUT /api/v1/siakad/civitas/beasiswa/{id}

Memperbarui status atau masa berlaku penetapan beasiswa mahasiswa.

### Request Body
```json
{
  "beasiswa_id": 2,
  "berlaku_mulai": "2026-09-01",
  "berlaku_sampai": "2027-08-31",
  "status": "selesai"
}
```

### Response Sukses (200 OK)
```json
{
  "status": "success",
  "message": "Penetapan beasiswa mahasiswa berhasil diperbarui",
  "data": {
    "id": 1,
    "status": "selesai"
  }
}
```

---

## DELETE /api/v1/siakad/civitas/beasiswa/{id}

Menghapus penetapan beasiswa mahasiswa.

### Response Sukses (200 OK)
```json
{
  "status": "success",
  "message": "Penetapan beasiswa mahasiswa berhasil dihapus"
}
```
