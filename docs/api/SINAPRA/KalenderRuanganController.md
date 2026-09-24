# Dokumentasi API: KalenderRuanganController

Controller ini menangani agregasi data visual kalender dan timeline ketersediaan ruangan kampus, menggabungkan agenda peminjaman dari modul **SINAPRA** dengan jadwal perkuliahan semester aktif dari modul **SIAKAD**.

- **Base URL:** `/api/sinapra/kalender-ruangan`
- **Autentikasi:** Bearer Token (JWT / Sanctum via `auth:api`)
- **Prefix:** `/sinapra`

---

## Daftar Endpoint

### 1. Mengambil Jadwal Ketersediaan Ruangan Terpadu

Mengambil seluruh event peminjaman ruangan dan perkuliahan yang terjadwal dalam rentang tanggal tertentu, dengan opsi filter per ruangan, gedung, atau sumber data.

- **Method:** `GET`
- **URL:** `/api/sinapra/kalender-ruangan`
- **Headers:**
  - `Authorization: Bearer <token>`
  - `Accept: application/json`

#### Query Parameters

| Parameter | Tipe | Wajib | Deskripsi |
|---|---|---|---|
| `start_date` | `string (date)` | Tidak | Tanggal awal rentang (format `YYYY-MM-DD`). Default: awal minggu ini. |
| `end_date` | `string (date)` | Tidak | Tanggal akhir rentang (format `YYYY-MM-DD`). Default: akhir minggu ini. |
| `ruangan_id` | `integer` | Tidak | ID entitas ruangan (`sinapra_ruangan.id`) untuk filter jadwal ruangan spesifik. |
| `gedung_id` | `integer` | Tidak | ID entitas gedung (`sinapra_gedung.id`) untuk filter ruangan di gedung tertentu. |
| `source` | `string` | Tidak | Sumber data jadwal: `semua` (default), `sinapra`, atau `siakad`. |

#### Response Berhasil (HTTP 200)

```json
{
  "success": true,
  "message": "Data kalender ketersediaan ruangan berhasil diambil",
  "data": [
    {
      "id": "siakad-12-2026-09-28",
      "raw_id": 12,
      "source": "siakad",
      "ruangan_id": 3,
      "ruangan_nama": "Lab Komputer Multimedia",
      "gedung_nama": "Gedung Perkuliahan Terpadu A",
      "title": "Pemrograman Web Lanjut (TI-301)",
      "tanggal": "2026-09-28",
      "jam_mulai": "08:00",
      "jam_selesai": "10:30",
      "penanggung_jawab": "Teknik Informatika",
      "tipe": "perkuliahan",
      "status": "terjadwal",
      "badge_label": "Kuliah SIAKAD",
      "catatan": "Jadwal Reguler Semester Aktif"
    },
    {
      "id": "sinapra-5",
      "raw_id": 5,
      "source": "sinapra",
      "ruangan_id": 3,
      "ruangan_nama": "Lab Komputer Multimedia",
      "gedung_nama": "Gedung Perkuliahan Terpadu A",
      "title": "Workshop Machine Learning Komunitas Coding",
      "tanggal": "2026-09-28",
      "jam_mulai": "13:00",
      "jam_selesai": "16:00",
      "penanggung_jawab": "Ahmad Fauzi",
      "tipe": "peminjaman",
      "status": "disetujui",
      "badge_label": "Peminjaman SINAPRA",
      "catatan": "Disetujui Laboran & Sarpras"
    }
  ],
  "meta": {
    "start_date": "2026-09-28",
    "end_date": "2026-10-04",
    "total_events": 2
  }
}
```

#### Response Validasi Gagal (HTTP 422)

```json
{
  "success": false,
  "message": "Format tanggal selesai harus YYYY-MM-DD.",
  "errors": {
    "end_date": [
      "Format tanggal selesai harus YYYY-MM-DD."
    ]
  }
}
```
