# KoreksiAlokasiController

> **Modul**: SIKEU
> **Base URL**: `/api/v1/sikeu/koreksi-alokasi`
> **Autentikasi**: Bearer Token (Passport) ✅
> **Dibuat**: 2026-10-12
> **Diperbarui**: 2026-10-12

Menu Koreksi Alokasi Pembayaran: redistribusi ALOKASI antar tagihan milik
mahasiswa yang sama tanpa me-rewrite histori pembayaran. Jika target masih
bersisa setelah alokasi, sistem otomatis menerbitkan tagihan kekurangan
(`source_system = sikeu_koreksi_alokasi`).

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/koreksi-alokasi` | Daftar koreksi + pagination/filter | ✅ |
| GET | `/api/v1/sikeu/koreksi-alokasi/konsolidasi?mahasiswa_id=` | Konsolidasi tagihan + tersedia dialihkan | ✅ |
| POST | `/api/v1/sikeu/koreksi-alokasi/simulate` | Simulasi dampak + usulan kekurangan | ✅ |
| POST | `/api/v1/sikeu/koreksi-alokasi/draft` | Buat draft pending | ✅ |
| GET | `/api/v1/sikeu/koreksi-alokasi/{id}` | Detail + simulasi terkini | ✅ |
| POST | `/api/v1/sikeu/koreksi-alokasi/{id}/approve` | Setujui (pending → approved) | ✅ |
| POST | `/api/v1/sikeu/koreksi-alokasi/{id}/reject` | Tolak | ✅ |
| POST | `/api/v1/sikeu/koreksi-alokasi/{id}/apply` | Terapkan atomik + tagihan kekurangan | ✅ |
| POST | `/api/v1/sikeu/koreksi-alokasi/{id}/cancel` | Batalkan draft | ✅ |
| GET | `/api/v1/sikeu/koreksi-alokasi/tagihan/{id}/sisa` | Sisa + tersedia satu tagihan | ✅ |

---

## Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | Hanya POST |

---

## GET /api/v1/sikeu/koreksi-alokasi

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nomor koreksi / alasan / referensi |
| `status` | string | ❌ | `all` | `pending\|approved\|applied\|rejected\|cancelled` |
| `mahasiswa_id` | integer | ❌ | — | Filter milik mahasiswa |
| `sort_by` | string | ❌ | `created_at` | `id\|created_at\|nomor_koreksi\|status\|total_nominal_dialihkan` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman yang diminta |

### Response Sukses (200, pagination)

```json
{
    "status": "success",
    "message": "Daftar koreksi alokasi berhasil dimuat",
    "data": [
        {
            "id": 1,
            "nomor_koreksi": "KOR-20261012-AB12C",
            "status": "pending",
            "total_nominal_dialihkan": "1500000.00"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1,
        "from": 1,
        "to": 1
    }
}
```

---

## POST /api/v1/sikeu/koreksi-alokasi/draft

### Request Body

```json
{
    "alasan": "string, required, min:10 (mis. SK Pindah Prodi No.123)",
    "referensi": "string, nullable (nomor surat)",
    "tahun_akademik_id": "integer, nullable",
    "semester": "integer, nullable",
    "buat_tagihan_kekurangan": "boolean, default true",
    "details": [
        {
            "from_tagihan_id": "integer, required, exists",
            "to_tagihan_id": "integer, required, exists, different",
            "nominal_dialihkan": "numeric, required, min:1",
            "keterangan": "string, nullable"
        }
    ]
}
```

### Aturan Bisnis

- Asal & tujuan wajib milik mahasiswa yang sama.
- Tagihan `batal` ditolak; tujuan yang sudah lunas ditolak.
- Total keluar per sumber ≤ alokasi tersedia (total_bayar − reservasi pending/approved).
- Diblokir saat periode akuntansi hari ini berstatus `ditutup` (403).
- Setiap transisi status tercatat di audit log (`SIKEU/sikeu_koreksi_alokasi`).

### Response Sukses (201)

```json
{
    "status": "success",
    "message": "Draft koreksi KOR-20261012-XXXXX berhasil dibuat dan menunggu persetujuan.",
    "data": { "id": 1, "nomor_koreksi": "KOR-20261012-XXXXX", "status": "pending" }
}
```

---

## POST /api/v1/sikeu/koreksi-alokasi/simulate

Menghitung `dampak_target[]` (`sisa_setelah`, `akan_lunas`) dan
`total_kekurangan` + `usulan_tagihan_kekurangan` untuk preview UI.

### Response Sukses (200)

```json
{
    "status": "success",
    "data": {
        "total_dialihkan": 1500000,
        "dampak_target": [
            {
                "to_tagihan_id": 9,
                "nomor_tagihan": "INV-MHS-...",
                "sisa_sebelum": 2000000,
                "dialihkan_masuk": 1500000,
                "sisa_setelah": 500000,
                "akan_lunas": false
            }
        ],
        "ada_kekurangan": true,
        "total_kekurangan": 500000,
        "usulan_tagihan_kekurangan": "Buat tagihan koreksi kekurangan Rp 500.000"
    }
}
```

### Catatan Tambahan

- `apply` bersifat idempoten (double-apply dicegah via status + row lock).
- Redistribusi alokasi TIDAK membuat jurnal baru (kas tidak berpindah);
  tagihan kekurangan yang diterbitkan memakai jurnal penerbitan standar.
- Saldo lebih yang tidak dialokasikan tetap di tagihan asal sebagai hak mahasiswa.

---

## Response Error

**400 Bad Request** — aksi tidak valid pada status saat ini
```json
{
    "status": "error",
    "message": "Hanya koreksi yang sudah disetujui yang bisa diterapkan."
}
```

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden** — periode tutup atau tanpa izin
```json
{
    "status": "error",
    "message": "Periode akuntansi \"Januari 2026\" sudah ditutup. Koreksi tidak dapat diterapkan."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "KoreksiAlokasi tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Total dialihkan dari INV-MHS-20260101-XXXX (Rp 2.000.000) melebihi alokasi tersedia (Rp 1.500.000)."
}
```
