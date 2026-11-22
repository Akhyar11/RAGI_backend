# MobileStaffController

> **Modul**: Aplikasi Mobile Staf & Dosen Terpadu (myInU - Staff)  
> **Base URL**: `/api/v1/mobile/staff`  
> **Autentikasi**: Bearer Token (`auth:api`)  
> **Dibuat**: 2026-11-21  
> **Diperbarui**: 2026-11-22  

## Deskripsi Singkat

`MobileStaffController` menyediakan gateway API backend terpadu untuk aplikasi mobile dosen dan tenaga kependidikan (myInU - Staff). Modul ini menyajikan informasi ringkasan beranda staf, pelacakan jadwal mengajar harian & semester berjalan yang tersinkronisasi dengan SIAKAD, sinkronisasi foto profil (avatar) staf, penilaian mata kuliah berbasis OBE/KHS, bimbingan akademik DPA, persetujuan KRS mahasiswa bimbingan, serta kurasi fitur modul terpadu kampus (SIPPM, SIKEU, SINAPRA, ARSIP, LMS, dan SPMB Referral).

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
| GET | `/api/v1/mobile/staff/perkuliahan/kelas` | Daftar kelas yang diampu dosen beserta progres pertemuan | ✅ Bearer Token (`auth:api` / `siakad.kelas.read`) |
| GET | `/api/v1/mobile/staff/perkuliahan/kelas/{kelasId}` | Rincian kelas & daftar 16 sesi jurnal pertemuan (BAP) | ✅ Bearer Token (`auth:api` / `siakad.kelas.read`) |
| POST | `/api/v1/mobile/staff/perkuliahan/kelas/{kelasId}/mulai` | Mulai sesi perkuliahan & buka token absensi dinamis | ✅ Bearer Token (`auth:api` / `siakad.kelas.manage`) |
| POST | `/api/v1/mobile/staff/perkuliahan/pertemuan/{pertemuanId}/rotate-token` | Putar ulang token absensi dinamis (anti titip-hadir) | ✅ Bearer Token (`auth:api` / `siakad.kelas.manage`) |
| POST | `/api/v1/mobile/staff/perkuliahan/pertemuan/{pertemuanId}/selesai` | Selesaikan sesi perkuliahan & kunci absensi kelas | ✅ Bearer Token (`auth:api` / `siakad.kelas.manage`) |
| GET | `/api/v1/mobile/staff/perkuliahan/pertemuan/{pertemuanId}/presensi` | Rincian presensi mahasiswa & rekap kehadiran kelas | ✅ Bearer Token (`auth:api` / `siakad.kelas.read`) |
| POST | `/api/v1/mobile/staff/perkuliahan/pertemuan/{pertemuanId}/presensi` | Update / override status presensi mahasiswa oleh dosen | ✅ Bearer Token (`auth:api` / `siakad.kelas.manage`) |
| GET | `/api/v1/mobile/staff/sippm/proposal` | Daftar proposal penelitian & pengabdian milik dosen | ✅ Bearer Token (`auth:api`) |
| POST | `/api/v1/mobile/staff/sippm/proposal` | Ajukan proposal penelitian / pengabdian baru via mobile | ✅ Bearer Token (`auth:api`) |
| GET | `/api/v1/mobile/staff/sippm/luaran` | Daftar luaran publikasi & HKI penelitian milik dosen | ✅ Bearer Token (`auth:api`) |
| GET | `/api/v1/mobile/staff/sikeu/payroll` | Riwayat slip payroll & rincian potongan bulanan staf | ✅ Bearer Token (`auth:api`) |
| GET | `/api/v1/mobile/staff/sikeu/pengajuan` | Daftar pengajuan keuangan (reimbursement, uang muka) milik akun | ✅ Bearer Token (`auth:api`) |
| POST | `/api/v1/mobile/staff/sikeu/pengajuan` | Kirim pengajuan keuangan / reimbursement baru via mobile | ✅ Bearer Token (`auth:api`) |
| GET | `/api/v1/mobile/staff/sinapra/tiket` | Daftar tiket laporan kerusakan sarana & status perbaikan | ✅ Bearer Token (`auth:api`) |
| POST | `/api/v1/mobile/staff/sinapra/tiket` | Laporkan kerusakan sarana / fasilitas baru via mobile | ✅ Bearer Token (`auth:api`) |
| GET | `/api/v1/mobile/staff/sinapra/peminjaman` | Status peminjaman ruangan & fasilitas milik staf | ✅ Bearer Token (`auth:api`) |
| GET | `/api/v1/mobile/staff/arsip/e-file` | Dokumen e-file kepegawaian pribadi staf | ✅ Bearer Token (`auth:api`) |
| GET | `/api/v1/mobile/staff/arsip/surat` | Arsip persuratan dan surat tugas terkait staf | ✅ Bearer Token (`auth:api`) |
| GET | `/api/v1/mobile/staff/lms/bahan-ajar` | Daftar bahan ajar perkuliahan & RPS kelas dosen | ✅ Bearer Token (`auth:api`) |
| GET | `/api/v1/mobile/staff/lms/pengumuman` | Pengumuman kelas & notifikasi akademik dosen | ✅ Bearer Token (`auth:api`) |
| GET | `/api/v1/mobile/staff/spmb/referral` | Kode referral SPMB, statistik reward, & mahasiswa binaan | ✅ Bearer Token (`auth:api`) |
| POST | `/api/v1/mobile/staff/spmb/referral` | Buat / perbarui link share referral eksternal & QR | ✅ Bearer Token (`auth:api`) |

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

Memperbarui atau menghapus foto profil staf ke penyimpanan server backend RAG. Menerima string Base64 (`avatar_base64`), upload berkas multipart (`avatar`), atau opsi penghapusan avatar (`remove_avatar: true`). Sistem hanya mengizinkan pengguna memperbarui avatar akun miliknya sendiri dan mencatat perubahan ke audit log.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` atau `multipart/form-data` | ✅ |

### Request Body (JSON atau Multipart)

| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `avatar` | file | ❌ | Berkas gambar (JPEG, PNG, WEBP, maks. 5MB) |
| `avatar_base64` | string | ❌ | String Base64 gambar (JPEG/PNG/WEBP) |
| `remove_avatar` | boolean | ❌ | Jika `true`, sistem akan menghapus foto avatar dari server |

**Format JSON Base64:**
```json
{
    "avatar_base64": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII="
}
```

**Format JSON Hapus Avatar:**
```json
{
    "remove_avatar": true
}
```

**Format Multipart:**
- `avatar`: Berkas gambar (JPEG, PNG, WEBP, maks. 5MB)
- `remove_avatar`: `1` atau `true` (opsional)

### Response Sukses (200 OK - Pembaruan Berhasil)

```json
{
    "status": "success",
    "success": true,
    "message": "Foto profil berhasil diperbarui dan tersimpan di server RAG.",
    "data": {
        "user_id": 15,
        "avatar_url": "http://localhost:8000/storage/avatars/2026/11/4d7159c1-ef90-48e0-bb15-08168bb21389.png",
        "path": "avatars/2026/11/4d7159c1-ef90-48e0-bb15-08168bb21389.png",
        "updated_at": "2026-11-21T09:00:00+07:00"
    }
}
```

### Response Sukses (200 OK - Hapus Avatar Berhasil)

```json
{
    "status": "success",
    "message": "Foto profil berhasil dihapus dari server RAG.",
    "data": null
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

---

## 15. GET /api/v1/mobile/staff/perkuliahan/kelas

Daftar kelas perkuliahan aktif yang diampu dosen dengan ringkasan progres pertemuan perkuliahan (BAP), total pertemuan (16 sesi), jumlah pertemuan yang telah selesai, dan indikator sesi yang sedang aktif.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `nidn` | string | ❌ | — | NIDN dosen (opsional untuk override query admin) |
| `nip` | string | ❌ | — | NIP dosen |
| `username` | string | ❌ | — | Username akun dosen |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar kelas perkuliahan berhasil dimuat.",
    "data": [
        {
            "kelas_id": 12,
            "kode_kelas": "TI-3A",
            "nama_kelas": "Pemrograman Web (TI-3A)",
            "kode_mk": "TI301",
            "nama_mk": "Pemrograman Web",
            "sks": 3,
            "ruangan": "Lab Komputer 1",
            "hari": "Senin",
            "jam": "08:00 - 10:30",
            "total_mahasiswa": 32,
            "total_pertemuan": 16,
            "pertemuan_selesai": 5,
            "has_active_session": false,
            "active_session_id": null,
            "active_session_ke": null
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

## 16. GET /api/v1/mobile/staff/perkuliahan/kelas/{kelasId}

Rincian kelas perkuliahan dan daftar lengkap pertemuan ke-1 s/d ke-16 beserta statistik kehadiran mahasiswa (hadir, izin, sakit, alfa).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar jurnal pertemuan perkuliahan berhasil dimuat.",
    "data": {
        "kelas": {
            "id": 12,
            "kode_kelas": "TI-3A",
            "nama_kelas": "Pemrograman Web (TI-3A)",
            "kode_mk": "TI301",
            "nama_mk": "Pemrograman Web",
            "sks": 3,
            "ruangan": "Lab Komputer 1",
            "hari": "Senin",
            "jam": "08:00 - 10:30",
            "total_mahasiswa": 32,
            "pertemuan_selesai": 5,
            "has_active_session": true,
            "active_session_ke": 6
        },
        "pertemuan": [
            {
                "id": 48,
                "pertemuan_ke": 1,
                "tanggal": "02 Okt 2026",
                "tanggal_raw": "2026-10-02",
                "jam_mulai": "08:00",
                "jam_selesai": "10:30",
                "materi": "Kontrak Perkuliahan & Pengantar Web",
                "catatan": "Perkuliahan berlangsung tertib.",
                "status": "selesai",
                "token_absensi": "847291",
                "is_active": false,
                "total_hadir": 30,
                "total_izin": 1,
                "total_sakit": 1,
                "total_alfa": 0,
                "total_mahasiswa": 32
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
    "message": "Kelas perkuliahan tidak ditemukan."
}
```

---

## 17. POST /api/v1/mobile/staff/perkuliahan/kelas/{kelasId}/mulai

Memulai sesi perkuliahan aktif (BAP), mencatat jam mulai perkuliahan, dan mengaktifkan token presensi 6-digit dinamis beserta QR payload untuk mahasiswa.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "pertemuan_ke": 6,
    "materi": "RESTful API Integration dengan Next.js & Laravel",
    "catatan": "Praktikum di lab komputer.",
    "tanggal": "2026-11-22",
    "jam_mulai": "08:00"
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Sesi perkuliahan Pertemuan 6 berhasil dimulai.",
    "data": {
        "id": 55,
        "kelas_id": 12,
        "pertemuan_ke": 6,
        "tanggal": "22 Nov 2026",
        "materi": "RESTful API Integration dengan Next.js & Laravel",
        "catatan": "Praktikum di lab komputer.",
        "status": "berlangsung",
        "token_absensi": "583921",
        "token_expired_at": "2026-11-22T10:00:00.000000Z",
        "jam_mulai": "08:00",
        "qr_payload": "{\"type\":\"siakad_presensi\",\"pertemuan_id\":55,\"kelas_id\":12,\"pertemuan_ke\":6,\"token\":\"583921\",\"timestamp\":1795305600}"
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
    "message": "Kelas tidak ditemukan atau gagal membuka sesi perkuliahan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "pertemuan_ke": [
            "Nomor pertemuan wajib diisi antara 1 sampai 16."
        ],
        "materi": [
            "Materi perkuliahan wajib diisi minimal 3 karakter."
        ]
    }
}
```

---

## 18. POST /api/v1/mobile/staff/perkuliahan/pertemuan/{pertemuanId}/rotate-token

Memutar ulang (refresh) kode token presensi 6-digit dinamis pada sesi perkuliahan yang sedang berlangsung untuk mencegah kecurangan titip presensi.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Token absensi berhasil diputar ulang.",
    "data": {
        "pertemuan_id": 55,
        "kelas_id": 12,
        "token_absensi": "924817",
        "qr_payload": "RAG-PRESENSI:55:924817",
        "rotate_interval_seconds": 30,
        "expired_at": "2026-11-22T08:00:30.000000Z"
    }
}
```

### Response Error

**400 Bad Request**
```json
{
    "status": "error",
    "message": "Pertemuan tidak ditemukan atau sesi telah ditutup."
}
```

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

## 19. POST /api/v1/mobile/staff/perkuliahan/pertemuan/{pertemuanId}/selesai

Menyelesaikan sesi perkuliahan aktif, mencatat jam selesai, mengunci penerimaan presensi mahasiswa, dan mengembalikan rekapitulasi akhir absensi kelas.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Sesi perkuliahan Pertemuan 6 telah ditutup.",
    "data": {
        "id": 55,
        "kelas_id": 12,
        "pertemuan_ke": 6,
        "status": "selesai",
        "materi": "RESTful API Integration dengan Next.js & Laravel",
        "jam_mulai": "08:00",
        "jam_selesai": "10:30",
        "rekap": {
            "hadir": 31,
            "izin": 1,
            "sakit": 0,
            "alfa": 0
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

**404 Not Found**
```json
{
    "status": "error",
    "message": "Pertemuan perkuliahan tidak ditemukan."
}
```

---

## 20. GET /api/v1/mobile/staff/perkuliahan/pertemuan/{pertemuanId}/presensi

Mengambil rincian data absensi seluruh mahasiswa terdaftar di kelas untuk pertemuan tertentu beserta ringkasan status kehadiran (hadir, izin, sakit, alfa).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar presensi mahasiswa berhasil dimuat.",
    "data": {
        "pertemuan": {
            "id": 55,
            "kelas_id": 12,
            "pertemuan_ke": 6,
            "materi": "RESTful API Integration dengan Next.js & Laravel",
            "catatan": "Praktikum di lab komputer.",
            "tanggal": "22 Nov 2026",
            "jam_mulai": "08:00",
            "jam_selesai": null,
            "status": "berlangsung",
            "token_absensi": "924817",
            "is_active": true,
            "nama_mk": "Pemrograman Web",
            "nama_kelas": "Pemrograman Web (TI-3A)",
            "ruangan": "Lab Komputer 1"
        },
        "rekap": {
            "total": 32,
            "hadir": 30,
            "izin": 1,
            "sakit": 1,
            "alfa": 0
        },
        "mahasiswa": [
            {
                "mahasiswa_id": 45,
                "nim": "230101001",
                "nama": "Aditya Pratama",
                "status": "hadir",
                "catatan": null,
                "is_submitted": true
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
    "message": "Pertemuan tidak ditemukan."
}
```

---

## 21. POST /api/v1/mobile/staff/perkuliahan/pertemuan/{pertemuanId}/presensi

Memperbarui atau meng-override status kehadiran mahasiswa (hadir, izin, sakit, alfa) secara manual oleh dosen pengampu kelas.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ |

### Request Body

```json
{
    "items": [
        {
            "mahasiswa_id": 45,
            "status": "izin",
            "catatan": "Dispen kegiatan lomba robotika kampus."
        }
    ]
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Status presensi mahasiswa berhasil diperbarui.",
    "data": {
        "updated_count": 1,
        "rekap": {
            "hadir": 29,
            "izin": 2,
            "sakit": 1,
            "alfa": 0
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

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "items": [
            "Data items presensi wajib diisi."
        ],
        "items.0.status": [
            "Status presensi harus salah satu dari: hadir, izin, sakit, alfa."
        ]
    }
}
```

---

## 22. GET /api/v1/mobile/staff/sippm/proposal

Mengambil daftar proposal hibah penelitian dan pengabdian masyarakat (PkM) milik dosen yang sedang login (baik sebagai ketua pengusul maupun anggota).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `page` | integer | ❌ | 1 | Nomor halaman data |
| `per_page` | integer | ❌ | 15 | Jumlah item per halaman (maks. 100) |
| `sort_order` | string | ❌ | desc | Arah urutan waktu (`asc` / `desc`) |
| `search` | string | ❌ | — | Cari berdasarkan judul atau rumpun ilmu |
| `status` | string | ❌ | — | Filter status (`draft`, `submitted`, `approved`, `rejected`) |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar proposal SIPPM berhasil dimuat.",
    "data": [
        {
            "id": "1",
            "title": "Pengembangan Sistem Mobile Terpadu Kampus",
            "subtitle": "Penelitian Dosen Pemula (Ilmu Komputer)",
            "status": "draft",
            "date": "2026-11-22",
            "amount": "Rp 15.000.000",
            "kode_proposal": "PRP-2026-001",
            "skema": "Penelitian Dosen Pemula",
            "rumpun_ilmu": "Ilmu Komputer",
            "target_tkt": 3,
            "file_proposal_url": "http://localhost:8000/api/v1/storage/signed/sippm/proposal/dokumen_usulan.pdf?signature=abc123xyz",
            "raw": {}
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

### Response Error

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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data proposal tidak ditemukan."
}
```

---

## 23. POST /api/v1/mobile/staff/sippm/proposal

Mengajukan proposal kegiatan penelitian atau pengabdian masyarakat baru melalui aplikasi mobile.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `multipart/form-data` | ✅ |

### Request Body

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `judul` | string | ✅ | Judul penelitian / pengabdian |
| `abstrak` | string | ❌ | Ringkasan usulan kegiatan |
| `rumpun_ilmu` | string | ✅ | Bidang keilmuan usulan |
| `skema_id` | integer | ✅ | ID master skema kegiatan SIPPM |
| `periode_id` | integer | ✅ | ID periode hibah SIPPM |
| `target_tkt` | integer | ❌ | Tingkat Kesiapan Teknologi (1-9) |
| `anggaran_diajukan` | numeric | ✅ | Nominal usulan dana hibah |
| `file_proposal` | file | ✅ | Berkas PDF usulan proposal (maks. 10MB) |

```json
{
    "judul": "Pengembangan Sistem Mobile Terpadu Kampus",
    "abstrak": "Abstrak riset mengenai integrasi sistem mobile di lingkungan kampus.",
    "rumpun_ilmu": "Ilmu Komputer",
    "skema_id": 1,
    "periode_id": 1,
    "target_tkt": 3,
    "anggaran_diajukan": 15000000,
    "file_proposal": "(binary PDF file)"
}
```

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Proposal penelitian/pengabdian berhasil didaftarkan.",
    "data": {
        "id": "1",
        "title": "Pengembangan Sistem Mobile Terpadu Kampus",
        "subtitle": "Penelitian Dosen Pemula (Ilmu Komputer)",
        "status": "draft",
        "date": "2026-11-22",
        "amount": "Rp 15.000.000",
        "kode_proposal": "PRP-2026-001",
        "file_proposal_url": "http://localhost:8000/api/v1/storage/signed/sippm/proposal/dokumen_usulan.pdf?signature=abc123xyz",
        "raw": {}
    }
}
```

### Response Error

**400 Bad Request**
```json
{
    "status": "error",
    "message": "Format berkas proposal tidak sesuai ketentuan."
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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "judul": [
            "Judul proposal wajib diisi."
        ],
        "file_proposal": [
            "Dokumen proposal wajib diunggah dalam format PDF (maks. 10MB)."
        ],
        "anggaran_diajukan": [
            "Anggaran yang diajukan wajib berupa angka positif."
        ]
    }
}
```

---

## 24. GET /api/v1/mobile/staff/sippm/luaran

Mengambil daftar publikasi ilmiah, jurnal, prosiding, serta HKI dan buku yang telah dicatatkan oleh dosen.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `page` | integer | ❌ | 1 | Nomor halaman data |
| `per_page` | integer | ❌ | 15 | Jumlah item per halaman (maks. 100) |
| `sort_order` | string | ❌ | desc | Arah urutan (`asc` / `desc`) |
| `search` | string | ❌ | — | Cari berdasarkan judul publikasi atau HKI |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar luaran penelitian berhasil dimuat.",
    "data": [
        {
            "id": "PUB-1",
            "title": "A Smart Attendance System Using Geofencing and Biometrics",
            "subtitle": "Journal of Intelligent Systems (Jurnal)",
            "status": "terverifikasi",
            "date": "2026-10-15",
            "amount": "",
            "raw": {}
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

### Response Error

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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data luaran tidak ditemukan."
}
```

---

## 25. GET /api/v1/mobile/staff/sikeu/payroll

Mengambil riwayat slip gaji bulanan, gaji pokok, total tunjangan, total potongan, dan gaji bersih milik staf.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `page` | integer | ❌ | 1 | Nomor halaman data |
| `per_page` | integer | ❌ | 15 | Jumlah item per halaman (maks. 100) |
| `sort_order` | string | ❌ | desc | Arah urutan periode (`asc` / `desc`) |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Riwayat slip gaji payroll berhasil dimuat.",
    "data": [
        {
            "id": "5",
            "title": "Slip Gaji Periode 2026-10",
            "subtitle": "Gaji Pokok: Rp 6.000.000 | Potongan: Rp 250.000",
            "status": "sudah_ditransfer",
            "date": "2026-10-28",
            "amount": "Rp 7.500.000",
            "gaji_bersih": "7500000",
            "periode": "2026-10",
            "raw": {}
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

### Response Error

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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data payroll tidak ditemukan."
}
```

---

## 26. GET /api/v1/mobile/staff/sikeu/pengajuan

Mengambil daftar pengajuan pencairan kas, reimbursement, uang muka kerja, atau kas bon operasional milik staf yang sedang login.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `page` | integer | ❌ | 1 | Nomor halaman data |
| `per_page` | integer | ❌ | 15 | Jumlah item per halaman (maks. 100) |
| `sort_order` | string | ❌ | desc | Arah urutan (`asc` / `desc`) |
| `search` | string | ❌ | — | Cari berdasarkan judul / nomor / deskripsi |
| `status` | string | ❌ | — | Filter status approval |
| `jenis_pengajuan` | string | ❌ | — | Filter jenis pengajuan (`reimbursement`, `uang_muka`, `operasional`, `kegiatan`, `sarpras`, `lainnya`) |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar pengajuan keuangan berhasil dimuat.",
    "data": [
        {
            "id": "12",
            "title": "Reimbursement Pembelian Perangkat Praktikum",
            "subtitle": "PK-202611-0001 - Penggantian dana modul IoT",
            "status": "pending_keuangan",
            "date": "2026-11-22",
            "amount": "Rp 750.000",
            "jenis_pengajuan": "reimbursement",
            "nomor_pengajuan": "PK-202611-0001",
            "raw": {}
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

### Response Error

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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data pengajuan kas tidak ditemukan."
}
```

---

## 27. POST /api/v1/mobile/staff/sikeu/pengajuan

Mengirimkan pengajuan reimbursement, uang muka, atau dana operasional baru langsung dari ponsel staf.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `multipart/form-data` / `application/json` | ✅ |

### Request Body

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `judul_pengajuan` | string | ✅ | Judul pengajuan dana / klaim |
| `deskripsi` | string | ❌ | Uraian keperluan pengajuan |
| `jenis_pengajuan` | string | ✅ | `operasional`, `kegiatan`, `reimbursement`, `sarpras`, `lainnya` |
| `nominal_diajukan` | numeric | ✅ | Nominal rupiah yang diajukan (min: 1) |
| `nama_bank_penerima` | string | ❌ | Nama bank rekening pencairan |
| `nomor_rekening_penerima` | string | ❌ | Nomor rekening tujuan transfer |
| `nama_rekening_penerima` | string | ❌ | Nama pemegang rekening |
| `unit_kas_id` | integer | ❌ | ID unit kas yang relevan |
| `file_lampiran` | file | ❌ | Berkas pendukung/bukti nota (PDF/JPG/PNG, maks. 5MB) |

```json
{
    "judul_pengajuan": "Reimbursement Pembelian Perangkat Praktikum",
    "deskripsi": "Penggantian dana pembelian modul Arduino untuk perkuliahan IoT.",
    "jenis_pengajuan": "reimbursement",
    "nominal_diajukan": 750000,
    "nama_bank_penerima": "Bank Mandiri",
    "nomor_rekening_penerima": "1234567890",
    "nama_rekening_penerima": "Dr. Budi Santoso",
    "file_lampiran": "(binary receipt file)"
}
```

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Pengajuan keuangan berhasil dikirim.",
    "data": {
        "id": "1",
        "title": "Reimbursement Pembelian Perangkat Praktikum",
        "subtitle": "PK-202611-0001 - Penggantian dana pembelian modul Arduino untuk perkuliahan IoT.",
        "status": "pending_keuangan",
        "date": "2026-11-22",
        "amount": "Rp 750.000",
        "nomor_pengajuan": "PK-202611-0001",
        "raw": {}
    }
}
```

### Response Error

**400 Bad Request**
```json
{
    "status": "error",
    "message": "Unit kas tidak valid atau tidak memiliki akses terhadap unit kerja yang bersangkutan."
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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "judul_pengajuan": [
            "Judul pengajuan wajib diisi."
        ],
        "nominal_diajukan": [
            "Nominal pengajuan wajib berupa angka lebih dari 0."
        ],
        "jenis_pengajuan": [
            "Jenis pengajuan yang dipilih tidak valid."
        ]
    }
}
```

---

## 28. GET /api/v1/mobile/staff/sinapra/tiket

Mengambil riwayat tiket laporan kerusakan sarana prasarana yang diajukan oleh staf beserta status proses perbaikannya.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `page` | integer | ❌ | 1 | Nomor halaman data |
| `per_page` | integer | ❌ | 15 | Jumlah item per halaman (maks. 100) |
| `sort_order` | string | ❌ | desc | Arah urutan (`asc` / `desc`) |
| `search` | string | ❌ | — | Cari berdasarkan judul / deskripsi kerusakan |
| `status` | string | ❌ | — | Filter status (`dilaporkan`, `dijadwalkan`, `dalam_proses`, `selesai`) |
| `prioritas` | string | ❌ | — | Filter prioritas (`rendah`, `sedang`, `tinggi`, `darurat`) |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar tiket sarana berhasil dimuat.",
    "data": [
        {
            "id": "1",
            "title": "AC Lab Komputer 1 Rusak",
            "subtitle": "Ruangan: Lab Komputer 1 | AC tidak dingin",
            "status": "dilaporkan",
            "date": "2026-11-22",
            "amount": "",
            "prioritas": "tinggi",
            "foto_url": "http://localhost:8000/api/v1/storage/signed/sinapra/maintenance/foto_ac.jpg?signature=abc123xyz",
            "raw": {}
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

### Response Error

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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data tiket sarana tidak ditemukan."
}
```

---

## 29. POST /api/v1/mobile/staff/sinapra/tiket

Melaporkan kerusakan sarana atau fasilitas kampus baru beserta prioritas dan lampiran foto kerusakan.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `multipart/form-data` / `application/json` | ✅ |

### Request Body

| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `judul` | string | ✅ | Judul kerusakan / keluhan fasilitas |
| `deskripsi_kerusakan` | string | ✅ | Deskripsi rinci kerusakan |
| `prioritas` | string | ❌ | `rendah`, `sedang`, `tinggi`, `darurat` (default: `sedang`) |
| `ruangan_id` | integer | ❌ | ID ruangan lokasi kerusakan |
| `aset_id` | integer | ❌ | ID aset yang mengalami kerusakan |
| `foto` | file | ❌ | Foto bukti kerusakan (JPG/PNG, maks. 5MB) |

```json
{
    "judul": "AC Lab Komputer 1 Rusak",
    "deskripsi_kerusakan": "AC tidak mengeluarkan udara dingin dan meneteskan air.",
    "prioritas": "tinggi",
    "ruangan_id": 2,
    "aset_id": 15,
    "foto": "(binary image file)"
}
```

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Laporan kerusakan berhasil dilaporkan.",
    "data": {
        "id": "1",
        "title": "AC Lab Komputer 1 Rusak",
        "subtitle": "AC tidak mengeluarkan udara dingin dan meneteskan air.",
        "status": "dilaporkan",
        "date": "2026-11-22",
        "amount": "",
        "prioritas": "tinggi",
        "foto_url": "http://localhost:8000/api/v1/storage/signed/sinapra/maintenance/foto_ac.jpg?signature=abc123xyz",
        "raw": {}
    }
}
```

### Response Error

**400 Bad Request**
```json
{
    "status": "error",
    "message": "Ruangan atau aset yang dilaporkan tidak valid."
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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "judul": [
            "Judul laporan kerusakan wajib diisi."
        ],
        "deskripsi_kerusakan": [
            "Deskripsi kerusakan wajib diisi."
        ],
        "prioritas": [
            "Prioritas kerusakan harus salah satu dari: rendah, sedang, tinggi, darurat."
        ]
    }
}
```

---

## 30. GET /api/v1/mobile/staff/sinapra/peminjaman

Mengambil riwayat dan status peminjaman ruangan atau aset sarana milik staf yang login.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `page` | integer | ❌ | 1 | Nomor halaman data |
| `per_page` | integer | ❌ | 15 | Jumlah item per halaman (maks. 100) |
| `sort_order` | string | ❌ | desc | Arah urutan (`asc` / `desc`) |
| `jenis` | string | ❌ | ruangan | Jenis peminjaman (`ruangan` atau `aset`) |
| `search` | string | ❌ | — | Cari keperluan atau nama ruangan / aset |
| `status` | string | ❌ | — | Filter status peminjaman (`pending`, `disetujui`, `ditolak`, `selesai`) |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar peminjaman sarana & ruangan berhasil dimuat.",
    "data": [
        {
            "id": "RUANG-3",
            "title": "Auditorium Utama (Lt. 3)",
            "subtitle": "Seminar Nasional Teknologi Informasi",
            "status": "disetujui",
            "date": "2026-11-30",
            "amount": "",
            "raw": {}
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

### Response Error

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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data peminjaman tidak ditemukan."
}
```

---

## 31. GET /api/v1/mobile/staff/arsip/e-file

Mengambil daftar berkas dan dokumen kepegawaian pribadi staf yang tersimpan pada arsip digital (seperti SK Pengangkatan, Ijazah, KTP, KK, Sertifikat Pendidik).

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `page` | integer | ❌ | 1 | Nomor halaman data |
| `per_page` | integer | ❌ | 15 | Jumlah item per halaman (maks. 100) |
| `sort_order` | string | ❌ | desc | Arah urutan waktu (`asc` / `desc`) |
| `search` | string | ❌ | — | Cari berdasarkan nama atau kategori dokumen |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar dokumen e-file berhasil dimuat.",
    "data": [
        {
            "id": "4",
            "title": "SK Pengangkatan Dosen Tetap",
            "subtitle": "SK Kepegawaian (1.2 MB)",
            "status": "terverifikasi",
            "date": "2026-08-01",
            "amount": "",
            "file_url": "http://localhost:8000/api/v1/storage/signed/simpeg/dokumen/sk_pengangkatan.pdf?signature=abc123xyz",
            "raw": {}
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

### Response Error

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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data dokumen e-file tidak ditemukan."
}
```

---

## 32. GET /api/v1/mobile/staff/arsip/surat

Mengambil arsip persuratan resmi dan surat tugas yang diterbitkan untuk atau oleh staf.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `page` | integer | ❌ | 1 | Nomor halaman data |
| `per_page` | integer | ❌ | 15 | Jumlah item per halaman (maks. 100) |
| `sort_order` | string | ❌ | desc | Arah urutan waktu (`asc` / `desc`) |
| `jenis` | string | ❌ | surat | Jenis arsip (`surat` untuk nomor surat / surat resmi, atau `tugas` untuk surat tugas staf) |
| `search` | string | ❌ | — | Cari perihal, peruntukan, atau nomor surat |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar surat masuk & keluar berhasil dimuat.",
    "data": [
        {
            "id": "SRT-10",
            "title": "Undangan Rapat Koordinasi Kurikulum",
            "subtitle": "005/UN/AKD/2026 | Tujuan: Seluruh Dosen Tetap",
            "status": "terbit",
            "date": "2026-11-20",
            "amount": "",
            "raw": {}
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

### Response Error

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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data persuratan tidak ditemukan."
}
```

---

## 33. GET /api/v1/mobile/staff/lms/bahan-ajar

Mengambil daftar materi pembelajaran, file presentasi, modul ajar, dan RPS pada kelas-kelas yang diampu oleh dosen.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `page` | integer | ❌ | 1 | Nomor halaman data |
| `per_page` | integer | ❌ | 15 | Jumlah item per halaman (maks. 100) |
| `sort_order` | string | ❌ | desc | Arah urutan (`asc` / `desc`) |
| `search` | string | ❌ | — | Cari berdasarkan judul materi atau nama kelas |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar bahan ajar berhasil dimuat.",
    "data": [
        {
            "id": "2",
            "title": "Modul Pertemuan 1 - Pengantar Web Frameworks",
            "subtitle": "Pemrograman Web Lanjut (Pertemuan ke-1)",
            "status": "published",
            "date": "2026-09-02",
            "amount": "",
            "raw": {}
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

### Response Error

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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data bahan ajar tidak ditemukan."
}
```

---

## 34. GET /api/v1/mobile/staff/lms/pengumuman

Mengambil notifikasi dan pengumuman kelas perkuliahan terkini dosen.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `page` | integer | ❌ | 1 | Nomor halaman data |
| `per_page` | integer | ❌ | 15 | Jumlah item per halaman (maks. 100) |
| `sort_order` | string | ❌ | desc | Arah urutan (`asc` / `desc`) |
| `search` | string | ❌ | — | Cari judul atau isi pengumuman |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar pengumuman kelas berhasil dimuat.",
    "data": [
        {
            "id": "1",
            "title": "Pengingat Pengumpulan Tugas 1",
            "subtitle": "Batas akhir pengumpulan tugas pada tanggal 25 November 2026 pukul 23:59 WIB.",
            "status": "baru",
            "date": "2026-11-22",
            "amount": "",
            "raw": {}
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

### Response Error

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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data pengumuman tidak ditemukan."
}
```

---

## 35. GET /api/v1/mobile/staff/spmb/referral

Mengambil kode referral unik staf, statistik perolehan calon mahasiswa baru, tautan pendaftaran, dan daftar pendaftar binaan.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `page` | integer | ❌ | 1 | Nomor halaman data |
| `per_page` | integer | ❌ | 15 | Jumlah item per halaman (maks. 100) |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Data referral SPMB berhasil dimuat.",
    "data": [
        {
            "id": "1",
            "title": "Kode Referral: REF-STAFF123",
            "subtitle": "Bagikan kode atau link referral kepada calon mahasiswa baru",
            "status": "aktif",
            "date": "2026-11-22",
            "amount": "Total: 5 pendaftar",
            "referral_code": "REF-STAFF123",
            "kode_referral": "REF-STAFF123",
            "share_url": "http://localhost:8000/spmb/daftar?ref=REF-STAFF123",
            "stats": {
                "total": 5,
                "claimed": 3,
                "qualified": 2,
                "rewarded": 1,
                "cancelled": 0
            },
            "raw": {}
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

### Response Error

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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data referral SPMB tidak ditemukan."
}
```

---

## 36. POST /api/v1/mobile/staff/spmb/referral

Membuat atau memperbarui tautan referral eksternal serta muatan QR Code untuk dibagikan ke calon mahasiswa baru.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Link referral SPMB berhasil dibuat.",
    "data": {
        "referral_code": "REF-STAFF123",
        "kode_referral": "REF-STAFF123",
        "share_url": "http://localhost:8000/spmb/daftar?ref=REF-STAFF123",
        "qr_payload": "http://localhost:8000/spmb/daftar?ref=REF-STAFF123",
        "stats": {
            "total": 5,
            "claimed": 3,
            "qualified": 2,
            "rewarded": 1,
            "cancelled": 0
        }
    }
}
```

### Response Error

**400 Bad Request**
```json
{
    "status": "error",
    "message": "Tidak dapat membuat referral untuk pengguna ini."
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
    "message": "Anda tidak memiliki izin untuk melakukan aksi ini."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "referral": [
            "Gagal menghasilkan kode referral baru."
        ]
    }
}
```


