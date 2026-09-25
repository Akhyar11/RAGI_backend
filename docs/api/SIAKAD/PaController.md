# PaController

> **Modul**: SIAKAD / **Base URL**: `/api/v1/siakad/bimbingan` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-09-26

Manajemen Pembimbing Akademik (PA): rekap bimbingan dosen, penugasan mahasiswa bimbingan, pencatatan log sesi jurnal per pertemuan, pelaporan berkala per kelas/angkatan (Model SIMPA), dan cetak laporan PDF resmi.

## Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ untuk POST/PUT/PATCH |

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/siakad/bimbingan/rekap` | Rekapitulasi bimbingan seluruh PA / dosen bersangkutan | ✅ |
| GET | `/api/v1/siakad/bimbingan/advisees` | Daftar mahasiswa bimbingan PA + riwayat sesi log | ✅ |
| GET | `/api/v1/siakad/bimbingan/catatan` | Daftar catatan bimbingan per mahasiswa | ✅ |
| POST | `/api/v1/siakad/bimbingan/catatan` | Simpan catatan bimbingan baru | ✅ |
| DELETE | `/api/v1/siakad/bimbingan/catatan/{id}` | Hapus catatan bimbingan | ✅ |
| GET | `/api/v1/siakad/bimbingan/laporan` | Ambil laporan akhir PA per tahun akademik | ✅ |
| POST | `/api/v1/siakad/bimbingan/laporan` | Simpan/finalisasi laporan PA | ✅ |
| GET | `/api/v1/siakad/bimbingan/aktivitas` | Daftar aktivitas bimbingan PA per kelas (SIMPA) | ✅ |
| GET | `/api/v1/siakad/bimbingan/aktivitas/komposisi` | Hitung komposisi kelas (A/N/C/K) dinamis dari DB BAAK | ✅ |
| POST | `/api/v1/siakad/bimbingan/aktivitas` | Simpan / perbarui sesi aktivitas bimbingan PA | ✅ |
| DELETE | `/api/v1/siakad/bimbingan/aktivitas/{id}` | Hapus sesi aktivitas bimbingan PA | ✅ |
| GET | `/api/v1/siakad/bimbingan/aktivitas/cetak` | Cetak laporan resmi aktivitas bimbingan PDF | ✅ |

---

## [GET] /api/v1/siakad/bimbingan/aktivitas

Mengambil riwayat sesi aktivitas bimbingan PA yang dicatat per kelas dan per tanggal.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tahun_akademik_id` | integer | ❌ | — | Filter ID Tahun Akademik |
| `kelas` | string | ❌ | — | Filter kelas atau angkatan bimbingan |
| `dosen_id` | integer | ❌ | — | ID Dosen (otomatis diambil dari user login jika role dosen) |

### Response Sukses (200 OK)

```json
{
  "success": true,
  "message": "Daftar aktivitas bimbingan PA berhasil diambil",
  "data": [
    {
      "id": 1,
      "dosen_id": 2,
      "tahun_akademik_id": 1,
      "kelas": "Angkatan 2024",
      "tanggal": "2026-09-26",
      "pertemuan_ke": 1,
      "mhs_aktif": 3,
      "mhs_nonaktif": 0,
      "mhs_cuti": 0,
      "mhs_keluar": 0,
      "kondisi_mahasiswa": "Mahasiswa aktif mengikuti perkuliahan",
      "penanganan_mahasiswa": "Tidak ada kendala khusus",
      "kesimpulan": "Perkuliahan semester berjalan lancar",
      "status": "final"
    }
  ]
}
```

---

## [GET] /api/v1/siakad/bimbingan/aktivitas/komposisi

Menghitung statistik komposisi mahasiswa (Aktif, Non-Aktif/Mangkir, Cuti, Keluar/Lulus) secara dinamis dari tabel `siakad_mahasiswa` tanpa hardcode.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `kelas` | string | ❌ | — | Filter kelas atau angkatan bimbingan |
| `dosen_id` | integer | ❌ | — | ID Dosen |

### Response Sukses (200 OK)

```json
{
  "success": true,
  "message": "Komposisi mahasiswa kelas berhasil dihitung",
  "data": {
    "kelas": "Angkatan 2024",
    "total_mahasiswa": 3,
    "mhs_aktif": 3,
    "mhs_nonaktif": 0,
    "mhs_cuti": 0,
    "mhs_keluar": 0
  }
}
```

---

## [POST] /api/v1/siakad/bimbingan/aktivitas

Menyimpan atau memperbarui laporan sesi aktivitas bimbingan PA per kelas.

### Request Body

```json
{
  "id": 1,
  "tahun_akademik_id": 1,
  "kelas": "Angkatan 2024",
  "tanggal": "2026-09-26",
  "mhs_aktif": 3,
  "mhs_nonaktif": 0,
  "mhs_cuti": 0,
  "mhs_keluar": 0,
  "kondisi_mahasiswa": "Perkuliahan berjalan lancar",
  "penanganan_mahasiswa": "Pendampingan 1 mahasiswa transfer",
  "kesimpulan": "KRS dan konversi selesai disetujui"
}
```

### Response Sukses (200 OK)

```json
{
  "success": true,
  "message": "Aktivitas bimbingan PA berhasil disimpan",
  "data": {
    "id": 1,
    "dosen_id": 2,
    "tahun_akademik_id": 1,
    "kelas": "Angkatan 2024",
    "tanggal": "2026-09-26",
    "mhs_aktif": 3
  }
}
```

---

## [GET] /api/v1/siakad/bimbingan/aktivitas/cetak

Menghasilkan format cetak printable PDF resmi laporan aktivitas bimbingan PA per kelas lengkap dengan kop surat, tabel pertemuan (Per, Tanggal, Aktifitas Kuliah A/N/C/K, Kondisi Mahasiswa, Penanganan Khusus, Kesimpulan), dan tanda tangan dosen PA / pimpinan.
