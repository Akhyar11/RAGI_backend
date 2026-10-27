# SystemSettingController

> **Modul**: IAM  
> **Base URL**: `/api/admin/system-settings`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat/Diperbarui**: 2026-09-25  

Dokumentasi API untuk pengelolaan konfigurasi sistem terpusat (System Settings), mencakup konfigurasi SMTP Mailer, Cloudflare R2 Object Storage, Neo Feeder PDDikti, LMS & Absensi Perkuliahan, serta pembatasan peran akun (Restricted Roles). Seluruh endpoint ini dilindungi hak akses administrator sistem (Super Admin).

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/admin/system-settings` | Mengambil seluruh daftar pengaturan sistem aktif | ✅ Super Admin |
| POST | `/api/admin/system-settings` | Menyimpan atau memperbarui pengaturan sistem secara massal | ✅ Super Admin |
| POST | `/api/admin/system-settings/test-smtp` | Mengirim email uji coba untuk verifikasi konfigurasi SMTP | ✅ Super Admin |
| POST | `/api/admin/system-settings/test-r2` | Menguji konektivitas baca-tulis ke bucket Cloudflare R2 | ✅ Super Admin |
| GET | `/api/auth/branding` | Mengambil data publik visual login (banner, judul, slogan) | ❌ Publik (Tanpa Auth) |
| POST | `/api/admin/system-settings/upload-banner` | Mengunggah foto banner login ke storage publik | ✅ Admin (`iam.settings.update`) |

---

## 1. [GET] /api/admin/system-settings

> Mengambil daftar seluruh pengaturan sistem yang tersimpan di database beserta nilai bawaan sistem (SMTP, R2, Neo Feeder, Google Workspace, dan LMS).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": {
        "mail_mailer": {
            "id": 1,
            "key": "mail_mailer",
            "value": "smtp",
            "description": "Konfigurasi SMTP default dari sistem"
        },
        "mail_host": {
            "id": 2,
            "key": "mail_host",
            "value": "smtp.gmail.com",
            "description": "Host SMTP server"
        },
        "mail_port": {
            "id": 3,
            "key": "mail_port",
            "value": "465",
            "description": "Port SMTP server"
        },
        "filesystem_disk": {
            "id": 4,
            "key": "filesystem_disk",
            "value": "local",
            "description": "Default storage disk"
        },
        "r2_bucket": {
            "id": 5,
            "key": "r2_bucket",
            "value": "kampus-public",
            "description": "Konfigurasi Cloudflare R2 / Object Storage"
        },
        "lms_storage_disk": {
            "id": 6,
            "key": "lms_storage_disk",
            "value": "r2",
            "description": "Konfigurasi LMS & Absensi Perkuliahan"
        },
        "lms_max_file_materi_mb": {
            "id": 7,
            "key": "lms_max_file_materi_mb",
            "value": "50",
            "description": "Konfigurasi LMS & Absensi Perkuliahan"
        },
        "lms_max_video_mb": {
            "id": 8,
            "key": "lms_max_video_mb",
            "value": "500",
            "description": "Konfigurasi LMS & Absensi Perkuliahan"
        },
        "lms_max_file_tugas_mb": {
            "id": 9,
            "key": "lms_max_file_tugas_mb",
            "value": "50",
            "description": "Konfigurasi LMS & Absensi Perkuliahan"
        },
        "lms_allow_token_absensi": {
            "id": 10,
            "key": "lms_allow_token_absensi",
            "value": "true",
            "description": "Konfigurasi LMS & Absensi Perkuliahan"
        },
        "lms_token_ttl_minutes": {
            "id": 11,
            "key": "lms_token_ttl_minutes",
            "value": "15",
            "description": "Konfigurasi LMS & Absensi Perkuliahan"
        }
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki wewenang untuk mengakses pengaturan sistem."
}
```

---

## 2. [POST] /api/admin/system-settings

> Memperbarui konfigurasi sistem dalam bentuk array key-value.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Content-Type` | `application/json` | ✅ |
| `Accept` | `application/json` | ✅ |

### Request Body

```json
{
    "settings": [
        {
            "key": "lms_storage_disk",
            "value": "r2"
        },
        {
            "key": "lms_max_file_materi_mb",
            "value": "50"
        },
        {
            "key": "lms_max_video_mb",
            "value": "500"
        },
        {
            "key": "lms_max_file_tugas_mb",
            "value": "50"
        },
        {
            "key": "lms_allow_token_absensi",
            "value": "true"
        },
        {
            "key": "lms_token_ttl_minutes",
            "value": "15"
        }
    ]
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "System settings updated successfully."
}
```

### Response Error

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "The settings field is required.",
    "errors": {
        "settings": [
            "The settings field is required."
        ]
    }
}
```

---

## 3. [POST] /api/admin/system-settings/test-smtp

> Mengirim email uji coba untuk memverifikasi host, port, kredensial, dan scheme SMTP sebelum atau sesudah disimpan.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Content-Type` | `application/json` | ✅ |
| `Accept` | `application/json` | ✅ |

### Request Body

```json
{
    "email": "admin@kampus.ac.id",
    "mail_host": "smtp.gmail.com",
    "mail_port": 465,
    "mail_scheme": "smtps",
    "mail_username": "notifikasi@kampus.ac.id",
    "mail_password": "app-specific-password"
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Email uji coba berhasil dikirim ke admin@kampus.ac.id"
}
```

---

## 4. [POST] /api/admin/system-settings/test-r2

> Menguji koneksi write & read pada Cloudflare R2 bucket.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Content-Type` | `application/json` | ✅ |
| `Accept` | `application/json` | ✅ |

### Request Body

```json
{
    "r2_endpoint": "https://accountid.r2.cloudflarestorage.com",
    "r2_access_key_id": "example_access_key",
    "r2_secret_access_key": "example_secret_key",
    "r2_bucket": "kampus-public",
    "r2_default_region": "auto",
    "r2_use_path_style_endpoint": true
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Koneksi ke Cloudflare R2 berhasil! Bucket 'kampus-public' dapat diakses dan ditulis dengan baik."
}
```

---

## 5. [GET] /api/auth/branding

> Mengambil data konfigurasi publik branding login (URL foto banner, judul lencana, dan subjudul/slogan). Endpoint ini dapat diakses oleh siapa saja tanpa autentikasi (publik) agar halaman login dapat merender banner visual yang dikonfigurasi.

### Headers

| Key | Value | Required |
|---|---|---|
| `Accept` | `application/json` | ✅ |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": {
        "login_banner_url": "http://localhost:8000/storage/branding/banners/2026/10/banner-uuid.jpg",
        "login_banner_title": "SSO Campus",
        "login_banner_subtitle": "Enterprise Identity Platform"
    }
}
```

---

## 6. [POST] /api/admin/system-settings/upload-banner

> Mengunggah berkas foto banner visual halaman login ke storage publik (local/Cloudflare R2 via `FileStorageService`). Aksi ini otomatis mencatat aktivitas ke audit log IAM.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Content-Type` | `multipart/form-data` | ✅ |
| `Accept` | `application/json` | ✅ |

### Hak Akses / Permission
Wajib memiliki salah satu:
- Role `superadmin`
- Role `admin`
- Permission `iam.settings.update`

### Request Body (multipart/form-data)

| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `banner` | File (image) | ✅ | Berkas gambar (JPG, PNG, JPEG, WEBP, GIF, SVG), maks. 5120 KB (5 MB). |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Foto banner login berhasil diunggah.",
    "data": {
        "path": "branding/banners/2026/10/7c4f1c9d-8b01-443b-a912-192a014a09e1.jpg",
        "url": "http://localhost:8000/storage/branding/banners/2026/10/7c4f1c9d-8b01-443b-a912-192a014a09e1.jpg"
    }
}
```

### Response Error

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak akses untuk mengunggah banner sistem."
}
```

**422 Unprocessable Entity**
```json
{
    "message": "Ukuran gambar banner maksimal 5MB.",
    "errors": {
        "banner": [
            "Ukuran gambar banner maksimal 5MB."
        ]
    }
}
```

