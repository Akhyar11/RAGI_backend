# Dokumentasi API Data Presensi Karyawan (Attendance Data API)

Dokumentasi ini dikhususkan **hanya untuk mengakses, membaca, dan merekap data presensi/kehadiran karyawan**. Dokumentasi ini ditujukan bagi pengembang sistem eksternal seperti **Sistem Payroll/Penggajian**, **HRIS**, **Dashboard Eksekutif Kampus**, atau **AI/Chatbot** yang memerlukan data kehadiran secara *real-time*.

---

## 1. Spesifikasi Teknis & Autentikasi

### Base URL
```http
http://localhost:8000/api/v1/integration
```
*(Ganti host dan port dengan domain server produksi Anda)*

### Autentikasi (API Key)
Setiap permintaan ke API wajib menyertakan API Key yang telah didaftarkan pada server presensi.

| Parameter | Letak | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `X-API-KEY` | Header HTTP | **Ya** (Direkomendasikan) | Contoh: `X-API-KEY: indo_absen_sec_2026_x89a7f3d` |
| `Authorization` | Header HTTP | Opsional | Contoh: `Authorization: Bearer indo_absen_sec_2026_x89a7f3d` |
| `api_key` | Query String | Opsional | Contoh: `?api_key=indo_absen_sec_2026_x89a7f3d` |

> [!NOTE]
> Jika API Key tidak disertakan atau salah, server akan merespon dengan status **`401 Unauthorized`**:
> ```json
> {
>   "success": false,
>   "message": "Akses ditolak: API Key tidak valid atau tidak disertakan pada header X-API-KEY."
> }
> ```

---

## 2. Kamus Data & Nilai Status

### Nilai Status Kehadiran (`status`)
| Nilai Status | Keterangan |
| :--- | :--- |
| `hadir` | Karyawan hadir melakukan presensi masuk tepat waktu (masih dalam toleransi shift). |
| `terlambat` | Karyawan hadir melakukan presensi masuk melebihi batas toleransi shift (kolom `late_minutes` terisi menit keterlambatan). |
| `ditolak` | Upaya presensi ditolak oleh sistem karena melanggar aturan (misal: di luar radius GPS, terdeteksi Fake GPS, atau wajah tidak cocok). Alasan penolakan tertera di `rejection_reason`. |
| `menunggu_approval` | Presensi masuk dilakukan pada hari libur reguler tanpa jadwal tugas, memerlukan persetujuan manual admin/HR. |

### Penjelasan Field Utama
- **`employee.nip`**: Nomor Induk Pegawai / Kode Karyawan.
- **`clock_in.time`**: Waktu presensi masuk dalam format `HH:mm:ss` (WIB).
- **`clock_in.face_score`**: Skor kecocokan biometrik wajah (0.0000 s/d 1.0000). Standar kelulusan model ArcFace $\ge 0.68$.
- **`clock_in.distance_meters`**: Jarak fisik perangkat karyawan ke titik koordinat kantor dalam satuan meter.
- **`clock_in.is_mock_location`**: `true` jika karyawan terdeteksi menggunakan aplikasi GPS palsu (Fake GPS).
- **`durations.work_minutes`** & **`durations.work_hours`**: Durasi jam kerja efektif (selisih antara clock-in dan clock-out).
- **`durations.late_minutes`**: Jumlah menit keterlambatan dari jadwal masuk shift.

---

## 3. Daftar Endpoint

---

### 3.1. List Log Presensi (`GET /attendances`)
Mengambil daftar log presensi karyawan dengan filter yang sangat fleksibel dan paginasi otomatis.

- **Method:** `GET`
- **Path:** `/api/v1/integration/attendances`
- **Header:** `X-API-KEY: {API_KEY}`

#### Parameter Query (Opsional)
| Parameter | Tipe | Contoh | Deskripsi |
| :--- | :--- | :--- | :--- |
| `date` | `string` | `2026-09-14` | Ambil data presensi satu tanggal tertentu (`YYYY-MM-DD`). |
| `start_date` | `string` | `2026-09-01` | Tanggal awal rentang waktu (wajib bersama `end_date`). |
| `end_date` | `string` | `2026-09-30` | Tanggal akhir rentang waktu (wajib bersama `start_date`). |
| `month` | `integer` | `9` | Filter bulan (1 - 12). Wajib bersama `year`. |
| `year` | `integer` | `2026` | Filter tahun (YYYY). |
| `nip` | `string` | `199003152015041002` | Filter khusus satu NIP pegawai (bisa juga menggunakan `employee_code`). |
| `department` | `string` | `Sistem Informasi` | Pencarian berdasarkan unit kerja / prodi / divisi. |
| `status` | `string` | `hadir` | Filter status: `hadir`, `terlambat`, `ditolak`, `menunggu_approval`, atau alias `present`, `late`, `on_time`. |
| `per_page` | `integer` | `20` | Jumlah data per halaman (default: `20`, maksimum: `100`). |
| `page` | `integer` | `1` | Halaman yang ingin diambil. |

#### Contoh Request (cURL)
```bash
curl -X GET "http://localhost:8000/api/v1/integration/attendances?date=2026-09-14&status=hadir" \
  -H "X-API-KEY: indo_absen_sec_2026_x89a7f3d" \
  -H "Accept: application/json"
```

#### Contoh Respon (200 OK)
```json
{
  "success": true,
  "message": "Data absensi berhasil diambil.",
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total_records": 1,
    "total_pages": 1
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
        "radius_meters": 150.0
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

### 3.2. Detail Satu Log Presensi (`GET /attendances/{id}`)
Mengambil detail satu log presensi secara lengkap, termasuk data audit GPS, akurasi sinyal, approval status HR, dan catatan/alasan.

- **Method:** `GET`
- **Path:** `/api/v1/integration/attendances/{id}`
- **Header:** `X-API-KEY: {API_KEY}`

#### Parameter Path
| Parameter | Tipe | Contoh | Deskripsi |
| :--- | :--- | :--- | :--- |
| `id` | `integer` | `142` | ID unik dari baris log presensi. |

#### Contoh Request (cURL)
```bash
curl -X GET "http://localhost:8000/api/v1/integration/attendances/142" \
  -H "X-API-KEY: indo_absen_sec_2026_x89a7f3d" \
  -H "Accept: application/json"
```

#### Contoh Respon (200 OK)
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
      "radius_meters": 150.0
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
      "work_hours": 0.0,
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

#### Respon Jika ID Tidak Ditemukan (404 Not Found)
```json
{
  "success": false,
  "message": "Data absensi dengan ID 999999 tidak ditemukan."
}
```

---

### 3.3. Rekapitulasi Statistik Kehadiran (`GET /attendances/recap`)
Menghasilkan ringkasan data kehadiran bulanan atau tahunan. Sangat cocok untuk pembuatan slip gaji, laporan bulanan pimpinan, atau evaluasi kedisiplinan.

- **Method:** `GET`
- **Path:** `/api/v1/integration/attendances/recap`
- **Header:** `X-API-KEY: {API_KEY}`

#### Parameter Query (Opsional)
| Parameter | Tipe | Default | Deskripsi |
| :--- | :--- | :--- | :--- |
| `month` | `integer` | Bulan saat ini | Angka bulan (1 s/d 12). |
| `year` | `integer` | Tahun saat ini | Angka tahun (contoh: `2026`). |
| `nip` | `string` | *(Semua)* | Filter untuk melihat rekapitulasi satu individu pegawai tertentu. |
| `department` | `string` | *(Semua)* | Filter untuk melihat rekapitulasi satu unit kerja tertentu. |

#### Contoh Request (cURL)
```bash
curl -X GET "http://localhost:8000/api/v1/integration/attendances/recap?month=9&year=2026" \
  -H "X-API-KEY: indo_absen_sec_2026_x89a7f3d" \
  -H "Accept: application/json"
```

#### Contoh Respon (200 OK)
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
    },
    {
      "employee_id": 5,
      "nip": "198807202014022001",
      "name": "Siti Nurhaliza, S.T., M.Eng.",
      "department": "D3 Farmasi",
      "total_hadir": 21,
      "total_terlambat": 0,
      "total_tepat_waktu": 21,
      "total_menit_terlambat": 0,
      "total_jam_kerja": 168.0
    }
  ]
}
```

---

## 4. Contoh Implementasi Pemanggilan API

### A. JavaScript / TypeScript (Node.js & Frontend)
```javascript
const BASE_URL = 'http://localhost:8000/api/v1/integration';
const API_KEY = 'indo_absen_sec_2026_x89a7f3d';

async function getTodayAttendances() {
  const today = new Date().toISOString().split('T')[0]; // Format: YYYY-MM-DD
  
  const response = await fetch(`${BASE_URL}/attendances?date=${today}`, {
    headers: {
      'X-API-KEY': API_KEY,
      'Accept': 'application/json'
    }
  });

  const data = await response.json();
  if (data.success) {
    console.log(`Total presensi hari ini: ${data.meta.total_records}`);
    data.data.forEach(item => {
      console.log(`- ${item.employee.name} (${item.employee.nip}): ${item.status} [Masuk: ${item.clock_in?.time || '-'}]`);
    });
  }
}

getTodayAttendances();
```

---

### B. Python (Untuk Script Pengolahan Data / AI Integration)
```python
import requests

API_URL = "http://localhost:8000/api/v1/integration"
API_KEY = "indo_absen_sec_2026_x89a7f3d"

headers = {
    "X-API-KEY": API_KEY,
    "Accept": "application/json"
}

def get_employee_monthly_recap(nip: str, month: int, year: int):
    """Mengambil rekap kehadiran bulanan pegawai untuk perhitungan payroll/kehadiran."""
    params = {
        "nip": nip,
        "month": month,
        "year": year
    }
    response = requests.get(f"{API_URL}/attendances/recap", headers=headers, params=params)
    
    if response.status_code == 200:
        data = response.json()
        employees = data.get("employees", [])
        if employees:
            emp = employees[0]
            print(f"Pegawai: {emp['name']}")
            print(f"Total Hadir: {emp['total_hadir']} hari")
            print(f"Total Terlambat: {emp['total_terlambat']} kali ({emp['total_menit_terlambat']} menit)")
            print(f"Total Jam Kerja: {emp['total_jam_kerja']} jam")
            return emp
    else:
        print(f"Error {response.status_code}: {response.text}")
    return None

# Contoh pemanggilan:
get_employee_monthly_recap("199003152015041002", 9, 2026)
```

---

### C. PHP (cURL Native)
```php
<?php

$apiKey = 'indo_absen_sec_2026_x89a7f3d';
$url = 'http://localhost:8000/api/v1/integration/attendances?date=' . date('Y-m-d');

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-KEY: ' . $apiKey,
    'Accept: application/json'
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if ($result && $result['success']) {
    foreach ($result['data'] as $log) {
        echo "Nama: " . $log['employee']['name'] . " | Status: " . $log['status'] . "\n";
    }
}
```

---

## 5. Ringkasan Kode Error HTTP

| Kode Status | Keterangan & Solusi |
| :--- | :--- |
| `200 OK` | Permintaan berhasil diproses dan mengembalikan data yang diminta. |
| `401 Unauthorized` | Header `X-API-KEY` tidak disertakan atau nilai kunci salah. Periksa file `.env` server. |
| `404 Not Found` | Data spesifik dengan ID yang diminta tidak ada di database. |
| `500 Server Error` | Terjadi kesalahan internal pada server database/aplikasi. Periksa `storage/logs/laravel.log`. |
