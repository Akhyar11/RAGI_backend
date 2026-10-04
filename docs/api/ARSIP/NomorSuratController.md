# NomorSuratController

> **Modul**: ARSIP  
> **Base URL**: `/api/arsip/nomor-surat`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-10-02  
> **Diperbarui**: 2026-10-02  

Modul ini mengelola penerbitan nomor surat resmi kampus baik secara satuan (single) maupun banyak sekaligus (bulk / massal). Format standar nomor surat mengikuti kaidah persuratan universitas: `[Nomor Urut]/[Kode Unit]/[Kode Klasifikasi]/[Bulan Romawi]/[Tahun]` (contoh: `247/DIII/INDO/II/2025`). Setiap nomor surat yang diterbitkan secara otomatis mengikat kop surat yang sesuai dengan periode tahun surat ($\ge 2021$ versi baru, $< 2021$ versi lama).

---

## Headers Standar

- `Authorization: Bearer {token}`
- `Accept: application/json`
- `Content-Type: application/json`

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/arsip/nomor-surat` | Daftar nomor surat resmi | ✅ `arsip.nomor_surat.read` |
| POST | `/api/arsip/nomor-surat` | Terbitkan nomor surat (satuan atau bulk) | ✅ `arsip.nomor_surat.create` |
| GET | `/api/arsip/nomor-surat/{id}` | Detail nomor surat | ✅ `arsip.nomor_surat.read` |
| PUT | `/api/arsip/nomor-surat/{id}` | Perbarui data perihal/tujuan nomor surat | ✅ `arsip.nomor_surat.update` |
| POST | `/api/arsip/nomor-surat/{id}/batalkan` | Batalkan nomor surat | ✅ `arsip.nomor_surat.update` |

---

## 1. GET /api/arsip/nomor-surat

Mengambil daftar nomor surat resmi dengan filter tahun, unit, klasifikasi, status, dan pagination.

### Query Parameters

- `search` (string, optional) - Pencarian nomor surat, perihal, tujuan, atau catatan.
- `tahun` (integer, optional) - Filter tahun surat.
- `kode_unit` (string, optional) - Filter kode unit (misal: `DIII`, `S1`, `REK`).
- `kode_klasifikasi` (string, optional) - Filter kode klasifikasi (misal: `INDO`, `AKD`, `SARPRAS`).
- `status` (string, optional) - Filter status (`terpakai`, `direservasi`, `dibatalkan`).
- `module_origin` (string, optional) - Filter asal modul (misal: `arsip`, `sinapra`, `simpeg`).
- `sort_by` (string, default: `created_at`) - Kolom pengurutan (`id`, `nomor_urut`, `tanggal_surat`, `created_at`).
- `sort_order` (string, default: `desc`) - Arah pengurutan (`asc`, `desc`).
- `per_page` (integer, default: 15, max: 100) - Jumlah data per halaman.
- `page` (integer, default: 1) - Halaman data.

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Daftar nomor surat berhasil diambil",
  "data": [
    {
      "id": 1,
      "nomor_surat": "247/DIII/INDO/II/2025",
      "nomor_urut": 247,
      "kode_unit": "DIII",
      "kode_klasifikasi": "INDO",
      "bulan_romawi": "II",
      "tahun": 2025,
      "tanggal_surat": "2025-02-14",
      "perihal": "Peminjaman Laboratorium Praktikum",
      "tujuan": "Kepala Laboratorium Komputer",
      "status": "terpakai",
      "module_origin": "sinapra",
      "request_id": 5,
      "kop_surat_id": 2,
      "created_by": 1,
      "created_at": "2025-02-14T08:30:00.000000Z",
      "pembuat": {
        "id": 1,
        "name": "Super Administrator",
        "email": "admin@example.com"
      },
      "kop_surat": {
        "id": 2,
        "nama": "Kop Surat Resmi Universitas (2021-Sekarang)",
        "versi": "baru",
        "tahun_mulai": 2021
      }
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

## 2. POST /api/arsip/nomor-surat

Menerbitkan nomor surat resmi secara langsung (satuan atau bulk berurutan).

### Payload Satuan (Single)

```json
{
  "mode": "satuan",
  "tanggal_surat": "2025-02-14",
  "kode_unit": "DIII",
  "kode_klasifikasi": "INDO",
  "perihal": "Surat Keterangan Aktif Kuliah",
  "tujuan": "Instansi Terkait",
  "status": "terpakai",
  "module_origin": "arsip",
  "catatan": "Keterangan opsional"
}
```

### Payload Sekaligus Banyak (Bulk)

```json
{
  "mode": "bulk",
  "jumlah_nomor": 5,
  "tanggal_surat": "2025-02-14",
  "kode_unit": "DIII",
  "kode_klasifikasi": "INDO",
  "perihal": "Sertifikat Pelatihan Mahasiswa",
  "tujuan": "Peserta Pelatihan",
  "status": "terpakai",
  "module_origin": "arsip"
}
```

### Response Sukses (201 Created)

```json
{
  "status": "success",
  "message": "Nomor surat resmi berhasil diterbitkan.",
  "data": {
    "id": 1,
    "nomor_surat": "247/DIII/INDO/II/2025",
    "nomor_urut": 247,
    "kode_unit": "DIII",
    "kode_klasifikasi": "INDO",
    "bulan_romawi": "II",
    "tahun": 2025,
    "tanggal_surat": "2025-02-14",
    "perihal": "Surat Keterangan Aktif Kuliah"
  }
}
```

---

## 3. GET /api/arsip/nomor-surat/{id}

Mengambil detail lengkap nomor surat, kop surat yang terikat, serta pembuatnya.

---

## 4. POST /api/arsip/nomor-surat/{id}/batalkan

Membatalkan nomor surat resmi yang terlanjur terbit.

### Payload

```json
{
  "alasan": "Salah perihal surat dan dibatalkan atas permintaan pemohon"
}
```
