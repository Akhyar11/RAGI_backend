# FileStreamController

> **Modul**: System / **Base URL**: /api/files / **Autentikasi**: Signed URL (tanpa Bearer) / **Dibuat/Diperbarui**: 2026-09-26

Endpoint **generik** untuk menampilkan berkas privat (dokumen e-file, lampiran cuti, dll.) secara aman. URL bersifat **sementara** (Signed URL, berlaku 15 menit) sehingga dapat dibuka di tab baru tanpa Bearer token, dan berkas **tidak pernah** diekspos lewat URL storage langsung.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/files/view` | Stream inline berkas privat via Signed URL | ❌ Publik (Signed URL) |

---

## 1. GET /api/files/view

> Menampilkan (stream inline) berkas dari disk mana pun (r2-private, r2, public, local). URL dihasilkan oleh aplikasi melalui `FileStorageService::signedUrl()`.

### Headers

| Key | Value | Required |
|---|---|---|
| `Accept` | `application/json` | ❌ |

### Query Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `path` | string | ✅ | Path relatif penyimpanan (mis. `simpeg/dokumen_pegawai/2026/09/uuid.pdf`) |
| `expires` | integer | ✅ | Timestamp kedaluwarsa (diisi otomatis oleh Signed URL) |
| `signature` | string | ✅ | Tanda tangan HMAC (diisi otomatis oleh Signed URL) |

### Response Sukses (200 OK)
Binary stream (`Content-Disposition: inline`).

### Response Error

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Invalid signature."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "File fisik tidak ditemukan pada storage server."
}
```

---

## Catatan Khusus & Integritas Data
- **Secure by default**: Berkas yang tersimpan pada disk privat otomatis disajikan lewat Signed URL; helper terpusat `FileStorageService::url()` mendeteksi ini sehingga pemanggil tidak perlu memilih disk manual.
- **Pencarian multi-disk**: Berkas dicari berurutan di `r2-private`, `r2`, `public`, lalu `local`, agar berkas lama (masa migrasi) tetap tampil.
- **Pencegahan traversal**: Path dinormalisasi (`normalizePath`) dan URL wajib bertanda-tangan; `..` ditolak.
- **Tanpa auth Bearer**: Keamanan bergantung pada tanda tangan URL + masa berlaku 15 menit.
