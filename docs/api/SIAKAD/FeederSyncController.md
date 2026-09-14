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

### Query Parameters
| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `per_page` | integer | ❌ | `10` | Jumlah data per halaman |
| `page` | integer | ❌ | `1` | Nomor halaman |

---

## GET /api/v1/siakad/feeder-sync/mappings

> Menampilkan pemetaan ID entitas lokal SIAKAD dengan ID Feeder PDDikti.

### Query Parameters
| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `entity_type` | string | ❌ | — | Filter jenis entitas (dosen, kelas, mahasiswa, dll.) |
| `sync_status` | string | ❌ | — | Filter status (synced, pending, failed) |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman |
| `page` | integer | ❌ | `1` | Nomor halaman |
