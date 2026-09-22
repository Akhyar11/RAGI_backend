# Dokumentasi API: Izin Parsial Jam Kerja Pegawai (IzinJamKerjaController)

> **Modul**: SIMPEG (Kepegawaian Terintegrasi)  
> **Base URL**: `/api/simpeg/izin-kerja`  
> **Autentikasi**: Bearer Token (Passport)  
> **Dibuat**: 2026-09-16  

Modul ini mengelola perizinan jam kerja parsial bagi pegawai (dosen dan tenaga kependidikan), seperti izin keluar kampus sementara untuk urusan dinas/pribadi, izin datang terlambat karena kendala darurat, serta izin pulang lebih awal. Izin yang disetujui secara otomatis terintegrasi dengan catatan presensi harian pegawai.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth / Permission |
|---|---|---|---|
| GET | `/api/simpeg/izin-kerja/masters` | Data master referensi jenis izin jam kerja | ✅ `simpeg.izin_kerja.read` |
| GET | `/api/simpeg/izin-kerja` | Daftar pengajuan izin jam kerja | ✅ `simpeg.izin_kerja.read` |
| GET | `/api/simpeg/izin-kerja/{id}` | Detail rincian pengajuan izin jam kerja | ✅ `simpeg.izin_kerja.read` |
| POST | `/api/simpeg/izin-kerja` | Buat pengajuan izin jam kerja baru | ✅ `simpeg.izin_kerja.create` |
| POST/PUT | `/api/simpeg/izin-kerja/{id}` | Perbarui pengajuan izin jam kerja | ✅ `simpeg.izin_kerja.update` |
| POST | `/api/simpeg/izin-kerja/{id}/approve` | Persetujuan / penolakan atasan atau HR | ✅ `simpeg.izin_kerja.approve` |
| DELETE | `/api/simpeg/izin-kerja/{id}` | Hapus pengajuan izin jam kerja | ✅ `simpeg.izin_kerja.delete` |

---

## 1. GET /api/simpeg/izin-kerja/masters
Mengambil data master kategori/jenis izin jam kerja yang aktif secara dinamis.

### Response Sukses (200 OK)
```json
{
  "status": "success",
  "message": "Master jenis izin jam kerja berhasil diambil",
  "data": {
    "jenis_izin": [
      {
        "id": 1,
        "kode": "KELUAR_KANTOR",
        "nama": "Izin Meninggalkan Tempat Kerja Sementara",
        "kategori": "keluar_kantor",
        "keterangan": "Meninggalkan kampus maksimal beberapa jam untuk keperluan dinas/pribadi mendesak",
        "urutan": 1,
        "is_active": true
      }
    ]
  }
}
```

---

## 2. GET /api/simpeg/izin-kerja
Mengambil daftar pengajuan izin jam kerja dengan filter dan paginasi.

### Query Parameters
- `page`: integer (default: 1)
- `limit`: integer (default: 15)
- `search`: string (mencari alasan, nama pegawai, NIP, NIDN)
- `jenis_izin_id`: integer
- `status`: string (`menunggu`, `disetujui`, `ditolak`)
- `tanggal_mulai`: date (YYYY-MM-DD)
- `tanggal_selesai`: date (YYYY-MM-DD)
- `sort_by`: string (default: `created_at`)
- `sort_dir`: string (`asc`, `desc`, default: `desc`)

---

## 3. POST /api/simpeg/izin-kerja
Mengajukan permohonan izin jam kerja. Pegawai mandiri otomatis tersambung ke `pegawai_id` akun login.

### Request Body (Multipart/Form-Data)
- `pegawai_id`: integer (opsional bagi staf mandiri, wajib jika dibuat oleh admin HR)
- `jenis_izin_id`: integer (required, exists di `simpeg_master_jenis_izin_jam_kerja`)
- `tanggal_izin`: date `YYYY-MM-DD` (required)
- `jam_mulai`: string `H:i` (required)
- `jam_selesai`: string `H:i` (required)
- `alasan`: string (required)
- `berkas_bukti`: file (nullable, max: 5MB, mimes: pdf, jpg, jpeg, png)

---

## 4. POST /api/simpeg/izin-kerja/{id}/approve
Menyetujui atau menolak izin jam kerja. Saat disetujui, event `IzinJamKerjaDisetujui` akan disinkronisasikan ke catatan kehadiran presensi hari bersangkutan.

### Request Body (JSON)
- `status`: string (required, `disetujui` atau `ditolak`)
- `catatan_approval`: string (nullable)
