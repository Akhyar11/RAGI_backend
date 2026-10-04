# RequestNomorSuratController

> **Modul**: ARSIP  
> **Base URL**: `/api/arsip/request-nomor`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-10-02  
> **Diperbarui**: 2026-10-02  

Modul ini mengelola alur permohonan nomor surat yang diajukan oleh modul-modul lain di ekosistem kampus (seperti SINAPRA untuk peminjaman aset/ruangan, SIMPEG untuk surat tugas/SK, SIAKAD untuk surat keterangan aktif, dsb.) dengan verifikasi terpusat oleh Admin Arsip.

---

## Headers Standar

- `Authorization: Bearer {token}`
- `Accept: application/json`
- `Content-Type: application/json` (atau `multipart/form-data` bila ada dokumen lampiran)

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/arsip/request-nomor` | Daftar permohonan nomor surat | ✅ `auth:api` |
| POST | `/api/arsip/request-nomor` | Ajukan permohonan nomor surat dari modul lain | ✅ `auth:api` |
| GET | `/api/arsip/request-nomor/{id}` | Detail permohonan & nomor surat yang terbit | ✅ `auth:api` |
| POST | `/api/arsip/request-nomor/{id}/verify` | Verifikasi (setujui / tolak) permohonan | ✅ `arsip.request.approve` |

---

## 1. POST /api/arsip/request-nomor

Mengajukan permohonan nomor surat dari modul eksternal.

### Payload

```json
{
  "module_origin": "simpeg",
  "reference_type": "App\\Models\\Simpeg\\SuratTugas",
  "reference_id": 12,
  "perihal": "Surat Tugas: Diseminasi Penelitian AI",
  "tujuan": "Yogyakarta",
  "tanggal_surat": "2026-10-15",
  "kode_unit": "TI",
  "kode_klasifikasi": "DII",
  "jumlah_nomor": 1,
  "catatan_pemohon": "Diperlukan untuk pelaksanaan tugas dinas luar kampus"
}
```

### Response Sukses (201 Created)

```json
{
  "status": "success",
  "message": "Permohonan nomor surat berhasil diajukan dan sedang menunggu verifikasi admin arsip.",
  "data": {
    "id": 1,
    "kode_request": "REQ-ARSIP-2026-1740000000-ABCD",
    "module_origin": "simpeg",
    "reference_type": "App\\Models\\Simpeg\\SuratTugas",
    "reference_id": 12,
    "status": "menunggu_verifikasi",
    "perihal": "Surat Tugas: Diseminasi Penelitian AI",
    "jumlah_nomor": 1
  }
}
```

---

## 2. POST /api/arsip/request-nomor/{id}/verify

Admin Arsip menyetujui (`setujui`) atau menolak (`tolak`) permohonan. Ketika disetujui, sistem secara otomatis menerbitkan nomor surat resmi dan mengaitkannya ke request tersebut. Admin Arsip memiliki otoritas penuh untuk memvalidasi atau mengoreksi `kode_klasifikasi` (DI - DIX), `kode_unit`, `perihal`, dan `tujuan` sebelum nomor diterbitkan.

### Payload Setujui (Dengan Otoritas Override Klasifikasi Admin Arsip)

```json
{
  "action": "setujui",
  "kode_klasifikasi": "DII",
  "kode_unit": "TI",
  "perihal": "Surat Tugas Resmi: Diseminasi Penelitian AI",
  "tujuan": "Yogyakarta",
  "catatan": "Disetujui sesuai klasifikasi DII (Surat Tugas). Nomor resmi otomatis disinkronisasikan ke SIMPEG dan SIKEU."
}
```

### Payload Tolak

```json
{
  "action": "tolak",
  "catatan": "Klasifikasi surat tidak sesuai dengan perihal permohonan."
}
```
