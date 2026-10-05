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
| GET | `/api/v1/siakad/bimbingan/advisees` | Daftar mahasiswa bimbingan PA + riwayat sesi log (paginated) | ✅ |
| GET | `/api/v1/siakad/bimbingan/my-pa` | Data dosen PA mahasiswa login beserta riwayat catatan & KRS aktif | ✅ |
| GET | `/api/v1/siakad/bimbingan/catatan` | Daftar catatan bimbingan per mahasiswa | ✅ |
| POST | `/api/v1/siakad/bimbingan/catatan` | Simpan catatan bimbingan baru | ✅ |
| PUT | `/api/v1/siakad/bimbingan/catatan/{id}` | Perbarui catatan bimbingan | ✅ |
| DELETE | `/api/v1/siakad/bimbingan/catatan/{id}` | Hapus catatan bimbingan | ✅ |
| GET | `/api/v1/siakad/bimbingan/laporan` | Ambil laporan akhir PA per tahun akademik | ✅ |
| POST | `/api/v1/siakad/bimbingan/laporan` | Simpan/finalisasi laporan PA | ✅ |
| GET | `/api/v1/siakad/bimbingan/aktivitas` | Daftar aktivitas bimbingan PA per kelas (SIMPA) | ✅ |
| GET | `/api/v1/siakad/bimbingan/aktivitas/komposisi` | Hitung komposisi kelas (A/N/C/K) dinamis dari DB BAAK | ✅ |
| POST | `/api/v1/siakad/bimbingan/aktivitas` | Simpan / perbarui sesi aktivitas bimbingan PA | ✅ |
| DELETE | `/api/v1/siakad/bimbingan/aktivitas/{id}` | Hapus sesi aktivitas bimbingan PA | ✅ |
| GET | `/api/v1/siakad/bimbingan/aktivitas/cetak` | Cetak laporan resmi aktivitas bimbingan PDF | ✅ |

---

## [GET] /api/v1/siakad/bimbingan/rekap

Rekapitulasi per dosen PA: komposisi status mahasiswa bimbingan (point-in-time) +
aktivitas sesi bimbingan. Aktivitas (`total_bimbingan`, `butuh_khusus_aktif`,
`terakhir_bimbingan_at`) dapat dibatasi rentang tanggal.

#### Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `dosen_id` | integer | ❌ | — | Hanya dosen tertentu (kaprodi/admin) |
| `program_studi_id` | integer | ❌ | — | Filter homebase prodi dosen |
| `dari_tanggal` | date | ❌ | — | Batas awal aktivitas (`YYYY-MM-DD`) |
| `sampai_tanggal` | date | ❌ | — | Batas akhir aktivitas (`YYYY-MM-DD`) |
| `search` | string | ❌ | — | Pencarian nama dosen atau NIDN |
| `sort_by` | string | ❌ | `nama_lengkap` | Kolom pengurutan (`nama_lengkap`, `nidn`) |
| `sort_order` | string | ❌ | `asc` | Arah pengurutan: `asc` / `desc` |
| `per_page` | integer | ❌ | `15` | Jumlah rekaman data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Nomor halaman yang diminta |

**200 OK**
```json
{
    "status": "success",
    "message": "Rekap bimbingan PA berhasil dimuat",
    "data": [
        {
            "dosen_id": 3,
            "nama_lengkap": "Dr. Budi Santoso",
            "nidn": "0011223344",
            "program_studi": "Informatika",
            "komposisi": { "aktif": 12, "cuti": 1, "mangkir": 0, "keluar": 0, "lulus": 0, "total": 13 },
            "butuh_khusus_aktif": 1,
            "total_bimbingan": 8,
            "terakhir_bimbingan_at": "2026-09-20",
            "belum_bimbingan": false
        }
    ]
}
```

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda tidak memiliki izin untuk melihat data rekap ini."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "The given data was invalid.",
    "errors": {
        "dari_tanggal": [
            "Format tanggal dari_tanggal tidak valid."
        ],
        "dosen_id": [
            "Dosen tidak ditemukan."
        ]
    }
}
```

> Catatan: filter tanggal hanya membatasi agregat aktivitas sesi
> (`tanggal_bimbingan`, fallback `created_at` bila kosong); komposisi status
> mahasiswa selalu kondisi terkini.

---

## [GET] /api/v1/siakad/bimbingan/advisees

Mengambil daftar mahasiswa bimbingan PA beserta rekap catatan dan status KRS (paginated).

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `dosen_id` | integer | ❌ | — | ID Dosen PA (otomatis diambil dari user login jika role dosen) |
| `status_mahasiswa` | string | ❌ | — | Filter status (aktif, cuti, mangkir, lulus, keluar) |
| `search` | string | ❌ | — | Pencarian nama atau NIM mahasiswa |
| `per_page` | integer | ❌ | 15 | Jumlah data per halaman |
| `page` | integer | ❌ | 1 | Nomor halaman |

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Daftar mahasiswa bimbingan PA berhasil dimuat",
    "data": [
        {
            "id": 14,
            "nim": "202401001",
            "nama_lengkap": "Ahmad Dani",
            "angkatan": "2024",
            "status": "aktif",
            "program_studi": "Teknik Informatika",
            "total_catatan": 2,
            "butuh_khusus": false
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

## [GET] /api/v1/siakad/bimbingan/my-pa

Mengambil data dosen PA bagi mahasiswa yang sedang login beserta riwayat catatan bimbingan dan KRS semester aktif.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Data dosen PA berhasil dimuat",
    "data": {
        "mahasiswa": {
            "id": 14,
            "nim": "202401001",
            "nama_lengkap": "Ahmad Dani",
            "angkatan": "2024",
            "program_studi": "Teknik Informatika"
        },
        "dosen_pa": {
            "id": 5,
            "nama_lengkap": "Dr. Ir. Hendra Gunawan",
            "nidn": "0011223344",
            "nip": "198001012005011001",
            "email": "hendra@kampus.ac.id",
            "telepon": "081234567890",
            "program_studi": "Teknik Informatika",
            "jabatan_akademik": "Lektor Kepala"
        },
        "krs_aktif": {
            "id": 22,
            "status": "disetujui",
            "total_sks": 20
        },
        "catatan": [],
        "total_bimbingan": 0
    }
}
```

### Response Error
**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Unauthenticated."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data mahasiswa tidak ditemukan untuk akun ini."
}
```

---

## [GET] /api/v1/siakad/bimbingan/catatan

Mengambil daftar riwayat catatan bimbingan untuk mahasiswa tertentu.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Data catatan bimbingan berhasil diambil.",
    "data": [
        {
            "id": 1,
            "mahasiswa_id": 14,
            "dosen_id": 5,
            "tanggal_bimbingan": "2026-10-01",
            "kategori": "akademik",
            "isi": "Konsultasi pengambilan sks semester ganjil.",
            "kesimpulan": "Disetujui 20 SKS.",
            "butuh_penanganan_khusus": false
        }
    ]
}
```

### Response Error (401 / 403)
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

---

## [POST] /api/v1/siakad/bimbingan/catatan

Menyimpan catatan bimbingan baru untuk mahasiswa bimbingan.

### Request Body
```json
{
    "mahasiswa_id": 14,
    "tanggal_bimbingan": "2026-10-01",
    "kategori": "akademik",
    "isi": "Konsultasi rencana studi.",
    "kesimpulan": "Fokus pada mata kuliah prasyarat.",
    "butuh_penanganan_khusus": false
}
```

### Response Sukses (201 Created)
```json
{
    "status": "success",
    "message": "Catatan bimbingan berhasil disimpan.",
    "data": {
        "id": 1,
        "mahasiswa_id": 14,
        "dosen_id": 5,
        "tanggal_bimbingan": "2026-10-01",
        "kategori": "akademik",
        "isi": "Konsultasi rencana studi.",
        "kesimpulan": "Fokus pada mata kuliah prasyarat.",
        "butuh_penanganan_khusus": false
    }
}
```

### Response Error (422 Unprocessable Entity)
```json
{
    "status": "error",
    "message": "Validation failed",
    "errors": {
        "isi": ["The isi field is required."]
    }
}
```

---

## [PUT] /api/v1/siakad/bimbingan/catatan/{id}

Memperbarui isi catatan bimbingan yang telah dicatat sebelumnya.

### Request Body
```json
{
    "tanggal_bimbingan": "2026-10-01",
    "kategori": "akademik",
    "isi": "Revisi konsultasi rencana studi.",
    "kesimpulan": "Disetujui 22 SKS.",
    "butuh_penanganan_khusus": false
}
```

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Catatan bimbingan berhasil diperbarui.",
    "data": {
        "id": 1,
        "mahasiswa_id": 14,
        "dosen_id": 5,
        "tanggal_bimbingan": "2026-10-01",
        "kategori": "akademik",
        "isi": "Revisi konsultasi rencana studi.",
        "kesimpulan": "Disetujui 22 SKS.",
        "butuh_penanganan_khusus": false
    }
}
```

### Response Error (403 Forbidden / 404 Not Found)
```json
{
    "status": "error",
    "message": "Catatan ini milik PA lain."
}
```

---

## [DELETE] /api/v1/siakad/bimbingan/catatan/{id}

Menghapus catatan bimbingan PA.

### Response Sukses (200 OK)
```json
{
    "status": "success",
    "message": "Catatan bimbingan berhasil dihapus.",
    "data": null
}
```

### Response Error (403 Forbidden / 404 Not Found)
```json
{
    "status": "error",
    "message": "Catatan ini milik PA lain."
}
```

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
