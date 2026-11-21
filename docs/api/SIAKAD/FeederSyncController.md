# FeederSyncController

> **Modul**: SIAKAD  
> **Base URL**: `/api/v1/siakad/feeder-sync`  
> **Autentikasi**: Bearer Token (Sanctum/Passport)  
> **Dibuat**: 2026-08-25  
> **Diperbarui**: 2026-09-14

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/siakad/feeder-sync/config` | Mengambil konfigurasi koneksi Neo Feeder (URL & Username, password masked) | ✅ Admin |
| POST | `/api/v1/siakad/feeder-sync/config` | Menyimpan konfigurasi URL, username, dan password Neo Feeder | ✅ Admin |
| GET | `/api/v1/siakad/feeder-sync/token` | Mendapatkan token autentikasi aktif dari Neo Feeder | ✅ Admin |
| POST | `/api/v1/siakad/feeder-sync/trigger` | Menjalankan proses sinkronisasi/tarik data per entitas | ✅ Admin |
| GET | `/api/v1/siakad/feeder-sync/logs` | Riwayat log eksekusi sinkronisasi (paginated) | ✅ Admin |
| GET | `/api/v1/siakad/feeder-sync/mappings` | Pemetaan ID Lokal dengan ID Feeder PDDikti | ✅ Admin |

---

## GET /api/v1/siakad/feeder-sync/config

> Mengambil konfigurasi koneksi Neo Feeder saat ini.

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
        "url": "http://10.211.55.3:8082/ws/live2.php",
        "username": "admin",
        "password": "******"
    }
}
```

---

## POST /api/v1/siakad/feeder-sync/config

> Menyimpan konfigurasi koneksi Neo Feeder.

### Headers
| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body
```json
{
    "url": "http://10.211.55.3:8082/ws/live2.php",
    "username": "admin",
    "password": "secretpassword"
}
```

---

## GET /api/v1/siakad/feeder-sync/token

> Mendapatkan token autentikasi aktif dari Web Service Neo Feeder PDDikti secara STRICT.

### Headers
| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Berhasil terhubung ke Web Service Neo Feeder",
    "data": {
        "token": "79b389f4284d72851a6659c235ba92b1"
    }
}
```

### Response Gagal / Offline (503 Service Unavailable)
```json
{
    "status": "error",
    "message": "Gagal terhubung ke Web Service Neo Feeder: Connection refused"
}
```

---

## POST /api/v1/siakad/feeder-sync/trigger

> Memulai proses sinkronisasi atau penarikan data antara SIAKAD lokal dan Neo Feeder PDDikti.

### Headers
| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body
```json
{
    "entity_type": "pull_dosen"
}
```

**Pilihan `entity_type` yang didukung:**
- `pull_dosen`: Menarik (PULL) daftar dosen resmi dari Neo Feeder (`GetListDosen`), memperbarui `id_feeder` & NIDN, atau membuat record dosen baru jika belum ada tanpa menghapus/menimpa NIP lokal.
- `dosen`: Mencocokkan data dosen lokal yang memiliki NIDN ke Neo Feeder (`DetailBiodataDosen`). Dosen ber-NIP lokal tanpa NIDN otomatis di-skip secara aman.
- `penugasan_dosen`: Menarik penugasan dosen PT dari Feeder (`GetListPenugasanDosen`) untuk mendapatkan `id_registrasi_dosen` per tahun ajaran.
- `ajar_dosen`: Mengirimkan aktivitas ajar dosen ke kelas kuliah via `InsertDosenPengajarKelasKuliah` menggunakan `id_registrasi_dosen`. Dosen yang belum memiliki penugasan di-skip secara aman.
- `mahasiswa`: Sinkronisasi batch mahasiswa.
- `biodata_mahasiswa`: Sinkronisasi biodata mahasiswa.
- `riwayat_pendidikan_mahasiswa`: Sinkronisasi riwayat pendidikan mahasiswa.
- `mata_kuliah`: Sinkronisasi data kurikulum dan mata kuliah.
- `kelas`: Sinkronisasi data kelas perkuliahan dan nilai.
- `prodi`: Pull daftar prodi menggunakan `GetProdi`; nilai UUID `id_prodi` dipetakan ke `siakad_program_studi.id_feeder`. Pull ini juga tersedia pada Pengaturan Integrasi API → Migrasi Program Studi dengan sumber PDDIKTI. Gunakan salah satu jalur tersebut untuk mengisi mapping prodi sebelum impor kurikulum dan mata kuliah.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Sinkronisasi pull_dosen ke Neo Feeder PDDikti selesai",
    "data": {
        "id": 12,
        "entity_type": "pull_dosen",
        "sync_type": "pull",
        "total_records": 10,
        "success_count": 10,
        "failed_count": 0,
        "status": "success",
        "details": [
            {
                "nama_dosen": "Dr. Budi Santoso, M.Kom",
                "nidn": "0012345678",
                "status": "created"
            }
        ]
    }
}
```

---

## GET /api/v1/siakad/feeder-sync/logs

> Menampilkan riwayat log aktivitas sinkronisasi Neo Feeder.

### Headers
| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `page` | integer | ❌ | `1` | Nomor halaman yang ditampilkan |
| `per_page` | integer | ❌ | `15` | Jumlah entri per halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "entity_type": "pull_dosen",
            "sync_type": "pull",
            "total_records": 100,
            "success_count": 100,
            "failed_count": 0,
            "status": "success",
            "details": [
                {
                    "nama_dosen": "Dr. Budi Santoso, M.Kom",
                    "nidn": "0012345678",
                    "status": "created"
                }
            ]
        }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 1 }
}
```

---

## GET /api/v1/siakad/feeder-sync/mappings

> Menampilkan pemetaan ID entitas lokal SIAKAD dengan ID Feeder PDDikti.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `entity_type` | string | ❌ | — | Filter jenis entitas, misalnya `dosen`, `kelas`, `mahasiswa`, `mata_kuliah`, `prodi`, atau `semester`. |
| `sync_status` | string | ❌ | — | Filter status: `synced`, `pending`, atau `failed`. |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman. |
| `page` | integer | ❌ | `1` | Nomor halaman. |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "entity_type": "semester",
            "local_id": 3,
            "feeder_id": "20261",
            "sync_status": "synced",
            "last_synced_at": "2026-11-21T10:00:00.000000Z"
        }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 1 }
}
```
