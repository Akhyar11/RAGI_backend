# TutupKasController

> **Modul**: SIKEU
> **Base URL**: `/api/v1/sikeu/tutup-kas`
> **Autentikasi**: Bearer Token (Sanctum)
> **Otorisasi**: `sikeu.kas.manage` — dicek eksplisit di controller + FormRequest
> **Dibuat**: 2026-10-16
> **Diperbarui**: 2026-10-17

Parity legacy `tutup_bukus` per divisi (`id=sha1(divisi+tanggal)`).
Modern: kunci per `unit_kas_id + tanggal` (`unique`), melengkapi periode akuntansi global.
Kasir (`POST pembayaran/kasir`), UMK, kas kecil, dan pencairan operasional
menolak transaksi mundur bila unit kas terkunci.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/tutup-kas` | Daftar kunci tutup kas | ✅ `sikeu.kas.manage` |
| POST | `/api/v1/sikeu/tutup-kas` | Kunci unit kas pada tanggal (upsert) | ✅ `sikeu.kas.manage` |
| DELETE | `/api/v1/sikeu/tutup-kas/{id}` | Buka kunci (hapus) | ✅ `sikeu.kas.manage` |

---

## Request Headers

| Header | Type | Value | Deskripsi |
|---|---|---|---|
| Authorization | string | Bearer `<token>` | Token autentikasi pengguna |
| Accept | string | application/json | Format balasan yang diharapkan |
| Content-Type | string | application/json | Wajib untuk POST |

---

## GET /api/v1/sikeu/tutup-kas

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `unit_kas_id` | integer | ❌ | — | Filter unit kas |
| `tanggal` | date | ❌ | — | Filter tanggal tepat (YYYY-MM-DD) |
| `search` | string | ❌ | — | Cari nama kas |
| `sort_by` | string | ❌ | `tanggal` | `tanggal` / `created_at` / `saldo_akhir` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Data tutup kas berhasil dimuat",
    "data": [
        {
            "id": 1, "unit_kas_id": 3, "tanggal": "2026-10-15",
            "saldo_awal": "10000000.00", "saldo_masuk": "2500000.00",
            "saldo_keluar": "500000.00", "saldo_akhir": "12000000.00",
            "is_locked": true,
            "unit_kas": {"id": 3, "nama_kas": "Kas Utama", "tipe_kas": "tunai"}
        }
    ],
    "meta": {"current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1}
}
```

---

## POST /api/v1/sikeu/tutup-kas

Upsert berdasarkan `unique(unit_kas_id, tanggal)` — tutup ulang tanggal sama menimpa saldo.

### Request Body

```json
{
    "unit_kas_id": "integer, required, exists:sikeu_unit_kas,id",
    "tanggal": "date, required, before_or_equal:today",
    "saldo_awal": "numeric, nullable, min:0",
    "saldo_masuk": "numeric, nullable, min:0",
    "saldo_keluar": "numeric, nullable, min:0",
    "saldo_akhir": "numeric, nullable, min:0"
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Unit kas berhasil ditutup pada tanggal tersebut",
    "data": {"id": 1, "unit_kas_id": 3, "tanggal": "2026-10-15", "is_locked": true}
}
```

---

## DELETE /api/v1/sikeu/tutup-kas/{id}

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID kunci tutup kas |

### Response Sukses

**200 OK**
```json
{"status": "success", "message": "Kunci tutup kas berhasil dibuka", "data": null}
```

### Response Error

**404 Not Found**
```json
{"status": "error", "message": "TutupKasUnit tidak ditemukan."}
```

---

## Response Error Umum

**401 Unauthorized**
```json
{"status": "error", "message": "Token tidak valid atau sesi telah berakhir."}
```

**403 Forbidden**
```json
{"status": "error", "message": "Anda tidak memiliki izin menutup kas."}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {"tanggal": ["Tanggal tutup kas tidak boleh di masa depan."]}
}
```

### Efek Guard

> `POST /api/v1/sikeu/pembayaran/kasir`, `POST /api/v1/sikeu/umk`,
> transaksi/approve kas kecil, dan pencairan operasional menolak tanggal terkunci:
> `403/422 "Unit kas ... sudah tutup buku ..."`.
> Periode akuntansi global (`periode/{id}/tutup`) tetap berlaku; kunci ini granular per unit.

### Catatan Tambahan

> - Audit log otomatis via `TutupKasUnitObserver` ke `core_audit_logs`.
