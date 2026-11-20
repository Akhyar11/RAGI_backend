# AdminProdiController

> **Modul**: SIAKAD  
> **Base URL**: `/api/v1/siakad/akademik/admin-prodi`  
> **Autentikasi**: Bearer Token (Sanctum / Passport)  
> **Dibuat/Diperbarui**: 2026-10-08

Mengelola penugasan Admin OBE / Tim Kurikulum per Program Studi dan fitur impersonasi akun pengguna di modul SIAKAD.

---

## Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ untuk POST/PUT |

## Ringkasan Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/siakad/akademik/admin-prodi` | Daftar penugasan Admin OBE | `siakad.master.manage` |
| POST | `/api/v1/siakad/akademik/admin-prodi` | Tugaskan Admin OBE ke prodi | `siakad.master.manage` |
| GET | `/api/v1/siakad/akademik/admin-prodi/eligible-personnel` | Ambil opsi personel aktif yang eligible | `siakad.master.manage` |
| GET | `/api/v1/siakad/akademik/admin-prodi/{id}` | Detail penugasan | `siakad.master.manage` |
| PUT | `/api/v1/siakad/akademik/admin-prodi/{id}` | Perbarui penugasan | `siakad.master.manage` |
| DELETE | `/api/v1/siakad/akademik/admin-prodi/{id}` | Hapus penugasan | `siakad.master.manage` |

---

## 1. Daftar Penugasan Admin OBE

### [GET] `/api/v1/siakad/akademik/admin-prodi`
Mengambil daftar penugasan admin OBE program studi.

**Query Parameters**:
| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `search` | string | ❌ | Cari nama user, email, jabatan, atau nama prodi |
| `program_studi_id` | integer | ❌ | Filter ID program studi |
| `user_id` | integer | ❌ | Filter ID user |
| `can_approve_rps` | boolean | ❌ | Filter hak approval RPS |
| `is_active` | boolean | ❌ | Filter status penugasan |
| `sort_by` | string | ❌ | `user`, `program_studi`, `jabatan`, `created_at` |
| `sort_order` | string | ❌ | `asc` / `desc` |
| `per_page` | integer | ❌ | Limit pagination |
| `page` | integer | ❌ | Nomor halaman |

---

## 2. Tugaskan Admin OBE ke Prodi

### [POST] `/api/v1/siakad/akademik/admin-prodi`
Menugaskan user/dosen sebagai Admin OBE program studi.

**Request Body**:

```json
{
  "program_studi_id": 1,
  "user_id": 13,
  "jabatan": "Admin OBE / Tim Kurikulum",
  "can_approve_rps": true,
  "is_active": true
}
```

## 3. Opsi Personel Eligible Admin OBE

### [GET] `/api/v1/siakad/akademik/admin-prodi/eligible-personnel`

Mengambil akun aktif yang terhubung dengan Dosen SIAKAD aktif atau Pegawai SIMPEG aktif untuk dipilih sebagai Admin OBE. Akun umum, akun nonaktif, superadmin, dan role yang direstriksi tidak dikembalikan.

**Query Parameters**:

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nama, username, email, NIDN/NIP/NUPTK. |
| `page` | integer | ❌ | `1` | Halaman hasil. |
| `per_page` | integer | ❌ | `50` | Jumlah hasil per halaman, maksimum `100`. |
| `sort_order` | string | ❌ | `asc` | Urutan nama: `asc` atau `desc`. |

Setiap opsi mengembalikan `id` akun `core_users` untuk payload penugasan `user_id`, serta nama, username/email, tipe personel, identitas personel, dan homebase bila tersedia.

**Response Sukses (200 OK)**:

```json
{
  "status": "success",
  "message": "Opsi personel Admin OBE berhasil diambil",
  "data": [
    {
      "id": 15,
      "name": "Dr. Dosen Contoh, M.Kom.",
      "username": "dosen_contoh",
      "email": "dosen@univ.ac.id",
      "personnel_type": "Dosen / Pegawai",
      "personnel_identifier": "0011223344 / 198501012010121001",
      "homebase": "Teknik Informatika"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 50,
    "total": 1,
    "last_page": 1,
    "from": 1,
    "to": 1
  }
}
```

**Response Tanpa Izin (403 Forbidden)**:

```json
{
  "status": "error",
  "message": "Anda tidak memiliki hak akses."
}
```

**Response Tanpa Autentikasi (401 Unauthorized)** memakai envelope standar aplikasi:

```json
{
  "status": "error",
  "message": "Unauthenticated."
}
```

## 4. Detail Penugasan
```json
{
  "program_studi_id": 1,
  "user_id": 13,
  "jabatan": "Admin OBE / Tim Kurikulum",
  "can_approve_rps": true,
  "is_active": true
}
```

---

## 3. Detail Penugasan

### [GET] `/api/v1/siakad/akademik/admin-prodi/{id}`
Mengambil detail satu penugasan Admin OBE.

---

## 5. Perbarui Penugasan

### [PUT] `/api/v1/siakad/akademik/admin-prodi/{id}`
Memperbarui jabatan, status approval RPS, atau status aktif penugasan.

---

## 6. Hapus Penugasan

### [DELETE] `/api/v1/siakad/akademik/admin-prodi/{id}`
Menghapus penugasan Admin OBE dari program studi.

---

## 7. Merasuki (Impersonasi) Akun Admin OBE

### [POST] `/api/v1/siakad/akademik/admin-prodi/{id}/impersonate`
Merasuki akun Admin OBE dan menerbitkan token terisolasi lingkup SIAKAD.
