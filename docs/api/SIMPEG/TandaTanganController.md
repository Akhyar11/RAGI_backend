# TandaTanganController

> **Modul**: SIMPEG  
> **Base URL**: `/api/simpeg/tanda-tangan`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-10-01  
> **Diperbarui**: 2026-10-01  

Modul ini mengelola Master Tanda Tangan Digital untuk seluruh Pegawai (Dosen dan Tenaga Kependidikan) serta akun pengguna berwenang (Pejabat/Approver). Berkas tanda tangan disimpan secara privat di disk penyimpanan aman (`FileStorageService`) dan diakses menggunakan Signed URL sementara untuk mencegah akses langsung yang tidak berwenang. Tanda tangan digital aktif ditarik secara otomatis ke dalam dokumen resmi seperti Surat Izin Peminjaman Sarana dan Prasarana (SINAPRA), Berita Acara, dan Surat Tugas.

---

## Headers Standar

- `Authorization: Bearer {token}`
- `Accept: application/json`
- `Content-Type: application/json` (atau `multipart/form-data` saat mengunggah berkas)

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/simpeg/tanda-tangan` | Daftar master tanda tangan digital | ✅ `simpeg.tanda_tangan.read` |
| POST | `/api/simpeg/tanda-tangan` | Unggah & tambah tanda tangan digital baru | ✅ `simpeg.tanda_tangan.create` |
| GET | `/api/simpeg/tanda-tangan/{id}` | Detail tanda tangan digital | ✅ `simpeg.tanda_tangan.read` |
| POST/PUT | `/api/simpeg/tanda-tangan/{id}` | Perbarui data/file tanda tangan digital | ✅ `simpeg.tanda_tangan.update` |
| PATCH | `/api/simpeg/tanda-tangan/{id}/toggle-active` | Aktifkan/nonaktifkan tanda tangan | ✅ `simpeg.tanda_tangan.update` |
| DELETE | `/api/simpeg/tanda-tangan/{id}` | Hapus tanda tangan digital | ✅ `simpeg.tanda_tangan.delete` |
| GET | `/api/simpeg/tanda-tangan/user/{userId}` | Ambil tanda tangan aktif berdasarkan User ID | ✅ `simpeg.tanda_tangan.read` |

---

## 1. GET /api/simpeg/tanda-tangan

Mengambil daftar tanda tangan digital dengan pencarian, filter, dan pagination.

### Query Parameters

- `search` (string, optional) - Pencarian nama pegawai, email, username, judul tanda tangan, atau QR token.
- `user_id` (integer, optional) - Filter berdasarkan ID pengguna (`core_users.id`).
- `pegawai_id` (integer, optional) - Filter berdasarkan ID pegawai (`simpeg_pegawai.id`).
- `is_active` (boolean, optional) - Filter status aktif (`true` atau `false`).
- `sort_by` (string, default: `created_at`) - Kolom pengurutan (`id`, `created_at`, `judul`, `is_active`).
- `sort_order` (string, default: `desc`) - Arah pengurutan (`asc`, `desc`).
- `per_page` (integer, default: 15, max: 100) - Jumlah data per halaman.
- `page` (integer, default: 1) - Halaman data yang diambil.

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Daftar tanda tangan digital berhasil diambil",
  "data": [
    {
      "id": 1,
      "user_id": 4,
      "pegawai_id": 2,
      "file_path": "simpeg/tanda-tangan/a1b2c3d4-e5f6-7890-abcd-ef1234567890.png",
      "tipe": "gambar_spesimen",
      "judul": "Tanda Tangan Utama",
      "qr_token": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
      "is_active": true,
      "file_url": "https://ragibe.poltekindonusa.ac.id/api/files/view?path=simpeg%2Ftanda-tangan%2Fa1b2c3d4-e5f6-7890-abcd-ef1234567890.png&expires=1760000000&signature=abc123def456",
      "created_at": "2026-10-01T10:00:00.000000Z",
      "updated_at": "2026-10-01T10:00:00.000000Z",
      "deleted_at": null,
      "user": {
        "id": 4,
        "name": "Dr. Wasis Waluyo, M.Kom.",
        "email": "wasis@poltekindonusa.ac.id",
        "username": "wasis.waluyo"
      },
      "pegawai": {
        "id": 2,
        "user_id": 4,
        "nip": "198501012010121001",
        "nidn": "0601018501",
        "nama_lengkap": "Dr. Wasis Waluyo, M.Kom."
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
    "user_id": null,
    "pegawai_id": null,
    "is_active": null,
    "sort_by": "created_at",
    "sort_order": "desc"
  }
}
```

---

## 2. POST /api/simpeg/tanda-tangan

Mengunggah gambar spesimen tanda tangan digital untuk user/pegawai tertentu.

### Request Headers
- `Content-Type: multipart/form-data`

### Request Body (Form Data)

| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `user_id` | integer | ✅ | ID Akun Pengguna (`core_users.id`). |
| `pegawai_id` | integer | ❌ | ID Pegawai (`simpeg_pegawai.id`, otomatis terisi jika tidak dikirim). |
| `file_tanda_tangan` | file | ✅ | Berkas gambar (PNG, JPG, JPEG, maks. 2048 KB). PNG transparan disarankan. |
| `judul` | string | ❌ | Label/Judul tanda tangan (default: `Tanda Tangan Utama`). |
| `tipe` | string | ❌ | Tipe spesimen (default: `gambar_spesimen`). |
| `is_active` | boolean | ❌ | Jadikan tanda tangan aktif langsung (`true`/`false`, default: `true`). |

### Response Sukses (201 Created)

```json
{
  "status": "success",
  "message": "Tanda tangan digital berhasil ditambahkan",
  "data": {
    "id": 1,
    "user_id": 4,
    "pegawai_id": 2,
    "file_path": "simpeg/tanda-tangan/a1b2c3d4-e5f6-7890-abcd-ef1234567890.png",
    "tipe": "gambar_spesimen",
    "judul": "Tanda Tangan Utama",
    "qr_token": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
    "is_active": true,
    "file_url": "https://ragibe.poltekindonusa.ac.id/api/files/view?path=simpeg%2Ftanda-tangan%2Fa1b2c3d4-e5f6-7890-abcd-ef1234567890.png&expires=1760000000&signature=abc123def456",
    "created_at": "2026-10-01T10:00:00.000000Z",
    "updated_at": "2026-10-01T10:00:00.000000Z",
    "user": {
      "id": 4,
      "name": "Dr. Wasis Waluyo, M.Kom.",
      "email": "wasis@poltekindonusa.ac.id",
      "username": "wasis.waluyo"
    },
    "pegawai": {
      "id": 2,
      "user_id": 4,
      "nip": "198501012010121001",
      "nidn": "0601018501",
      "nama_lengkap": "Dr. Wasis Waluyo, M.Kom."
    }
  }
}
```

---

## 3. GET /api/simpeg/tanda-tangan/{id}

Mengambil detail rincian tanda tangan digital berdasarkan ID.

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Detail tanda tangan digital berhasil diambil",
  "data": {
    "id": 1,
    "user_id": 4,
    "pegawai_id": 2,
    "file_path": "simpeg/tanda-tangan/a1b2c3d4-e5f6-7890-abcd-ef1234567890.png",
    "tipe": "gambar_spesimen",
    "judul": "Tanda Tangan Utama",
    "qr_token": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
    "is_active": true,
    "file_url": "https://ragibe.poltekindonusa.ac.id/api/files/view?path=simpeg%2Ftanda-tangan%2Fa1b2c3d4-e5f6-7890-abcd-ef1234567890.png&expires=1760000000&signature=abc123def456"
  }
}
```

---

## 4. PATCH /api/simpeg/tanda-tangan/{id}/toggle-active

Mengaktifkan tanda tangan tertentu sebagai tanda tangan utama pengguna (otomatis menonaktifkan tanda tangan lama milik pengguna yang sama).

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Status tanda tangan digital berhasil diperbarui",
  "data": {
    "id": 1,
    "user_id": 4,
    "is_active": true,
    "judul": "Tanda Tangan Utama"
  }
}
```

---

## 5. DELETE /api/simpeg/tanda-tangan/{id}

Menghapus tanda tangan digital (soft delete di database dan menghapus berkas fisik di disk penyimpanan).

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Tanda tangan digital berhasil dihapus",
  "data": null
}
```

---

## 6. GET /api/simpeg/tanda-tangan/user/{userId}

Mengambil spesimen tanda tangan digital yang sedang berstatus AKTIF (`is_active = true`) untuk user tertentu. Digunakan oleh modul SINAPRA, SIKEU, atau SIAKAD ketika mencetak surat resmi dan dokumen pengesahan.

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Tanda tangan digital aktif berhasil diambil",
  "data": {
    "id": 1,
    "user_id": 4,
    "pegawai_id": 2,
    "judul": "Tanda Tangan Utama",
    "qr_token": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
    "is_active": true,
    "file_url": "https://ragibe.poltekindonusa.ac.id/api/files/view?path=simpeg%2Ftanda-tangan%2Fa1b2c3d4-e5f6-7890-abcd-ef1234567890.png&expires=1760000000&signature=abc123def456"
  }
}
```

---

## Respons Error Standar

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
  "message": "Anda tidak memiliki hak akses untuk melihat master tanda tangan digital."
}
```

### 404 Not Found
```json
{
  "status": "error",
  "message": "Tanda tangan digital aktif tidak ditemukan untuk pengguna ini",
  "data": null
}
```

### 422 Unprocessable Entity (Validasi Gagal)
```json
{
  "status": "error",
  "message": "The given data was invalid.",
  "errors": {
    "file_tanda_tangan": [
      "Format berkas tanda tangan harus PNG, JPG, atau JPEG (PNG transparan disarankan)."
    ],
    "user_id": [
      "Pengguna (user) wajib dipilih."
    ]
  }
}
```

---

## Catatan Keamanan & Penyimpanan Berkas
- **Disk Privat & Signed URL**: Berkas tanda tangan digital tersimpan di direktori privat (`simpeg/tanda-tangan/`) dan dilindungi dari akses langsung publik. Akses berkas hanya dapat dilakukan melalui Signed URL (`/api/files/view?path=...`) yang memiliki masa berlaku terbatas (15 menit).
- **Soft Deletes**: Entitas tanda tangan mengimplementasikan soft delete untuk memastikan jejak riwayat surat masa lalu yang telah disahkan tetap dapat diaudit.
