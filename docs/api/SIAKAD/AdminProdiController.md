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

## 4. Perbarui Penugasan

### [PUT] `/api/v1/siakad/akademik/admin-prodi/{id}`
Memperbarui jabatan, status approval RPS, atau status aktif penugasan.

---

## 5. Hapus Penugasan

### [DELETE] `/api/v1/siakad/akademik/admin-prodi/{id}`
Menghapus penugasan Admin OBE dari program studi.

---

## 6. Merasuki (Impersonasi) Akun Admin OBE

### [POST] `/api/v1/siakad/akademik/admin-prodi/{id}/impersonate`
Merasuki akun Admin OBE dan menerbitkan token terisolasi lingkup SIAKAD.
