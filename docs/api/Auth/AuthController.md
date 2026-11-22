# AuthController (Mobile Staff API)

> **Modul**: Autentikasi Mobile API (`auth:api`)  
> **Base URL**: `/api/v1/auth`  
> **Autentikasi**: Bearer Token (Passport / Sanctum) — kecuali endpoint publik  
> **Dibuat**: 2026-09-14  
> **Diperbarui**: 2026-11-22  

Dokumentasi endpoint API autentikasi dan biometrik untuk aplikasi mobile staf, dosen, dan portal SIMPEG terintegrasi. Menangani login multi-identifier, profil akun tersinkronisasi SIMPEG, persetujuan UU PDP biometrik, pendaftaran foto wajah multi-pose (server-side verification), dan logout.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| POST | `/api/v1/auth/login` | Login pegawai via email/username/NIP/NIDN/NUPTK/NIK | ❌ Publik |
| GET | `/api/v1/auth/profile` | Profil akun dan data kepegawaian SIMPEG | ✅ Bearer |
| POST | `/api/v1/auth/consent` | Rekam persetujuan UU PDP biometrik wajah | ✅ Bearer |
| POST | `/api/v1/auth/enroll-face` | Daftarkan wajah biometrik minimal 3 pose foto | ✅ Bearer |
| POST | `/api/v1/auth/reset-face` | Reset / hapus data biometrik wajah pegawai | ✅ Bearer |
| POST | `/api/v1/auth/logout` | Revoke token akses aktif perangkat | ✅ Bearer |

---

## 1. POST /api/v1/auth/login

> Autentikasi staf/dosen mobile menggunakan fleksibilitas identifier (email, username, NIP, NIDN, NUPTK, NIK) dan kata sandi. Mengembalikan Bearer Token dan data profil pegawai SIMPEG.

### Headers

| Key | Value | Required |
|---|---|---|
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

| Field | Type | Required | Deskripsi |
|---|---|---|---|
| `login` / `username` / `email` / `identifier` | string | ✅ | Identifier login (email/username/NIP/NIDN/NUPTK/NIK) |
| `password` | string | ✅ | Kata sandi akun pengguna |
| `device_name` | string | ❌ | Nama perangkat klien (default: `mobile-absen`) |

```json
{
  "login": "199001012020121001",
  "password": "Password123!",
  "device_name": "Samsung Galaxy S24"
}
```

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Login berhasil",
  "token": "1|eyJ0eXAi...",
  "access_token": "1|eyJ0eXAi...",
  "token_type": "Bearer",
  "data": {
    "token": "1|eyJ0eXAi...",
    "access_token": "1|eyJ0eXAi...",
    "token_type": "Bearer",
    "user": {
      "id": 15,
      "name": "Budi Santoso, M.Kom.",
      "username": "budisantoso",
      "email": "budi@univ.ac.id",
      "referral_code": "REF-BUDI15",
      "roles": ["dosen", "dpa"]
    },
    "employee": {
      "id": 12,
      "employee_code": "199001012020121001",
      "nip": "199001012020121001",
      "nidn": "0601019001",
      "position": "Dosen Tetap",
      "department": "Informatika",
      "is_face_enrolled": true,
      "face_enrolled_at": "2026-09-15T08:00:00.000000Z",
      "consent_pdp_at": "2026-09-14T08:00:00.000000Z",
      "office": {
        "id": 1,
        "name": "Kampus Utama",
        "latitude": -7.5675,
        "longitude": 110.8036,
        "radius_meters": 150
      },
      "shift": {
        "id": 1,
        "name": "Shift Reguler Dosen",
        "start_time": "08:00:00",
        "end_time": "16:00:00"
      }
    }
  }
}
```

### Response Error

**422 Unprocessable Entity (Akun Belum Tertaut SIMPEG)**
```json
{
  "status": "error",
  "message": "Data yang diberikan tidak valid.",
  "errors": {
    "login": [
      "Akun belum tertaut dengan data pegawai SIMPEG. Hubungi administrator."
    ]
  }
}
```

**422 Unprocessable Entity (Kredensial Salah / Akun Nonaktif)**
```json
{
  "status": "error",
  "message": "Data yang diberikan tidak valid.",
  "errors": {
    "login": [
      "Kredensial login tidak cocok dengan data kami."
    ]
  }
}
```

---

## 2. GET /api/v1/auth/profile

> Mendapatkan detail informasi akun pengguna dan data kepegawaian SIMPEG yang sedang login. Vektor biometrik `face_embedding` mentah tidak diekspos ke klien demi keamanan data privasi.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "data": {
    "user": {
      "id": 15,
      "name": "Budi Santoso, M.Kom.",
      "username": "budisantoso",
      "email": "budi@univ.ac.id",
      "referral_code": "REF-BUDI15",
      "roles": ["dosen", "dpa"]
    },
    "employee": {
      "id": 12,
      "employee_code": "199001012020121001",
      "nip": "199001012020121001",
      "nidn": "0601019001",
      "position": "Dosen Tetap",
      "department": "Informatika",
      "is_face_enrolled": true,
      "face_enrolled_at": "2026-09-15T08:00:00.000000Z",
      "consent_pdp_at": "2026-09-14T08:00:00.000000Z",
      "office": {
        "id": 1,
        "name": "Kampus Utama",
        "latitude": -7.5675,
        "longitude": 110.8036,
        "radius_meters": 150
      },
      "shift": {
        "id": 1,
        "name": "Shift Reguler Dosen",
        "start_time": "08:00:00",
        "end_time": "16:00:00"
      }
    },
    "has_face_enrolled": true
  }
}
```

### Response Error

**403 Forbidden (Akun Belum Tertaut SIMPEG)**
```json
{
  "status": "error",
  "message": "Akun belum tertaut dengan data pegawai SIMPEG. Hubungi administrator."
}
```

---

## 3. POST /api/v1/auth/consent

> Mencatat persetujuan eksplisit pegawai terhadap pengumpulan & pemrosesan data biometrik wajah sesuai UU Perlindungan Data Pribadi (UU PDP).

### Request Body

```json
{
  "consent": true
}
```

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Persetujuan pengumpulan data biometrik (UU PDP) berhasil dicatat.",
  "data": {
    "consent_pdp_at": "2026-11-22T10:00:00.000000Z"
  }
}
```

---

## 4. POST /api/v1/auth/enroll-face

> Mendaftarkan biometrik wajah pegawai ke server microservice AI Python. Klien wajib mengunggah minimal 3 sampel foto pose wajah (tegak hadap kamera, nunduk sedikit, dan dongak sedikit). Raw embedding dari klien tidak diterima.

### Request Body

| Field | Type | Required | Deskripsi |
|---|---|---|---|
| `photos` / `images` | array of strings (Base64 / URL) | ✅ | Minimal 3 sampel foto wajah dari berbagai sudut pose |

```json
{
  "photos": [
    "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQ...",
    "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQ...",
    "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQ..."
  ]
}
```

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Pendaftaran biometrik wajah berhasil disimpan.",
  "data": {
    "face_enrolled_at": "2026-11-22T10:05:00.000000Z",
    "dimension": 512,
    "samples_received": 3,
    "pose_diversity": {
      "min_pairwise_similarity": 0.88,
      "max_pairwise_similarity": 0.94
    }
  }
}
```

### Response Error

**422 Unprocessable Entity (Kurang dari 3 Sampel Foto / Pose Kurang Beragam)**
```json
{
  "status": "error",
  "message": "Minimal 3 foto pose wajah diperlukan (tegak, nunduk, mendongak)."
}
```

**422 Unprocessable Entity (Belum Menyetujui Form Consent UU PDP)**
```json
{
  "status": "error",
  "message": "Wajib menyetujui formulir consent UU PDP sebelum mendaftarkan biometrik wajah."
}
```

---

## 5. POST /api/v1/auth/reset-face

> Menghapus data vektor biometrik wajah pegawai (hanya dapat dilakukan oleh pegawai bersangkutan atau admin berwenang).

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Data biometrik wajah berhasil direset.",
  "data": null
}
```

---

## 6. POST /api/v1/auth/logout

> Menghapus token autentikasi aktif pada perangkat mobile.

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Logout berhasil",
  "data": null
}
```
