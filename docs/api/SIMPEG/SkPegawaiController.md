# Dokumentasi API: Repositori & Arsip SK Pegawai Mandiri (SkPegawaiController)

> **Modul**: SIMPEG (Kepegawaian Terintegrasi)  
> **Base URL**: `/api/simpeg/sk-pegawai`  
> **Autentikasi**: Bearer Token (Passport)  
> **Dibuat**: 2026-09-16  

Modul ini mengelola repositori berkas Surat Keputusan (SK) kepegawaian (SK Jabatan Fungsional/Struktural, SK Mengajar, SK Bimbingan, SK Penguji, SK Kepanitiaan, KGB). Pegawai dapat mengunggah secara mandiri untuk portofolio dan klaim BKD/Remunerasi, yang kemudian diverifikasi oleh verifikator SDM.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth / Permission |
|---|---|---|---|
| GET | `/api/simpeg/sk-pegawai/masters` | Data master kategori SK dinamis | ✅ `simpeg.sk_pegawai.read` |
| GET | `/api/simpeg/sk-pegawai` | Daftar arsip SK pegawai | ✅ `simpeg.sk_pegawai.read` |
| GET | `/api/simpeg/sk-pegawai/{id}` | Detail rincian berkas SK pegawai | ✅ `simpeg.sk_pegawai.read` |
| POST | `/api/simpeg/sk-pegawai` | Unggah dan laporkan SK baru | ✅ `simpeg.sk_pegawai.create` |
| POST/PUT | `/api/simpeg/sk-pegawai/{id}` | Perbarui laporan SK pegawai | ✅ `simpeg.sk_pegawai.update` |
| POST | `/api/simpeg/sk-pegawai/{id}/verify` | Verifikasi atau tolak keabsahan SK oleh SDM | ✅ `simpeg.sk_pegawai.verify` |
| DELETE | `/api/simpeg/sk-pegawai/{id}` | Hapus laporan SK pegawai | ✅ `simpeg.sk_pegawai.delete` |

---

## 1. GET /api/simpeg/sk-pegawai/masters
Mengambil data master kategori SK dinamis dari database.

### Response Sukses (200 OK)
```json
{
  "status": "success",
  "message": "Master kategori SK pegawai berhasil diambil",
  "data": {
    "kategori_sk": [
      {
        "id": 1,
        "kode": "SK_JAFUNG",
        "nama": "SK Jabatan Fungsional Dosen",
        "deskripsi": "Asisten Ahli, Lektor, Lektor Kepala, Guru Besar",
        "urutan": 1,
        "is_active": true
      }
    ]
  }
}
```

---

## 2. GET /api/simpeg/sk-pegawai
Mengambil daftar repositori SK dengan filtering dan paginasi.

### Query Parameters
- `page`: integer (default: 1)
- `limit`: integer (default: 15)
- `search`: string (mencari nomor SK, judul SK, nama pejabat, nama pegawai)
- `kategori_sk_id`: integer
- `status_verifikasi`: string (`menunggu`, `terverifikasi`, `ditolak`)
- `is_remunerasi_bkd`: boolean
- `tahun`: integer
- `sort_by`: string (default: `tanggal_sk`)
- `sort_dir`: string (`asc`, `desc`, default: `desc`)

---

## 3. POST /api/simpeg/sk-pegawai
Melaporkan SK mandiri oleh dosen/tendik atau diinput langsung oleh HR.

### Request Body (Multipart/Form-Data)
- `pegawai_id`: integer (opsional bagi staf mandiri)
- `kategori_sk_id`: integer (required, exists di `simpeg_master_kategori_sk`)
- `nomor_sk`: string (required)
- `judul_sk`: string (required)
- `pejabat_penetap`: string (required)
- `tanggal_sk`: date `YYYY-MM-DD` (required)
- `tanggal_berlaku`: date `YYYY-MM-DD` (required)
- `tanggal_berakhir`: date `YYYY-MM-DD` (nullable)
- `is_remunerasi_bkd`: boolean (default: false)
- `keterangan`: string (nullable)
- `file_sk`: file PDF/gambar (nullable, max: 10MB)

---

## 4. POST /api/simpeg/sk-pegawai/{id}/verify
Memvalidasi keabsahan dokumen SK oleh verifikator SDM.

### Request Body (JSON)
- `status_verifikasi`: string (required, `terverifikasi` atau `ditolak`)
- `catatan_verifikasi`: string (nullable)
