# MobileStaffController

> **Modul**: Aplikasi Mobile Staf & Dosen Terpadu (myInU - Staff)  
> **Base URL**: `/api/v1/mobile/staff`  
> **Autentikasi**: Bearer Token (`auth:api`)  
> **Dibuat**: 2026-11-21  
> **Diperbarui**: 2026-11-21  

## Deskripsi Singkat

`MobileStaffController` menyediakan gateway API backend terpadu untuk aplikasi mobile dosen dan tenaga kependidikan (myInU - Staff). Modul ini menyajikan informasi ringkasan beranda staf, pelacakan jadwal mengajar harian & semester berjalan yang tersinkronisasi dengan SIAKAD, serta sinkronisasi foto profil (avatar) staf.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Autentikasi / Akses |
|---|---|---|---|
| GET | `/api/v1/mobile/staff/jadwal-mengajar` | Jadwal mengajar dosen (hari ini & semester berjalan) | ✅ Bearer Token (`auth:api` / `siakad.kelas.read`) |
| GET | `/api/v1/mobile/staff/home` | Ringkasan dashboard beranda staf & dosen | ✅ Bearer Token (`auth:api` / `siakad.kelas.read`) |
| POST | `/api/v1/mobile/staff/avatar` | Unggah & perbarui foto profil staf (multipart / base64) | ✅ Bearer Token (`auth:api` / `update` self) |
| GET | `/api/v1/siakad/perkuliahan/jadwal-mengajar` | Endpoint alternatif jadwal mengajar dosen via modul SIAKAD | ✅ Bearer Token (`auth:api` / `siakad.kelas.read`) |

---

## Headers Standar

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ Wajib |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` (atau `multipart/form-data` untuk upload avatar file) | Hanya POST |

---

## 1. GET /api/v1/mobile/staff/jadwal-mengajar

Mengambil daftar jadwal mengajar dosen yang sedang aktif, status perkuliahan (Akan Datang, Sedang Berlangsung, Selesai), ruangan, dan jumlah mahasiswa. Mendukung query filter hari dan tahun akademik.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `nidn` | string | ❌ | — | NIDN dosen (khusus role admin/pengelola, dosen reguler otomatis ter-scope ke akun sendiri) |
| `nip` | string | ❌ | — | NIP dosen |
| `username` | string | ❌ | — | Username akun SSO dosen |
| `hari` | string | ❌ | — | Filter jadwal hari spesifik (contoh: `senin`, `selasa`) |
| `tahun_akademik_id` | integer | ❌ | Aktif | ID Tahun Akademik spesifik (default: periode aktif) |
| `sort_by` | string | ❌ | `jam_mulai` | Kolom pengurutan (`jam_mulai`, `kode_kelas`, `created_at`) |
| `sort_order` | string | ❌ | `asc` | Arah pengurutan (`asc` atau `desc`) |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Jadwal mengajar berhasil dimuat.",
    "data": {
        "dosen": {
            "id": 12,
            "nama_lengkap": "Dr. Budi Santoso, M.Kom",
            "nama_gelar": "Dr. Budi Santoso, M.Kom",
            "nidn": "0620018801",
            "nip": "198801012015011001",
            "is_fallback": false
        },
        "today_day": "senin",
        "filter_day": null,
        "today_classes": [
            {
                "id": 45,
                "course_code": "TI301",
                "course_name": "Pemrograman Web Lanjut",
                "sks": 3,
                "class_name": "TI-3A",
                "room": "Lab Komputer 1 (Lt. 2)",
                "room_code": "LAB-01",
                "start_time": "08:00",
                "end_time": "10:30",
                "student_count": 32,
                "session": 10,
                "status": "Sedang Berlangsung",
                "hari": "senin",
                "is_today": true,
                "is_ongoing": true,
                "reminder_active": true,
                "peran": "Pengampu Utama"
            }
        ],
        "all_classes": [
            {
                "id": 45,
                "course_code": "TI301",
                "course_name": "Pemrograman Web Lanjut",
                "sks": 3,
                "class_name": "TI-3A",
                "room": "Lab Komputer 1 (Lt. 2)",
                "room_code": "LAB-01",
                "start_time": "08:00",
                "end_time": "10:30",
                "student_count": 32,
                "session": 10,
                "status": "Sedang Berlangsung",
                "hari": "senin",
                "is_today": true,
                "is_ongoing": true,
                "reminder_active": true,
                "peran": "Pengampu Utama"
            }
        ],
        "total_sks": 3,
        "total_kelas": 1
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

---

## 2. GET /api/v1/mobile/staff/home

Mengambil ringkasan beranda staf termasuk profil dasar dan widget jadwal mengajar hari ini.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Ringkasan beranda staf berhasil dimuat.",
    "data": {
        "user": {
            "id": 15,
            "name": "Dr. Budi Santoso, M.Kom",
            "username": "budi.santoso",
            "role": "Dosen Pengajar"
        },
        "jadwal": {
            "dosen": {
                "id": 12,
                "nama_lengkap": "Dr. Budi Santoso, M.Kom",
                "nama_gelar": "Dr. Budi Santoso, M.Kom",
                "nidn": "0620018801",
                "nip": "198801012015011001",
                "is_fallback": false
            },
            "today_day": "senin",
            "filter_day": null,
            "today_classes": [...],
            "all_classes": [...],
            "total_sks": 3,
            "total_kelas": 1
        }
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

---

## 3. POST /api/v1/mobile/staff/avatar

Memperbarui foto profil staf ke penyimpanan server backend RAG, baik dalam bentuk string Base64 (`avatar_base64`) atau upload berkas multipart (`avatar`). Sistem hanya mengizinkan pengguna memperbarui avatar akun miliknya sendiri dan mencatat perubahan ke audit log.

### Request Body (JSON atau Multipart)

**Contoh Format Base64:**
```json
{
    "avatar_base64": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII="
}
```

**Contoh Format Multipart:**
- `avatar`: Berkas gambar (JPEG, PNG, WEBP, maks. 5MB)

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Foto profil berhasil diperbarui dan tersimpan di server RAG.",
    "data": {
        "user_id": 15,
        "avatar_url": "http://localhost:8000/storage/avatars/2026/11/4d7159c1-ef90-48e0-bb15-08168bb21389.png",
        "path": "avatars/2026/11/4d7159c1-ef90-48e0-bb15-08168bb21389.png",
        "updated_at": "2026-11-21T09:00:00+07:00"
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Berkas base64 harus berupa gambar valid (JPEG, PNG, WEBP) dengan ukuran maksimal 5MB.",
    "errors": {
        "avatar": [
            "The avatar field must be an image."
        ]
    }
}
```

---

## 4. GET /api/v1/siakad/perkuliahan/jadwal-mengajar

Endpoint integrasi jadwal mengajar dosen yang berada di bawah prefix routing modul SIAKAD.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

Format response identik dengan `GET /api/v1/mobile/staff/jadwal-mengajar`.

### Response Error

**401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```
