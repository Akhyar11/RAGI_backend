# MobileStaffController

> **Modul**: Aplikasi Mobile Staf & Dosen Terpadu (myInU - Staff)  
> **Base URL**: `/api/v1/mobile/staff`  
> **Autentikasi**: Bearer Token (`auth:api`)  
> **Dibuat**: 2026-11-21  
> **Diperbarui**: 2026-11-21  

## Deskripsi Singkat

`MobileStaffController` menyediakan gateway API backend terpadu untuk aplikasi mobile dosen dan tenaga kependidikan (myInU - Staff). Modul ini menyajikan informasi ringkasan beranda staf, pelacakan jadwal mengajar harian & semester berjalan yang tersinkronisasi dengan SIAKAD, sinkronisasi foto profil (avatar) staf, penilaian mata kuliah berbasis OBE/KHS, bimbingan akademik DPA, serta persetujuan KRS mahasiswa bimbingan.

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Autentikasi / Akses |
|---|---|---|---|
| GET | `/api/v1/mobile/staff/jadwal-mengajar` | Jadwal mengajar dosen (hari ini & semester berjalan) | ✅ Bearer Token (`auth:api` / `siakad.kelas.read`) |
| GET | `/api/v1/mobile/staff/home` | Ringkasan dashboard beranda staf & dosen | ✅ Bearer Token (`auth:api` / `siakad.kelas.read`) |
| POST | `/api/v1/mobile/staff/avatar` | Unggah & perbarui foto profil staf (multipart / base64) | ✅ Bearer Token (`auth:api` / `update` self) |
| GET | `/api/v1/siakad/perkuliahan/jadwal-mengajar` | Endpoint alternatif jadwal mengajar dosen via modul SIAKAD | ✅ Bearer Token (`auth:api` / `siakad.kelas.read`) |
| GET | `/api/v1/mobile/staff/agendas` | Agenda kegiatan & seragam kerja hari ini | ✅ Bearer Token (`auth:api` / `simpeg.pegawai.read`) |
| GET | `/api/v1/mobile/staff/nilai/kelas` | Daftar kelas yang diampu dosen untuk penilaian | ✅ Bearer Token (`auth:api` / `siakad.nilai.read`) |
| GET | `/api/v1/mobile/staff/nilai/kelas/{kelasId}` | Detail rincian nilai & daftar mahasiswa kelas | ✅ Bearer Token (`auth:api` / `siakad.nilai.read`) |
| POST | `/api/v1/mobile/staff/nilai/kelas/{kelasId}` | Simpan bobot nilai mahasiswa (harian, UTS, UAS, praktik) | ✅ Bearer Token (`auth:api` / `siakad.nilai.manage`) |
| GET | `/api/v1/mobile/staff/bimbingan-pa/mahasiswa` | Daftar mahasiswa bimbingan akademik DPA | ✅ Bearer Token (`auth:api` / `siakad.pa.read`) |
| GET | `/api/v1/mobile/staff/bimbingan-pa/krs/{krsId}` | Rincian kartu rencana studi mahasiswa bimbingan | ✅ Bearer Token (`auth:api` / `siakad.krs.read`) |
| POST | `/api/v1/mobile/staff/bimbingan-pa/krs/{krsId}/approve` | Setujui & validasi KRS mahasiswa bimbingan | ✅ Bearer Token (`auth:api` / `siakad.krs.approve`) |
| POST | `/api/v1/mobile/staff/bimbingan-pa/krs/{krsId}/reject` | Tolak / minta revisi KRS mahasiswa bimbingan | ✅ Bearer Token (`auth:api` / `siakad.krs.approve`) |
| GET | `/api/v1/mobile/staff/bimbingan-pa/catatan/{mahasiswaId}` | Riwayat catatan bimbingan akademik mahasiswa | ✅ Bearer Token (`auth:api` / `siakad.pa.read`) |
| POST | `/api/v1/mobile/staff/bimbingan-pa/catatan/{mahasiswaId}` | Tambah catatan bimbingan akademik baru | ✅ Bearer Token (`auth:api` / `siakad.pa.create`) |

---

## 1. GET /api/v1/mobile/staff/jadwal-mengajar

Mengambil daftar jadwal mengajar dosen yang sedang aktif, status perkuliahan (Akan Datang, Sedang Berlangsung, Selesai), ruangan, dan jumlah mahasiswa. Mendukung query filter hari dan tahun akademik.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `nidn` | string | ❌ | — | NIDN dosen (khusus role admin/pengelola, dosen reguler otomatis ter-scope ke akun sendiri) |
| `nip` | string | ❌ | — | NIP dosen |
| `username` | string | ❌ | — | Username akun SSO dosen |
| `hari` | string | ❌ | — | Filter jadwal hari spesifik (contoh: `senin`, `selasa`) |
| `tahun_akademik_id` | integer | ❌ | Aktif | ID Tahun Akademik spesifik (default: periode aktif) |
| `sort_by` | string | ❌ | `jam_mulai` | Kolom pengurutan (`jam_mulai`, `kode_kelas`, `created_at`) |
| `sort_order` | string | ❌ | `asc` | Arah pengurutan (`asc` atau `desc`) |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Jadwal mengajar berhasil dimuat.",
    "data": {
        "dosen": {
            "id": 12,
            "nama_lengkap": "Dr. Budi Santoso, M.Kom",
            "nama_gelar": "Dr. Budi Santoso, M.Kom",
            "nidn": "0620018801",
            "nip": "198801012015011001",
            "is_fallback": false
        },
        "today_day": "senin",
        "filter_day": null,
        "today_classes": [
            {
                "id": 45,
                "course_code": "TI301",
                "course_name": "Pemrograman Web Lanjut",
                "sks": 3,
                "class_name": "TI-3A",
                "room": "Lab Komputer 1 (Lt. 2)",
                "room_code": "LAB-01",
                "start_time": "08:00",
                "end_time": "10:30",
                "student_count": 32,
                "session": 10,
                "status": "Sedang Berlangsung",
                "hari": "senin",
                "is_today": true,
                "is_ongoing": true,
                "reminder_active": true,
                "peran": "Pengampu Utama"
            }
        ],
        "all_classes": [
            {
                "id": 45,
                "course_code": "TI301",
                "course_name": "Pemrograman Web Lanjut",
                "sks": 3,
                "class_name": "TI-3A",
                "room": "Lab Komputer 1 (Lt. 2)",
                "room_code": "LAB-01",
                "start_time": "08:00",
                "end_time": "10:30",
                "student_count": 32,
                "session": 10,
                "status": "Sedang Berlangsung",
                "hari": "senin",
                "is_today": true,
                "is_ongoing": true,
                "reminder_active": true,
                "peran": "Pengampu Utama"
            }
        ],
        "total_sks": 3,
        "total_kelas": 1
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

---

## 2. GET /api/v1/mobile/staff/home

Mengambil ringkasan beranda staf termasuk profil dasar dan widget jadwal mengajar hari ini.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Ringkasan beranda staf berhasil dimuat.",
    "data": {
        "user": {
            "id": 15,
            "name": "Dr. Budi Santoso, M.Kom",
            "username": "budi.santoso",
            "role": "Dosen Pengajar"
        },
        "jadwal": {
            "dosen": {
                "id": 12,
                "nama_lengkap": "Dr. Budi Santoso, M.Kom",
                "nama_gelar": "Dr. Budi Santoso, M.Kom",
                "nidn": "0620018801",
                "nip": "198801012015011001",
                "is_fallback": false
            },
            "today_day": "senin",
            "filter_day": null,
            "today_classes": [],
            "all_classes": [],
            "total_sks": 3,
            "total_kelas": 1
        }
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

---

## 3. POST /api/v1/mobile/staff/avatar

Memperbarui foto profil staf ke penyimpanan server backend RAG, baik dalam bentuk string Base64 (`avatar_base64`) atau upload berkas multipart (`avatar`). Sistem hanya mengizinkan pengguna memperbarui avatar akun miliknya sendiri dan mencatat perubahan ke audit log.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` atau `multipart/form-data` | ✅ |

### Request Body (JSON atau Multipart)

**Format JSON Base64:**
```json
{
    "avatar_base64": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII="
}
```

**Format Multipart:**
- `avatar`: Berkas gambar (JPEG, PNG, WEBP, maks. 5MB)

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Foto profil berhasil diperbarui dan tersimpan di server RAG.",
    "data": {
        "user_id": 15,
        "avatar_url": "http://localhost:8000/storage/avatars/2026/11/4d7159c1-ef90-48e0-bb15-08168bb21389.png",
        "path": "avatars/2026/11/4d7159c1-ef90-48e0-bb15-08168bb21389.png",
        "updated_at": "2026-11-21T09:00:00+07:00"
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "avatar": [
            "Berkas foto profil harus berupa gambar bertipe JPEG, PNG, atau WEBP."
        ]
    }
}
```

---

## 4. GET /api/v1/siakad/perkuliahan/jadwal-mengajar

Endpoint integrasi jadwal mengajar dosen yang berada di bawah prefix routing modul SIAKAD.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

Format response identik dengan `GET /api/v1/mobile/staff/jadwal-mengajar`.

### Response Error

**401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

---

## 5. GET /api/v1/mobile/staff/agendas

Mengambil agenda kegiatan dan seragam kerja hari ini sesuai role pegawai (Dosen atau Tendik).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Agenda & pengumuman berhasil diambil.",
    "data": {
        "user_role": "dosen",
        "seragam_hari_ini": {
            "id": 5,
            "title": "Ketentuan Seragam Kerja Hari Senin",
            "category": "seragam",
            "content": "Pakaian batik lengan panjang / bebas rapi.",
            "event_date": "2026-11-23"
        },
        "upcoming_agendas": [
            {
                "id": 6,
                "title": "Rapat Koordinasi Akademik",
                "category": "rapat",
                "content": "Rapat di Ruang Senat.",
                "is_agenda": true,
                "event_date": "2026-11-25",
                "event_time": "09:00 WIB",
                "event_location": "Ruang Senat"
            }
        ]
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

---

## 6. GET /api/v1/mobile/staff/nilai/kelas

Mengambil daftar kelas perkuliahan aktif yang diampu dosen pada semester berjalan untuk keperluan input nilai OBE / KHS.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `nidn` | string | ❌ | — | Filter spesifik berdasarkan NIDN dosen |
| `nip` | string | ❌ | — | Filter spesifik berdasarkan NIP dosen |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar kelas penilaian berhasil dimuat.",
    "data": [
        {
            "kelas_id": 12,
            "kode_kelas": "TI-3A",
            "nama_kelas": "Pemrograman Web (TI-3A)",
            "kode_mk": "TI301",
            "nama_mk": "Pemrograman Web",
            "sks": 3,
            "prodi": "Teknik Informatika",
            "hari": "senin",
            "jam": "08:00 - 10:30",
            "total_mahasiswa": 32,
            "jumlah_dinilai": 28,
            "status_penilaian": "draft",
            "bobot": {
                "harian": 0.20,
                "uts": 0.25,
                "uas": 0.35,
                "praktik": 0.20
            }
        }
    ]
}
```

### Response Error

**401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

---

## 7. GET /api/v1/mobile/staff/nilai/kelas/{kelasId}

Mengambil detail rincian bobot penilaian dan daftar nilai seluruh mahasiswa yang terdaftar di kelas.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Detail komponen nilai kelas berhasil dimuat.",
    "data": {
        "kelas_id": 12,
        "kode_kelas": "TI-3A",
        "nama_kelas": "Pemrograman Web (TI-3A)",
        "kode_mk": "TI301",
        "nama_mk": "Pemrograman Web",
        "sks": 3,
        "prodi": "Teknik Informatika",
        "bobot": {
            "harian": 0.20,
            "uts": 0.25,
            "uas": 0.35,
            "praktik": 0.20
        },
        "students": [
            {
                "krs_detail_id": 105,
                "mahasiswa_id": 45,
                "nim": "230101001",
                "nama": "Aditya Pratama",
                "avatar": null,
                "nilai_harian": 88.0,
                "nilai_uts": 85.0,
                "nilai_uas": 86.0,
                "nilai_praktik": 90.0,
                "nilai_akhir": 87.05,
                "nilai_huruf": "A",
                "bobot_mutu": 4.00,
                "is_final": false
            }
        ]
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Kelas tidak ditemukan."
}
```

---

## 8. POST /api/v1/mobile/staff/nilai/kelas/{kelasId}

Menyimpan komponen nilai mahasiswa (harian, UTS, UAS, praktik) dan mengonversi nilai akhir ke skala huruf mutu & bobot standar program studi via `SkalaNilai`.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "grades": [
        {
            "krs_detail_id": 105,
            "nilai_harian": 88.5,
            "nilai_uts": 85.0,
            "nilai_uas": 90.0,
            "nilai_praktik": 92.0,
            "is_final": true
        }
    ]
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Berhasil menyimpan 1 nilai mahasiswa.",
    "data": {
        "kelas_id": 12,
        "updated_count": 1,
        "message": "Berhasil menyimpan 1 nilai mahasiswa."
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Kelas tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "grades": [
            "Data grades wajib diisi."
        ],
        "grades.0.krs_detail_id": [
            "KRS detail ID wajib diisi."
        ]
    }
}
```

---

## 9. GET /api/v1/mobile/staff/bimbingan-pa/mahasiswa

Mengambil daftar mahasiswa bimbingan akademik (advisees) dari dosen wali yang sedang login.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar mahasiswa bimbingan PA berhasil dimuat.",
    "data": [
        {
            "id": 45,
            "nim": "230101001",
            "nama": "Aditya Pratama",
            "avatar": null,
            "prodi": "Teknik Informatika",
            "angkatan": "2023",
            "semester": 3,
            "ipk": 3.75,
            "total_sks_lulus": 44,
            "krs": {
                "id": 88,
                "status": "menunggu_persetujuan",
                "total_sks": 21,
                "disetujui_at": null
            }
        }
    ]
}
```

### Response Error

**401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

---

## 10. GET /api/v1/mobile/staff/bimbingan-pa/krs/{krsId}

Mengambil rincian kartu rencana studi mahasiswa bimbingan akademik.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Rincian KRS mahasiswa bimbingan berhasil dimuat.",
    "data": {
        "krs_id": 88,
        "mahasiswa": {
            "id": 45,
            "nim": "230101001",
            "nama": "Aditya Pratama",
            "prodi": "Teknik Informatika",
            "semester": 3,
            "ipk": 3.75
        },
        "tahun_akademik": "2026/2027 Ganjil",
        "total_sks": 21,
        "status": "diajukan",
        "disetujui_at": null,
        "matakuliah": [
            {
                "krs_detail_id": 105,
                "kelas_id": 12,
                "kode_mk": "TI301",
                "nama_mk": "Pemrograman Web",
                "sks": 3,
                "kelas": "TI-3A",
                "hari": "senin",
                "jam": "08:00 - 10:30",
                "ruangan": "Lab Komputer 1"
            }
        ]
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "KRS mahasiswa tidak ditemukan."
}
```

---

## 11. POST /api/v1/mobile/staff/bimbingan-pa/krs/{krsId}/approve

Menyetujui dan memvalidasi KRS mahasiswa bimbingan secara resmi.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "KRS mahasiswa berhasil disetujui & divalidasi.",
    "data": {
        "krs_id": 88,
        "status": "disetujui"
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "KRS mahasiswa tidak ditemukan atau gagal disetujui."
}
```

---

## 12. POST /api/v1/mobile/staff/bimbingan-pa/krs/{krsId}/reject

Menolak / meminta mahasiswa bimbingan merevisi rencana studinya beserta catatan alasan revisi.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "alasan": "Silakan ganti mata kuliah pilihan semester 5 dengan prasyarat yang sudah lulus."
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Permintaan revisi KRS telah dikirim ke mahasiswa.",
    "data": {
        "krs_id": 88,
        "status": "revisi"
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "KRS mahasiswa tidak ditemukan atau gagal diproses."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "alasan": [
            "Alasan revisi tidak boleh melebihi 500 karakter."
        ]
    }
}
```

---

## 13. GET /api/v1/mobile/staff/bimbingan-pa/catatan/{mahasiswaId}

Mengambil riwayat catatan bimbingan akademik mahasiswa.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Riwayat catatan bimbingan berhasil dimuat.",
    "data": [
        {
            "id": 14,
            "tanggal": "21 Nov 2026",
            "kategori": "Konsultasi KRS",
            "isi": "Konsultasi pemilihan mata kuliah semester 3.",
            "kesimpulan": "Disetujui mengambil beban penuh 22 SKS.",
            "dosen": "Dr. Budi Santoso, M.Kom"
        }
    ]
}
```

### Response Error

**401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "This action is unauthorized."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Mahasiswa tidak ditemukan."
}
```

---

## 14. POST /api/v1/mobile/staff/bimbingan-pa/catatan/{mahasiswaId}

Mencatat hasil konsultasi dan catatan bimbingan akademik mahasiswa.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "isi": "Konsultasi rencana studi semester 3 dan pengajuan beasiswa.",
    "kategori": "Konsultasi Akademik",
    "kesimpulan": "Disetujui mengambil beban 22 SKS.",
    "butuh_penanganan_khusus": false,
    "tanggal": "2026-11-21"
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Catatan bimbingan akademik berhasil disimpan.",
    "data": {
        "id": 14,
        "tanggal": "21 Nov 2026",
        "kategori": "Konsultasi Akademik",
        "isi": "Konsultasi rencana studi semester 3 dan pengajuan beasiswa.",
        "kesimpulan": "Disetujui mengambil beban 22 SKS."
    }
}
```

### Response Error

**401 Unauthorized**
```json
{
    "message": "Unauthenticated."
}
```

**403 Forbidden**
```json
{
    "status": "error",
    "message": "Anda bukan Dosen Pembimbing Akademik mahasiswa ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Mahasiswa tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "isi": [
            "Isi catatan bimbingan wajib diisi minimal 5 karakter."
        ]
    }
}
```
