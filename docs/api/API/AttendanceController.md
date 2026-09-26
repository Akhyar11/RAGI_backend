# AttendanceController (Mobile Presensi API)

> **Modul**: SIMPEG / Mobile Attendance  
> **Base URL**: `/api/v1/attendance`  
> **Autentikasi**: Bearer Token (Passport / Sanctum)  
> **Dibuat**: 2026-09-14  
> **Diperbarui**: 2026-09-26  

Dokumentasi endpoint API presensi mobile pegawai untuk aplikasi Flutter dan portal SIMPEG terintegrasi. Menangani status harian presensi, jendela waktu (cutoff) masuk/pulang dinamis, presensi masuk/pulang biometrik wajah, pengajuan izin/keterangan mandiri, riwayat presensi, dan rekapitulasi individu.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/attendance/today` | Status presensi hari ini, lokasi kantor, jadwal shift & jendela cutoff | ✅ Diperlukan |
| POST | `/api/v1/attendance/clock-in` | Presensi masuk (validasi GPS, Anti-Mock GPS, Face Score, auto cutoff) | ✅ Diperlukan |
| POST | `/api/v1/attendance/clock-out` | Presensi pulang (validasi GPS, Face Score, verifikasi jendela pulang) | ✅ Diperlukan |
| POST | `/api/v1/attendance/keterangan` | Pengajuan izin, sakit, dinas luar, atau cuti mandiri | ✅ Diperlukan |
| GET | `/api/v1/attendance/history` | Riwayat presensi bulanan/rentang tanggal karyawan login | ✅ Diperlukan |
| GET | `/api/v1/attendance/recap` | Rekapitulasi absensi bulanan individu | ✅ Diperlukan |

---

## Headers Standar

| Header | Nilai | Wajib | Keterangan |
|---|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ | Token autentikasi pegawai yang login |
| `Accept` | `application/json` | ✅ | Format respons yang diharapkan |
| `Content-Type` | `application/json` / `multipart/form-data` | ✅ | Gunakan `multipart/form-data` jika mengunggah foto/berkas |

---

## 1. GET `/api/v1/attendance/today`

Mengambil data status presensi hari ini, profil pegawai login, lokasi kantor, serta konfigurasi jadwal shift beserta batas toleransi scan masuk dan scan pulang (`earliest_clock_in`, `latest_clock_in`, `earliest_clock_out`, `latest_clock_out`, `can_clock_in`, `can_clock_out`).

### Query Parameters

Tidak memerlukan parameter query khusus.

### Response 200 OK (Contoh Nyata)

```json
{
  "status": "success",
  "success": true,
  "data": {
    "server_time": "2026-09-26T08:05:00+07:00",
    "server_timestamp": 1790471100,
    "server_date": "2026-09-26",
    "server_time_formatted": "08:05:00",
    "can_clock_in": true,
    "can_clock_out": false,
    "is_clocked_in": false,
    "is_clocked_out": false,
    "clock_in_time": null,
    "clock_out_time": null,
    "status": "belum_absen",
    "user": {
      "id": "9d3e8b40-7e11-482f-8d99-0123456789ab",
      "name": "Budi Santoso, M.Kom.",
      "username": "budisantoso",
      "email": "budi@univ.ac.id",
      "avatar": null
    },
    "employee": {
      "id": "e0a293b4-41d3-4a11-8fc2-111122223333",
      "employee_code": "199001012020121001",
      "nip": "199001012020121001",
      "nama": "Budi Santoso, M.Kom.",
      "nama_lengkap": "Budi Santoso, M.Kom.",
      "position": "Dosen Tetap",
      "department": "Informatika",
      "avatar": null,
      "foto_url": null,
      "is_face_enrolled": true,
      "consent_pdp_at": "2026-09-01 08:00:00"
    },
    "date": "2026-09-26",
    "day_name": "Sabtu",
    "schedule": {
      "id": "s1a2b3c4-0000-0000-0000-111122223333",
      "day_of_week": 6,
      "day_name": "Sabtu",
      "start_time": "08:00:00",
      "end_time": "16:00:00",
      "break_start": "12:00:00",
      "break_end": "13:00:00",
      "is_day_off": false,
      "is_overnight": false,
      "duty_date": "2026-09-26",
      "late_tolerance_minutes": 15,
      "early_leave_tolerance_minutes": 15,
      "max_early_clock_in_minutes": 120,
      "max_late_clock_in_minutes": 240,
      "max_early_clock_out_minutes": 60,
      "max_late_clock_out_minutes": 240,
      "earliest_clock_in": "06:00:00",
      "latest_clock_in": "12:00:00",
      "earliest_clock_out": "15:00:00",
      "latest_clock_out": "20:00:00"
    },
    "office": {
      "id": "off-001",
      "name": "Kampus Utama Gedung Rektorat",
      "latitude": -7.7956,
      "longitude": 110.3695,
      "radius_meters": 100
    },
    "allowed_offices": [],
    "attendance": null,
    "is_national_holiday": false,
    "applies_national_holidays": true,
    "is_duty_on_holiday": false,
    "national_holiday": null
  }
}
```

### Response Error

- **401 Unauthorized**:
  ```json
  {
    "status": "error",
    "message": "Unauthenticated."
  }
  ```

---

## 2. POST `/api/v1/attendance/clock-in`

Melakukan presensi masuk pegawai. Apabila waktu scan saat ini telah melebihi batas toleransi akhir masuk (`latest_clock_in` / batas cutoff), request secara otomatis dialihkan dan diproses sebagai Presensi Pulang (`processClockOut`).

### Request Body (`multipart/form-data` atau `application/json`)

| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `latitude` | `numeric` | ✅ | Koordinat latitude perangkat pengguna |
| `longitude` | `numeric` | ✅ | Koordinat longitude perangkat pengguna |
| `accuracy` | `numeric` | ✅ | Akurasi GPS dalam meter |
| `face_score` | `numeric` | ❌ | Nilai kemiripan wajah lokal (opsional) |
| `foto` / `foto_presensi` | `file` | ❌ | Berkas gambar wajah foto live (jpg/png) |
| `is_mock_location` | `boolean` | ❌ | Indikator fake GPS / mock provider (default: false) |
| `device_id` | `string` | ❌ | Identitas perangkat keras / UUID perangkat |
| `notes` / `catatan` | `string` | ❌ | Catatan opsional dari pegawai (maks 500 karakter) |

### Contoh Request Body

```json
{
  "latitude": -7.7956,
  "longitude": 110.3695,
  "accuracy": 12.5,
  "face_score": 0.94,
  "is_mock_location": false,
  "device_id": "android-uuid-12345",
  "notes": "Presensi masuk kantor tepat waktu"
}
```

### Response 200 OK (Berhasil Tepat Waktu)

```json
{
  "status": "success",
  "success": true,
  "message": "Presensi masuk berhasil (Tepat Waktu).",
  "data": {
    "id": "att-123456",
    "pegawai_id": "e0a293b4-41d3-4a11-8fc2-111122223333",
    "tanggal": "2026-09-26",
    "jam_masuk": "07:55:00",
    "clock_in": "2026-09-26 07:55:00",
    "jam_pulang": null,
    "clock_out": null,
    "status": "hadir",
    "status_kehadiran": "hadir",
    "latitude_masuk": -7.7956,
    "longitude_masuk": 110.3695,
    "distance_meters": 14.2,
    "is_mock_location": false,
    "notes": "Presensi masuk kantor tepat waktu"
  }
}
```

### Response 422 Unprocessable Entity (Verifikasi Biometrik / Radius / Jam)

```json
{
  "status": "error",
  "success": false,
  "message": "Verifikasi wajah berhasil (Kemiripan: 95.0%), namun presensi masuk ditolak: Posisi Anda berada di luar radius kantor yang diizinkan (250m dari batas 100m).",
  "data": {
    "id": "att-rejected-01",
    "status": "ditolak",
    "rejection_reason": "Verifikasi wajah berhasil (Kemiripan: 95.0%), namun presensi masuk ditolak: Posisi Anda berada di luar radius kantor yang diizinkan (250m dari batas 100m)."
  }
}
```

---

## 3. POST `/api/v1/attendance/clock-out`

Melakukan presensi pulang pegawai. Jika pegawai tidak pernah melakukan presensi masuk sebelumnya, presensi pulang tetap dicatat dengan `clock_in` dan `jam_masuk` bernilai `null` serta catatan keterangan khusus.

### Request Body (`multipart/form-data` atau `application/json`)

| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `latitude` | `numeric` | ✅ | Koordinat latitude perangkat pengguna |
| `longitude` | `numeric` | ✅ | Koordinat longitude perangkat pengguna |
| `accuracy` | `numeric` | ✅ | Akurasi GPS dalam meter |
| `face_score` | `numeric` | ❌ | Nilai kemiripan wajah live (default: 0.85) |
| `foto` / `foto_presensi` | `file` | ❌ | Berkas gambar wajah foto live |
| `is_mock_location` | `boolean` | ❌ | Indikator fake GPS / mock provider |
| `notes` / `catatan` | `string` | ❌ | Catatan opsional (maks 500 karakter) |

### Response 200 OK (Berhasil Pulang)

```json
{
  "status": "success",
  "success": true,
  "message": "Presensi pulang berhasil dicatat.",
  "data": {
    "id": "att-123456",
    "pegawai_id": "e0a293b4-41d3-4a11-8fc2-111122223333",
    "tanggal": "2026-09-26",
    "jam_masuk": "07:55:00",
    "clock_in": "2026-09-26 07:55:00",
    "jam_pulang": "16:05:00",
    "clock_out": "2026-09-26 16:05:00",
    "status": "hadir",
    "latitude_pulang": -7.7956,
    "longitude_pulang": 110.3695
  }
}
```

### Response 200 OK (Presensi Pulang Tanpa Absen Masuk)

```json
{
  "status": "success",
  "success": true,
  "message": "Presensi pulang berhasil dicatat (tanpa presensi masuk sebelumnya).",
  "data": {
    "id": "att-123499",
    "pegawai_id": "e0a293b4-41d3-4a11-8fc2-111122223333",
    "tanggal": "2026-09-26",
    "jam_masuk": null,
    "clock_in": null,
    "jam_pulang": "16:10:00",
    "clock_out": "2026-09-26 16:10:00",
    "status": "hadir",
    "notes": "Presensi pulang tercatat tanpa presensi masuk sebelumnya."
  }
}
```

### Response 422 Unprocessable Entity (Di Luar Jendela Jam Pulang)

```json
{
  "status": "error",
  "message": "Belum memasuki jendela waktu presensi pulang. Presensi pulang dibuka mulai pukul 15:00:00."
}
```

---

## 4. POST `/api/v1/attendance/keterangan`

Pengajuan izin, sakit, dinas luar, atau cuti mandiri oleh pegawai melalui aplikasi mobile.

### Request Body (`multipart/form-data`)

| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `tanggal` | `date` | ✅ | Format `YYYY-MM-DD` |
| `status_kehadiran` | `string` | ✅ | Pilihan: `izin`, `sakit`, `dinas`, `cuti`, `alfa` |
| `catatan` / `notes` | `string` | ❌ | Alasan atau rincian permohonan (maks 1000 karakter) |
| `file` / `lampiran` / `foto` | `file` | ❌ | Dokumen pendukung (PDF, JPG, PNG maks 5MB) |

### Response 201 Created

```json
{
  "status": "success",
  "success": true,
  "message": "Pengajuan keterangan izin berhasil disimpan.",
  "data": {
    "id": "att-ket-7788",
    "pegawai_id": "e0a293b4-41d3-4a11-8fc2-111122223333",
    "tanggal": "2026-09-28",
    "status": "izin",
    "status_kehadiran": "izin",
    "notes": "Mengurus berkas kependudukan di Dukcapil",
    "catatan": "Mengurus berkas kependudukan di Dukcapil",
    "foto_presensi": "presensi/lampiran/doc-izin-7788.pdf",
    "is_approved_by_admin": false,
    "source": "mobile_gps"
  }
}
```

---

## 5. GET `/api/v1/attendance/history`

Mengambil riwayat absensi pegawai login dengan filter tanggal, bulan, tahun, status, dan pagination.

### Query Parameters

| Parameter | Tipe | Default | Keterangan |
|---|---|---|---|
| `start_date` | `date` | `-` | Tanggal awal rentang pencarian (`YYYY-MM-DD`) |
| `end_date` | `date` | `-` | Tanggal akhir rentang pencarian (`YYYY-MM-DD`) |
| `month` | `integer` | `-` | Filter bulan (1-12) |
| `year` | `integer` | `-` | Filter tahun (misal: 2026) |
| `date` | `date` | `-` | Filter tanggal tunggal spesifik (`YYYY-MM-DD`) |
| `status` | `string` | `-` | Filter status (`hadir`, `terlambat`, `izin`, `sakit`, `dinas`, `alfa`) |
| `per_page` | `integer` | `31` | Jumlah data per halaman (maksimal 100) |
| `page` | `integer` | `1` | Nomor halaman data |

### Response 200 OK

```json
{
  "status": "success",
  "success": true,
  "message": "Riwayat presensi berhasil diambil",
  "data": {
    "attendances": [
      {
        "id": "att-123456",
        "pegawai_id": "e0a293b4-41d3-4a11-8fc2-111122223333",
        "tanggal": "2026-09-26",
        "jam_masuk": "07:55:00",
        "clock_in": "2026-09-26 07:55:00",
        "jam_pulang": "16:05:00",
        "clock_out": "2026-09-26 16:05:00",
        "status": "hadir"
      }
    ],
    "total": 1
  },
  "meta": {
    "current_page": 1,
    "per_page": 31,
    "total": 1,
    "last_page": 1,
    "from": 1,
    "to": 1
  }
}
```

---

## 6. GET `/api/v1/attendance/recap`

Mengambil rekapitulasi kehadiran individu per bulan (akumulasi kehadiran, terlambat, izin, sakit, alpa, total jam kerja, dan persentase).

### Query Parameters

| Parameter | Tipe | Default | Keterangan |
|---|---|---|---|
| `month` | `integer` | Bulan saat ini | Angka bulan (1-12) |
| `year` | `integer` | Tahun saat ini | Angka tahun (misal: 2026) |

### Response 200 OK

```json
{
  "status": "success",
  "success": true,
  "message": "Rekap presensi berhasil diambil",
  "data": {
    "month": 9,
    "year": 2026,
    "pegawai_id": "e0a293b4-41d3-4a11-8fc2-111122223333",
    "summary": {
      "total_hari_kerja": 22,
      "total_hadir": 20,
      "total_terlambat": 1,
      "total_izin": 1,
      "total_sakit": 0,
      "total_cuti": 0,
      "total_alfa": 0,
      "persentase_kehadiran": 95.45,
      "total_jam_kerja_efektif": "160:30:00"
    }
  }
}
```

---

## Catatan Integritas & Keamanan

1. **Privasi Berkas Lampiran**: Berkas lampiran izin/surat dokter disimpan menggunakan `FileStorageService` pada disk privat dan hanya dapat diakses melalui Signed URL sementara.
2. **Kerahasiaan Kredensial**: Password akun tidak pernah dikembalikan dalam response profil user/pegawai.
3. **Audit Jejak**: Seluruh manipulasi data kehadiran dan status approval dicatat dalam riwayat audit log.
