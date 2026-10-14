# PaketKrsController

> **Modul**: SIAKAD  
> **Base URL**: `/api/v1/siakad/perkuliahan`  
> **Autentikasi**: Bearer Token (Sanctum) — kecuali dinyatakan lain  
> **Dibuat**: 2026-10-14  
> **Diperbarui**: 2026-10-14

Paket MK per rombel (pengganti kecocokan nama `nm_kls` legacy): BAAK/Kaprodi menetapkan daftar MK yang disediakan untuk satu rombel (cth. 24A), mahasiswa mengambil 1-klik via `ambil-paket` dengan seluruh validasi KRS tetap berlaku (SKS/IP, prasyarat, bentrok, duplikat, kuota, gate keuangan + hak akses). Termasuk gate hak akses KRS per mahasiswa per periode (pengganti `wsia_hakakses` legacy; tanpa baris = terbuka).

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/siakad/perkuliahan/paket-mk` | Daftar paket MK rombel | ✅ `siakad.krs.read` |
| POST | `/api/v1/siakad/perkuliahan/paket-mk` | Tambah MK ke paket rombel | ✅ `siakad.kelas.manage` |
| POST | `/api/v1/siakad/perkuliahan/paket-mk/bulk-sync` | Sinkronisasi massal isi paket satu rombel | ✅ `siakad.kelas.manage` |
| GET | `/api/v1/siakad/perkuliahan/paket-mk/{id}` | Detail paket | ✅ `siakad.krs.read` |
| PUT | `/api/v1/siakad/perkuliahan/paket-mk/{id}` | Perbarui paket | ✅ `siakad.kelas.manage` |
| DELETE | `/api/v1/siakad/perkuliahan/paket-mk/{id}` | Hapus MK dari paket | ✅ `siakad.kelas.manage` |
| GET | `/api/v1/siakad/perkuliahan/krs/paket-saya` | Pratinjau paket rombel saya | ✅ Mahasiswa / `mahasiswa_id` |
| POST | `/api/v1/siakad/perkuliahan/krs/ambil-paket` | Ambil paket 1-klik | ✅ `siakad.krs.create` |
| GET | `/api/v1/siakad/perkuliahan/krs/hakakses` | Daftar hak akses KRS | ✅ `siakad.krs.approve` |
| POST | `/api/v1/siakad/perkuliahan/krs/hakakses` | Buka/kunci akses satu mahasiswa | ✅ `siakad.krs.approve` |
| POST | `/api/v1/siakad/perkuliahan/krs/hakakses-rombel` | Buka/kunci akses massal satu rombel | ✅ `siakad.krs.approve` |

---

## [GET] /api/v1/siakad/perkuliahan/paket-mk

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters (untuk GET dengan filter)

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `master_kelas_id` | integer | ❌ | — | Filter rombel (`siakad_master_kelas`) |
| `tahun_akademik_id` | integer | ❌ | — | Filter periode (termasuk paket global `null`) |
| `is_wajib` | boolean | ❌ | — | Filter MK wajib/pilihan |
| `search` | string | ❌ | — | Cari nama/kode MK |
| `sort_by` | string | ❌ | `semester_anjuran` | `semester_anjuran`, `created_at`, `id` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Paket mata kuliah rombel berhasil dimuat",
    "data": [
        {
            "id": 1,
            "master_kelas_id": 4,
            "mata_kuliah_id": 12,
            "tahun_akademik_id": null,
            "semester_anjuran": 3,
            "is_wajib": true,
            "master_kelas": { "id": 4, "nama_kelas": "24A" },
            "mata_kuliah": { "id": 12, "kode_mk": "IF201", "nama": "Basis Data" }
        }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1 }
}
```

---

## [GET] /api/v1/siakad/perkuliahan/paket-mk/{id}

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Detail paket mata kuliah berhasil dimuat",
    "data": { "id": 1, "master_kelas_id": 4, "mata_kuliah_id": 12 }
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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "PaketMkRombel tidak ditemukan."
}
```

---

## [POST] /api/v1/siakad/perkuliahan/paket-mk

### Request Body

```json
{
    "master_kelas_id": "integer, required, exists:siakad_master_kelas,id",
    "mata_kuliah_id": "integer, required, exists:siakad_mata_kuliah,id",
    "tahun_akademik_id": "integer, nullable, exists:siakad_tahun_akademik,id (null = berlaku semua periode)",
    "semester_anjuran": "integer, nullable, 1-14",
    "is_wajib": "boolean, nullable, default true"
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Mata kuliah berhasil ditambahkan ke paket rombel",
    "data": { "id": 1, "master_kelas_id": 4, "mata_kuliah_id": 12 }
}
```

---

## [POST] /api/v1/siakad/perkuliahan/paket-mk/bulk-sync

Mengganti seluruh isi paket satu rombel (+periode opsional) dengan daftar dari UI centang. Baris yang tidak ada di daftar akan dihapus.

### Request Body

```json
{
    "master_kelas_id": "integer, required, exists:siakad_master_kelas,id",
    "tahun_akademik_id": "integer, nullable, exists:siakad_tahun_akademik,id",
    "items": [
        { "mata_kuliah_id": 12, "semester_anjuran": 3, "is_wajib": true }
    ]
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Paket rombel disinkronkan (6 MK, 1 dihapus)",
    "data": [...]
}
```

---

## [GET] /api/v1/siakad/perkuliahan/krs/paket-saya

Pratinjau paket untuk rombel mahasiswa login (atau `mahasiswa_id` untuk BAAK/PA).

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `mahasiswa_id` | integer | ❌ | login | Lihat paket mahasiswa lain (BAAK/PA) |
| `tahun_akademik_id` | integer | ❌ | aktif | Periode paket |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Paket mata kuliah rombel berhasil dimuat",
    "data": {
        "rombel": { "id": 4, "nama_kelas": "24A" },
        "is_krs_dibuka": true,
        "items": [...]
    }
}
```

---

## [POST] /api/v1/siakad/perkuliahan/krs/ambil-paket

### Request Body

```json
{
    "mahasiswa_id": "integer, nullable, exists:siakad_mahasiswa,id",
    "tahun_akademik_id": "integer, nullable, exists:siakad_tahun_akademik,id"
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Pengambilan paket selesai: 5 berhasil, 1 gagal, 0 dilewati",
    "data": {
        "rombel": { "id": 4, "nama_kelas": "24A" },
        "hasil": {
            "berhasil": [{ "mk": "IF201 — Basis Data", "kelas": "Basis Data (Kelas A)" }],
            "gagal": [{ "mk": "IF202 — Jaringan", "alasan": "Kuota kelas ini sudah penuh." }],
            "dilewati": [{ "mk": "MKU101 — Agama", "alasan": "Sudah lulus/di konversi" }]
        }
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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Akses KRS Anda untuk periode ini belum dibuka/dikunci oleh BAAK. Hubungi bagian akademik."
}
```

```json
{
    "status": "error",
    "message": "Belum ada paket mata kuliah untuk rombel 24A pada periode ini."
}
```

---

## [GET] /api/v1/siakad/perkuliahan/krs/hakakses

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tahun_akademik_id` | integer | ❌ | — | Filter periode |
| `is_dibuka` | boolean | ❌ | — | Filter status akses |
| `search` | string | ❌ | — | Cari nama/NIM |
| `sort_by` | string | ❌ | `created_at` | `created_at`, `updated_at`, `id` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data hak akses KRS berhasil dimuat",
    "data": [
        { "id": 1, "mahasiswa_id": 5, "is_dibuka": true }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1 }
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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

---

## [POST] /api/v1/siakad/perkuliahan/krs/hakakses

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "mahasiswa_id": "integer, required, exists:siakad_mahasiswa,id",
    "tahun_akademik_id": "integer, required, exists:siakad_tahun_akademik,id",
    "is_dibuka": "boolean, required"
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Akses KRS dibuka.",
    "data": { "id": 1, "mahasiswa_id": 5, "is_dibuka": true }
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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "is_dibuka": ["Status akses wajib diisi."]
    }
}
```

---

## [POST] /api/v1/siakad/perkuliahan/krs/hakakses-rombel

Buka/kunci akses massal seluruh mahasiswa aktif satu rombel.

### Request Body

```json
{
    "master_kelas_id": "integer, required, exists:siakad_master_kelas,id",
    "tahun_akademik_id": "integer, required, exists:siakad_tahun_akademik,id",
    "is_dibuka": "boolean, required"
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Akses KRS dibuka untuk 42 mahasiswa rombel.",
    "data": { "dibuka": 42, "dikunci": 0, "total": 42 }
}
```

---

## [PUT] /api/v1/siakad/perkuliahan/paket-mk/{id}

Update hanya `tahun_akademik_id`, `semester_anjuran`, `is_wajib` (rombel + MK terkunci sebagai identitas paket).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "tahun_akademik_id": "integer, nullable, exists:siakad_tahun_akademik,id",
    "semester_anjuran": "integer, nullable, 1-14",
    "is_wajib": "boolean, nullable"
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Paket mata kuliah berhasil diperbarui",
    "data": { "id": 1, "is_wajib": false }
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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "PaketMkRombel tidak ditemukan."
}
```

---

## [DELETE] /api/v1/siakad/perkuliahan/paket-mk/{id}

Menghapus baris paket (hard delete, tercatat audit log).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Mata kuliah berhasil dihapus dari paket rombel",
    "data": null
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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "PaketMkRombel tidak ditemukan."
}
```

### Catatan Tambahan

> - Seluruh tulis paket & hak akses tercatat di `audit_logs` via `PaketMkRombelObserver` / `HakaksesKrsObserver` (modul `SIAKAD`).
> - `KrsService::addClass` kini memeriksa gate hak akses: baris `is_dibuka=false` menolak tambah kelas individual maupun ambil paket.
> - Resolusi kelas paket mengutamakan kelas yang menempelkan rombel mahasiswa (`siakad_kelas_rombel`), lalu yang masih berkuota.
