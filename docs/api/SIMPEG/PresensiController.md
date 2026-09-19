# PresensiController & Mobile Attendance API

> **Modul**: SIMPEG (Sistem Informasi Kepegawaian & Presensi Biometrik)  
> **Base URL**: `/api/v1` dan `/api/simpeg`  
> **Autentikasi**: Bearer Token (Passport/Sanctum) / X-API-KEY (Integrasi)  
> **Dibuat**: 2026-09-14  
> **Diperbarui**: 2026-09-18 (jendela `max_late_clock_in_minutes` + shift lintas hari 22:00-06:00)

Dokumentasi ini mencakup endpoint presensi karyawan berbasis biometrik wajah (Python port 8001), geofencing Haversine, dan jadwal shift dinamis yang digunakan oleh aplikasi **Mobile Android (Flutter)** dan dashboard **SIMPEG Web**.

---

## Daftar Endpoint Utama

### 1. Mobile Android & Presensi Karyawan (`/api/v1/...`)

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| POST | `/api/v1/auth/login` | Login karyawan mobile (Email / Username / NIP / NIDN) | ❌ Publik |
| GET | `/api/v1/auth/profile` | Profil karyawan & status biometrik terdaftar | ✅ Bearer |
| GET | `/api/v1/auth/me` | Alias profil karyawan & informasi sesi | ✅ Bearer |
| POST | `/api/v1/auth/consent` | Rekam persetujuan pemrosesan biometrik (UU PDP) | ✅ Bearer |
| POST | `/api/v1/auth/enroll-face` | Daftarkan foto wajah multi-shot / embedding | ✅ Bearer |
| POST | `/api/v1/auth/reset-face` | Reset data biometrik wajah karyawan | ✅ Bearer |
| POST | `/api/v1/auth/logout` | Revoke token mobile | ✅ Bearer |
| GET | `/api/v1/attendance/today` | Status presensi, lokasi kantor & jadwal shift hari ini | ✅ Bearer |
| POST | `/api/v1/attendance/clock-in` | Presensi masuk (validasi GPS, Anti Fake GPS, Face Score) | ✅ Bearer |
| POST | `/api/v1/attendance/clock-out` | Presensi pulang (validasi GPS & Face Score) | ✅ Bearer |
| POST | `/api/v1/attendance/keterangan` | Pengajuan mandiri izin, sakit, atau dinas luar | ✅ Bearer |
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

### 4. Otomasi Presensi, Toleransi Shift, Mesin Fingerprint & Cut-off (`/api/simpeg/presensi/...`)

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| POST | `/api/simpeg/presensi/fingerprint/sync` | Sinkronisasi batch punch log mesin absensi biometrik | ✅ Bearer |
| POST | `/api/simpeg/presensi/daily-cutoff` | Eksekusi cut-off harian untuk menandai pegawai tidak hadir sebagai Alfa | ✅ Bearer |
| POST | `/api/simpeg/presensi/shift-assign-bulk` | Penugasan kelompok shift secara massal ke pegawai / unit kerja | ✅ Bearer |
| GET | `/api/simpeg/presensi/pegawai/{id}/office-locations` | Lokasi absen sah pegawai (utama + tambahan) | ✅ Bearer |
| PUT | `/api/simpeg/presensi/pegawai/{id}/office-locations` | Atur lokasi absen tambahan pegawai | ✅ Bearer |
| POST | `/api/simpeg/presensi/office-assign-bulk` | Tugaskan lokasi tambahan massal (misal semua dosen) | ✅ Bearer |

---

## Daftar Kredensial Login Siap Pakai

Semua akun berikut menggunakan password default: `password`

| Peran | Username | Email | NIP | Nama Lengkap |
|---|---|---|---|---|
| Admin & Dosen | `admin` | `admin@kampus.ac.id` | `198501152010121001` | Dr. Wasis Utama, M.T. |
| Dosen Demo | `dosen` | `dosen@kampus.ac.id` | `199008152015122001` | Anisa Rahmawati, M.Kom. |
| Tendik Demo | `tendik` | `tendik@kampus.ac.id` | `199205102016031002` | Rahmat Hidayat, S.Kom. |
| Pimpinan / Dosen | `wasis` | `wasis@kampus.ac.id` | `198501152010121099` | Wasis Utama, Ph.D. |
| Admin SIMPEG | `admin_simpeg` | `admin.simpeg@kampus.ac.id` | `198811202012011003` | Staff Admin SIMPEG |
| Dosen Informatika | `if_dosen1` | `if.dosen1@kampus.ac.id` | `1991061520101101` | Prof. Dr. Ir. H. Ahmad Dahlan, M.Kom |
| Tendik Staff | `tendik_101` | `tendik_101@kampus.ac.id` | `199002102015032001` | Siti Rahmawati, A.Md. |

---

## Detail Request & Response

### POST `/api/v1/auth/login`
Mendukung body dengan key `username`, `email`, `login`, atau `identifier`.

**Request Body (Format 1 - via Username/Email/NIP):**
```json
{
  "username": "dosen",
  "password": "password",
  "device_name": "flutter-android-device"
}
```

*Atau menggunakan key `login`:*
```json
{
  "login": "199008152015122001",
  "password": "password"
}
```

**Response Sukses (200 OK):**
```json
{
  "status": "success",
  "success": true,
  "message": "Login berhasil",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGci...",
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGci...",
  "token_type": "Bearer",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGci...",
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGci...",
    "token_type": "Bearer",
    "user": {
      "id": 2,
      "name": "Anisa Rahmawati, M.Kom.",
      "username": "dosen",
      "email": "dosen@kampus.ac.id",
      "roles": ["dosen"]
    },
    "employee": {
      "id": 1,
      "employee_code": "199008152015122001",
      "nip": "199008152015122001",
      "nidn": null,
      "position": "dosen",
      "department": "Rektorat Universitas",
      "is_face_enrolled": false,
      "face_enrolled_at": null,
      "consent_pdp_at": null,
      "office": {
        "id": 1,
        "name": "Politeknik Indonusa Surakarta",
        "address": "Jl. KH Samanhudi No.84, Sondakan, Laweyan, Surakarta, Jawa Tengah 57147",
        "latitude": -7.5675,
        "longitude": 110.8036,
        "radius_meters": 150,
        "is_active": true
      },
      "shift": {
        "id": 1,
        "name": "Shift Reguler 5 Hari"
      }
    }
  }
}
```

---

### GET `/api/v1/attendance/today` & `GET /api/simpeg/presensi/today`

Mengambil status presensi hari ini, authoritative server time (anti clock-tampering di Android/iOS), jadwal kerja/shift aktif, dan helper status flags untuk antarmuka tombol presensi mobile.

**Response Sukses (200 OK):**
```json
{
  "status": "success",
  "success": true,
  "data": {
    "server_time": "2026-09-17T12:00:00.000000+07:00",
    "server_timestamp": 1789621200,
    "server_date": "2026-09-17",
    "server_time_formatted": "12:00:00",
    "can_clock_in": true,
    "can_clock_out": false,
    "is_clocked_in": false,
    "is_clocked_out": false,
    "clock_in_time": null,
    "clock_out_time": null,
    "status": "belum_absen",
    "user": {
      "id": 2,
      "name": "Anisa Rahmawati, M.Kom.",
      "username": "dosen",
      "email": "dosen@kampus.ac.id",
      "avatar": "https://ui-avatars.com/api/?name=Anisa+Rahmawati..."
    },
    "employee": {
      "id": 1,
      "employee_code": "199008152015122001",
      "nip": "199008152015122001",
      "nama": "Anisa Rahmawati, M.Kom.",
      "avatar": "https://ui-avatars.com/api/?name=Anisa+Rahmawati...",
      "foto_url": "https://ui-avatars.com/api/?name=Anisa+Rahmawati...",
      "position": "dosen",
      "department": "Rektorat Universitas",
      "is_face_enrolled": true,
      "office": {
        "id": 1,
        "name": "Politeknik Indonusa Surakarta",
        "latitude": -7.5675,
        "longitude": 110.8036,
        "radius_meters": 150
      }
    },
    "schedule": {
      "start_time": "08:00:00",
      "end_time": "17:00:00",
      "late_tolerance_minutes": 15,
      "early_leave_tolerance_minutes": 15,
      "max_early_clock_in_minutes": 60,
      "max_late_clock_in_minutes": 240,
      "is_day_off": false,
      "is_overnight": false,
      "duty_date": "2026-09-17"
    },
    "office": {
      "id": 1,
      "name": "Politeknik Indonusa Surakarta",
      "latitude": -7.5675,
      "longitude": 110.8036,
      "radius_meters": 150
    },
    "allowed_offices": [
      { "id": 1, "name": "Politeknik Indonusa Surakarta", "latitude": -7.5675, "longitude": 110.8036, "radius_meters": 150 },
      { "id": 2, "name": "Gedung Kuliah Kampus 2", "latitude": -7.6, "longitude": 110.85, "radius_meters": 150 }
    ],
    "attendance": null
  }
}
```

> `allowed_offices` = seluruh titik absen sah pegawai (utama + tambahan multi-lokasi); mobile dapat menampilkan semua pin geofence. Clock-in/out diterima bila masuk radius salah satu titik.

---

### POST `/api/v1/attendance/clock-in` & `POST /api/simpeg/presensi/clock-in`

Mencatat presensi masuk karyawan dengan validasi geofencing, GPS accuracy, anti-mock GPS, dan biometrik wajah. Mendukung auto-sync ke kolom legacy `jam_masuk` dan `lat_long`, serta penyimpanan foto presensi.

**Request Body:**
```json
{
  "latitude": -7.5675,
  "longitude": 110.8036,
  "accuracy": 10.0,
  "face_score": 0.88,
  "face_image": "data:image/jpeg;base64,...",
  "is_mock_location": false,
  "device_id": "flutter-android-device-uuid",
  "notes": "Hadir tepat waktu"
}
```

**Response Sukses (200 OK):**
```json
{
  "status": "success",
  "success": true,
  "message": "Presensi masuk berhasil (Tepat Waktu).",
  "data": {
    "id": 109,
    "pegawai_id": 1,
    "tanggal": "2026-09-17",
    "jam_masuk": "08:00:00",
    "clock_in": "2026-09-17T08:00:00.000000Z",
    "lat_long": "-7.5675,110.8036",
    "status": "hadir",
    "late_minutes": 0,
    "clock_in_latitude": -7.5675,
    "clock_in_longitude": 110.8036,
    "clock_in_distance_meters": 0,
    "clock_in_face_score": 0.88,
    "foto_url": "http://localhost:8000/storage/simpeg/presensi/2026/09/uuid.jpg"
  }
}
```

---

### POST `/api/v1/attendance/clock-out` & `POST /api/simpeg/presensi/clock-out`

Mencatat presensi pulang karyawan. Nilai `face_score` dan `is_mock_location` bersifat opsional (default terisi aman).

**Request Body:**
```json
{
  "latitude": -7.5675,
  "longitude": 110.8036,
  "accuracy": 12.0,
  "notes": "Pulang kerja"
}
```

> **Shift lintas hari (misal satpam 22:00-06:00):** clock-out pagi (misal Selasa 06:05) otomatis menutup record tanggal dinas kemarin (Senin), bukan mencari record hari ini. Endpoint `today`/`todayStatus` di jam 00:xx-06:xx menampilkan record dinas kemarin yang masih terbuka (`can_clock_out: true`) dengan `schedule.is_overnight: true` dan `schedule.duty_date` = tanggal dinas. Clock-in 00:xx (misal 00:30) tercatat sebagai `terlambat` pada tanggal dinas kemarin. Cut-off menunda Alfa sampai jam pulang shift terlewati.

---

### POST `/api/v1/attendance/keterangan`

Pengajuan mandiri izin, sakit, atau dinas luar oleh pegawai langsung dari aplikasi mobile. Mendukung upload berkas/foto lampiran bukti (surat dokter / surat penugasan dinas) hingga 5MB (PDF/JPG/PNG).

**Request Body (Multipart Form-Data / JSON):**
```
tanggal: "2026-09-28"
status_kehadiran: "sakit"  (atau key "status")
catatan: "Demam tinggi, istirahat dokter"  (atau key "notes")
file: [File Binary: surat_dokter.pdf / .jpg / .png]  (atau key "lampiran" / "foto" / "bukti")
```

---

### GET `/api/v1/attendance/history`

Mengambil riwayat presensi individu dengan paginasi server-side dan filter.

**Query Parameters:**
- `per_page`: int (default: 31, max: 100)
- `page`: int
- `month`: int (1-12)
- `year`: int (e.g. 2026)
- `start_date`: YYYY-MM-DD
- `end_date`: YYYY-MM-DD
- `status`: hadir | terlambat | izin | sakit | dinas | alfa

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
| GET | `/api/simpeg/presensi` | Daftar presensi (Realtime biometrik atau `type=bundle`) dengan filter & dynamic sorting (`sort_by`, `sort_order`) | ✅ Bearer |
| POST | `/api/simpeg/presensi/{id}/approve` | Persetujuan manual HR (clock-in hari libur / upaya ditolak) | ✅ Bearer |
| POST | `/api/simpeg/presensi/keterangan` | Tetapkan keterangan ketidakhadiran (izin/sakit/dinas/alfa) untuk pegawai terjadwal masuk tanpa log | ✅ Bearer |
| GET | `/api/simpeg/presensi/recap` | Rekap bulanan per pegawai (silang cuti disetujui) | ✅ Bearer |

> Clock-in valid langsung tercatat `hadir`/`terlambat` tanpa verifikasi. Verifikasi manual hanya untuk clock-in di hari libur jadwal shift (`menunggu_approval`) dan upaya yang ditolak validasi (`ditolak`).

### GET `/api/simpeg/presensi`

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `type` | string | ❌ | — | `bundle` untuk melihat daftar periode presensi, atau kosongkan untuk realtime biometrik logs |
| `search` | string | ❌ | — | Pencarian nama, NIP, NIDN, NUPTK |
| `status` | string | ❌ | — | Filter status kehadiran (`hadir`, `terlambat`, `izin`, `sakit`, `dinas`, `alfa`) |
| `tanggal` | date | ❌ | — | Filter tanggal presensi format YYYY-MM-DD |
| `start_date` | date | ❌ | — | Tanggal awal rentang presensi |
| `end_date` | date | ❌ | — | Tanggal akhir rentang presensi |
| `month` | integer | ❌ | — | Bulan (1-12) |
| `year` | integer | ❌ | — | Tahun (contoh: 2026) |
| `sort_by` | string | ❌ | `created_at` | Kolom pengurutan (`created_at`, `tanggal`, `clock_in`, `clock_out`, `status_kehadiran`, `id`) |
| `sort_order` | string | ❌ | `desc` | Arah pengurutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah baris data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Nomor halaman pagination |

**Hak Akses RBAC:**
- Pengguna dengan permission `simpeg.presensi.manage` / `simpeg.presensi.read` atau role `admin_simpeg` / `admin` dapat melihat presensi seluruh pegawai.
- Karyawan biasa tanpa permission di atas secara otomatis difilter hanya melihat log presensi milik dirinya sendiri (`pegawai_id`).

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Data presensi biometrik berhasil diambil",
    "data": [
        {
            "id": 101,
            "employee_id": 10,
            "tanggal": "2026-09-19",
            "clock_in": "07:45:00",
            "clock_out": "16:05:00",
            "status_kehadiran": "hadir",
            "employee": {
                "id": 10,
                "nama_lengkap": "Dr. Siti Aminah, M.Kom",
                "nip": "198501012010122001"
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
        "search": "",
        "status": "",
        "tanggal": "",
        "start_date": "",
        "end_date": "",
        "month": "",
        "year": "",
        "sort_by": "created_at",
        "sort_order": "desc"
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
    "message": "Anda tidak memiliki hak akses melihat data presensi."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Parameter query tidak valid.",
    "errors": {
        "per_page": [
            "Nilai per_page harus berupa angka."
        ]
    }
}
```


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

---

### DELETE `/api/simpeg/presensi/log/{id}`

Menghapus 1 baris log presensi individual (`Attendance`) berdasarkan ID log.

**Hak Akses RBAC:**
- Memerlukan salah satu dari: `simpeg.presensi.delete`, `simpeg.presensi.manage`, atau role `admin` / `super_admin`.
- Menghapus record secara permanen dari tabel `simpeg_presensi_pegawai` dan mencatat jejak audit ke tabel `audit_logs` (`module: 'SIMPEG'`, `action: 'delete'`).

**Response Sukses (200 OK):**
```json
{
  "status": "success",
  "message": "Log presensi berhasil dihapus."
}
```

---

### Logika Presensi Shift & Multi-Lokasi Otomatis

1. **Scan Setelah Shift Selesai (Clock-out Tanpa Clock-in):**
   - Jika pegawai pada jadwal shift (misal 08:00 - 16:00) belum sempat melakukan clock-in dan baru melakukan scan pada atau setelah jam shift berakhir (misal jam 17:00), sistem otomatis mencatat aktivitas tersebut sebagai **Presensi Pulang (Clock-out)**.
   - Kolom `clock_in` dan `jam_masuk` dibiarkan `null`, `clock_out` dan `jam_keluar` terisi dengan waktu scan aktual, serta `notes` diberi keterangan *"Presensi pulang tercatat tanpa presensi masuk sebelumnya"*.
   - Jika pegawai menggunakan endpoint `/api/v1/attendance/clock-in` saat shift sudah berakhir, request otomatis didelegasikan ke `processClockOut` tanpa error.

2. **Multi-Lokasi Kampus Otomatis:**
   - Pegawai tidak diwajibkan dikaitkan ke satu kantor tertentu secara kaku.
   - `getAllowedOfficeLocations()` mengembalikan seluruh lokasi kantor/kampus aktif (`OfficeLocation::where('is_active', true)`).
   - Validasi koordinat GPS mencocokkan ke lokasi kampus terdekat yang berada dalam radius geofence.

3. **Pencegahan Salah Scan Pulang Sesaat Setelah Masuk (Cooldown & Jendela Jam Pulang):**
   - **Terkunci di Mobile UI (`can_clock_out: false`)**: Setelah pegawai berhasil melakukan presensi masuk (misal jam 08:00), tombol presensi pulang di aplikasi mobile **tidak aktif** (`can_clock_out = false`) hingga jam kerja memasuki jendela jam pulang shift (misal pukul 15:45 pada shift 08:00 - 16:00).
   - **Pencegahan Duplikasi Masuk**: Jika pegawai mencoba scan masuk lagi di jam 08:01 untuk "memastikan", sistem menolak dengan status 422 (*"Karyawan sudah melakukan presensi masuk yang sah hari ini"*), sehingga data jam masuk awal (08:00:00) tetap aman dan tidak tertimpa.
   - **Validasi Server Cooldown**: Jika ada request presensi pulang sesaat setelah presensi masuk (kurang dari jeda minimal 15 menit), sistem menolak dengan validasi (*"Presensi pulang tidak dapat dilakukan sesaat setelah presensi masuk (jeda minimal 15 menit)"*).


