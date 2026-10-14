# KartuUjianController

> **Modul**: SIAKAD  
> **Base URL**: `/api/v1/siakad`  
> **Autentikasi**: Bearer Token (Sanctum) — kecuali dinyatakan lain  
> **Dibuat**: 2026-10-14  
> **Diperbarui**: 2026-10-14

Kartu peserta ujian UTS/UAS: penerbitan bersyarat lunas SIKEU (aturan sama dengan gate KRS), token acak + QR menuju halaman validasi publik, monitoring BAAK, dan endpoint validasi publik tanpa login (throttle anti-enumerasi).

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/siakad/perkuliahan/kartu-ujian` | Terbitkan kartu UTS/UAS + QR SVG | ✅ `siakad.krs.read` |
| GET | `/api/v1/siakad/perkuliahan/kartu-ujian/monitoring` | Monitoring kartu terbit | ✅ `siakad.mahasiswa.read` |
| PATCH | `/api/v1/siakad/perkuliahan/kartu-ujian/{id}/nonaktifkan` | Nonaktifkan kartu | ✅ `siakad.mahasiswa.manage` |
| GET | `/api/v1/siakad/kartu-ujian/validasi/{token}` | Validasi publik via QR | ❌ Publik (`throttle:30,1`) |

---

## [GET] /api/v1/siakad/perkuliahan/kartu-ujian

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tipe_ujian` | string | ✅ | — | `UTS` / `UAS` |
| `mahasiswa_id` | integer | ❌ | login | Terbitkan untuk mahasiswa lain (BAAK) |
| `tahun_akademik_id` | integer | ❌ | aktif | Periode kartu |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Kartu UTS berhasil diterbitkan.",
    "data": {
        "mahasiswa": { "id": 5, "nim": "2401001", "nama_lengkap": "..." },
        "tahun_akademik": { "id": 1, "nama": "2026/2027 Ganjil" },
        "tipe_ujian": "UTS",
        "jadwal": [{ "kode_mk": "IF201", "nama_mk": "Basis Data", "kelas": "...", "hari": "senin", "jam": "08:00-10:30", "ruangan": "R.301", "dosen": "..." }],
        "keuangan_lunas": true,
        "token": "acakan 48 karakter",
        "tanggal_cetak": "14 Oktober 2026 10:00",
        "validasi_url": "http://localhost:3000/validasi-kartu-ujian/{token}",
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
    "message": "Kartu ujian tidak dapat dicetak karena Anda memiliki tagihan SIKEU yang belum diselesaikan. Silakan lunasi tagihan atau ajukan dispensasi keuangan."
}
```

---

## [GET] /api/v1/siakad/kartu-ujian/validasi/{token}

> Tanpa login. QR pada kartu cetak mengarah ke halaman frontend `/validasi-kartu-ujian/{token}` yang memanggil endpoint ini.

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Kartu ujian valid dan terdaftar di sistem.",
    "data": {
        "is_valid": true,
        "verified_at": "14 Oktober 2026 10:05",
        "tipe_ujian": "UTS",
        "mahasiswa": { "nama_lengkap": "...", "nim": "...", "program_studi": "...", "angkatan": 2024, "status": "aktif" },
        "tahun_akademik": "2026/2027 Ganjil",
        "keuangan_lunas": true
    }
}
```

### Response Error

**404 Not Found**
```json
{
    "status": "error",
    "message": "Kartu ujian tidak ditemukan atau sudah tidak berlaku."
}
```

---

## [GET] /api/v1/siakad/perkuliahan/kartu-ujian/monitoring

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tahun_akademik_id` | integer | ❌ | — | Filter periode |
| `tipe_ujian` | string | ❌ | — | `UTS` / `UAS` |
| `search` | string | ❌ | — | Cari NIM / nama |
| `sort_by` | string | ❌ | `tanggal_cetak` | `tanggal_cetak`, `created_at`, `id` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Monitoring kartu ujian berhasil dimuat",
    "data": [
        { "id": 1, "mahasiswa_id": 5, "tipe_ujian": "UTS", "is_active": true }
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

## [PATCH] /api/v1/siakad/perkuliahan/kartu-ujian/{id}/nonaktifkan

Menonaktifkan kartu bermasalah (QR tidak valid lagi). Auth: ✅ `siakad.mahasiswa.manage`.

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
    "message": "Kartu ujian dinonaktifkan.",
    "data": { "id": 7, "is_active": false }
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
    "message": "KartuUjianToken tidak ditemukan."
}
```

### Catatan Tambahan

> - Mencetak ulang kartu yang sama menyegarkan token + tanggal cetak (QR lama otomatis mati karena token unik per baris).
> - Penerbitan tercatat di `audit_logs` via `KartuUjianTokenObserver` (modul `SIAKAD`).
> - Token acak 48 karakter — tidak dapat ditebak dari NIM.
