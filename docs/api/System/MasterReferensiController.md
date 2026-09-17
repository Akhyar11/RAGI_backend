# API Dokumentasi: Master Referensi

Dokumentasi ini menjelaskan _endpoint_ untuk pengelolaan opsi data referensi kampus (Agama, Status Sipil, Kewarganegaraan, dll) lintas modul universitas (SSO, SPMB, SIAKAD, SIMPEG, SIKEU).

**Base URL:** `/api/admin/master-referensi` & `/api/referensi`

---

## 1. List Master Referensi
Menampilkan daftar item referensi dengan filter modul, tipe, pencarian, dan paginasi.

**Endpoint:** `GET /api/admin/master-referensi`  
**Auth Required:** Yes (Bearer Token, Admin)

### Parameter Query (Opsional)
- `modul` (string): Filter berdasarkan modul (`all`, `global`, `spmb`, `siakad`, `simpeg`, `sikeu`).
- `tipe` (string): Filter berdasarkan kategori kode tipe referensi.
- `search` (string): Pencarian nama, kode, atau tipe.
- `is_active` (bool): Filter status aktif.
- `page` (int): Nomor halaman paginasi.
- `per_page` (int): Jumlah item per halaman (default: 20).

### Response Sukses (200 OK)
```json
{
  "status": "success",
  "message": "Daftar master referensi berhasil diambil.",
  "data": [
    {
      "id": 1,
      "tipe": "agama",
      "modul": "global",
      "kode": "ISLAM",
      "nama": "Islam",
      "urutan": 1,
      "is_active": true,
      "created_at": "2026-09-17T04:00:00.000000Z",
      "updated_at": "2026-09-17T04:00:00.000000Z"
    }
  ]
}
```

---

## 2. Kategori Tipe & Modul
Mengambil ringkasan kategori tipe referensi terdaftar beserta jumlah itemnya.

**Endpoint:** `GET /api/admin/master-referensi/categories`  
**Auth Required:** Yes (Bearer Token, Admin)

### Response Sukses (200 OK)
```json
{
  "status": "success",
  "data": {
    "categories": [
      {
        "tipe": "agama",
        "nama": "Agama",
        "modul": "global",
        "deskripsi": "Daftar agama resmi",
        "total_items": 6
      }
    ],
    "modules": [
      { "modul": "global", "total_items": 10 },
      { "modul": "spmb", "total_items": 20 }
    ]
  }
}
```

---

## 3. Tambah Item Referensi Baru
Menambahkan item referensi baru.

**Endpoint:** `POST /api/admin/master-referensi`  
**Auth Required:** Yes (Bearer Token, Admin)

### Request Body
```json
{
  "tipe": "agama",
  "modul": "global",
  "kode": "HINDU",
  "nama": "Hindu",
  "urutan": 4,
  "is_active": true
}
```

### Response Sukses (201 Created)
```json
{
  "status": "success",
  "message": "Data referensi berhasil ditambahkan.",
  "data": { ... }
}
```

---

## 4. Ubah Item Referensi
Mengubah data item referensi yang sudah ada.

**Endpoint:** `PUT /api/admin/master-referensi/{id}`  
**Auth Required:** Yes (Bearer Token, Admin)

---

## 5. Toggle Status Aktif
Mengubah status aktif/nonaktif item referensi secara cepat.

**Endpoint:** `PATCH /api/admin/master-referensi/{id}/toggle`  
**Auth Required:** Yes (Bearer Token, Admin)

---

## 6. Hapus Item Referensi
Menghapus item referensi berdasarkan ID.

**Endpoint:** `DELETE /api/admin/master-referensi/{id}`  
**Auth Required:** Yes (Bearer Token, Admin)

---

## 7. Dropdown Public Lookup
Endpoint publik / terotentikasi untuk pengisian pilihan formulir dropdown berdasarkan tipe.

**Endpoint:** `GET /api/referensi/{tipe}`  
**Auth Required:** No / Optional (Public read-only)

### Parameter Query (Opsional)
- `modul` (string): Filter modul (default menyertakan `global` + modul terkait).
