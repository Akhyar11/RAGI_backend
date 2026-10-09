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
6. Distribusi Mata Kuliah visual (`siakad_distribusi_matakuliah`)
7. Plotting Role-Menu Admin OBE (`siakad_admin_obe_role_menus`)

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

## 6. Role Menu Plotting Admin OBE

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
