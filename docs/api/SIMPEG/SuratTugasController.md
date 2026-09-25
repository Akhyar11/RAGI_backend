# Dokumentasi API: Surat Tugas, Logistik Dinas & LPJ (SuratTugasController)

> **Modul**: SIMPEG (Kepegawaian Terintegrasi)  
> **Base URL**: `/api/simpeg/surat-tugas`  
> **Autentikasi**: Bearer Token (Passport)  
> **Dibuat**: 2026-09-16  

Modul ini mengelola permohonan surat tugas kedinasan luar kampus, penugasan armada kendaraan dinas dan driver, pengelolaan anggota tim rombongan, persetujuan & penomoran resmi oleh pimpinan/admin, otomatisasi status presensi kepegawaian menjadi "dinas", serta unggah laporan pertanggungjawaban (LPJ).

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth / Permission |
|---|---|---|---|
| GET | `/api/simpeg/surat-tugas/masters` | Data master jenis transportasi & kategori kegiatan | ✅ `simpeg.surat_tugas.read` |
| GET | `/api/simpeg/surat-tugas` | Daftar permohonan surat tugas dinas | ✅ `simpeg.surat_tugas.read` |
| GET | `/api/simpeg/surat-tugas/{id}` | Detail surat tugas & anggota rombongan tim | ✅ `simpeg.surat_tugas.read` |
| POST | `/api/simpeg/surat-tugas` | Buat permohonan surat tugas baru | ✅ `simpeg.surat_tugas.create` |
| POST/PUT | `/api/simpeg/surat-tugas/{id}` | Perbarui permohonan surat tugas | ✅ `simpeg.surat_tugas.update` |
| POST | `/api/simpeg/surat-tugas/{id}/approve` | Persetujuan/penolakan pimpinan & penomoran resmi | ✅ `simpeg.surat_tugas.approve` |
| POST | `/api/simpeg/surat-tugas/{id}/lpj` | Unggah dokumen LPJ & laporan hasil dinas | ✅ `simpeg.surat_tugas.update` |
| DELETE | `/api/simpeg/surat-tugas/{id}` | Hapus pengajuan surat tugas | ✅ `simpeg.surat_tugas.delete` |

---

## 1. GET /api/simpeg/surat-tugas/masters
Mengambil semua data referensi dinamis: moda transportasi dan kategori kegiatan tugas dinas.

### Response Sukses (200 OK)
```json
{
  "status": "success",
  "message": "Master data surat tugas dinas berhasil diambil",
  "data": {
    "kategori_kegiatan": [
      {
        "id": 1,
        "kode": "KONSOR",
        "nama": "Konsorsium / Pertemuan Ilmiah",
        "deskripsi": "Seminar, lokakarya, atau konferensi ilmiah",
        "urutan": 1,
        "is_active": true
      }
    ],
    "jenis_transportasi": [
      {
        "id": 1,
        "kode": "MOBIL_DINAS",
        "nama": "Mobil Dinas Kampus",
        "is_kendaraan_kampus": true,
        "urutan": 1,
        "is_active": true
      }
    ]
  }
}
```

---

## 2. GET /api/simpeg/surat-tugas
Mengambil daftar surat tugas dengan filtering dan pagination.

### Query Parameters
- `page`: integer (default: 1)
- `per_page`: integer (default: 15, max: 100)
- `search`: string (mencari nomor surat, nama kegiatan, lokasi tujuan, nama pegawai)
- `status`: string (`draft`, `diajukan`, `disetujui`, `ditolak`, `selesai`)
- `kategori_kegiatan_id`: integer
- `jenis_transportasi_id`: integer
- `tahun`: integer (contoh: 2026)
- `sort_by`: string (`created_at`, `tanggal_berangkat`, `status`, `nomor_surat`)
- `sort_order`: string (`asc` / `desc`)

---

## 3. POST /api/simpeg/surat-tugas
Mengajukan permohonan surat tugas dinas luar.

### Headers
- `Content-Type`: `multipart/form-data`
- `Authorization`: `Bearer {token}`

### Request Body
- `pegawai_id`: integer, required (ID Pegawai penanggung jawab / ketua rombongan)
- `kategori_kegiatan_id`: integer, required (ID Kategori kegiatan dinas)
- `jenis_transportasi_id`: integer, required (ID Moda transportasi)
- `nama_kegiatan`: string, required (Nama kegiatan tugas)
- `tempat_berangkat`: string, required (Tempat asal keberangkatan)
- `lokasi_tujuan`: string, required (Kota / institusi tujuan)
- `tanggal_berangkat`: date, required (YYYY-MM-DD)
- `tanggal_kembali`: date, required (YYYY-MM-DD)
- `tanggal_mulai`: date, required (YYYY-MM-DD)
- `tanggal_selesai`: date, required (YYYY-MM-DD)
- `maksud_tujuan`: text, required (Uraian maksud kedinasan)
- `beban_anggaran`: string, nullable
- `estimasi_biaya`: numeric, nullable
- `keterangan`: text, nullable
- `kendaraan_dinas`: string, nullable (Nomor polisi / tipe mobil dinas jika menggunakan armada kampus)
- `nama_driver`: string, nullable
- `kontak_driver`: string, nullable
- `anggota`: array, nullable
  - `anggota[0][pegawai_id]`: integer, required
  - `anggota[0][peran]`: string, nullable (contoh: 'Anggota', 'Narasumber', 'Fasilitator')
  - `anggota[0][keterangan]`: string, nullable
- `file_surat_tugas`: file (PDF max 10MB), optional

---

## 4. POST /api/simpeg/surat-tugas/{id}/approve
Menyetujui atau menolak permohonan dinas luar, menetapkan nominal anggaran yang disetujui, dan menerbitkan nomor surat tugas resmi.

> ℹ️ **Catatan Otomatisasi**:
> 1. **Presensi Otomatis**: Saat disetujui, sistem secara otomatis menerbitkan data presensi kepegawaian berstatus **DINAS** untuk ketua rombongan dan seluruh anggota pada rentang tanggal dinas melalui Event `SuratTugasDisetujui`.
> 2. **Integrasi SIKEU Otomatis**: Jika `nominal_disetujui > 0`, sistem otomatis membuat antrean permohonan pencairan kasbon/panjar perjalanan dinas di modul SIKEU (`sikeu_pengajuan_pencairan_kas` dengan kategori `non_barang`, kanal `simpeg_surat_tugas`, status `pending_keuangan`) serta rincian item (`sikeu_pengajuan_item`). Jika `nominal_disetujui = 0` (contoh: pelatihan daring via Zoom / non-biaya), pengajuan ke SIKEU di-bypass (tidak dibuat).

### Request Body
- `status`: string, required (`disetujui` / `ditolak`)
- `nomor_surat`: string, required if `status = disetujui` (Nomor resmi surat tugas)
- `nominal_disetujui`: numeric, optional (Nominal dana dinas yang disetujui. Default diambil dari estimasi biaya diajukan jika tidak diisi. Isi 0 jika tugas non-anggaran / daring Zoom)
- `catatan_approval`: string, optional
- `file_surat_tugas`: file (PDF max 10MB), optional (Surat bertandatangan)

---

## 5. POST /api/simpeg/surat-tugas/{id}/lpj
Mengunggah berkas laporan pertanggungjawaban (LPJ) kedinasan.

> 🔒 **Otorisasi Ketat**: Hanya **Penanggung Jawab Tugas** (`pegawai_id`) atau **Admin / Pejabat Approver** yang berhak mengunggah berkas LPJ. Anggota rombongan dinas hanya berstatus *read-only* (melihat data dan mengunduh berkas LPJ yang sudah diunggah).  
> 💰 **Kalkulasi Selisih & Pengembalian Dana**: Sistem secara otomatis menghitung selisih panjar disetujui dikurangi biaya realisasi (`sisa_nominal`). Jika biaya terpakai lebih kecil daripada panjar disetujui (contoh: disetujui Rp 500.000, terpakai Rp 300.000), sistem secara tegas menampilkan **Dana yang Harus Dikembalikan ke Kas Kampus: Rp 200.000**. Jika terpakai lebih besar, menampilkan klaim kurang bayar (reimbursement). Nilai ini otomatis disinkronkan ke pembukuan transaksi SIKEU.

### Request Body
- `file_lpj`: file (PDF max 10MB), required (1 file dokumen bundel LPJ lengkap memuat laporan kegiatan, foto dokumentasi, dan scan rekap bukti/slip pembayaran)
- `laporan_kegiatan`: string, optional (Ringkasan laporan capaian hasil dinas)
- `biaya_realisasi`: numeric, optional (Total realisasi pengeluaran dinas yang terpakai untuk rekonsiliasi kas SIKEU)

---

## 6. POST /api/simpeg/surat-tugas/{id}/konfirmasi-panjar
Konfirmasi penerimaan panjar oleh dosen/pegawai pemohon (Tahap 4 alur 8-tahap integrasi).
Setelah Bagian Keuangan (SIKEU) menyetujui besaran dana panjar dan menetapkan unit kas, dosen pemohon mengonfirmasi kesediaan tugas dinas dan nominal dana tersebut sehingga status pengajuan di SIKEU siap untuk dicairkan oleh bendahara.

> 🔒 **Otorisasi**: Hanya **Penanggung Jawab Tugas** (`pegawai_id`) atau **Admin SIMPEG** yang berhak melakukan konfirmasi.  
> ⚠️ **Syarat Status**: Surat tugas harus memiliki `status_pencairan = panjar_disetujui`.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Konfirmasi panjar berhasil disimpan. Pengajuan siap dicairkan oleh Keuangan.",
    "data": {
        "id": 1,
        "nomor_surat": "ST/FT/001/2026",
        "status": "disetujui",
        "status_pencairan": "siap_cair",
        "nominal_disetujui": "450000.00"
    }
}
```

