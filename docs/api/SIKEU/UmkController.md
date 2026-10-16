# UmkController

> **Modul**: SIKEU
> **Base URL**: `/api/v1/sikeu/umk`
> **Autentikasi**: Bearer Token (Sanctum)
> **Otorisasi**: `sikeu.kaskecil.read` (list/detail), `sikeu.kaskecil.pengajuan` (create/update),
> `sikeu.kaskecil.approve` (status/delete) — dicek eksplisit di controller + FormRequest
> **Dibuat**: 2026-10-16
> **Diperbarui**: 2026-10-17

Parity legacy `transaksi_umk` (nama_penerima, tanggal, tanggal_selesai, total, status 1/2).
Status modern: `aktif` (=1, baru/diberikan) dan `selesai` (=2).

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/umk` | Daftar UMK + filter | ✅ `sikeu.kaskecil.read` |
| GET | `/api/v1/sikeu/umk/{id}` | Detail UMK | ✅ `sikeu.kaskecil.read` |
| POST | `/api/v1/sikeu/umk` | Catat UMK baru (status aktif) | ✅ `sikeu.kaskecil.pengajuan` |
| PUT | `/api/v1/sikeu/umk/{id}` | Ubah UMK aktif | ✅ `sikeu.kaskecil.pengajuan` |
| POST | `/api/v1/sikeu/umk/{id}/status` | Ubah status aktif/selesai | ✅ `sikeu.kaskecil.approve` |
| DELETE | `/api/v1/sikeu/umk/{id}` | Hapus UMK aktif | ✅ `sikeu.kaskecil.approve` |

---

## Request Headers

| Header | Type | Value | Deskripsi |
|---|---|---|---|
| Authorization | string | Bearer `<token>` | Token autentikasi pengguna |
| Accept | string | application/json | Format balasan yang diharapkan |
| Content-Type | string | application/json | Wajib untuk POST/PUT |

---

## GET /api/v1/sikeu/umk

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nama_penerima/keterangan |
| `status` | string | ❌ | — | `aktif` / `selesai` |
| `unit_kas_id` | integer | ❌ | — | Filter unit kas (`exists:sikeu_unit_kas,id`) |
| `tanggal_awal` | date | ❌ | — | Filter tanggal >= (YYYY-MM-DD) |
| `tanggal_akhir` | date | ❌ | — | Filter tanggal <= (YYYY-MM-DD) |
| `sort_by` | string | ❌ | `tanggal` | `tanggal` / `total` / `created_at` / `nama_penerima` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data UMK berhasil dimuat",
    "data": [
        {
            "id": 1,
            "unit_kas_id": 3,
            "nama_penerima": "Bendahara Fakultas",
            "tanggal": "2026-10-15",
            "tanggal_selesai": null,
            "keterangan": "Panjar kegiatan seminar",
            "total": "1500000.00",
            "status": "aktif",
            "unit_kas": {"id": 3, "nama_kas": "Kas Utama", "tipe_kas": "tunai"}
        }
    ],
    "meta": {"current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1},
    "filters": {"search": null, "sort_by": "tanggal", "sort_order": "desc"}
}
```

---

## GET /api/v1/sikeu/umk/{id}

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID UMK |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Detail UMK berhasil dimuat",
    "data": {
        "id": 1,
        "unit_kas_id": 3,
        "nama_penerima": "Bendahara Fakultas",
        "tanggal": "2026-10-15",
        "tanggal_selesai": null,
        "keterangan": "Panjar kegiatan seminar",
        "total": "1500000.00",
        "status": "aktif"
    }
}
```

### Response Error

**404 Not Found**
```json
{"status": "error", "message": "UangMukaKerja tidak ditemukan."}
```

---

## POST /api/v1/sikeu/umk

### Request Body

```json
{
    "unit_kas_id": "integer, nullable, exists:sikeu_unit_kas,id",
    "nama_penerima": "string, required, min:3",
    "tanggal": "date, required, before_or_equal:today",
    "tanggal_selesai": "date, nullable, after_or_equal:tanggal",
    "keterangan": "string, nullable, max:2000",
    "total": "numeric, required, min:1"
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "UMK berhasil dicatat",
    "data": {"id": 1, "status": "aktif", "total": "1500000.00"}
}
```

### Response Error

**422** bila unit kas sudah tutup buku pada tanggal tersebut:
```json
{"status": "error", "message": "Unit kas sudah tutup buku pada tanggal tersebut. UMK tidak dapat dicatat mundur."}
```

---

## PUT /api/v1/sikeu/umk/{id}

### Request Body

Semua field opsional (`sometimes`); aturan sama seperti POST.

```json
{
    "unit_kas_id": "integer, nullable, exists:sikeu_unit_kas,id",
    "nama_penerima": "string, min:3",
    "tanggal": "date, before_or_equal:today",
    "tanggal_selesai": "date, nullable",
    "keterangan": "string, nullable",
    "total": "numeric, min:1"
}
```

### Response Sukses

**200 OK**
```json
{"status": "success", "message": "UMK berhasil diperbarui", "data": {"id": 1, "nama_penerima": "Bendahara Fakultas"}}
```

### Response Error

**422** bila UMK sudah selesai:
```json
{"status": "error", "message": "UMK yang sudah selesai tidak dapat diubah. Buat UMK baru bila diperlukan."}
```

---

## POST /api/v1/sikeu/umk/{id}/status

### Request Body

```json
{
    "status": "string, required, in:aktif,selesai",
    "tanggal_selesai": "date, nullable, before_or_equal:today"
}
```

### Response Sukses

**200 OK**
```json
{"status": "success", "message": "Status UMK berhasil diperbarui", "data": {"id": 1, "status": "selesai"}}
```

---

## DELETE /api/v1/sikeu/umk/{id}

Hanya UMK berstatus `aktif` yang dapat dihapus.

### Response Sukses

**200 OK**
```json
{"status": "success", "message": "UMK berhasil dihapus", "data": null}
```

### Response Error

**422**
```json
{"status": "error", "message": "UMK yang sudah selesai tidak dapat dihapus."}
```

---

## Response Error Umum

**401 Unauthorized**
```json
{"status": "error", "message": "Token tidak valid atau sesi telah berakhir."}
```

**403 Forbidden**
```json
{"status": "error", "message": "Anda tidak memiliki izin mencatat UMK."}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {"nama_penerima": ["Nama penerima minimal 3 karakter."]}
}
```

### Catatan Tambahan

> - Audit log otomatis via `UmkObserver` (create/update/delete) ke `core_audit_logs`.
> - UMK aktif tampil sebagai komponen saldo pada laporan bendahara harian/bulanan, mengikuti legacy.
