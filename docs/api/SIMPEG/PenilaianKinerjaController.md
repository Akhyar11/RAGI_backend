# Dokumentasi API: Sasaran Kinerja Pegawai (SKP) Butir-per-Butir (PenilaianKinerjaController)

> **Modul**: SIMPEG (Kepegawaian Terintegrasi)  
> **Base URL**: `/api/simpeg/penilaian-kinerja`  
> **Autentikasi**: Bearer Token (Passport)  
> **Dibuat**: 2026-09-16  

Modul ini mengelola siklus hidup Sasaran Kinerja Pegawai (SKP) butir-per-butir berdasarkan target dan realisasi Tridharma Perguruan Tinggi (Pendidikan, Penelitian, Pengabdian, Penunjang, Tugas Tambahan). Siklus alur meliputi penyusunan target (`draft`), pengajuan ke atasan (`diajukan`), persetujuan atasan (`disetujui`), pengisian realisasi & bukti luaran fisik, hingga evaluasi akhir skor SKP, BKD, dan penetapan predikat kinerja (`dinilai`).

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth / Permission |
|---|---|---|---|
| GET | `/api/simpeg/penilaian-kinerja/masters` | Data master kategori SKP & daftar pejabat penilai | ✅ `simpeg.kinerja.read` |
| GET | `/api/simpeg/penilaian-kinerja` | Daftar SKP pegawai dengan filter & paginasi | ✅ `simpeg.kinerja.read` |
| GET | `/api/simpeg/penilaian-kinerja/{id}` | Detail rincian SKP dan butir target/realisasi | ✅ `simpeg.kinerja.read` |
| POST | `/api/simpeg/penilaian-kinerja` | Susun sasaran kerja SKP baru (Draft) | ✅ `simpeg.kinerja.create` |
| PUT | `/api/simpeg/penilaian-kinerja/{id}` | Perbarui draf susunan butir SKP | ✅ `simpeg.kinerja.update` |
| DELETE | `/api/simpeg/penilaian-kinerja/{id}` | Hapus dokumen SKP | ✅ `simpeg.kinerja.delete` |
| POST | `/api/simpeg/penilaian-kinerja/{id}/submit-target` | Ajukan target sasaran kerja ke atasan penilai | ✅ `simpeg.kinerja.create` |
| POST | `/api/simpeg/penilaian-kinerja/{id}/approve-target` | Setujui target sasaran kerja oleh pejabat penilai | ✅ `simpeg.kinerja.evaluate` |
| POST | `/api/simpeg/penilaian-kinerja/{id}/submit-realisasi` | Isi realisasi capaian & unggah berkas bukti fisik luaran | ✅ `simpeg.kinerja.create` |
| POST | `/api/simpeg/penilaian-kinerja/{id}/evaluate` | Evaluasi capaian, skor SKP & BKD, serta penetapan predikat | ✅ `simpeg.kinerja.evaluate` |

---

## 1. GET /api/simpeg/penilaian-kinerja/masters
Mengambil data master kategori SKP yang aktif dan daftar calon pejabat penilai aktif.

### Response Sukses (200 OK)
```json
{
  "status": "success",
  "data": {
    "kategori_skp": [
      {
        "id": 1,
        "nama": "Pendidikan dan Pengajaran",
        "kode": "PENDIDIKAN",
        "deskripsi": "Pelaksanaan perkuliahan, bimbingan tugas akhir, pengujian...",
        "urutan": 1,
        "is_active": true
      }
    ],
    "pejabat_penilai": [
      {
        "id": 10,
        "nama_lengkap": "Dr. Ir. Budi Santoso, M.Kom.",
        "nip": "198001012005011001",
        "jabatan_terakhir": "Dekan Fakultas Ilmu Komputer"
      }
    ]
  }
}
```

---

## 2. GET /api/simpeg/penilaian-kinerja
Mengambil daftar dokumen evaluasi SKP dengan filter dan paginasi.

### Query Parameters
- `page`: integer (default: 1)
- `limit`: integer (default: 15)
- `search`: string (nama pegawai, NIP, NIDN)
- `tahun`: integer
- `semester`: string (`ganjil`, `genap`, `tahunan`)
- `status`: string (`draft`, `diajukan`, `disetujui`, `dinilai`)
- `predikat`: string (`sangat_baik`, `baik`, `cukup`, `kurang`, `sangat_kurang`)
- `orderBy`: string (`tahun`, `status`, `nilai_skp`, `id`)
- `orderDir`: string (`asc`, `desc`)

---

## 3. POST /api/simpeg/penilaian-kinerja
Menyusun SKP baru dengan butir target kinerja.

### Payload Request (JSON)
```json
{
  "pegawai_id": 15,
  "tahun": 2026,
  "semester": "ganjil",
  "pejabat_penilai_id": 10,
  "items": [
    {
      "kategori_skp_id": 1,
      "uraian_tugas": "Melaksanakan pengajaran mata kuliah Pemrograman Berorientasi Objek (3 SKS)",
      "target_output": "1 Dokumen Nilai Perkuliahan & RPS",
      "target_mutu": 100,
      "target_waktu": "6 Bulan",
      "target_biaya": null
    }
  ]
}
```

---

## 4. POST /api/simpeg/penilaian-kinerja/{id}/submit-realisasi
Mengisi realisasi capaian butir tugas dan mengunggah berkas bukti fisik (PDF/ZIP).

### Payload Request (Multipart Form Data)
- `items[0][id]`: `1`
- `items[0][realisasi_output]`: `1 Dokumen Nilai Perkuliahan & RPS Final`
- `items[0][realisasi_mutu]`: `98`
- `items[0][realisasi_waktu]`: `6 Bulan`
- `items[0][realisasi_biaya]`: `0`
- `items[0][keterangan]`: `Selesai sesuai kalender akademik`
- `items[0][berkas_bukti]`: `file.pdf` (max 20MB)

---

## 5. POST /api/simpeg/penilaian-kinerja/{id}/evaluate
Evaluasi akhir capaian oleh pejabat penilai / asesor SDM.

### Payload Request (JSON)
```json
{
  "nilai_skp": 92.50,
  "nilai_bkd": 14.00,
  "predikat": "sangat_baik",
  "catatan_evaluator": "Kinerja pengajaran dan publikasi sangat memuaskan melampaui target standar.",
  "items": [
    {
      "id": 1,
      "nilai_capaian": 95.00
    }
  ]
}
```
