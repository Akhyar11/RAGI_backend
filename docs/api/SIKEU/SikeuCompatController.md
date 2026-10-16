# SikeuCompatController

> **Modul**: SIKEU
> **Base URL**: `/api/v1/sikeu/compat`
> **Autentikasi**: Bearer Token (akun layanan SIAKAD/SPMB) + throttle `60/menit`
> **Otorisasi**: `sikeu.tagihan.read` — dicek eksplisit di setiap method
> **Dibuat**: 2026-10-17
> **Diperbarui**: 2026-10-17

Shim server-to-server pengganti legacy `cek_pembayaran.js`,
`cek_tagihan_ujian.js`, `biaya_lain.js`, dan `pendaftar.js (belumdu)`.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/sikeu/compat/ringkasan-pembayaran?mahasiswa_id=&nim=` | Total tagihan/bayar/sisa + flag lunas | ✅ |
| GET | `/api/v1/sikeu/compat/tagihan-aktif?mahasiswa_id=&nim=` | Tagihan belum lunas + VA | ✅ |
| GET | `/api/v1/sikeu/compat/eligibilitas-ujian?mahasiswa_id=&tipe_ujian=` | Layak UTS/UAS/ALL + bypass dispensasi | ✅ |
| GET | `/api/v1/sikeu/compat/biaya-lain?tipe=&search=` | Katalog master biaya | ✅ |
| GET | `/api/v1/sikeu/compat/pendaftar/belum-du?search=` | Calon lulus belum daftar ulang | ✅ |

---

## Request Headers

| Header | Type | Value | Deskripsi |
|---|---|---|---|
| Authorization | string | Bearer `<token>` | Token akun layanan |
| Accept | string | application/json | Format balasan yang diharapkan |

---

## GET ringkasan-pembayaran

Salah satu dari `mahasiswa_id` (`exists:siakad_mahasiswa,id`) atau `nim` wajib diisi.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `mahasiswa_id` | integer | ⚠️ | — | Salah satu wajib diisi |
| `nim` | string | ⚠️ | — | Salah satu wajib diisi |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Ringkasan pembayaran berhasil dimuat",
    "data": {
        "mahasiswa_id": 1, "nim": "2024001", "nama": "Budi",
        "total_tagihan": 5000000, "total_bayar": 2000000,
        "sisa": 3000000, "lunas": false, "jumlah_tagihan_aktif": 2
    }
}
```

### Response Error

**404**
```json
{"status": "error", "message": "Mahasiswa tidak ditemukan."}
```

---

## GET tagihan-aktif

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `mahasiswa_id` | integer | ⚠️ | — | Salah satu wajib diisi |
| `nim` | string | ⚠️ | — | Salah satu wajib diisi |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Tagihan aktif berhasil dimuat",
    "data": [
        {"tagihan_id": 1, "nomor_tagihan": "INV-...", "status": "sebagian", "sisa": 3000000, "va_number": "12345", "bank_kode": "BNI"}
    ],
    "meta": {"current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1}
}
```

---

## GET eligibilitas-ujian

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `mahasiswa_id` | integer | ✅ | `exists:siakad_mahasiswa,id` |
| `tipe_ujian` | string | ✅ | `UTS` / `UAS` / `ALL` |
| `semester` | integer | ❌ | Batasi lingkup tunggakan (1-14) |

Aturan: `eligible = sisa<=0 ATAU dispensasi approved allow_krs`.

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Eligibilitas ujian berhasil dicek",
    "data": {
        "mahasiswa_id": 1, "tipe_ujian": "UTS",
        "eligible": false, "sisa_tunggakan": 3000000,
        "bypass_dispensasi": false,
        "alasan": "Masih ada tunggakan Rp 3.000.000."
    }
}
```

### Response Error

**422** — `tipe_ujian` selain UTS/UAS/ALL, atau validasi gagal.

---

## GET biaya-lain

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tipe` | string | ❌ | — | `spp`, `sks`, `praktikum`, `wisuda`, `spmb_adm`, `lainnya` |
| `search` | string | ❌ | — | Cari kode/nama |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

Scoping prodi modern tetap di `SettingTarif`/`katalog-biaya`.

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Katalog biaya berhasil dimuat",
    "data": [{"id": 1, "kode": "WISUDA", "nama": "Biaya Wisuda", "tipe": "wisuda", "nominal_standar": 1500000}],
    "meta": {"current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1}
}
```

---

## GET pendaftar/belum-du

Hasil seleksi `lulus` dengan `status_daftar_ulang != lunas`,
dilengkapi status tagihan DU terkini (`spmb_daftar_ulang`).

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari nama/no_pendaftaran/nim |
| `per_page` | integer | ❌ | `15` | Maks. 100 |
| `page` | integer | ❌ | `1` | Halaman |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Pendaftar belum daftar ulang berhasil dimuat",
    "data": [
        {
            "pendaftaran_id": 1, "no_pendaftaran": "REG-1", "nama": "Calon",
            "status_daftar_ulang": "menunggu_pembayaran",
            "tagihan_du": {"nomor_tagihan": "INV-...", "status": "belum_bayar", "sisa": 2500000}
        }
    ],
    "meta": {"current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "from": 1, "to": 1}
}
```

---

## Response Error Umum

**401 Unauthorized**
```json
{"status": "error", "message": "Token tidak valid atau sesi telah berakhir."}
```

**403 Forbidden**
```json
{"status": "error", "message": "Anda tidak memiliki akses data tagihan."}
```

**404 Not Found**
```json
{"status": "error", "message": "Mahasiswa tidak ditemukan."}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {"tipe_ujian": ["Tipe ujian tidak valid."]}
}
```

---

## Kwitansi Email Otomatis

Setiap pembayaran kasir sukses (`kasir`, `direct-cashier`) dan
`approve-manual` memicu `KwitansiPembayaranService` **setelah commit**
(non-blocking; kegagalan hanya di-log). Isi: kode transaksi, rincian,
dan tautan `FRONTEND_URL/validasi-pembayaran/{kode}`. Dilewati bila
mahasiswa tanpa alamat email.
