# ObeMasterController

> **Modul**: SIAKAD  
> **Base URL**: `/api/v1/siakad/akademik`  
> **Autentikasi**: Bearer Token (Sanctum / Passport)  
> **Dibuat/Diperbarui**: 2026-10-08

Mengelola data master kurikulum OBE untuk Admin OBE Homebase Program Studi dan BAAK:
1. Rumpun Mata Kuliah (`siakad_rumpun_mk`)
2. Jenis / Aspek CPL SN-DIKTI (`siakad_jenis_cpl`)
3. Profesi / Prospek Karir Lulusan (`siakad_profesi_karir`)
4. Rubrik Asesmen OBE & Butir Kriteria (`siakad_obe_rubrik`, `siakad_obe_rubrik_kriteria`)
5. Distribusi Mengajar Dosen per Semester (`siakad_distribusi_mengajar`)
6. Master Kelas & Pemetaan Mahasiswa Kelas (`siakad_master_kelas`)
7. Distribusi Mata Kuliah visual (`siakad_distribusi_matakuliah`)
8. Plotting Role-Menu Admin OBE (`siakad_admin_obe_role_menus`)

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/siakad/obe/rumpun-mk` | Daftar Rumpun Mata Kuliah | ✅ |
| POST | `/api/v1/siakad/obe/rumpun-mk` | Tambah Rumpun Mata Kuliah | ✅ |
| PUT | `/api/v1/siakad/obe/rumpun-mk/{id}` | Perbarui Rumpun Mata Kuliah | ✅ |
| DELETE | `/api/v1/siakad/obe/rumpun-mk/{id}` | Hapus Rumpun Mata Kuliah | ✅ |
| GET | `/api/v1/siakad/obe/jenis-cpl` | Daftar Jenis CPL | ✅ |
| POST | `/api/v1/siakad/obe/jenis-cpl` | Tambah Jenis CPL | ✅ |
| PUT | `/api/v1/siakad/obe/jenis-cpl/{id}` | Perbarui Jenis CPL | ✅ |
| DELETE | `/api/v1/siakad/obe/jenis-cpl/{id}` | Hapus Jenis CPL | ✅ |
| GET | `/api/v1/siakad/obe/profesi-karir` | Daftar Profesi / Karir | ✅ |
| POST | `/api/v1/siakad/obe/profesi-karir` | Tambah Profesi / Karir | ✅ |
| PUT | `/api/v1/siakad/obe/profesi-karir/{id}` | Perbarui Profesi / Karir | ✅ |
| DELETE | `/api/v1/siakad/obe/profesi-karir/{id}` | Hapus Profesi / Karir | ✅ |
| GET | `/api/v1/siakad/obe/rubrik` | Daftar Rubrik Penilaian | ✅ |
| POST | `/api/v1/siakad/obe/rubrik` | Tambah Rubrik Penilaian | ✅ |
| PUT | `/api/v1/siakad/obe/rubrik/{id}` | Perbarui Rubrik Penilaian | ✅ |
| DELETE | `/api/v1/siakad/obe/rubrik/{id}` | Hapus Rubrik Penilaian | ✅ |
| GET | `/api/v1/siakad/obe/distribusi-mengajar` | Daftar Distribusi Mengajar | ✅ |
| POST | `/api/v1/siakad/obe/distribusi-mengajar` | Simpan Distribusi Mengajar | ✅ |
| PUT | `/api/v1/siakad/obe/distribusi-mengajar/{id}` | Perbarui Distribusi Mengajar | ✅ |
| DELETE | `/api/v1/siakad/obe/distribusi-mengajar/{id}` | Hapus Distribusi Mengajar | ✅ |
| GET | `/api/v1/siakad/obe/master-kelas` | Daftar Master Kelas | ✅ |
| POST | `/api/v1/siakad/obe/master-kelas` | Buat Master Kelas | ✅ |
| PUT | `/api/v1/siakad/obe/master-kelas/{id}` | Perbarui Master Kelas | ✅ |
| DELETE | `/api/v1/siakad/obe/master-kelas/{id}` | Hapus Master Kelas | ✅ |
| GET | `/api/v1/siakad/obe/master-kelas/mahasiswa-pemetaan` | Daftar Mahasiswa Pemetaan Kelas | ✅ |
| POST | `/api/v1/siakad/obe/master-kelas/assign-mahasiswa` | Petakan Mahasiswa ke Kelas | ✅ |
| GET | `/api/v1/siakad/obe/distribusi-matakuliah` | Distribusi Mata Kuliah visual | ✅ |
| GET | `/api/v1/siakad/akademik/admin-obe-roles` | Daftar Role Admin OBE | ✅ |
| GET | `/api/v1/siakad/akademik/admin-obe-role-menus/{roleId}` | Daftar Menu Role Admin OBE | ✅ |
| POST | `/api/v1/siakad/akademik/admin-obe-role-menus/{roleId}` | Simpan Menu Role Admin OBE | ✅ |

---

## Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ untuk POST/PUT |

> **Kebijakan scope prodi (2026-10-08):** user scoped (non-admin tanpa role `admin`/`admin_siakad`) pada list rumpun-mk / jenis-cpl / profesi-karir / rubrik **hanya melihat baris milik prodinya** (`program_studi_id ∈ getSiakadProdiIds()`); baris global (`program_studi_id = null`) disembunyikan. Admin melihat semua; bila mengirim `program_studi_id` eksplisit, baris global tetap diikutsertakan sebagai referensi bersama. Store rumpun-mk / jenis-cpl: scoped user tanpa pilihan prodi otomatis diatribusikan ke prodi pertamanya.

> **Aturan umum OBE admin (2026-10-09):** halaman OBE admin tidak menyediakan input/filter Program Studi karena prodi selalu aktif milik pengguna. Store rubrik menolak prodi dari klien dan menurunkan sendiri dari `getSiakadProdiIds()` (lihat §4).

---

## 1. Rumpun Mata Kuliah

### [GET] `/api/v1/siakad/obe/rumpun-mk`
Mengambil daftar rumpun mata kuliah (paginated / scoped by homebase prodi).

**Query Parameters**:
| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `search` | string | ❌ | Cari nama / kode rumpun |
| `program_studi_id` | integer | ❌ | Filter prodi |
| `dosen_koordinator_id` | integer | ❌ | Filter dosen koordinator |
| `is_active` | boolean | ❌ | Filter status aktif |
| `sort_by` | string | ❌ | `nama_rumpun`, `kode_rumpun`, `created_at` |
| `sort_order` | string | ❌ | `asc` / `desc` |
| `per_page` | integer | ❌ | Limit pagination (default 15) |
| `page` | integer | ❌ | Halaman |

### [POST] `/api/v1/siakad/obe/rumpun-mk`
Menambah rumpun mata kuliah baru.

```json
{
  "nama_rumpun": "Software Engineering",
  "kode_rumpun": "RMP-SE",
  "dosen_koordinator_id": 5,
  "deskripsi": "Bidang keahlian rekayasa perangkat lunak",
  "is_active": true
}
```

### [PUT] `/api/v1/siakad/obe/rumpun-mk/{id}`
Memperbarui rumpun mata kuliah.

### [DELETE] `/api/v1/siakad/obe/rumpun-mk/{id}`
Menghapus rumpun mata kuliah.

---

## 2. Jenis CPL SN-DIKTI

### [GET] `/api/v1/siakad/obe/jenis-cpl`
Daftar kategori/aspek CPL.

### [POST] `/api/v1/siakad/obe/jenis-cpl`
Tambah jenis CPL.

```json
{
  "nama_jenis": "Sikap & Tata Nilai",
  "kode_jenis": "SIKAP",
  "urutan": 1,
  "is_active": true
}
```

### [PUT] `/api/v1/siakad/obe/jenis-cpl/{id}`
Update jenis CPL.

### [DELETE] `/api/v1/siakad/obe/jenis-cpl/{id}`
Hapus jenis CPL.

---

## 3. Profesi / Prospek Karir Lulusan

### [GET] `/api/v1/siakad/obe/profesi-karir`
Daftar prospek karir dan profesi lulusan.

### [POST] `/api/v1/siakad/obe/profesi-karir`
Tambah profesi karir baru.

```json
{
  "nama": "Software Engineer",
  "sumber": "SKKNI / Asosiasi Profesi",
  "is_active": true
}
```

### [PUT] `/api/v1/siakad/obe/profesi-karir/{id}`
Update profesi karir.

### [DELETE] `/api/v1/siakad/obe/profesi-karir/{id}`
Hapus profesi karir.

---

## 4. Rubrik Penilaian OBE

### [GET] `/api/v1/siakad/obe/rubrik`
Daftar instrumen rubrik asesmen.

### [GET] `/api/v1/siakad/obe/rubrik/{id}`
Detail rubrik beserta butir kriteria penilaian.

### [POST] `/api/v1/siakad/obe/rubrik`
Membuat rubrik penilaian baru.

> **Program studi tidak dikirim dari klien.** Sesuai aturan OBE admin, halaman tidak
> menyediakan input program studi karena prodi selalu mengikuti prodi aktif akun.
> Backend menurunkan `program_studi_id` dari `getSiakadProdiIds()`:
> 1 prodi aktif → dipakai otomatis; 0 prodi aktif → `422`; >1 prodi aktif → `422`
> kecuali body menyertakan `program_studi_id` yang berada di dalam rentang prodi
> aktif user. Prodi bersifat tetap dan tidak dipindahkan saat `PUT`.

```json
{
  "kode_rubrik": "RBK-PROJ-01",
  "nama_rubrik": "Rubrik Penilaian Proyek",
  "tipe_rubrik": "analitik",
  "kriterias": [
    {
      "nama_kriteria": "Kualitas Kode",
      "bobot_persen": 50,
      "skor_min": 80,
      "skor_max": 100,
      "deskripsi": "Sangat rapi dan modular"
    }
  ],
  "is_active": true
}
```

### [PUT] `/api/v1/siakad/obe/rubrik/{id}`
Memperbarui rubrik dan kriteria penilaian. `program_studi_id` pada body diabaikan;
prodi rubrik mengikuti nilai tersimpan saat pembuatan.

### [DELETE] `/api/v1/siakad/obe/rubrik/{id}`
Menghapus rubrik penilaian.

---

## 5. Distribusi Mengajar

### [GET] `/api/v1/siakad/obe/distribusi-mengajar`
Daftar penugasan tim pengajar mata kuliah per semester.

### [POST] `/api/v1/siakad/obe/distribusi-mengajar`
Menetapkan distribusi mengajar.

```json
{
  "kurikulum_id": 1,
  "mata_kuliah_id": 5,
  "semester": 1,
  "dosen_koordinator_id": 13,
  "dosen_anggota_ids": [14, 15],
  "is_active": true
}
```

### [PUT] `/api/v1/siakad/obe/distribusi-mengajar/{id}`
Update penugasan mengajar.

### [DELETE] `/api/v1/siakad/obe/distribusi-mengajar/{id}`
Hapus penugasan mengajar.

---

## 6. Master Kelas & Pemetaan Mahasiswa Kelas

### [GET] `/api/v1/siakad/obe/master-kelas`
Mengambil daftar master kelas (paginated, scoped prodi aktif).

**Query Parameters**:
| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `search` | string | ❌ | Cari nama kelas, keterangan, atau nama Dosen PA |
| `tahun_angkatan` | integer | ❌ | Filter tahun angkatan (contoh: 2025) |
| `dosen_pa_id` | integer | ❌ | Filter dosen PA |
| `is_active` | boolean | ❌ | Filter status |
| `sort_by` | string | ❌ | `nama_kelas`, `tahun_angkatan`, `id`, `created_at` |
| `sort_order` | string | ❌ | `asc` / `desc` |
| `per_page` | integer | ❌ | Default 15 |
| `page` | integer | ❌ | Halaman |

**Response Sukses (200 OK)**:
```json
{
  "status": "success",
  "message": "Daftar master kelas berhasil diambil",
  "data": [
    {
      "id": 1,
      "program_studi_id": 1,
      "nama_kelas": "25A",
      "tahun_angkatan": 2025,
      "dosen_pa_id": 1,
      "dosen_pa": { "id": 1, "nama_lengkap": "Dr. Dosen, M.Kom." },
      "mahasiswas_count": 30,
      "is_active": true
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 1,
    "last_page": 1
  }
}
```

### [POST] `/api/v1/siakad/obe/master-kelas`
Membuat master kelas baru.

**Request Body**:
```json
{
  "nama_kelas": "25A",
  "tahun_angkatan": 2025,
  "dosen_pa_id": 1,
  "keterangan": "Kelas Reguler Pagi",
  "is_active": true
}
```

**Response Sukses (201 Created)**:
```json
{
  "status": "success",
  "message": "Master kelas berhasil dibuat",
  "data": {
    "id": 1,
    "program_studi_id": 1,
    "nama_kelas": "25A",
    "tahun_angkatan": 2025,
    "dosen_pa_id": 1,
    "keterangan": "Kelas Reguler Pagi",
    "is_active": true,
    "dosen_pa": { "id": 1, "nama_lengkap": "Dr. Dosen, M.Kom." }
  }
}
```

**Response Error (422 Unprocessable Entity)**:
```json
{
  "status": "error",
  "message": "Nama kelas 25A sudah ada untuk angkatan 2025 di program studi ini."
}
```

### [PUT] `/api/v1/siakad/obe/master-kelas/{id}`
Memperbarui master kelas.

**Request Body**:
```json
{
  "nama_kelas": "25A",
  "tahun_angkatan": 2025,
  "dosen_pa_id": 1,
  "keterangan": "Kelas Reguler Pagi - Update",
  "is_active": true
}
```

**Response Sukses (200 OK)**:
```json
{
  "status": "success",
  "message": "Master kelas berhasil diperbarui",
  "data": {
    "id": 1,
    "nama_kelas": "25A",
    "tahun_angkatan": 2025,
    "dosen_pa_id": 1
  }
}
```

### [DELETE] `/api/v1/siakad/obe/master-kelas/{id}`
Menghapus master kelas (soft delete).

**Response Sukses (200 OK)**:
```json
{
  "status": "success",
  "message": "Master kelas berhasil dihapus",
  "data": null
}
```

### [GET] `/api/v1/siakad/obe/master-kelas/mahasiswa-pemetaan`
Mengambil daftar mahasiswa aktif untuk pemetaan kelas (dikunci server-side ke prodi aktif).

**Query Parameters**:
| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `search` | string | ❌ | Cari NIM, Nama, atau Kelas saat ini |
| `angkatan` | integer | ❌ | Filter tahun angkatan mahasiswa |
| `kelas` | string | ❌ | Filter kelas saat ini |
| `hanya_belum_ada_kelas` | boolean | ❌ | Filter hanya mahasiswa yang belum memiliki kelas |
| `per_page` | integer | ❌ | Default 25 |
| `page` | integer | ❌ | Halaman |

**Response Sukses (200 OK)**:
```json
{
  "status": "success",
  "message": "Daftar mahasiswa untuk pemetaan kelas berhasil diambil",
  "data": [
    {
      "id": 10,
      "nim": "2501001",
      "nama_lengkap": "Budi Santoso",
      "angkatan": 2025,
      "kelas": "25A",
      "master_kelas_id": 1
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 1,
    "last_page": 1
  }
}
```

### [POST] `/api/v1/siakad/obe/master-kelas/assign-mahasiswa`
Menetapkan daftar mahasiswa ke dalam suatu master kelas secara massal dan menyinkronkan Dosen PA kelas.

**Request Body**:
```json
{
  "master_kelas_id": 1,
  "mahasiswa_ids": [10, 11, 12],
  "sync_dosen_pa": true
}
```

**Response Sukses (200 OK)**:
```json
{
  "status": "success",
  "message": "Berhasil memetakan 3 mahasiswa ke kelas 25A.",
  "data": {
    "updated_count": 3,
    "kelas": "25A"
  }
}
```

---

## 7. Role Menu Plotting Admin OBE

### [GET] `/api/v1/siakad/akademik/admin-obe-roles`
Daftar role pengguna yang relevan untuk penugasan Admin OBE.

### [GET] `/api/v1/siakad/akademik/admin-obe-role-menus/{roleId}`
Daftar menu yang di-plot untuk role Admin OBE tertentu.

### [POST] `/api/v1/siakad/akademik/admin-obe-role-menus/{roleId}`
Menyimpan konfigurasi menu khusus Admin OBE untuk role terpilih.

```json
{
  "menu_ids": [1, 20, 21, 72, 73, 74, 75, 76, 77, 78, 79]
}
```
