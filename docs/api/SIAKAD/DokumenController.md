# DokumenController

> **Modul**: SIAKAD  
> **Base URL**: `/api/v1/siakad`  
> **Autentikasi**: Bearer Token (Sanctum) — kecuali dinyatakan lain  
> **Dibuat**: 2026-10-14  
> **Diperbarui**: 2026-10-14

Dokumen resmi KHS & Transkrip: nomor dokumen stabil, snapshot nilai anti-tamper saat diterbitkan, token acak + QR menuju halaman validasi publik.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| POST | `/api/v1/siakad/perkuliahan/dokumen/terbitkan` | Terbitkan dokumen resmi + QR SVG | ✅ `siakad.nilai.read` |
| GET | `/api/v1/siakad/perkuliahan/dokumen` | Monitoring dokumen terbit | ✅ `siakad.mahasiswa.read` |
| GET | `/api/v1/siakad/dokumen/validasi/{token}` | Validasi publik via QR | ❌ Publik (`throttle:30,1`) |

---

## [POST] /api/v1/siakad/perkuliahan/dokumen/terbitkan

Menerbitkan ulang dokumen yang sama menyegarkan token + snapshot + tanggal (QR lama otomatis mati).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "jenis": "KHS | TRANSKRIP (required)",
    "mahasiswa_id": "integer, nullable, exists:siakad_mahasiswa,id (default: login)",
    "tahun_akademik_id": "integer, nullable (default: aktif; wajib untuk KHS)"
}
```

### Response Sukses

**201 Created**
```json
{
    "status": "success",
    "message": "Dokumen KHS resmi diterbitkan.",
    "data": {
        "jenis": "KHS",
        "nomor_dokumen": "KHS/2401001/1/2026",
        "snapshot": { "ips": 3.5, "sks_semester": 20, "sks_kumulatif": 60, "ipk": 3.4, "is_locked": true },
        "tanggal_terbit": "14 Oktober 2026",
        "token": "acakan 48 karakter",
        "validasi_url": "http://localhost:3000/validasi-dokumen/{token}",
        "qr_code_svg": "<svg ...>...</svg>"
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
    "message": "Belum ada KHS tersimpan untuk periode ini (nilai belum final)."
}
```

```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "jenis": ["Jenis dokumen wajib diisi."]
    }
}
```

---

## [GET] /api/v1/siakad/perkuliahan/dokumen

Monitoring seluruh dokumen resmi yang pernah diterbitkan (BAAK).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `jenis` | string | ❌ | — | `KHS` / `TRANSKRIP` |
| `mahasiswa_id` | integer | ❌ | — | Filter mahasiswa |
| `search` | string | ❌ | — | Cari nomor dokumen / NIM / nama |
| `sort_by` | string | ❌ | `tanggal_terbit` | `tanggal_terbit`, `created_at`, `id` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Dokumen akademik terbit berhasil dimuat",
    "data": [
        { "id": 1, "jenis": "KHS", "nomor_dokumen": "KHS/2401001/1/2026", "is_active": true }
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

## [GET] /api/v1/siakad/dokumen/validasi/{token}

> Tanpa login. QR pada dokumen cetak mengarah ke halaman frontend `/validasi-dokumen/{token}` yang memanggil endpoint ini.
>
> Auth: ❌ Publik (`throttle:30,1`).

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Dokumen valid dan terdaftar di sistem.",
    "data": {
        "is_valid": true,
        "jenis": "TRANSKRIP",
        "nomor_dokumen": "TRX/2401001/KUM/2026",
        "snapshot": { "total_sks_lulus": 120, "ipk": 3.4, "predikat": "Sangat Memuaskan" },
        "mahasiswa": { "nama_lengkap": "...", "nim": "...", "program_studi": "..." }
    }
}
```

### Response Error

**404 Not Found**
```json
{
    "status": "error",
    "message": "Dokumen tidak ditemukan atau sudah tidak berlaku."
}
```

### Catatan Tambahan

> - Snapshot KHS diambil dari baris `siakad_khs` tersimpan (bukan hitungan live client) sehingga angka cetak = angka sistem.
> - Penerbitan tercatat di `audit_logs` via `DokumenTokenObserver` (modul `SIAKAD`).
