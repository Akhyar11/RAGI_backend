# Dokumentasi API Integrasi Presensi & Sinkronisasi SIMPEG RAG

Dokumentasi ini menjelaskan spesifikasi API dan tata cara integrasi data presensi karyawan Politeknik Indonusa Surakarta, serta mekanisme sinkronisasi data pegawai secara otomatis dari database modul **SIMPEG** di project **RAG**.

---

## 1. Ikhtisar & Arsitektur

```
┌───────────────────────────┐                ┌───────────────────────────────┐
│   Project RAG (SIMPEG)    │                │      Sistem Presensi          │
│ SQLite: database.sqlite   │                │      MySQL: indonusa_absen    │
│  - simpeg_pegawai         │ ──(Sync)─────> │  - users                      │
│  - core_users             │                │  - employees                  │
│  - simpeg_unit_kerja      │                │  - attendances                │
└───────────────────────────┘                └───────────────┬───────────────┘
                                                             │
                                                             ▼
                                             ┌───────────────────────────────┐
                                             │    API Integrasi Eksternal    │
                                             │    Header: X-API-KEY          │
                                             │  - GET /attendances           │
                                             │  - GET /attendances/{id}      │
                                             │  - GET /attendances/recap     │
                                             │  - POST /sync-simpeg          │
                                             └───────────────────────────────┘
```

Aplikasi Presensi menyediakan REST API publik/eksternal yang diproteksi menggunakan **API Key**. Endpoint ini memungkinkan sistem luar (seperti modul SIMPEG pada project RAG, sistem Payroll, atau Dashboard Eksekutif) untuk:
1. Mengambil data log kehadiran harian/rentang tanggal lengkap dengan titik GPS, foto, dan skor biometrik.
2. Mengambil rekapitulasi statistik kehadiran bulanan/tahunan per karyawan maupun per departemen/unit kerja.
3. Memicu sinkronisasi data pegawai langsung dari SQLite database SIMPEG RAG ke database Presensi tanpa perlu entri data manual satu per satu.

---

## 2. Autentikasi API Key

Setiap request ke endpoint integrasi (`/api/v1/integration/*`) wajib menyertakan API Key.

### Konfigurasi Kunci di Server
API Key dikonfigurasi melalui environment variable di `.env`:
```env
ATTENDANCE_API_KEY=indo_absen_sec_2026_x89a7f3d
```

### Metode Pengiriman API Key
Klien dapat mengirimkan API Key melalui salah satu dari 3 cara berikut:

1. **HTTP Header `X-API-KEY` (Direkomendasikan):**
   ```http
   X-API-KEY: indo_absen_sec_2026_x89a7f3d
   ```
2. **HTTP Header `Authorization: Bearer`:**
   ```http
   Authorization: Bearer indo_absen_sec_2026_x89a7f3d
   ```
3. **URL Query Parameter:**
   ```
   ?api_key=indo_absen_sec_2026_x89a7f3d
   ```

### Respon Error Autentikasi (401 Unauthorized)
```json
{
  "success": false,
  "message": "Akses ditolak: API Key tidak valid atau tidak disertakan pada header X-API-KEY."
}
```

---

## 3. Sinkronisasi Data Pegawai dari SIMPEG RAG

Modul sinkronisasi menghubungkan database SQLite SIMPEG di `/Users/it/Project/RAG/backend/database/database.sqlite` dengan database Presensi (`indonusa_absen`).

### Pemetaan Kolom Data
| SIMPEG RAG (`simpeg_pegawai` + join) | Presensi (`employees` & `users`) | Catatan |
| :--- | :--- | :--- |
| `nip` | `employees.employee_code` | Kunci unik (identifier pegawai) |
| `nama_lengkap` | `users.name` | Nama lengkap karyawan |
| `core_users.email` | `users.email` | Email login (default fallback: `{nip}@indonusa.ac.id`) |
| `simpeg_unit_kerja.nama` | `employees.department` | Departemen / Unit kerja |
| `jenis_pegawai` | `employees.position` | Jabatan / Status Kepegawaian (Dosen, Tenaga Kependidikan, dll) |
| `telepon` | `employees.phone` | Nomor kontak |
| (Default) | `employees.office_location_id` | Otomatis diatur ke `Politeknik Indonusa Surakarta` |
| (Default) | `employees.shift_template_id` | Otomatis diatur ke `Shift Reguler 5 Hari` |

### Tiga Cara Menjalankan Sinkronisasi

#### Cara 1: Tombol di Web Admin Filament (Mudah & Visual)
1. Buka Web Admin: `http://localhost:8000/admin`
2. Masuk ke menu **Kelola Karyawan**.
3. Di pojok kanan atas, klik tombol **"Tarik Data dari SIMPEG (RAG)"**.
4. Muncul dialog konfirmasi:
   - Terdapat opsi checkbox *"Simulasi saja (Dry Run)"* jika hanya ingin melihat pratinjau.
   - Kolom *"Password Default Karyawan Baru"* (standar: `Indonusa@123`).
5. Klik **"Mulai Tarik Data"**. Notifikasi sukses akan menampilkan jumlah pegawai baru dan yang diperbarui.

#### Cara 2: Melalui Terminal / CLI Artisan
```bash
# Uji coba / simulasi tanpa mengubah database:
php artisan simpeg:sync-employees --dry-run

# Eksekusi sinkronisasi langsung:
php artisan simpeg:sync-employees

# Eksekusi dengan kustom password awal akun baru:
php artisan simpeg:sync-employees --password="PasswordBaru123!"
```

#### Cara 3: Melalui REST API
Kirim request HTTP `POST` ke endpoint `/api/v1/integration/sync-simpeg`.

---

## 4. Spesifikasi Endpoint Integrasi Presensi

Base URL:
```
http://localhost:8000/api/v1/integration
```

---

### 4.1. List Log Presensi (`GET /attendances`)
Mengambil daftar log riwayat presensi karyawan dengan filter yang fleksibel dan paginasi.

- **URL:** `/api/v1/integration/attendances`
- **Metode:** `GET`
- **Header:** `X-API-KEY: {API_KEY}`

#### Query Parameters
| Parameter | Tipe | Contoh | Keterangan |
| :--- | :--- | :--- | :--- |
| `date` | `string` | `2026-09-14` | Filter spesifik satu tanggal (`YYYY-MM-DD`). |
| `start_date` | `string` | `2026-09-01` | Tanggal awal rentang waktu. Wajib bersama `end_date`. |
| `end_date` | `string` | `2026-09-30` | Tanggal akhir rentang waktu. Wajib bersama `start_date`. |
| `month` | `int` | `9` | Filter bulan (1-12). Wajib bersama `year`. |
| `year` | `int` | `2026` | Filter tahun (YYYY). |
| `nip` / `employee_code` | `string` | `198501012010121001` | Filter berdasarkan NIP pegawai tertentu. |
| `department` | `string` | `Informatika` | Filter pencarian nama unit kerja / departemen. |
| `status` | `string` | `hadir` | Nilai yang didukung: `hadir`, `terlambat`, `ditolak`, `menunggu_approval`, atau alias `present`, `late`, `on_time`. |
| `per_page` | `int` | `20` | Jumlah data per halaman (default: 20, max: 100). |
| `page` | `int` | `1` | Nomor halaman data. |

#### Contoh Respon (200 OK)
```json
{
  "success": true,
  "message": "Data absensi berhasil diambil.",
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total_records": 35,
    "total_pages": 2
  },
  "data": [
    {
      "id": 142,
      "attendance_date": "2026-09-14",
      "status": "hadir",
      "is_late": false,
      "employee": {
        "id": 4,
        "nip": "199003152015041002",
        "name": "Ahmad Fauzi, M.Kom.",
        "email": "ahmad.fauzi@indonusa.ac.id",
        "department": "D3 Sistem Informasi",
        "position": "Dosen Tetap"
      },
      "office": {
        "id": 8,
        "name": "Politeknik Indonusa Surakarta",
        "radius_meters": 150
      },
      "clock_in": {
        "time": "07:54:12",
        "datetime": "2026-09-14T07:54:12+07:00",
        "status": "on_time",
        "latitude": -7.5612345,
        "longitude": 110.8523456,
        "accuracy": 8.5,
        "distance_meters": 42.1,
        "face_score": 0.8845,
        "is_mock_location": false
      },
      "clock_out": {
        "time": "17:02:40",
        "datetime": "2026-09-14T17:02:40+07:00",
        "latitude": -7.5612400,
        "longitude": 110.8523500,
        "accuracy": 9.0,
        "distance_meters": 43.0,
        "face_score": 0.8920,
        "is_mock_location": false
      },
      "durations": {
        "work_minutes": 548,
        "work_hours": 9.13,
        "late_minutes": 0
      },
      "rejection_reason": null,
      "notes": null,
      "is_approved_by_admin": null,
      "created_at": "2026-09-14T07:54:12+07:00",
      "updated_at": "2026-09-14T17:02:40+07:00"
    }
  ]
}
```

---

### 4.2. Detail Log Presensi (`GET /attendances/{id}`)
Mengambil detail informasi lengkap satu data log absensi berdasarkan ID log.

- **URL:** `/api/v1/integration/attendances/{id}`
- **Metode:** `GET`
- **Header:** `X-API-KEY: {API_KEY}`

#### Contoh Respon Sukses (200 OK)
```json
{
  "success": true,
  "data": {
    "id": 142,
    "attendance_date": "2026-09-14",
    "status": "terlambat",
    "is_late": true,
    "employee": {
      "id": 4,
      "nip": "199003152015041002",
      "name": "Ahmad Fauzi, M.Kom.",
      "email": "ahmad.fauzi@indonusa.ac.id",
      "department": "D3 Sistem Informasi",
      "position": "Dosen Tetap"
    },
    "office": {
      "id": 8,
      "name": "Politeknik Indonusa Surakarta",
      "radius_meters": 150
    },
    "clock_in": {
      "time": "08:24:10",
      "datetime": "2026-09-14T08:24:10+07:00",
      "status": "late",
      "latitude": -7.5612345,
      "longitude": 110.8523456,
      "accuracy": 10.0,
      "distance_meters": 35.2,
      "face_score": 0.8650,
      "is_mock_location": false
    },
    "clock_out": null,
    "durations": {
      "work_minutes": 0,
      "work_hours": 0,
      "late_minutes": 24
    },
    "rejection_reason": null,
    "notes": "Terlambat karena perbaikan jalan Solo-Semarang",
    "is_approved_by_admin": true,
    "created_at": "2026-09-14T08:24:10+07:00",
    "updated_at": "2026-09-14T08:24:10+07:00",
    "details": {
      "clock_in_distance_meters": 35.2,
      "clock_out_distance_meters": null,
      "clock_in_is_mock_location": false,
      "clock_out_is_mock_location": false,
      "notes": "Terlambat karena perbaikan jalan Solo-Semarang",
      "approved_by": 1,
      "approved_at": "2026-09-14T09:00:00+07:00"
    }
  }
}
```

#### Contoh Respon Tidak Ditemukan (404 Not Found)
```json
{
  "success": false,
  "message": "Data absensi dengan ID 999999 tidak ditemukan."
}
```

---

### 4.3. Rekapitulasi Statistik Kehadiran (`GET /attendances/recap`)
Menghasilkan ringkasan kehadiran (total hadir, tepat waktu, terlambat, ditolak, akumulasi menit terlambat, total jam kerja) per periode bulan/tahun, baik untuk seluruh organisasi maupun per individu pegawai.

- **URL:** `/api/v1/integration/attendances/recap`
- **Metode:** `GET`
- **Header:** `X-API-KEY: {API_KEY}`

#### Query Parameters
| Parameter | Tipe | Default | Keterangan |
| :--- | :--- | :--- | :--- |
| `month` | `int` | Bulan berjalan | Angka bulan (1 - 12) |
| `year` | `int` | Tahun berjalan | Angka tahun (misal: 2026) |
| `nip` / `employee_code` | `string` | *(opsional)* | Rekap khusus untuk satu pegawai |
| `department` | `string` | *(opsional)* | Rekap khusus satu unit kerja |

#### Contoh Respon Sukses (200 OK)
```json
{
  "success": true,
  "period": {
    "month": 9,
    "year": 2026
  },
  "summary": {
    "total_records": 680,
    "total_present": 650,
    "total_on_time": 620,
    "total_late": 30,
    "total_rejected": 5,
    "total_late_minutes": 420,
    "total_work_hours": 5120.5
  },
  "employees": [
    {
      "employee_id": 4,
      "nip": "199003152015041002",
      "name": "Ahmad Fauzi, M.Kom.",
      "department": "D3 Sistem Informasi",
      "total_hadir": 20,
      "total_terlambat": 1,
      "total_tepat_waktu": 19,
      "total_menit_terlambat": 12,
      "total_jam_kerja": 164.5
    }
  ]
}
```

---

### 4.4. Trigger Sinkronisasi Pegawai (`POST /sync-simpeg`)
Memicu proses penarikan data pegawai terbaru dari SQLite SIMPEG RAG.

- **URL:** `/api/v1/integration/sync-simpeg`
- **Metode:** `POST`
- **Header:**
  - `X-API-KEY: {API_KEY}`
  - `Content-Type: application/json`

#### Request Body (JSON)
```json
{
  "dry_run": false,
  "default_password": "Indonusa@123"
}
```

#### Contoh Respon Sukses (200 OK)
```json
{
  "success": true,
  "message": "Sinkronisasi pegawai dari SIMPEG RAG berhasil.",
  "data": {
    "success": true,
    "dry_run": false,
    "total_source": 36,
    "created": 0,
    "updated": 36,
    "skipped": 0,
    "default_office": "Politeknik Indonusa Surakarta",
    "default_shift": "Shift Reguler 5 Hari",
    "details": [
      {
        "nip": "199003152015041002",
        "name": "Ahmad Fauzi, M.Kom.",
        "email": "ahmad.fauzi@indonusa.ac.id",
        "department": "D3 Sistem Informasi",
        "position": "Dosen Tetap",
        "action": "updated"
      }
    ]
  }
}
```

---

## 5. Contoh Kode Integrasi

### A. Menggunakan cURL
```bash
# 1. Ambil riwayat absen hari ini
curl -X GET "http://localhost:8000/api/v1/integration/attendances?date=$(date +%Y-%m-%d)" \
  -H "X-API-KEY: indo_absen_sec_2026_x89a7f3d" \
  -H "Accept: application/json"

# 2. Ambil rekap bulanan untuk NIP tertentu
curl -X GET "http://localhost:8000/api/v1/integration/attendances/recap?month=9&year=2026&nip=199003152015041002" \
  -H "X-API-KEY: indo_absen_sec_2026_x89a7f3d"

# 3. Jalankan sinkronisasi pegawai
curl -X POST "http://localhost:8000/api/v1/integration/sync-simpeg" \
  -H "X-API-KEY: indo_absen_sec_2026_x89a7f3d" \
  -H "Content-Type: application/json" \
  -d '{"dry_run": false}'
```

---

### B. Menggunakan Python (Untuk AI Assistant / Modul Backend di Project RAG)
```python
import requests

BASE_URL = "http://localhost:8000/api/v1/integration"
API_KEY = "indo_absen_sec_2026_x89a7f3d"

headers = {
    "X-API-KEY": API_KEY,
    "Accept": "application/json"
}

def get_employee_attendance_today(nip: str):
    """Mendapatkan status kehadiran pegawai hari ini untuk AI Chatbot RAG."""
    response = requests.get(
        f"{BASE_URL}/attendances",
        headers=headers,
        params={"nip": nip, "date": "2026-09-14"}
    )
    if response.status_code == 200:
        data = response.json().get("data", [])
        if data:
            att = data[0]
            status = att["status"]
            clock_in = att["clock_in"]["time"] if att["clock_in"] else "Belum clock-in"
            return f"Pegawai {att['employee']['name']} status: {status}, masuk jam: {clock_in}"
        return "Pegawai belum melakukan presensi hari ini."
    return f"Gagal mengambil data: {response.status_code}"

# Contoh panggil fungsi:
print(get_employee_attendance_today("199003152015041002"))
```

---

### C. Menggunakan PHP / Guzzle
```php
<?php

use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'http://localhost:8000/api/v1/integration/',
    'headers' => [
        'X-API-KEY' => 'indo_absen_sec_2026_x89a7f3d',
        'Accept' => 'application/json',
    ]
]);

// Ambil rekap bulan berjalan
$response = $client->get('attendances/recap', [
    'query' => [
        'month' => date('n'),
        'year' => date('Y'),
    ]
]);

$recap = json_decode($response->getBody()->getContents(), true);
print_r($recap['summary']);
```

---

### D. Menggunakan JavaScript (Node.js / Frontend Fetch)
```javascript
const API_URL = 'http://localhost:8000/api/v1/integration/attendances';
const API_KEY = 'indo_absen_sec_2026_x89a7f3d';

async function fetchAttendanceByDate(dateString) {
  const response = await fetch(`${API_URL}?date=${dateString}`, {
    method: 'GET',
    headers: {
      'X-API-KEY': API_KEY,
      'Accept': 'application/json'
    }
  });

  const result = await response.json();
  if (result.success) {
    console.log(`Ditemukan ${result.meta.total_records} data log presensi:`, result.data);
  }
}

fetchAttendanceByDate('2026-09-14');
```

---

## 6. Integrasi dengan Modul SIMPEG di RAG

Sistem RAG (Retrieval-Augmented Generation) yang berjalan pada backend FastAPI/Python dapat memanfaatkan API ini secara langsung sebagai Tool/Plugin atau API Service:
1. **Penyelarasan Data Pegawai:** Jalankan cron harian atau panggil endpoint `POST /sync-simpeg` setiap kali ada penambahan pegawai baru di SIMPEG.
2. **Kueri Chatbot Cerdas:** Asisten AI pada sistem RAG dapat menjawab pertanyaan civitas akademika seperti:
   - *"Apakah Pak Ahmad Fauzi sudah ada di kampus hari ini?"* -> AI memanggil `GET /attendances?nip=...&date=today`.
   - *"Bagaimana rekapitulasi kehadiran dosen prodi Sistem Informasi bulan ini?"* -> AI memanggil `GET /attendances/recap?department=Sistem Informasi`.
