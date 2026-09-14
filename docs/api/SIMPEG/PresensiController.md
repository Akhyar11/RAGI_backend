# PresensiController & Mobile Attendance API

> **Modul**: SIMPEG (Sistem Informasi Kepegawaian & Presensi Biometrik)  
> **Base URL**: `/api/v1` dan `/api/simpeg`  
> **Autentikasi**: Bearer Token (Passport/Sanctum) / X-API-KEY (Integrasi)  
> **Dibuat**: 2026-09-14  
> **Diperbarui**: 2026-09-14 (endpoint keterangan ketidakhadiran + rekap silang cuti)

Dokumentasi ini mencakup endpoint presensi karyawan berbasis biometrik wajah (Python port 8001), geofencing Haversine, dan jadwal shift dinamis yang digunakan oleh aplikasi **Mobile Android (Flutter)** dan dashboard **SIMPEG Web**.

---

## Daftar Endpoint Utama

### 1. Mobile Android & Presensi Karyawan (`/api/v1/...`)

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| POST | `/api/v1/auth/login` | Login karyawan mobile via NIP atau Email | ❌ Publik |
| GET | `/api/v1/auth/profile` | Profil karyawan & status biometrik terdaftar | ✅ Bearer |
| POST | `/api/v1/auth/consent` | Rekam persetujuan pemrosesan biometrik (UU PDP) | ✅ Bearer |
| POST | `/api/v1/auth/enroll-face` | Daftarkan foto wajah multi-shot / embedding | ✅ Bearer |
| POST | `/api/v1/auth/reset-face` | Reset data biometrik wajah karyawan | ✅ Bearer |
| POST | `/api/v1/auth/logout` | Revoke token mobile | ✅ Bearer |
| GET | `/api/v1/attendance/today` | Status presensi, lokasi kantor & jadwal shift hari ini | ✅ Bearer |
| POST | `/api/v1/attendance/clock-in` | Presensi masuk (validasi GPS, Anti Fake GPS, Face Score) | ✅ Bearer |
| POST | `/api/v1/attendance/clock-out` | Presensi pulang (validasi GPS & Face Score) | ✅ Bearer |
| GET | `/api/v1/attendance/history` | Riwayat presensi bulanan milik karyawan login | ✅ Bearer |
| GET | `/api/v1/attendance/recap` | Rekap kehadiran bulanan per individu | ✅ Bearer |

### 2. Validasi Biometrik Wajah ke Python Port 8001 (`/api/v1/face/...`)

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/face/health` | Cek status & koneksi ke Python port 8001 | ❌ Publik |
| POST | `/api/v1/face/verify` | Verifikasi kecocokan foto live vs embedding referensi | ❌ Publik |
| POST | `/api/v1/face/extract` | Ekstrak vektor embedding (512 float) dari foto | ❌ Publik |
| POST | `/api/v1/face/enroll` | Multi-shot enrollment dari beberapa foto | ❌ Publik |

### 3. Integrasi Sistem Eksternal (`/api/v1/integration/...`)

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/integration/attendances` | Ambil daftar log absensi (filter tanggal, NIP, dept) | ✅ X-API-KEY |
| GET | `/api/v1/integration/attendances/{id}` | Detail log presensi lengkap dengan GPS audit | ✅ X-API-KEY |
| GET | `/api/v1/integration/attendances/recap` | Rekapitulasi kehadiran organisasi per bulan/tahun | ✅ X-API-KEY |

---

## Detail Request & Response

### POST `/api/v1/auth/login`
Mendukung input email atau NIP pegawai.

**Request Body:**
```json
{
  "login": "198501152010121001",
  "password": "password",
  "device_name": "flutter-android-device"
}
```

**Response Sukses (200 OK):**
```json
{
  "success": true,
  "message": "Login berhasil",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGci...",
    "user": {
      "id": 2,
      "name": "Dr. Wasis Utama, M.T.",
      "email": "admin@kampus.ac.id"
    },
    "employee": {
      "id": 1,
      "employee_code": "198501152010121001",
      "position": "dosen",
      "department": "Rektorat Universitas",
      "is_face_enrolled": false,
      "consent_pdp_at": "2026-09-14T06:21:49.000000Z",
      "office": {
        "id": 1,
        "name": "Politeknik Indonusa Surakarta",
        "address": "Jl. KH Samanhudi No.84, Sondakan, Laweyan, Surakarta, Jawa Tengah 57147",
        "latitude": -7.5675,
        "longitude": 110.8036,
        "radius_meters": 150,
        "is_active": true
      }
    }
  }
}
```

---

### POST `/api/v1/attendance/clock-in`

**Request Body:**
```json
{
  "latitude": -7.5675,
  "longitude": 110.8036,
  "accuracy": 10.0,
  "face_score": 0.88,
  "face_image": "data:image/jpeg;base64,...",
  "is_mock_location": false
}
```

**Response Sukses (200 OK):**
```json
{
  "success": true,
  "message": "Presensi masuk berhasil (Tepat Waktu).",
  "data": {
    "id": 109,
    "pegawai_id": 1,
    "tanggal": "2026-09-14",
    "clock_in": "2026-09-14T08:05:00.000000Z",
    "status": "hadir",
    "late_minutes": 0,
    "clock_in_latitude": -7.5675,
    "clock_in_longitude": 110.8036,
    "clock_in_distance_meters": 0,
    "clock_in_face_score": 0.88
  }
}
```

---

### GET `/api/v1/face/health`
Mengecek konektivitas dan kesiapan microservice Python yang berjalan di port 8001.

**Response (200 OK):**
```json
{
  "status": "success",
  "connected": true,
  "target_url": "http://127.0.0.1:8001",
  "python_service": {
    "status": "healthy",
    "service": "Absen Face Recognition Microservice",
    "version": "1.0.7",
    "engine": "ArcFace",
    "deepface_available": true,
    "default_threshold": 0.68
  }
}
```

---

### 4. Presensi Web SIMPEG (`/api/simpeg/presensi/...`)

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| POST | `/api/simpeg/presensi/{id}/approve` | Persetujuan manual HR (clock-in hari libur / upaya ditolak) | ✅ Bearer (`simpeg.presensi.manage`) |
| POST | `/api/simpeg/presensi/keterangan` | Tetapkan keterangan ketidakhadiran (izin/sakit/dinas/alfa) untuk pegawai terjadwal masuk tanpa log | ✅ Bearer (`simpeg.presensi.manage`) |
| GET | `/api/simpeg/presensi/recap?pegawai_id=&month=&year=` | Rekap bulanan per pegawai (silang cuti disetujui) | ✅ Bearer |

> Clock-in valid langsung tercatat `hadir`/`terlambat` tanpa verifikasi. Verifikasi manual hanya untuk clock-in di hari libur jadwal shift (`menunggu_approval`) dan upaya yang ditolak validasi (`ditolak`).

### POST `/api/simpeg/presensi/keterangan`

Idempotent per pasangan (`pegawai_id`, `tanggal`). Data hasil scan (`clock_in`/`clock_out`) **tidak dapat** ditimpa lewat endpoint ini (`422`).

**Request Body:**
```json
{
  "pegawai_id": "integer, required, exists:simpeg_pegawai,id",
  "tanggal": "string, required, format YYYY-MM-DD",
  "status_kehadiran": "enum: izin|sakit|dinas|alfa, required",
  "catatan": "string, nullable, max 1000"
}
```

**Response Sukses (201 Created):**
```json
{
  "status": "success",
  "message": "Keterangan ketidakhadiran (izin) berhasil disimpan.",
  "data": { "id": 55, "pegawai_id": 3, "tanggal": "2026-09-11", "status_kehadiran": "izin" }
}
```

**422 Unprocessable Entity** (sudah ada hasil scan):
```json
{
  "status": "error",
  "message": "Tanggal tersebut sudah memiliki data hasil scan presensi dan tidak dapat ditimpa dengan keterangan manual."
}
```

### GET `/api/simpeg/presensi/recap` — prioritas keterangan per tanggal

1. Ada log presensi → pakai status log (`hadir`, `terlambat`, `ditolak`, `menunggu_approval`, atau `izin`/`sakit`/`dinas`/`alfa` dari input HR).
2. Tanpa log + tanggal merah → `libur_nasional`.
3. Tanpa log + hari libur shift → `libur_reguler`.
4. Tanpa log + ada cuti berstatus `approved` menutupi tanggal → badge `cuti`/`sakit`, keterangan mis. `Cuti Tahunan (disetujui)`.
5. Tanpa log + hari kerja yang sudah lewat → `alpa` (`Tidak Hadir (Alpa)`).

**Ringkasannya (`summary`):** `total_hadir`, `total_terlambat`, `total_alpa`, `total_libur`, ditambah `total_izin`, `total_sakit`, `total_dinas`, `total_cuti`.
