# Dokumentasi API: Kompetensi & Pelatihan Dosen (KompetensiController)

Modul ini mengelola data kompetensi dosen dan tenaga kependidikan: **Sertifikasi Dosen**, **Riwayat Tes Kemampuan (Bahasa & Potensi Akademik)**, dan **Riwayat Pelatihan / Diklat / Workshop**.

---

## 1. Master Data Kompetensi
Mengambil semua data referensi dinamis (Zero Hardcode).

- **Method**: `GET`
- **URL**: `/api/simpeg/kompetensi/masters`
- **Headers**: `Authorization: Bearer <token>`
- **Response**:
  ```json
  {
    "status": "success",
    "message": "Master data kompetensi berhasil diambil",
    "data": {
      "jenis_sertifikasi": [ ... ],
      "jenis_tes": [ ... ],
      "jenis_pelatihan": [ ... ],
      "peran_pelatihan": [ ... ],
      "tingkat_kegiatan": [ ... ]
    }
  }
  ```

---

## 2. Sertifikasi Dosen

### A. List Sertifikasi
- **Method**: `GET`
- **URL**: `/api/simpeg/kompetensi/sertifikasi`
- **Query Params**: `page`, `per_page`, `search`, `jenis_sertifikasi_id`, `tahun`, `sort_by`, `sort_order`

### B. Tambah Sertifikasi
- **Method**: `POST`
- **URL**: `/api/simpeg/kompetensi/sertifikasi`
- **Content-Type**: `multipart/form-data`
- **Body**:
  - `pegawai_id`: required, integer
  - `jenis_sertifikasi_id`: required, integer
  - `nama_sertifikat`: required, string
  - `bidang_studi`: required, string
  - `nomor_registrasi`: optional, string
  - `nomor_sk`: optional, string
  - `tahun_sertifikasi`: required, integer
  - `penyelenggara`: required, string
  - `tautan`: optional, url
  - `file`: optional, file (PDF/JPG/PNG max 10MB)

### C. Update Sertifikasi
- **Method**: `POST` / `PUT`
- **URL**: `/api/simpeg/kompetensi/sertifikasi/{id}`

### D. Hapus Sertifikasi
- **Method**: `DELETE`
- **URL**: `/api/simpeg/kompetensi/sertifikasi/{id}`

---

## 3. Riwayat Tes Kemampuan (TOEFL, TKDA, TPA)

### A. List Tes
- **Method**: `GET`
- **URL**: `/api/simpeg/kompetensi/tes`
- **Query Params**: `page`, `per_page`, `search`, `jenis_tes_id`, `tahun`, `sort_by`, `sort_order`

### B. Tambah Riwayat Tes
- **Method**: `POST`
- **URL**: `/api/simpeg/kompetensi/tes`
- **Content-Type**: `multipart/form-data`
- **Body**:
  - `pegawai_id`: required, integer
  - `jenis_tes_id`: required, integer
  - `nama_tes`: required, string
  - `penyelenggara`: required, string
  - `tahun`: required, integer
  - `skor`: required, numeric
  - `masa_berlaku`: optional, date
  - `tautan`: optional, url
  - `file`: optional, file (PDF/JPG/PNG max 10MB)

### C. Update Riwayat Tes
- **Method**: `POST` / `PUT`
- **URL**: `/api/simpeg/kompetensi/tes/{id}`

### D. Hapus Riwayat Tes
- **Method**: `DELETE`
- **URL**: `/api/simpeg/kompetensi/tes/{id}`

---

## 4. Riwayat Pelatihan, Diklat & Workshop

### A. List Pelatihan
- **Method**: `GET`
- **URL**: `/api/simpeg/kompetensi/pelatihan`
- **Query Params**: `page`, `per_page`, `search`, `jenis_pelatihan_id`, `peran_id`, `tingkat_id`, `sort_by`, `sort_order`

### B. Tambah Pelatihan
- **Method**: `POST`
- **URL**: `/api/simpeg/kompetensi/pelatihan`
- **Content-Type**: `multipart/form-data`
- **Body**:
  - `pegawai_id`: required, integer
  - `nama_kegiatan`: required, string
  - `jenis_pelatihan_id`: optional, integer
  - `peran_id`: required, integer
  - `tingkat_id`: optional, integer
  - `tanggal_mulai`: required, date
  - `tanggal_selesai`: optional, date
  - `jumlah_jam`: optional, integer
  - `penyelenggara`: required, string
  - `tempat`: optional, string
  - `nomor_sertifikat`: optional, string
  - `tautan`: optional, url
  - `file`: optional, file (PDF/JPG/PNG max 10MB)

### C. Update Pelatihan
- **Method**: `POST` / `PUT`
- **URL**: `/api/simpeg/kompetensi/pelatihan/{id}`

### D. Hapus Pelatihan
- **Method**: `DELETE`
- **URL**: `/api/simpeg/kompetensi/pelatihan/{id}`

---

## 5. Admin Pencarian & Rekapitulasi (Akreditasi & BKD)
Digunakan oleh Admin SDM untuk memfilter dosen berdasarkan sertifikasi, tes, atau pelatihan untuk laporan akreditasi institusi.

- **Method**: `GET`
- **URL**: `/api/simpeg/kompetensi/pencarian`
- **Query Params**:
  - `kategori`: required (`sertifikat`, `tes`, `pelatihan`)
  - `search`: filter nama/NIP/NIDN
  - `unit_kerja_id`: filter prodi / unit kerja
  - `jenis_id`: filter ID jenis
  - `skor_min`: minimal skor tes (jika kategori=tes)
  - `tahun`: filter tahun
  - `page`, `per_page`
- **Permissions**: `simpeg.kompetensi.manage`
