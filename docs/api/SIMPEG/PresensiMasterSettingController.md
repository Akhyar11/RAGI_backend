# PresensiMasterSettingController

> **Modul**: SIMPEG (Master Pengaturan Presensi)
> **Base URL**: `/api/simpeg/presensi`
> **Autentikasi**: Bearer Token (Passport) — seluruh endpoint terproteksi `auth:api`
> **Dibuat**: 2026-09-14
> **Diperbarui**: 2026-09-18 (tambah `max_late_clock_in_minutes` + override per-hari)

Master pengaturan presensi: parameter sistem, lokasi kantor (geofencing), tipe shift kerja (multi-tipe dengan jadwal 7 hari), dan kalender libur nasional/tanggal merah.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/simpeg/presensi/settings` | Baca parameter sistem presensi | ✅ Bearer |
| PUT | `/api/simpeg/presensi/settings` | Perbarui parameter sistem presensi | ✅ Bearer |
| GET | `/api/simpeg/presensi/office-locations` | Daftar lokasi kantor + jumlah pegawai | ✅ Bearer |
| POST | `/api/simpeg/presensi/office-locations` | Tambah lokasi kantor | ✅ Bearer |
| PUT | `/api/simpeg/presensi/office-locations/{id}` | Ubah lokasi kantor | ✅ Bearer |
| DELETE | `/api/simpeg/presensi/office-locations/{id}` | Hapus lokasi kantor (ditolak bila dipakai pegawai) | ✅ Bearer |
| GET | `/api/simpeg/presensi/shift-templates` | Daftar tipe shift + jadwal 7 hari (auto-seed default bila kosong) | ✅ Bearer |
| POST | `/api/simpeg/presensi/shift-templates` | Tambah tipe shift baru (jam bisa beda per tipe) | ✅ Bearer |
| PUT | `/api/simpeg/presensi/shift-templates/{id}` | Ubah info tipe shift + jadwal harian | ✅ Bearer |
| DELETE | `/api/simpeg/presensi/shift-templates/{id}` | Hapus tipe shift (ditolak bila dipakai pegawai) | ✅ Bearer |
| GET | `/api/simpeg/presensi/national-holidays?year=` | Daftar tanggal libur per tahun | ✅ Bearer |
| POST | `/api/simpeg/presensi/national-holidays/sync` | Sinkronisasi libur dari API publik nasional | ✅ Bearer |
| POST | `/api/simpeg/presensi/national-holidays` | Tambah tanggal libur manual | ✅ Bearer |
| PUT | `/api/simpeg/presensi/national-holidays/{id}` | Ubah tanggal libur | ✅ Bearer |
| DELETE | `/api/simpeg/presensi/national-holidays/{id}` | Hapus tanggal libur | ✅ Bearer |
| GET | `/api/simpeg/presensi/fingerprint-devices` | Daftar perangkat mesin absensi sidik jari / wajah | ✅ Bearer |
| POST | `/api/simpeg/presensi/fingerprint-devices` | Tambah perangkat mesin presensi | ✅ Bearer |
| PUT | `/api/simpeg/presensi/fingerprint-devices/{id}` | Ubah konfigurasi perangkat mesin | ✅ Bearer |
| DELETE | `/api/simpeg/presensi/fingerprint-devices/{id}` | Hapus perangkat mesin | ✅ Bearer |
| POST | `/api/simpeg/presensi/fingerprint-devices/{id}/test-connection` | Uji ping/koneksi perangkat mesin | ✅ Bearer |
| GET | `/api/simpeg/presensi/pegawai/{id}/office-locations` | Lokasi absen sah pegawai (utama + tambahan) | ✅ Bearer |
| PUT | `/api/simpeg/presensi/pegawai/{id}/office-locations` | Atur lokasi absen tambahan pegawai | ✅ Bearer |
| POST | `/api/simpeg/presensi/office-assign-bulk` | Tugaskan lokasi tambahan massal (filter unit/jenis/ids) | ✅ Bearer |

---

## GET /api/simpeg/presensi/shift-templates

> Bila tabel masih kosong, otomatis membuat template default "Shift Reguler 5 Hari" (Senin–Jumat 08:00–17:00, Sabtu–Minggu libur) beserta 7 baris harinya. Template yang kekurangan baris hari akan di-backfill otomatis.

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "name": "Shift Reguler 5 Hari",
            "description": "Jam kerja standar 08:00 s/d 17:00 (Senin - Jumat)",
            "is_active": true,
            "late_tolerance_minutes": 15,
            "early_leave_tolerance_minutes": 15,
            "max_early_clock_in_minutes": 60,
            "max_late_clock_in_minutes": 240,
            "max_early_clock_out_minutes": null,
            "max_late_clock_out_minutes": 240,
            "applies_national_holidays": true,
            "employees_count": 36,
            "days": [
                { "id": 1, "shift_template_id": 1, "day_of_week": 0, "start_time": null, "end_time": null, "is_day_off": true },
                { "id": 2, "shift_template_id": 1, "day_of_week": 1, "start_time": "08:00:00", "end_time": "17:00:00", "is_day_off": false }
            ]
        }
    ]
}
```

> `day_of_week`: 0=Minggu, 1=Senin, …, 6=Sabtu.

---

## POST /api/simpeg/presensi/shift-templates

> Tambah tipe shift baru. `days` opsional (tepat 7 entitas bila dikirim); bila tidak dikirim, dibuatkan 7 hari default yang bisa diubah lewat `PUT`.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body (untuk POST/PUT)

```json
{
    "name": "string, required, unique (contoh: Shift Pagi Satpam)",
    "description": "string, nullable",
    "late_tolerance_minutes": "integer, nullable, 0-120 (default 15)",
    "early_leave_tolerance_minutes": "integer, nullable, 0-120 (default 15)",
    "max_early_clock_in_minutes": "integer, nullable, 0-720 (default 60)",
    "max_late_clock_in_minutes": "integer, nullable, 0-720 (default 240, cutoff scan masuk; setelah ini otomatis diarahkan ke presensi pulang)",
    "max_early_clock_out_minutes": "integer, nullable, 0-720 (default null/otomatis mengikuti cutoff masuk atau toleransi pulang cepat)",
    "max_late_clock_out_minutes": "integer, nullable, 0-720 (default 240, batas maksimal presensi pulang terecord setelah jam pulang)",
    "applies_national_holidays": "boolean, nullable (default true — matikan untuk shift satpam/operasional)",
    "is_active": "boolean, required",
    "days": "array, nullable (PUT: array berisi {id, start_time, end_time, is_day_off, max_early_clock_out_minutes?, max_late_clock_out_minutes?}; POST: array 7 item berisi {day_of_week, start_time, end_time, is_day_off})"
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Tipe shift 'Shift Pagi Satpam' berhasil ditambahkan",
    "data": { "id": 2, "name": "Shift Pagi Satpam", "days": [] }
}
```

### Response Error

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "name": ["Nama tipe shift sudah digunakan."]
    }
}
```

---

## DELETE /api/simpeg/presensi/shift-templates/{id}

> Dihapus beserta 7 baris harinya (`cascadeOnDelete`). Ditolak dengan `422` bila masih dipakai pegawai.

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Tipe shift 'Shift Malam' berhasil dihapus"
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Tipe shift 'Shift Reguler 5 Hari' tidak dapat dihapus karena masih digunakan oleh 36 pegawai."
}
```

---

## GET /api/simpeg/presensi/national-holidays?year=

### Query Parameters (untuk GET dengan filter)

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `year` | integer | ❌ | tahun berjalan | Tahun kalender libur |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": [
        { "id": 1, "holiday_date": "2026-08-17", "name": "Hari Kemerdekaan RI ke-81", "is_mass_leave": false, "description": null }
    ]
}
```

---

## POST /api/simpeg/presensi/national-holidays

### Request Body (untuk POST/PUT)

```json
{
    "holiday_date": "string, required, format Y-m-d, unique",
    "name": "string, required, max 255",
    "is_mass_leave": "boolean, required (true = cuti bersama, false = libur nasional)",
    "description": "string, nullable"
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Tanggal libur 17 Aug 2026 berhasil ditambahkan",
    "data": { "id": 99, "holiday_date": "2026-08-17", "name": "Libur Khusus Kampus", "is_mass_leave": false }
}
```

---

## POST /api/simpeg/presensi/national-holidays/sync

> Menarik daftar libur nasional & cuti bersama tahun berjalan dari API Libur Indonesia (Opica: `https://app.opica.id/api-libur/api?year={tahun}`, fallback legacy `dayoffapi.vercel.app`). `holiday_type = cuti_bersama` dipetakan ke `is_mass_leave = true`. Data manual yang sudah ada tidak dihapus (`updateOrCreate` per tanggal), sehingga aman ditekan berulang kali.
>
> Konfigurasi: `HOLIDAY_API_BASE_URL` (default `https://app.opica.id/api-libur`), `HOLIDAY_API_TIMEOUT` (default `10` detik) — lihat `config/services.php` key `services.holiday`.

### Request Body (untuk POST/PUT)

```json
{
    "year": "integer, nullable, 2020-2035 (default tahun berjalan)"
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Sinkronisasi libur nasional 2026 berhasil — 19 tanggal diproses, total 19 tanggal tersimpan.",
    "data": { "year": 2026, "synced": 19, "total": 19 }
}
```

**502 Bad Gateway**
```json
{
    "status": "error",
    "message": "Sinkronisasi libur nasional 2026 gagal — API publik tidak dapat dijangkau. Data manual tetap aman."
}
```

---

## Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "ShiftTemplate tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "holiday_date": ["Tanggal libur sudah terdaftar."]
    }
}
```

### Catatan Tambahan

> - `GET shift-templates` tidak pernah mengembalikan kosong: selalu ada minimal 1 template default (auto-seed).
> - Field `applies_national_holidays = false` cocok untuk shift satpam/operasional yang tetap wajib masuk saat tanggal merah.
> - Penghapusan shift/lokasi yang masih dipakai pegawai ditolak (`422`) demi integritas referensi `simpeg_pegawai.shift_template_id`.
> - Jendela clock-in: `[start - max_early, start + max_late]`. Keterlambatan dalam jendela tercatat `terlambat` + `late_minutes` + notes audit. `max_late = 0` berarti tanpa batas atas. Override per-hari via `days[].max_late_clock_in_minutes` / `days[].max_early_clock_in_minutes` (PUT).
> - Shift lintas hari: isi `end_time` lebih kecil dari `start_time` (misal `22:00-06:00`, sudah dipakai Shift Satpam Malam). Sistem otomatis +1 hari untuk jam pulang, punch 00:xx diatribusikan ke tanggal dinas kemarin, dan clock-out pagi menutup record dinas kemarin. Cut-off menunda Alfa sampai shift selesai.
> - Multi-lokasi absen: setiap pegawai punya 1 lokasi utama (`office_location_id`) + N lokasi tambahan (pivot `simpeg_pegawai_office_locations`). Clock-in/out sah bila masuk radius lokasi mana pun; lokasi yang cocok dicatat di `office_location_id` + notes audit. Cocok untuk dosen mengajar di gedung/kampus lain.
> - `PUT pegawai/{id}/office-locations` menerima `{office_location_ids: [...]}` (lokasi utama otomatis dikecualikan dari pivot). Bulk: `{office_location_ids, unit_kerja_id?, jenis_pegawai? (dosen|tendik), pegawai_ids?, mode?: attach|sync}`.

---

---

## Fingerprint Devices Management

### GET `/api/simpeg/presensi/fingerprint-devices`
Mengembalikan daftar terminal mesin absensi sidik jari / wajah yang terdaftar di jaringan kampus.

**Response (200 OK):**
```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "device_name": "Terminal Fingerprint Rektorat Lt. 1",
            "device_code": "FP-UTAMA-REKTORAT",
            "ip_address": "192.168.1.201",
            "port": 4370,
            "location": "Lobi Utama Gedung Rektorat",
            "device_model": "ZKTeco ProCapture-X",
            "is_active": true,
            "last_status": "online",
            "last_sync_at": "2026-09-16 11:20:00"
        }
    ]
}
```

### POST `/api/simpeg/presensi/fingerprint-devices/{id}/test-connection`
Menguji status koneksi soket IP dan port mesin fingerprint.

**Response (200 OK):**
```json
{
    "status": "success",
    "message": "Koneksi ke mesin Terminal Fingerprint Rektorat Lt. 1 (192.168.1.201:4370) terhubung dengan baik.",
    "data": {
        "device_id": 1,
        "device_code": "FP-UTAMA-REKTORAT",
        "ip_address": "192.168.1.201",
        "port": 4370,
        "status": "online",
        "latency_ms": 24
    }
}
```

