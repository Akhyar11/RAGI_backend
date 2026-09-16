# Dokumentasi API: Agregasi Portofolio Tridharma Terpadu Dosen (TridharmaDossierController)

> **Modul**: SIMPEG (Kepegawaian Terintegrasi)  
> **Base URL**: `/api/simpeg/pegawai/{id}/tridharma-dossier`  
> **Autentikasi**: Bearer Token (Passport)  
> **Dibuat**: 2026-09-16  

Modul ini menyediakan endpoint agregasi terpadu (Unified Academic Dossier) yang menyatukan seluruh rekam jejak akademik dosen: **Pengajaran** (SIAKAD: kelas mata kuliah diampu, beban SKS, mahasiswa wali), **Penelitian & Publikasi** (SIPPM: proposal hibah riset, jurnal Scopus/Sinta, prosiding, HKI/paten, buku ajar), **Pengabdian Kepada Masyarakat** (SIPPM: proposal hibah PkM), serta **Penunjang & Kinerja** (SIMPEG: surat tugas kedinasan, SK mandiri penugasan, sertifikasi dosen/pelatihan, dan evaluasi nilai SKP & BKD) secara dinamis **tanpa duplikasi data**.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth / Permission |
|---|---|---|---|
| GET | `/api/simpeg/pegawai/{id}/tridharma-dossier` | Mengambil seluruh portofolio Tridharma dosen terpadu | ✅ `simpeg.pegawai.read` / `simpeg.kinerja.read` / Self |

---

## 1. GET /api/simpeg/pegawai/{id}/tridharma-dossier

Mengambil portofolio akademik lengkap dosen beserta metrik ringkasan untuk keperluan akreditasi prodi dan Beban Kerja Dosen (BKD).

### Response Sukses (200 OK)
```json
{
  "status": "success",
  "message": "Portofolio Tridharma terpadu berhasil dimuat.",
  "data": {
    "pegawai": {
      "id": 5,
      "nama_lengkap": "Dr. Ir. Ahmad Dahlan, M.T.",
      "nama_gelar": "Dr. Ir. Ahmad Dahlan, M.T.",
      "nip": "198805122015041003",
      "nidn": "0412058801",
      "nuptk": "9876543210123456",
      "unit_kerja": "Fakultas Ilmu Komputer",
      "program_studi": "Teknik Informatika",
      "jabatan_fungsional": "Lektor Kepala",
      "status_kepegawaian": "tetap",
      "sinta_id": "6021849",
      "scopus_id": "57201948211",
      "google_scholar_id": "abCdEfGAAAAJ",
      "orcid_id": "0000-0002-1825-0097"
    },
    "metrics": {
      "total_kelas_ajar": 12,
      "total_sks_ajar": 36,
      "total_mhs_wali": 45,
      "total_penelitian": 6,
      "total_dana_penelitian": 150000000.00,
      "total_pengabdian": 4,
      "total_dana_pengabdian": 45000000.00,
      "total_publikasi": 8,
      "total_publikasi_scopus": 3,
      "total_publikasi_sinta": 4,
      "total_hki_buku": 2,
      "total_surat_tugas": 5,
      "total_sk_penugasan": 7,
      "total_sertifikasi": 2,
      "total_pelatihan": 6,
      "rerata_nilai_skp": 91.50,
      "rerata_nilai_bkd": 14.25
    },
    "pengajaran": {
      "kelas": [
        {
          "id": 101,
          "kelas_id": 12,
          "kode_mk": "IF301",
          "nama_mk": "Rekayasa Perangkat Lunak",
          "sks": 3,
          "kode_kelas": "TI-3A",
          "nama_kelas": "Kelas A Reguler",
          "tahun_akademik": "2025/2026 Ganjil",
          "semester": "ganjil",
          "peran": "pengampu_utama"
        }
      ],
      "mahasiswa_wali": [
        {
          "id": 1,
          "nim": "230101001",
          "nama_lengkap": "Siti Aminah",
          "program_studi": "Teknik Informatika",
          "angkatan": "2023",
          "status": "aktif"
        }
      ]
    },
    "penelitian": {
      "hibah_ketua": [],
      "hibah_anggota": [],
      "publikasi": [],
      "hki_buku": []
    },
    "pengabdian": {
      "hibah_ketua": [],
      "hibah_anggota": []
    },
    "penunjang": {
      "surat_tugas": [],
      "sk_pegawai": [],
      "sertifikasi": [],
      "pelatihan": [],
      "kinerja_skp": []
    }
  }
}
```
