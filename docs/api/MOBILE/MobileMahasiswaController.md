# MobileMahasiswaController

> **Modul**: Aplikasi Mobile Mahasiswa (LMS, Presensi QR 30 Detik, SIAKAD, & SIKEU)  
> **Base URL**: `/api/v1/mobile/mahasiswa`  
> **Autentikasi**: Bearer Token (`auth:api`) + wajib memiliki profil `Mahasiswa` (`siakad_mahasiswa.user_id`)  
> **Dibuat**: 2026-11-09  
> **Diperbarui**: 2026-11-09

## Aturan Otorisasi & Periode Aktif

1. **Isolasi Peran Mahasiswa Murni**: Semua endpoint di bawah `/api/v1/mobile/mahasiswa/*` memvalidasi bahwa pengguna yang login memiliki profil `Mahasiswa` aktif di `siakad_mahasiswa`. Akun Super Admin atau Dosen tanpa profil `Mahasiswa` ditolak dengan status `403 Forbidden`.
2. **Default Periode Akademik Aktif**: Seluruh endpoint yang menerima query `?tahun_akademik_id=` secara otomatis menampilkan data **Periode Akademik Aktif (`is_active = true`)** apabila parameter tidak dikirim. Mahasiswa dapat berpindah ke periode lama menggunakan daftar dari `GET /api/v1/mobile/mahasiswa/periode`.

##Daftar Endpoint

| Method | Endpoint | Fungsi | Auth / Permission |
|---|---|---|---|
| GET | `/api/v1/mobile/mahasiswa/home` | Beranda terpadu (Profil, Periode Aktif, Sesi Presensi Aktif 30d, Jadwal Hari Ini, Ringkasan LMS, & Tagihan SIKEU) | ✅ `siakad.kelas.read` |
| GET | `/api/v1/mobile/mahasiswa/periode` | Daftar seluruh periode akademik + penanda periode aktif (`active_periode_id`) | ✅ `siakad.kelas.read` |
| GET | `/api/v1/mobile/mahasiswa/presensi/sesi-aktif` | Daftar sesi presensi kelas yang sedang dibuka dosen pada hari ini | ✅ `siakad.kelas.read` |
| POST | `/api/v1/mobile/mahasiswa/presensi/scan` | Scan QR (`RAG-PRESENSI:{pertemuan_id}:{token}`) atau input token 6 digit (rotasi 30 detik) | ✅ `lms.presensi.create` |
| POST | `/api/v1/mobile/mahasiswa/presensi/pertemuan/{pertemuanId}/izin` | Ajukan permohonan izin/sakit beserta unggah surat bukti (private storage) | ✅ `lms.presensi.create` |
| GET | `/api/v1/mobile/mahasiswa/presensi/rekap` | Rekapitulasi kehadiran per mata kuliah beserta status kelayakan UAS (`eligible_uas`) | ✅ `siakad.kelas.read` |
| GET | `/api/v1/mobile/mahasiswa/lms/overview` | Ringkasan dashboard LMS mahasiswa pada periode terpilih | ✅ `siakad.kelas.read` |
| GET | `/api/v1/mobile/mahasiswa/lms/kelas` | Daftar kelas perkuliahan LMS yang diikuti mahasiswa (paginated) | ✅ `siakad.kelas.read` |
| GET | `/api/v1/mobile/mahasiswa/lms/kelas/{kelasId}` | Detail kelas LMS (16 pertemuan, materi, tugas, quiz, absensi) | ✅ `siakad.kelas.read` |
| GET | `/api/v1/mobile/mahasiswa/lms/pertemuan/{pertemuanId}` | Detail satu pertemuan LMS (jurnal dosen, materi, tugas, quiz, status presensi) | ✅ `siakad.kelas.read` |
| GET | `/api/v1/mobile/mahasiswa/lms/tugas` | Daftar seluruh tugas mahasiswa lintas kelas (paginated) | ✅ `siakad.kelas.read` |
| GET | `/api/v1/mobile/mahasiswa/lms/tryout` | Daftar tryout kelas & ujian khusus di luar mata kuliah (paginated) | ✅ `siakad.kelas.read` |
| GET | `/api/v1/mobile/mahasiswa/lms/forum` | Daftar topik diskusi forum kelas (paginated) | ✅ `lms.forum.read` |
| GET | `/api/v1/mobile/mahasiswa/siakad/profil` | Biodata akademik mahasiswa, Dosen PA, & payload QR KTM Digital (`ktm_qr_payload`) | ✅ `siakad.kelas.read` |
| GET | `/api/v1/mobile/mahasiswa/siakad/jadwal` | Jadwal kuliah mingguan & daftar jadwal hari ini | ✅ `siakad.kelas.read` |
| GET | `/api/v1/mobile/mahasiswa/siakad/krs` | Kartu Rencana Studi (KRS) per periode akademik | ✅ `siakad.krs.read` |
| GET | `/api/v1/mobile/mahasiswa/siakad/khs` | Kartu Hasil Studi (KHS), IPS semester, & rincian nilai per periode | ✅ `siakad.nilai.read` |
| GET | `/api/v1/mobile/mahasiswa/siakad/transkrip` | Transkrip nilai kumulatif sementara, total SKS lulus, & IPK | ✅ `siakad.nilai.read` |
| GET | `/api/v1/mobile/mahasiswa/sikeu/ringkasan` | Ringkasan keuangan mahasiswa (total tagihan, terbayar, tunggakan, beasiswa, dispensasi aktif) | ✅ `sikeu.tagihan.read` |
| GET | `/api/v1/mobile/mahasiswa/sikeu/tagihan` | Daftar invoice/tagihan mahasiswa beserta rincian item & potongan beasiswa | ✅ `sikeu.tagihan.read` |
| GET | `/api/v1/mobile/mahasiswa/sikeu/pembayaran` | Riwayat transaksi pembayaran & nomor kwitansi resmi | ✅ `sikeu.tagihan.read` |
| GET | `/api/v1/mobile/mahasiswa/sikeu/beasiswa` | Riwayat penerimaan beasiswa mahasiswa | ✅ `sikeu.tagihan.read` |
| GET | `/api/v1/mobile/mahasiswa/sikeu/dispensasi` | Riwayat pengajuan dispensasi cicilan/penundaan pembayaran & peringatan tunggakan | ✅ `sikeu.dispensasi.read` |

---

## Headers Standar (Berlaku untuk Seluruh Endpoint)

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` (atau `multipart/form-data` untuk upload izin) | Hanya POST |

## Response Error Standar (Berlaku untuk Seluruh Endpoint)

**400 Bad Request**
```json
{
    "status": "error",
    "message": "Permintaan tidak dapat diproses."
}
```

**401 Unauthorized**
```json
{
    "status": "error",
    "message": "Token tidak valid atau sesi telah berakhir."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Endpoint ini khusus untuk akun Mahasiswa aktif."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data yang diminta tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "token": ["Token presensi sudah kedaluwarsa atau tidak cocok."]
    }
}
```

---

## 1. GET `/api/v1/mobile/mahasiswa/home`

> Mengambil ringkasan terpadu untuk layar Beranda (Home) aplikasi mobile mahasiswa.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tahun_akademik_id` | integer | ❌ | Periode Aktif | Filter periode akademik tertentu |

### Response Sukses (`200 OK`)

```json
{
    "status": "success",
    "message": "Data beranda aplikasi mobile mahasiswa berhasil diambil.",
    "data": {
        "mahasiswa": {
            "id": 12,
            "nim": "2501001",
            "nama_lengkap": "Mahasiswa Mobile Satu",
            "angkatan": 2025,
            "kelas": "25A",
            "jalur_kelas": "reguler",
            "semester_berjalan": 1,
            "status_mahasiswa": "aktif",
            "program_studi": {
                "id": 1,
                "kode_prodi": "TI",
                "nama_prodi": "Teknik Informatika",
                "jenjang": "S1"
            }
        },
        "periode_aktif": {
            "id": 4,
            "kode_tahun": "20251",
            "nama_tahun": "2025/2026 Ganjil",
            "semester": "ganjil",
            "is_active": true
        },
        "sesi_presensi_aktif": [],
        "jadwal_hari_ini": [],
        "lms_ringkasan": {
            "total_kelas": 6,
            "tugas_aktif_belum_kumpul": 1,
            "tryout_mendatang": []
        },
        "sikeu_ringkasan": {
            "total_tagihan": 4000000,
            "total_potongan": 500000,
            "total_terbayar": 1500000,
            "total_sisa_tagihan": 2500000,
            "jumlah_invoice_belum_lunas": 1
        }
    }
}
```

---

## 2. GET `/api/v1/mobile/mahasiswa/periode`

> Mengambil seluruh daftar periode akademik beserta ID periode akademik aktif (`active_periode_id`).

### Query Parameters

Tidak ada query parameter.

### Response Sukses (`200 OK`)

```json
{
    "status": "success",
    "message": "Daftar periode akademik berhasil diambil.",
    "data": {
        "active_periode_id": 4,
        "items": [
            {
                "id": 4,
                "kode_tahun": "20251",
                "nama_tahun": "2025/2026 Ganjil",
                "semester": "ganjil",
                "is_active": true
            }
        ]
    }
}
```

---

## 3. GET `/api/v1/mobile/mahasiswa/presensi/sesi-aktif`

> Mengambil daftar sesi presensi kelas yang sedang dibuka dosen pada periode terpilih.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tahun_akademik_id` | integer | ❌ | Periode Aktif | Filter periode akademik |

### Response Sukses (`200 OK`)

```json
{
    "status": "success",
    "message": "Daftar sesi presensi aktif berhasil diambil.",
    "data": [
        {
            "pertemuan_id": 88,
            "pertemuan_ke": 1,
            "tanggal": "2026-11-09",
            "jam_mulai": "08:00",
            "jam_selesai": "09:40",
            "materi": "Arsitektur Mobile & REST API",
            "kelas_id": 15,
            "kode_kelas": "IF-A",
            "nama_kelas": "Kelas IF-A",
            "mata_kuliah": "Pemrograman Mobile",
            "ruangan": "Lab 1",
            "sudah_absen": false,
            "status_absensi": null,
            "waktu_absen": null
        }
    ]
}
```

---

## 4. POST `/api/v1/mobile/mahasiswa/presensi/scan`

> Mencatat kehadiran mahasiswa melalui pemindaian QR Code dinamis (`RAG-PRESENSI:{pertemuan_id}:{token}`) yang berputar setiap 30 detik, atau input manual token 6 karakter.

### Request Body

```json
{
    "qr_payload": "RAG-PRESENSI:88:849201",
    "pertemuan_id": 88,
    "token": "849201",
    "device_info": "Android 15 - Pixel 8",
    "latitude": -6.914744,
    "longitude": 107.609810
}
```

### Response Sukses (`200 OK`)

```json
{
    "status": "success",
    "message": "Presensi berhasil dicatat melalui QR/Token scanner.",
    "data": {
        "pertemuan_id": 88,
        "kelas_id": 15,
        "pertemuan_ke": 1,
        "mahasiswa_id": 12,
        "nim": "2501001",
        "nama_mahasiswa": "Mahasiswa Mobile Satu",
        "is_present": true,
        "waktu_scan": "2026-11-09T08:12:10+07:00"
    }
}
```

---

## 5. POST `/api/v1/mobile/mahasiswa/presensi/pertemuan/{pertemuanId}/izin`

> Mengajukan permohonan izin atau sakit untuk suatu pertemuan perkuliahan beserta unggah bukti surat (disimpan di private storage).

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `pertemuanId` | integer | ✅ | ID pertemuan perkuliahan |

### Request Body (`multipart/form-data`)

| Field | Type | Required | Deskripsi |
|---|---|---|---|
| `tipe_izin_id` | integer | ❌ | ID referensi tipe izin (`spmb_master_referensi`) |
| `tipe_izin` | string (`izin`\|`sakit`) | ❌ | Tipe pengajuan izin/sakit |
| `alasan` | string | ✅ | Alasan lengkap ketidakhadiran |
| `file_surat` | file (`pdf,jpg,jpeg,png`, maks 2MB) | ❌ | Lampiran surat dokter/dispensasi |

### Response Sukses (`201 Created`)

```json
{
    "status": "success",
    "message": "Permohonan izin absensi berhasil diajukan.",
    "data": {
        "id": 5,
        "pertemuan_id": 88,
        "mahasiswa_id": 12,
        "tipe_izin": "sakit",
        "alasan": "Rawat jalan di klinik kampus",
        "status": "pending",
        "created_at": "2026-11-09T08:00:00.000000Z"
    }
}
```

---

## 6. GET `/api/v1/mobile/mahasiswa/presensi/rekap`

> Mengambil rekapitulasi kehadiran per kelas mata kuliah pada periode akademik terpilih beserta status kelayakan mengikuti UAS (`eligible_uas`).

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tahun_akademik_id` | integer | ❌ | Periode Aktif | Filter periode akademik |

### Response Sukses (`200 OK`)

```json
{
    "status": "success",
    "message": "Rekap kehadiran perkuliahan mahasiswa berhasil diambil.",
    "data": {
        "tahun_akademik_id": 4,
        "items": [
            {
                "kelas_id": 15,
                "kode_kelas": "IF-A",
                "nama_kelas": "Kelas IF-A",
                "kode_mk": "IF301",
                "nama_mk": "Pemrograman Mobile",
                "sks": 3,
                "total_pertemuan_terlaksana": 1,
                "hadir": 1,
                "izin": 0,
                "sakit": 0,
                "alfa": 0,
                "persentase_kehadiran": 100,
                "batas_minimal_persen": 75,
                "eligible_uas": true
            }
        ]
    }
}
```

---

## 7. GET `/api/v1/mobile/mahasiswa/lms/overview`

> Mengambil statistik ringkasan aktivitas LMS mahasiswa pada periode terpilih.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tahun_akademik_id` | integer | ❌ | Periode Aktif | Filter periode akademik |

### Response Sukses (`200 OK`)

```json
{
    "status": "success",
    "message": "Ringkasan LMS mahasiswa berhasil diambil.",
    "data": {
        "role": "mahasiswa",
        "tahun_akademik_id": 4,
        "ringkasan": {
            "kelas": 6,
            "tugas_aktif": 2,
            "tryout_aktif": 1,
            "forum_topik": 4,
            "notifikasi_belum_dibaca": 0
        },
        "tambahan": {
            "tugas_belum_dikumpul": 1,
            "tryout_selesai": 0
        },
        "tren": []
    }
}
```

---

## 8. GET `/api/v1/mobile/mahasiswa/lms/kelas`

> Mengambil daftar kelas perkuliahan LMS yang diikuti mahasiswa (mendukung pagination, pencarian, & filter periode).

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tahun_akademik_id` | integer | ❌ | Periode Aktif | Filter periode akademik |
| `search` | string | ❌ | — | Cari nama/kode kelas atau mata kuliah |
| `sort_by` | string | ❌ | `created_at` | Kolom urut (`id`, `nama_kelas`, `kode_kelas`, `created_at`, `updated_at`) |
| `sort_order` | string | ❌ | `desc` | Arah urutan (`asc` / `desc`) |
| `per_page` | integer | ❌ | `15` | Jumlah item per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Nomor halaman |

### Response Sukses (`200 OK`)

```json
{
    "status": "success",
    "message": "Daftar kelas LMS mahasiswa berhasil diambil.",
    "data": [
        {
            "id": 15,
            "kode_kelas": "IF-A",
            "nama_kelas": "Kelas IF-A",
            "hari": "Senin",
            "jam_mulai": "08:00",
            "jam_selesai": "09:40"
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

## 9. GET `/api/v1/mobile/mahasiswa/lms/kelas/{kelasId}`

> Mengambil detail lengkap satu kelas LMS beserta daftar pertemuan dan progres kehadiran mahasiswa.

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `kelasId` | integer | ✅ | ID kelas perkuliahan |

### Response Sukses (`200 OK`)

```json
{
    "status": "success",
    "message": "Detail kelas LMS berhasil diambil.",
    "data": {
        "kelas": {
            "id": 15,
            "kode_kelas": "IF-A",
            "nama_kelas": "Kelas IF-A"
        },
        "viewer_context": {
            "viewer_mode": "mahasiswa",
            "is_peserta_krs": true,
            "can_participate": true
        },
        "pertemuans": []
    }
}
```

---

## 10. GET `/api/v1/mobile/mahasiswa/lms/pertemuan/{pertemuanId}`

> Mengambil detail satu pertemuan perkuliahan LMS (materi, tugas, kuis, jurnal dosen, dan status absensi pribadi mahasiswa).

### Path Parameters

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `pertemuanId` | integer | ✅ | ID pertemuan perkuliahan |

### Response Sukses (`200 OK`)

```json
{
    "status": "success",
    "message": "Detail pertemuan LMS berhasil diambil.",
    "data": {
        "pertemuan": {
            "id": 88,
            "pertemuan_ke": 1,
            "materi": "Arsitektur Mobile & REST API",
            "status_pertemuan": "berlangsung"
        },
        "my_absensi": {
            "status": "hadir",
            "is_submitted": true
        },
        "materi_list": [],
        "tugas_list": [],
        "quiz_list": []
    }
}
```

---

## 11. GET `/api/v1/mobile/mahasiswa/lms/tugas`

> Mengambil daftar seluruh tugas perkuliahan milik mahasiswa lintas kelas (paginated).

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari judul atau deskripsi tugas |
| `sort_by` | string | ❌ | `created_at` | Kolom urut (`id`, `judul`, `deadline_at`, `created_at`, `updated_at`) |
| `sort_order` | string | ❌ | `desc` | Arah urutan (`asc` / `desc`) |
| `per_page` | integer | ❌ | `15` | Jumlah item per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Nomor halaman |

### Response Sukses (`200 OK`)

```json
{
    "status": "success",
    "message": "Daftar tugas LMS mahasiswa berhasil diambil.",
    "data": [
        {
            "id": 21,
            "judul": "Implementasi Scanner QR Mobile",
            "deadline_at": "2026-11-16T23:59:00.000000Z",
            "maks_nilai": 100
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

## 12. GET `/api/v1/mobile/mahasiswa/lms/tryout`

> Mengambil daftar tryout mata kuliah maupun tryout ujian khusus (di luar mata kuliah) yang diikuti mahasiswa (paginated).

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tahun_akademik_id` | integer | ❌ | Periode Aktif | Filter periode akademik |
| `kelas_id` | integer | ❌ | — | Filter kelas tertentu |
| `standalone` | boolean | ❌ | — | `true` hanya ujian khusus di luar MK, `false` hanya tryout kelas |
| `search` | string | ❌ | — | Cari judul tryout |
| `sort_by` | string | ❌ | `id` | Kolom urut (`id`, `judul`, `durasi_menit`, `dibuka_at`, `ditutup_at`, `created_at`) |
| `sort_order` | string | ❌ | `desc` | Arah urutan (`asc` / `desc`) |
| `per_page` | integer | ❌ | `15` | Jumlah item per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Nomor halaman |

### Response Sukses (`200 OK`)

```json
{
    "status": "success",
    "message": "Daftar tryout & ujian khusus mahasiswa berhasil diambil.",
    "data": [
        {
            "id": 9,
            "kelas_id": null,
            "tipe": "tryout",
            "judul": "Tryout Ujian Kompetensi Angkatan 2025",
            "durasi_menit": 90,
            "soal_count": 50
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

## 13. GET `/api/v1/mobile/mahasiswa/lms/forum`

> Mengambil daftar topik diskusi forum dari seluruh kelas yang diikuti mahasiswa (paginated).

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tahun_akademik_id` | integer | ❌ | Periode Aktif | Filter periode akademik |
| `kelas_id` | integer | ❌ | — | Filter kelas tertentu |
| `is_pinned` | boolean | ❌ | — | Filter topik yang disematkan |
| `search` | string | ❌ | — | Cari judul topik diskusi |
| `sort_by` | string | ❌ | `id` | Kolom urut (`id`, `judul`, `created_at`, `updated_at`) |
| `sort_order` | string | ❌ | `desc` | Arah urutan (`asc` / `desc`) |
| `per_page` | integer | ❌ | `15` | Jumlah item per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Nomor halaman |

### Response Sukses (`200 OK`)

```json
{
    "status": "success",
    "message": "Daftar topik forum diskusi berhasil diambil.",
    "data": [
        {
            "id": 3,
            "kelas_id": 15,
            "judul": "Diskusi Arsitektur REST API Mobile",
            "is_pinned": true
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

## 14. GET `/api/v1/mobile/mahasiswa/siakad/profil`

> Mengambil biodata akademik mahasiswa, informasi Program Studi, Dosen Pembimbing Akademik (PA), dan payload QR Kartu Tanda Mahasiswa Digital (`ktm_qr_payload`).

### Query Parameters

Tidak ada query parameter.

### Response Sukses (`200 OK`)

```json
{
    "status": "success",
    "message": "Profil akademik & KTM digital mahasiswa berhasil diambil.",
    "data": {
        "id": 12,
        "nim": "2501001",
        "nama_lengkap": "Mahasiswa Mobile Satu",
        "angkatan": 2025,
        "kelas": "25A",
        "jalur_kelas": "reguler",
        "semester_berjalan": 1,
        "status_mahasiswa": "aktif",
        "ktm_qr_payload": "RAG-KTM:2501001:12",
        "program_studi": {
            "id": 1,
            "kode_prodi": "TI",
            "nama_prodi": "Teknik Informatika",
            "jenjang": "S1"
        },
        "dosen_wali": null
    }
}
```

---

## 15. GET `/api/v1/mobile/mahasiswa/siakad/jadwal`

> Mengambil jadwal kuliah mingguan serta jadwal hari ini pada periode akademik terpilih.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tahun_akademik_id` | integer | ❌ | Periode Aktif | Filter periode akademik |

### Response Sukses (`200 OK`)

```json
{
    "status": "success",
    "message": "Jadwal kuliah mahasiswa berhasil diambil.",
    "data": {
        "tahun_akademik_id": 4,
        "hari_ini": "Senin",
        "jadwal_hari_ini": [],
        "jadwal_mingguan": [
            {
                "kelas_id": 15,
                "kode_kelas": "IF-A",
                "nama_kelas": "Kelas IF-A",
                "kode_mk": "IF301",
                "nama_mk": "Pemrograman Mobile",
                "sks": 3,
                "hari": "Senin",
                "jam_mulai": "08:00",
                "jam_selesai": "09:40",
                "ruangan": "Lab 1",
                "dosen_pengampu": ["Dr. Dosen Pengampu, M.Kom."]
            }
        ]
    }
}
```

---

## 16. GET `/api/v1/mobile/mahasiswa/siakad/krs`

> Mengambil Kartu Rencana Studi (KRS) mahasiswa beserta daftar mata kuliah dan total SKS pada periode terpilih.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tahun_akademik_id` | integer | ❌ | Periode Aktif | Filter periode akademik |

### Response Sukses (`200 OK`)

```json
{
    "status": "success",
    "message": "Data KRS mahasiswa berhasil diambil.",
    "data": {
        "tahun_akademik_id": 4,
        "krs": {
            "id": 31,
            "semester": 1,
            "status": "disetujui",
            "total_sks": 3,
            "items": [
                {
                    "krs_detail_id": 90,
                    "kelas_id": 15,
                    "kode_kelas": "IF-A",
                    "nama_kelas": "Kelas IF-A",
                    "kode_mk": "IF301",
                    "nama_mk": "Pemrograman Mobile",
                    "sks": 3
                }
            ]
        }
    }
}
```

---

## 17. GET `/api/v1/mobile/mahasiswa/siakad/khs`

> Mengambil Kartu Hasil Studi (KHS), Indeks Prestasi Semester (IPS), dan rincian nilai per mata kuliah pada periode terpilih.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tahun_akademik_id` | integer | ❌ | Periode Aktif | Filter periode akademik |

### Response Sukses (`200 OK`)

```json
{
    "status": "success",
    "message": "Data KHS mahasiswa berhasil diambil.",
    "data": {
        "tahun_akademik_id": 4,
        "khs": {
            "krs_id": 31,
            "semester": 1,
            "total_sks": 3,
            "total_mutu": 12,
            "ips": 4.0,
            "items": [
                {
                    "krs_detail_id": 90,
                    "kode_mk": "IF301",
                    "nama_mk": "Pemrograman Mobile",
                    "sks": 3,
                    "nilai_angka": 88.5,
                    "nilai_huruf": "A",
                    "nilai_indeks": 4.0,
                    "mutu": 12.0,
                    "is_locked": true
                }
            ]
        }
    }
}
```

---

## 18. GET `/api/v1/mobile/mahasiswa/siakad/transkrip`

> Mengambil transkrip nilai kumulatif sementara mahasiswa beserta total SKS lulus dan IPK.

### Query Parameters

Tidak ada query parameter.

### Response Sukses (`200 OK`)

```json
{
    "status": "success",
    "message": "Transkrip nilai sementara mahasiswa berhasil diambil.",
    "data": {
        "mahasiswa": {
            "id": 12,
            "nim": "2501001",
            "nama_lengkap": "Mahasiswa Mobile Satu"
        },
        "total_sks_lulus": 3,
        "total_mutu": 12.0,
        "ipk": 4.0,
        "items": [
            {
                "kode_mk": "IF301",
                "nama_mk": "Pemrograman Mobile",
                "sks": 3,
                "semester": 1,
                "nilai_angka": 88.5,
                "nilai_huruf": "A",
                "nilai_indeks": 4.0,
                "mutu": 12.0
            }
        ]
    }
}
```

---

## 19. GET `/api/v1/mobile/mahasiswa/sikeu/ringkasan`

> Mengambil ringkasan posisi keuangan mahasiswa (total tagihan, potongan beasiswa, total terbayar, sisa tunggakan, dan jumlah beasiswa/dispensasi aktif).

### Query Parameters

Tidak ada query parameter.

### Response Sukses (`200 OK`)

```json
{
    "status": "success",
    "message": "Ringkasan keuangan mahasiswa berhasil diambil.",
    "data": {
        "total_tagihan": 4000000,
        "total_potongan": 500000,
        "total_terbayar": 1500000,
        "total_sisa_tagihan": 2500000,
        "jumlah_invoice": 1,
        "jumlah_invoice_lunas": 0,
        "jumlah_invoice_belum_lunas": 1,
        "beasiswa_aktif_count": 1,
        "dispensasi_aktif_count": 0,
        "memiliki_tunggakan_dispensasi": false
    }
}
```

---

## 20. GET `/api/v1/mobile/mahasiswa/sikeu/tagihan`

> Mengambil daftar seluruh invoice/tagihan keuangan mahasiswa beserta rincian komponen biaya dan potongan beasiswa.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `status` | string | ❌ | — | Filter status tagihan (`paid`, `partial`, `unpaid`) |

### Response Sukses (`200 OK`)

```json
{
    "status": "success",
    "message": "Daftar tagihan & invoice mahasiswa berhasil diambil.",
    "data": [
        {
            "id": 44,
            "nomor_tagihan": "INV-20251-2501001",
            "semester": "20251",
            "tahun_akademik": "2025/2026 Ganjil",
            "nominal_kotor": 4500000,
            "nominal_potongan": 500000,
            "total_tagihan": 4000000,
            "total_terbayar": 1500000,
            "sisa_tagihan": 2500000,
            "status_bayar": "partial",
            "jatuh_tempo": "2026-12-01",
            "items": []
        }
    ]
}
```

---

## 21. GET `/api/v1/mobile/mahasiswa/sikeu/pembayaran`

> Mengambil riwayat transaksi pembayaran dan nomor kwitansi resmi mahasiswa.

### Query Parameters

Tidak ada query parameter.

### Response Sukses (`200 OK`)

```json
{
    "status": "success",
    "message": "Riwayat pembayaran mahasiswa berhasil diambil.",
    "data": [
        {
            "id": 71,
            "tagihan_id": 44,
            "nomor_tagihan": "INV-20251-2501001",
            "nomor_kwitansi": "KWT-20251-0001",
            "jumlah_bayar": 1500000,
            "metode_pembayaran": "VA_BANK",
            "channel_bank": "BSI",
            "status_validasi": "valid",
            "tanggal_bayar": "2026-11-09T09:00:00+07:00"
        }
    ]
}
```

---

## 22. GET `/api/v1/mobile/mahasiswa/sikeu/beasiswa`

> Mengambil daftar riwayat penerimaan beasiswa mahasiswa yang memotong tagihan otomatis.

### Query Parameters

Tidak ada query parameter.

### Response Sukses (`200 OK`)

```json
{
    "status": "success",
    "message": "Daftar beasiswa mahasiswa berhasil diambil.",
    "data": [
        {
            "id": 3,
            "beasiswa_id": 1,
            "kode_beasiswa": "BEA-PRESTASI",
            "nama_beasiswa": "Beasiswa Prestasi Akademik",
            "tipe_potongan": "nominal",
            "nilai_potongan": 500000,
            "status_aktif": true
        }
    ]
}
```

---

## 23. GET `/api/v1/mobile/mahasiswa/sikeu/dispensasi`

> Mengambil daftar riwayat pengajuan dispensasi pembayaran mahasiswa beserta indikator peringatan apabila masih terdapat tunggakan dispensasi sebelumnya yang belum dilunasi.

### Query Parameters

Tidak ada query parameter.

### Response Sukses (`200 OK`)

```json
{
    "status": "success",
    "message": "Daftar dispensasi pembayaran mahasiswa berhasil diambil.",
    "data": {
        "memiliki_tunggakan_dispensasi": false,
        "peringatan_tunggakan": null,
        "items": []
    }
}
```
