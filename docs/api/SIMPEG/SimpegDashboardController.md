# SimpegDashboardController

> **Modul**: SIMPEG (Sistem Informasi Kepegawaian)  
> **Base URL**: `/api/simpeg`  
> **Autentikasi**: Bearer Token (Passport)  
> **Dibuat**: 2026-09-15  
> **Diperbarui**: 2026-10-21  

Controller ini menyajikan metrik statistik, ringkasan data kepegawaian secara *real-time*, dan visual analitik 2 Tab Dashboard (Kedisiplinan & Presensi serta Kualifikasi & Demografi SDM).

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth / Permission |
|---|---|---|---|
| GET | `/api/simpeg/dashboard-stats` | Statistik real-time KPI kepegawaian & pegawai terkini | ✅ Login User (`simpeg.dashboard.read`) |
| GET | `/api/simpeg/analytics/presensi` | Analitik kedisiplinan & presensi 12 bulan (Tab 1) | ✅ Login User (`simpeg.dashboard.read`) |
| GET | `/api/simpeg/analytics/demografi` | Analitik kualifikasi, JAD, serdos & usia (Tab 2) | ✅ Login User (`simpeg.dashboard.read`) |

---

## GET /api/simpeg/dashboard-stats

> Mengambil metrik data kepegawaian terkini langsung dari query database secara dinamis.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Success (200 OK)

```json
{
  "status": "success",
  "data": {
    "total_pegawai": 205,
    "total_dosen": 142,
    "total_tendik": 63,
    "total_unit_kerja": 12,
    "recent_pegawai": [
      {
        "id": 511,
        "nama_lengkap": "YULITA MAULANI",
        "nip": null,
        "nidn": "0601079801",
        "jenis_pegawai": "dosen",
        "unit_kerja": {
          "id": 5,
          "nama": "S1 Teknik Informatika",
          "kode": "IF"
        },
        "roles": [
          {
            "id": 5,
            "name": "Dosen Pengajar",
            "slug": "dosen"
          }
        ]
      }
    ]
  }
}
```

### Response Error

#### 401 Unauthorized
```json
{
  "status": "error",
  "message": "Unauthenticated."
}
```

#### 403 Forbidden
```json
{
  "status": "error",
  "message": "Anda tidak memiliki hak akses untuk melihat statistik dashboard SIMPEG."
}
```

---

## GET /api/simpeg/analytics/presensi

> Mengambil data analitik kedisiplinan dan kehadiran pegawai 12 bulan untuk panel Tab 1 Dashboard SIMPEG (Tren Kehadiran, Tren Penerbitan SP, dan Top 5 Unit Kerja Disiplin vs Rawan).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tahun` | integer | ❌ | Tahun saat ini | Tahun periode analitik yang dihitung (misal: 2026) |

### Response Success (200 OK)

```json
{
  "status": "success",
  "message": "Data analitik presensi dan kedisiplinan berhasil diambil",
  "data": {
    "tahun": 2026,
    "summary": {
      "total_kehadiran": 5549,
      "total_tepat_waktu": 4920,
      "total_terlambat": 530,
      "total_sp": 73,
      "tingkat_disiplin_persen": 88.7
    },
    "monthly_attendance": [
      {
        "month_code": "01",
        "month_name": "Jan",
        "tepat_waktu": 410,
        "terlambat": 45,
        "izin_sakit_cuti": 8,
        "alfa": 2,
        "total_kehadiran": 465
      }
    ],
    "monthly_sp": [
      {
        "month_code": "01",
        "month_name": "Jan",
        "sp_1": 4,
        "sp_2": 1,
        "sp_3": 0,
        "total_sp": 5
      }
    ],
    "top_unit_kerja": {
      "disiplin": [
        {
          "unit_kerja_id": 1,
          "unit_kerja_nama": "Rektorat & Pimpinan",
          "total_presensi": 250,
          "total_tepat_waktu": 245,
          "total_terlambat": 5,
          "persentase_disiplin": 98.0
        }
      ],
      "rawan": [
        {
          "unit_kerja_id": 4,
          "unit_kerja_nama": "Fakultas Teknik",
          "total_presensi": 420,
          "total_tepat_waktu": 340,
          "total_terlambat": 80,
          "persentase_disiplin": 81.0
        }
      ]
    }
  }
}
```

### Response Error

#### 401 Unauthorized
```json
{
  "status": "error",
  "message": "Unauthenticated."
}
```

#### 403 Forbidden
```json
{
  "status": "error",
  "message": "Anda tidak memiliki hak akses untuk melihat analitik presensi SIMPEG."
}
```

---

## GET /api/simpeg/analytics/demografi

> Mengambil data analitik kualifikasi akademik dan demografi pegawai untuk panel Tab 2 Dashboard SIMPEG (Piramida JAD Dosen, Rasio Kelulusan Serdos, dan Distribusi Usia/Pensiun).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Success (200 OK)

```json
{
  "status": "success",
  "message": "Data analitik demografi dan kualifikasi SDM berhasil diambil",
  "data": {
    "jad_pyramid": [
      {
        "level": "guru_besar",
        "label": "Guru Besar / Profesor",
        "order": 5,
        "count": 4,
        "percentage": 2.8,
        "color": "#8b5cf6"
      },
      {
        "level": "lektor_kepala",
        "label": "Lektor Kepala",
        "order": 4,
        "count": 18,
        "percentage": 12.7,
        "color": "#3b82f6"
      },
      {
        "level": "lektor",
        "label": "Lektor",
        "order": 3,
        "count": 42,
        "percentage": 29.6,
        "color": "#06b6d4"
      },
      {
        "level": "asisten_ahli",
        "label": "Asisten Ahli",
        "order": 2,
        "count": 55,
        "percentage": 38.7,
        "color": "#10b981"
      },
      {
        "level": "tenaga_pengajar",
        "label": "Tenaga Pengajar (Belum JAD)",
        "order": 1,
        "count": 23,
        "percentage": 16.2,
        "color": "#94a3b8"
      }
    ],
    "serdos_ratio": {
      "total_dosen": 142,
      "tersertifikasi": 98,
      "belum_tersertifikasi": 44,
      "persentase": 69.0
    },
    "age_distribution": [
      {
        "group": "under_30",
        "label": "< 30 Tahun",
        "sublabel": "Generasi Baru",
        "count": 32,
        "percentage": 15.6,
        "color": "#0ea5e9",
        "is_mpp": false
      },
      {
        "group": "30_39",
        "label": "30 - 39 Tahun",
        "sublabel": "Produktif Awal",
        "count": 78,
        "percentage": 38.0,
        "color": "#10b981",
        "is_mpp": false
      },
      {
        "group": "40_49",
        "label": "40 - 49 Tahun",
        "sublabel": "Produktif Madya",
        "count": 54,
        "percentage": 26.3,
        "color": "#f59e0b",
        "is_mpp": false
      },
      {
        "group": "50_59",
        "label": "50 - 59 Tahun",
        "sublabel": "Senior",
        "count": 30,
        "percentage": 14.6,
        "color": "#6366f1",
        "is_mpp": false
      },
      {
        "group": "60_plus",
        "label": "≥ 60 Tahun",
        "sublabel": "Masa Pensiun (MPP)",
        "count": 11,
        "percentage": 5.4,
        "color": "#ef4444",
        "is_mpp": true
      }
    ],
    "summary": {
      "total_dosen": 142,
      "total_pegawai_terdata": 205,
      "persentase_dosen_ber_jad": 83.8,
      "total_mpp_pensiun": 11
    }
  }
}
```

### Response Error

#### 401 Unauthorized
```json
{
  "status": "error",
  "message": "Unauthenticated."
}
```

#### 403 Forbidden
```json
{
  "status": "error",
  "message": "Anda tidak memiliki hak akses untuk melihat analitik demografi SIMPEG."
}
```
