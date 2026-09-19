# SettingTarifController

> **Modul**: SIKEU — Keuangan  
> **Base URL**: `/api/v1/sikeu/master/setting-tarif`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 10 September 2026  
> **Diperbarui**: 10 September 2026

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/master/setting-tarif` | List setting tarif (paginated, filtered) | ✅ operator_sikeu, kabag_keuangan |
| POST | `/api/v1/sikeu/master/setting-tarif` | Buat setting tarif baru | ✅ operator_sikeu, kabag_keuangan |
| PUT | `/api/v1/sikeu/master/setting-tarif/{id}` | Update setting tarif | ✅ operator_sikeu, kabag_keuangan |
| DELETE | `/api/v1/sikeu/master/setting-tarif/{id}` | Hapus setting tarif | ✅ kabag_keuangan |

---

## GET /api/v1/sikeu/master/setting-tarif

> Mengambil daftar setting tarif biaya per angkatan/prodi/semester/jalur kelas, dengan filter dan pagination.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tahun_angkatan` | integer | ❌ | — | Filter per tahun angkatan |
| `program_studi_id` | integer | ❌ | — | Filter per program studi |
| `semester` | integer | ❌ | — | Filter per semester (1-14) |
| `jalur_kelas` | string | ❌ | — | Filter per jalur (Reguler/Karyawan/Internasional) |
| `is_active` | boolean | ❌ | — | Filter aktif/nonaktif |
| `search` | string | ❌ | — | Cari berdasarkan keterangan atau nama master biaya |
| `sort_by` | string | ❌ | `id` | Kolom pengurutan: id, created_at, tahun_angkatan, semester, nominal |
| `sort_order` | string | ❌ | `desc` | Arah urutan: asc / desc |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data setting tarif berhasil dimuat",
    "data": [
        {
            "id": 1,
            "master_biaya_id": 1,
            "tahun_angkatan": 2025,
            "program_studi_id": null,
            "semester": null,
            "jalur_kelas": "Reguler",
            "nominal": 3500000.00,
            "is_active": true,
            "keterangan": "UKT Reguler Angkatan 2025",
            "created_by": 1,
            "created_at": "2026-09-10T14:00:00.000000Z",
            "updated_at": "2026-09-10T14:00:00.000000Z",
            "master_biaya": {
                "id": 1,
                "kode": "UKT_REG",
                "nama": "Uang Kuliah Tunggal Reguler",
                "tipe": "spp"
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 12,
        "last_page": 1,
        "from": 1,
        "to": 12
    },
    "filters": {
        "tahun_angkatan": "2025",
        "search": null,
        "sort_by": "id",
        "sort_order": "desc"
    }
}
```

---

## POST /api/v1/sikeu/master/setting-tarif

> Membuat setting tarif baru. Kombinasi (master_biaya + angkatan + prodi + semester + jalur) harus unik.

### Request Body

```json
{
    "master_biaya_id": 1,
    "tahun_angkatan": 2025,
    "program_studi_id": null,
    "semester": 3,
    "jalur_kelas": "Reguler",
    "nominal": 3500000,
    "is_active": true,
    "keterangan": "UKT Reguler Angkatan 2025"
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Setting tarif berhasil ditambahkan.",
    "data": {
        "id": 2,
        "master_biaya_id": 1,
        "tahun_angkatan": 2025,
        "semester": 3,
        "jalur_kelas": "Reguler",
        "nominal": 3500000.00,
        "is_active": true,
        "master_biaya": { "id": 1, "kode": "UKT_REG", "nama": "Uang Kuliah Tunggal Reguler" }
    }
}
```

### Response Error — Duplikat

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Kombinasi tarif (Jenis Biaya + Angkatan + Prodi + Semester + Jalur Kelas) sudah ada."
}
```

---

## PUT /api/v1/sikeu/master/setting-tarif/{id}

> Update setting tarif yang sudah ada.

### Request Body

```json
{
    "nominal": 4000000,
    "keterangan": "UKT Reguler Angkatan 2025 (revisi)"
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Setting tarif berhasil diperbarui.",
    "data": { ... }
}
```

---

## DELETE /api/v1/sikeu/master/setting-tarif/{id}

> Hapus setting tarif.

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Setting tarif berhasil dihapus."
}
```
