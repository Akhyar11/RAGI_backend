# API Dokumentasi: Master Tipe Referensi

Dokumentasi ini menjelaskan _endpoint_ untuk pengelolaan kategori master tipe referensi (`core_tipe_referensi`) sistem kampus. Tipe referensi ini berfungsi sebagai induk kategori untuk seluruh opsi referensi di sistem.

**Base URL:** `/api/admin/master-tipe-referensi` & `/api/tipe-referensi`

---

## 1. List Master Tipe Referensi
Menampilkan daftar seluruh tipe referensi beserta total item terkait (`items_count`).

**Endpoint:** `GET /api/admin/master-tipe-referensi`  
**Auth Required:** Yes (Bearer Token, Admin)

### Parameter Query (Opsional)
- `modul` (string): Filter berdasarkan modul (`all`, `global`, `spmb`, `siakad`, `simpeg`, `sikeu`).
- `search` (string): Mencari berdasarkan kode, nama, atau deskripsi.
- `is_active` (bool): Filter status aktif.
- `page` (int): Nomor halaman paginasi.
- `per_page` (int): Jumlah item per halaman.

### Response Sukses (200 OK)
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "kode": "agama",
      "nama": "Agama",
      "modul": "global",
      "deskripsi": "Daftar agama resmi",
      "urutan": 1,
      "is_active": true,
      "items_count": 6,
      "created_at": "2026-09-17T04:00:00.000000Z",
      "updated_at": "2026-09-17T04:00:00.000000Z"
    }
  ]
}
```

---

## 2. Tambah Master Tipe Baru
Membuat kategori tipe referensi baru.

**Endpoint:** `POST /api/admin/master-tipe-referensi`  
**Auth Required:** Yes (Bearer Token, Admin)

### Request Body
```json
{
  "kode": "kewarganegaraan",
  "nama": "Kewarganegaraan",
  "modul": "global",
  "deskripsi": "Kewarganegaraan civitas akademika",
  "urutan": 3,
  "is_active": true
}
```

### Response Sukses (201 Created)
```json
{
  "status": "success",
  "message": "Tipe referensi berhasil ditambahkan.",
  "data": { ... }
}
```

---

## 3. Detail Master Tipe
Mengambil detail satu tipe referensi berdasarkan ID atau kode unik.

**Endpoint:** `GET /api/admin/master-tipe-referensi/{id}`  
**Auth Required:** Yes (Bearer Token, Admin)

---

## 4. Ubah Master Tipe
Mengubah data tipe referensi. Jika `kode` diubah, seluruh data item pada `spmb_master_referensi` yang menggunakan kode lama akan otomatis di-update dalam satu transaksi database.

**Endpoint:** `PUT /api/admin/master-tipe-referensi/{id}`  
**Auth Required:** Yes (Bearer Token, Admin)

---

## 5. Toggle Status Aktif
Mengubah status aktif/nonaktif tipe referensi.

**Endpoint:** `PATCH /api/admin/master-tipe-referensi/{id}/toggle`  
**Auth Required:** Yes (Bearer Token, Admin)

---

## 6. Hapus Master Tipe
Menghapus tipe referensi. Sistem akan menolak penghapusan dengan status **422 Unprocessable Entity** jika tipe tersebut masih memiliki data item di dalamnya.

**Endpoint:** `DELETE /api/admin/master-tipe-referensi/{id}`  
**Auth Required:** Yes (Bearer Token, Admin)

---

## 7. Lookup Master Tipe (Public / Form)
Endpoint untuk pengisian dropdown pilihan master tipe di formulir.

**Endpoint:** `GET /api/tipe-referensi`  
**Auth Required:** No / Optional
