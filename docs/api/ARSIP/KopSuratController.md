# KopSuratController

> **Modul**: ARSIP  
> **Base URL**: `/api/arsip/kop-surat`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-10-02  
> **Diperbarui**: 2026-10-02  

Modul ini mengelola berkas kop surat resmi kampus yang dibedakan menjadi dua versi berdasarkan periode tahun:
1. **Versi Baru**: Untuk dokumen bertahun $\ge 2021$.
2. **Versi Lama**: Untuk dokumen bertahun $< 2021$.

Berkas gambar/dokumen kop surat disimpan secara privat di disk penyimpanan berkas (`FileStorageService`) dan diakses melalui Signed URL sementara untuk mencegah paparan berkas langsung tanpa otorisasi.

---

## Headers Standar

- `Authorization: Bearer {token}`
- `Accept: application/json`
- `Content-Type: application/json` (atau `multipart/form-data` saat upload)

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/arsip/kop-surat` | Daftar master kop surat | ✅ `arsip.kop_surat.read` |
| POST | `/api/arsip/kop-surat` | Unggah & simpan berkas kop surat baru | ✅ `arsip.kop_surat.manage` |
| GET | `/api/arsip/kop-surat/by-year` | Ambil kop surat aktif berdasarkan tahun surat (`?year=YYYY`) | ✅ `arsip.kop_surat.read` |
| GET | `/api/arsip/kop-surat/{id}` | Detail kop surat | ✅ `arsip.kop_surat.read` |
| POST/PUT | `/api/arsip/kop-surat/{id}` | Perbarui data atau berkas kop surat | ✅ `arsip.kop_surat.manage` |
| POST | `/api/arsip/kop-surat/{id}/toggle-active` | Aktifkan/nonaktifkan status kop surat | ✅ `arsip.kop_surat.manage` |
| DELETE | `/api/arsip/kop-surat/{id}` | Hapus berkas kop surat | ✅ `arsip.kop_surat.manage` |

---

## 1. GET /api/arsip/kop-surat/by-year

Mengambil data dan Signed URL kop surat yang berlaku berdasarkan parameter tahun surat.

### Query Parameters

- `year` (integer, default: tahun saat ini) - Tahun surat (contoh: `2025` atau `2019`).

### Logika Resolusi Periode

- Jika `year < 2021`: Mengembalikan kop surat aktif versi **lama**.
- Jika `year >= 2021`: Mengembalikan kop surat aktif versi **baru**.

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Kop surat untuk tahun 2025 berhasil dimuat.",
  "data": {
    "id": 1,
    "nama": "Kop Surat Resmi Universitas (2021-Sekarang)",
    "versi": "baru",
    "tahun_mulai": 2021,
    "tahun_selesai": null,
    "nama_institusi": "Universitas Sains & Teknologi",
    "alamat_institusi": "Jl. Kampus Terpadu No. 1",
    "kontak_institusi": "(021) 12345678",
    "website_institusi": "https://kampus.ac.id",
    "is_active": true,
    "file_url": "http://127.0.0.1:8000/api/files/view?path=arsip%2Fkop-surat%2F...&signature=..."
  },
  "tahun_query": 2025,
  "versi_diterapkan": "baru"
}
```

---

## 2. POST /api/arsip/kop-surat

Mengunggah berkas spesimen kop surat resmi baru (`multipart/form-data`).

### Form Data

- `nama` (string, wajib) - Label nama kop surat.
- `versi` (enum: `lama`, `baru`, wajib) - Versi periode kop surat.
- `tahun_mulai` (integer, wajib) - Tahun awal berlaku.
- `tahun_selesai` (integer, opsional) - Tahun batas akhir (khusus versi lama, misal `2020`).
- `file_kop` (file, wajib) - Berkas gambar/dokumen kop surat (PNG, JPG, PDF, maks 5MB).
- `nama_institusi` (string, opsional) - Nama lembaga/universitas.
- `alamat_institusi` (string, opsional) - Alamat surat.
- `kontak_institusi` (string, opsional) - Nomor telepon / email.
- `is_active` (boolean, opsional) - Status aktif kop surat.
