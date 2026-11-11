# SystemSettingController

> **Modul**: IAM  
> **Base URL**: `/api/admin/system-settings`  
> **Autentikasi**: Bearer Token (Passport)
> **Dibuat/Diperbarui**: 2026-11-11

Dokumentasi API untuk pengelolaan konfigurasi sistem terpusat (System Settings), mencakup konfigurasi SMTP Mailer, Cloudflare R2 Object Storage, Neo Feeder PDDikti, LMS & Absensi Perkuliahan, pembatasan peran akun (Restricted Roles), serta mapping penerima admin SPMB. Baca/simpan pengaturan dan opsi akun admin SPMB memerlukan kewenangan IAM `iam.settings.update` (termasuk akses superadmin melalui Gate). Akses pengelola role untuk request yang hanya memperbarui `restricted_role_ids` tetap mengikuti policy role yang sudah ada.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/admin/system-settings` | Mengambil dictionary konfigurasi sistem, termasuk mapping admin SPMB | ✅ `iam.settings.update` |
| POST | `/api/admin/system-settings` | Menyimpan atau memperbarui pengaturan sistem secara massal | ✅ `iam.settings.update`; pengecualian request khusus restriksi role sesuai policy |
| GET | `/api/admin/system-settings/spmb-admin-options` | Cari akun aktif untuk mapping admin SPMB | ✅ `iam.settings.update` |
| POST | `/api/admin/system-settings/test-smtp` | Mengirim email uji coba untuk verifikasi konfigurasi SMTP | ✅ Super Admin |
| POST | `/api/admin/system-settings/test-r2` | Menguji konektivitas baca-tulis ke bucket Cloudflare R2 | ✅ Super Admin |
| GET | `/api/auth/branding` | Mengambil data publik visual login (banner, judul, slogan) | ❌ Publik (Tanpa Auth) |
| POST | `/api/admin/system-settings/upload-logo` | Mengunggah logo sistem ke storage publik | ✅ Admin (`iam.settings.update`) |
| DELETE | `/api/admin/system-settings/logo` | Menghapus logo sistem dan berkas publiknya | ✅ Admin (`iam.settings.update`) |
| POST | `/api/admin/system-settings/upload-banner` | Mengunggah foto banner login ke storage publik | ✅ Admin (`iam.settings.update`) |

## [GET] /api/admin/system-settings/spmb-admin-options

Pencarian server untuk pilihan akun pada mapping admin SPMB. Headers wajib: `Authorization: Bearer {access_token}` dan `Accept: application/json`. Tidak ada request body. Hanya akun aktif yang ditampilkan, tanpa filter nama role atau ID akun tetap.

| Query | Tipe | Default | Keterangan |
|---|---|---|---|
| search | string | kosong | Nama, username, atau email; maksimal 200 karakter |
| page | integer | 1 | Minimal 1 |
| per_page | integer | 15 | Minimal 1, maksimum efektif 100 |
| sort_by | string | name | name, username, email, atau id |
| sort_order | string | asc | asc atau desc |

**200 OK**:

```json
{
  "status":"success",
  "message":"Pilihan akun penerima admin SPMB berhasil dimuat.",
  "data":[{"id":12,"name":"Admin Penerimaan","username":"penerimaan","email":"penerimaan@example.test","is_active":true}],
  "meta":{"current_page":1,"per_page":15,"total":1,"last_page":1,"from":1,"to":1},
  "filters":{"search":null,"sort_by":"name","sort_order":"asc"}
}
```

**401 Unauthorized**: `{"status":"error","message":"Token tidak valid atau sesi telah berakhir."}`

**403 Forbidden**: `{"status":"error","message":"Anda tidak memiliki izin untuk melakukan aksi ini."}`

**422 Unprocessable Entity**: query tidak valid, dengan envelope `status`, `message`, dan `errors`. Endpoint opsi tidak memakai 400/404 pada operasi normal. List kosong mengembalikan `data: []` dan `meta.total: 0`.

---

## 1. [GET] /api/admin/system-settings

Respons merupakan dictionary konfigurasi, bukan list terpaginasikan. Key `spmb_admin_notification_user_ids` selalu tersedia dengan default `value: "[]"`. Field `users` menyediakan label pilihan tersimpan, termasuk akun yang kemudian nonaktif. Tidak mengembalikan password atau token akun penerima.

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

Untuk mapping admin SPMB, kirim satu item `settings` dengan `key: "spmb_admin_notification_user_ids"` dan `value` JSON string array ID akun. Contoh body:

```json
{"settings":[{"key":"spmb_admin_notification_user_ids","value":"[12,34]"}]}
```

- Setiap ID harus integer positif dan menunjuk akun `core_users` yang aktif. Tidak ada pemilihan penerima berdasarkan nama role, email tertentu, atau inferensi permission SPMB.
- Maksimal 100 entri; ID duplikat disimpan satu kali. `value: "[]"` mengosongkan mapping. Tidak ada penerima admin otomatis bila mapping kosong.
- JSON bukan array, ID negatif/string/boolean, akun hilang/nonaktif, serta batas terlampaui menghasilkan 422 pada `errors["settings.{index}.value"]`, sebelum item pengaturan lainnya ditulis.
- Perubahan mapping dicatat di `core_audit_logs`. Mapping tidak memberikan permission modul; akses akun tetap dikelola melalui IAM.
- Pengiriman berikutnya membaca mapping terbaru. Akun nonaktif/hilang dilewati. Salinan admin menggunakan inbox dan email setelah commit; detail pemicu ada pada [SystemNotificationController](../System/SystemNotificationController.md).

Contoh field mapping pada respons GET setelah disimpan:

```json
{
  "id": 51,
  "key": "spmb_admin_notification_user_ids",
  "value": "[12]",
  "description": "ID akun penerima notifikasi sistem dan email admin SPMB",
  "users": [{"id":12,"name":"Admin Penerimaan","username":"penerimaan","email":"penerimaan@example.test","is_active":true}]
}
```

Contoh error mapping:

```json
{"status":"error","message":"Semua akun penerima admin SPMB harus tersedia dan aktif.","errors":{"settings.0.value":["Semua akun penerima admin SPMB harus tersedia dan aktif."]}}
```

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

> Mengambil konfigurasi publik logo sistem dan branding visual login. Endpoint ini dapat diakses tanpa autentikasi agar halaman autentikasi dan layout utama dapat menampilkan identitas sistem.

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
        "system_logo_url": "https://cdn.example.test/branding/logos/2026/11/logo-uuid.png",
        "login_banner_url": "http://localhost:8000/storage/branding/banners/2026/10/banner-uuid.jpg",
        "login_banner_title": "SSO Campus",
        "login_banner_subtitle": "Enterprise Identity Platform"
    }
}
```

---

## 6. [POST] /api/admin/system-settings/upload-logo

> Mengunggah logo sistem publik melalui `FileStorageService`, menyimpan path relatif pada `system_logo_path`, memperbarui URL publik `system_logo_url`, dan menghapus berkas logo lama setelah penggantian berhasil.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Content-Type` | `multipart/form-data` | ✅ |
| `Accept` | `application/json` | ✅ |

### Hak Akses
Wajib memiliki salah satu role `superadmin`, role `admin`, atau permission `iam.settings.update`.

### Request Body

| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `logo` | File gambar | ✅ | PNG, JPG/JPEG, atau WebP; maksimal 2048 KB. SVG tidak diterima. |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Logo sistem berhasil diunggah.",
    "data": {
        "path": "branding/logos/2026/11/logo-uuid.png",
        "url": "https://cdn.example.test/branding/logos/2026/11/logo-uuid.png"
    }
}
```

### Response Error

**401 Unauthorized**
```json
{"status":"error","message":"Unauthenticated."}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak akses untuk mengunggah logo sistem."
}
```

**422 Unprocessable Entity**
```json
{
    "message": "Berkas logo wajib diunggah.",
    "errors": {"logo": ["Berkas logo wajib diunggah."]}
}
```

**500 Internal Server Error**
```json
{
    "status": "error",
    "message": "Gagal mengunggah logo sistem: [detail error]"
}
```

## 7. [DELETE] /api/admin/system-settings/logo

> Mengosongkan `system_logo_path` dan `system_logo_url`, serta menghapus berkas logo publik sebelumnya. Memerlukan autentikasi dan hak akses yang sama dengan upload logo.

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
    "message": "Logo sistem berhasil dihapus.",
    "data": null
}
```

### Response Error

**401 Unauthorized**
```json
{"status":"error","message":"Unauthenticated."}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak akses untuk menghapus logo sistem."
}
```

## 8. [POST] /api/admin/system-settings/upload-banner

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

