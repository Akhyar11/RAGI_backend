# PegawaiKomponenGajiController

> **Modul**: SIMPEG / **Base URL**: /api/simpeg/payroll / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-09-19

Modul ini mengelola konfigurasi kustomisasi komponen gaji per pegawai secara nested sub-resource. Admin dan pengelola kepegawaian dapat menyesuaikan nominal khusus, mengaktifkan/menonaktifkan komponen tertentu untuk pegawai spesifik, serta menambahkan catatan SK/insentif.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/simpeg/payroll/pegawai-matriks` | Matriks seluruh pegawai & nilai komponen aktif (Tab 1) | ✅ Bearer |
| PATCH | `/api/simpeg/payroll/komponen/{id}/toggle-kolom` | Toggle switch visibilitas kolom komponen di Tab 1 | ✅ Bearer |
| PATCH | `/api/simpeg/payroll/pegawai/{pegawaiId}/terdampak-absensi` | Ubah status terdampak/bebas absensi pegawai (Tab 3) | ✅ Bearer |
| POST | `/api/simpeg/payroll/pegawai/batch-terdampak-absensi` | Bulk update status terdampak absensi pegawai | ✅ Bearer |
| GET | `/api/simpeg/payroll/pegawai/{pegawaiId}/komponen` | Ambil konfigurasi komponen gaji milik pegawai | ✅ Bearer |
| PUT | `/api/simpeg/payroll/pegawai/{pegawaiId}/komponen` | Simpan / perbarui konfigurasi komponen gaji pegawai | ✅ Bearer |

---

## 1. GET /api/simpeg/payroll/pegawai/{pegawaiId}/komponen

> Mengambil daftar konfigurasi komponen gaji fleksibel milik seorang pegawai tertentu.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters
*Tidak memerlukan query parameters.*

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Data konfigurasi komponen gaji pegawai berhasil diambil",
    "data": {
        "pegawai": {
            "id": 1,
            "nama_lengkap": "Dr. Budi Santoso",
            "nip": "198001012005011001"
        },
        "komponen": [
            {
                "komponen_gaji_id": 1,
                "kode": "GP",
                "nama": "Gaji Pokok",
                "jenis": "pendapatan",
                "tipe_nilai": "nominal_tetap",
                "nilai_default": 4500000,
                "nominal_kustom": 5000000,
                "is_active": true,
                "catatan": "Kenaikan berkala"
            }
        ]
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak akses melihat konfigurasi komponen pegawai."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Pegawai tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "pegawaiId": [
            "ID pegawai tidak valid."
        ]
    }
}
```

---

## 2. PUT /api/simpeg/payroll/pegawai/{pegawaiId}/komponen

> Menyimpan atau memperbarui konfigurasi kustomisasi komponen gaji seorang pegawai.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Query Parameters
*Tidak memerlukan query parameters.*

### Request Body

| Field | Type | Required | Validasi | Deskripsi |
|---|---|---|---|---|
| `komponen` | array | ✅ | `required|array` | Daftar komponen kustom |
| `komponen.*.komponen_gaji_id` | integer | ✅ | `required|exists:simpeg_master_komponen_gaji,id` | ID master komponen gaji |
| `komponen.*.nominal_kustom` | numeric | ❌ | `nullable|numeric|min:0` | Nominal kustom khusus pegawai |
| `komponen.*.is_active` | boolean | ❌ | `boolean` | Status keaktifan komponen |
| `komponen.*.catatan` | string | ❌ | `nullable|string` | Catatan penyesuaian |

```json
{
    "komponen": [
        {
            "komponen_gaji_id": 1,
            "nominal_kustom": 5000000,
            "is_active": true,
            "catatan": "Kenaikan berkala"
        }
    ]
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Konfigurasi komponen gaji untuk Dr. Budi Santoso berhasil disimpan.",
    "data": {
        "id": 1,
        "nama_lengkap": "Dr. Budi Santoso",
        "nip": "198001012005011001",
        "email": "budi.santoso@kampus.ac.id",
        "status_pegawai": "tetap",
        "created_at": "2026-07-28T14:00:00.000000Z",
        "updated_at": "2026-09-19T08:15:00.000000Z"
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki hak akses mengatur komponen gaji pegawai."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Pegawai tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "komponen": [
            "Bidang komponen wajib diisi."
        ]
    }
}
```

---

---

## 3. GET /api/simpeg/payroll/pegawai-matriks

> Mengambil daftar seluruh pegawai beserta nominal komponen gaji aktif dalam bentuk matriks terstruktur.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Tipe | Keterangan |
|---|---|---|
| `search` | string | Pencarian nama atau NIP pegawai |
| `unit_kerja_id` | integer | Filter berdasarkan unit kerja |
| `jenis_pegawai` | string | Filter jenis pegawai (dosen, tendik, magang, dll.) |
| `terdampak_absensi` | boolean | Filter status terdampak absensi (`true` / `false`) |
| `page` | integer | Nomor halaman (default: 1) |
| `per_page` | integer | Jumlah item per halaman (default: 15, max: 100) |
| `sort_by` | string | Kolom pengurutan (`nama_lengkap`, `nip`, `id`, `created_at` - default: `nama_lengkap`) |
| `sort_order` | string | Arah pengurutan (`asc` atau `desc` - default: `asc`) |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Matriks komponen gaji pegawai berhasil dimuat",
    "data": {
        "pegawai": [
            {
                "id": 1,
                "nip": "198001012005011001",
                "nama_lengkap": "Dr. Budi Santoso",
                "unit_kerja": "Fakultas Teknik",
                "jenis_pegawai": "dosen",
                "status_kepegawaian": "tetap_yayasan",
                "is_active": true,
                "terdampak_absensi": true,
                "komponen_values": {
                    "GAJI_POKOK": {
                        "komponen_id": 1,
                        "kode": "GAJI_POKOK",
                        "nama": "Gaji Pokok",
                        "jenis": "pendapatan",
                        "nominal": 5000000,
                        "is_custom": true,
                        "is_active": true
                    }
                }
            }
        ],
        "active_columns": [
            {
                "id": 1,
                "kode": "GAJI_POKOK",
                "nama": "Gaji Pokok",
                "jenis": "pendapatan",
                "is_kolom_aktif": true
            }
        ]
    },
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1
    }
}
```

### Response Error

**400 Bad Request**
```json
{
    "status": "error",
    "message": "Parameter permintaan tidak valid."
}
```

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
    "message": "Anda tidak memiliki hak akses melihat matriks komponen gaji pegawai."
}
```

---

## 4. PATCH /api/simpeg/payroll/komponen/{id}/toggle-kolom

> Mengubah visibilitas kolom komponen gaji untuk tampil atau tidak di Tab 1.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Visibilitas kolom 'Gaji Pokok' berhasil diubah menjadi Aktif.",
    "data": {
        "id": 1,
        "nama": "Gaji Pokok",
        "is_kolom_aktif": true
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
    "message": "Anda tidak memiliki hak akses mengubah kolom komponen gaji."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Komponen gaji tidak ditemukan."
}
```

---

## 5. PATCH /api/simpeg/payroll/pegawai/{pegawaiId}/terdampak-absensi

> Mengubah status pegawai apakah terdampak atau bebas dari potongan/insentif absensi pada penggajian.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "terdampak_absensi": false
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Status absensi pegawai Dr. Budi Santoso diubah menjadi Bebas Absensi (Tidak Terdampak).",
    "data": {
        "id": 1,
        "nama_lengkap": "Dr. Budi Santoso",
        "terdampak_absensi": false
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
    "message": "Anda tidak memiliki hak akses mengubah status terdampak absensi pegawai."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Pegawai tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "terdampak_absensi": [
            "Bidang terdampak_absensi wajib diisi."
        ]
    }
}
```

---

## 6. POST /api/simpeg/payroll/pegawai/batch-terdampak-absensi

> Memperbarui status terdampak absensi secara massal (bulk) untuk banyak pegawai sekaligus.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "pegawai_ids": [1, 2, 3],
    "terdampak_absensi": false
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Berhasil memperbarui status 3 pegawai menjadi Bebas Absensi.",
    "data": {
        "updated_count": 3,
        "status": "Bebas Absensi"
    }
}
```

### Response Error

**400 Bad Request**
```json
{
    "status": "error",
    "message": "Permintaan batch tidak valid."
}
```

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
    "message": "Anda tidak memiliki hak akses mengubah status absensi pegawai."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Satu atau lebih data pegawai tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "pegawai_ids": [
            "Bidang pegawai_ids wajib diisi."
        ],
        "terdampak_absensi": [
            "Bidang terdampak_absensi wajib diisi."
        ]
    }
}
```

---

## Catatan Khusus & Integritas Data
- Konfigurasi komponen tersimpan pada tabel `simpeg_pegawai_komponen_gaji`.
- Kolom visibilitas tersimpan pada tabel `simpeg_master_komponen_gaji.is_kolom_aktif`.
- Status dampak presensi tersimpan pada tabel `simpeg_pegawai.terdampak_absensi`.
- Perubahan komponen gaji dipantau oleh `PegawaiKomponenGajiObserver` untuk pencatatan audit log perubahan nilai sensitif.
- **Soft Delete**: Data riwayat komponen dan relasi master dilindungi untuk menjamin jejak audit keuangan/kepegawaian.
- **Password & Token**: Password, hashed password, dan token autentikasi tidak pernah dikembalikan dalam response API ini.
