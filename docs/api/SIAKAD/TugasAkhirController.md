# TugasAkhirController

> **Modul**: SIAKAD  
> **Base URL**: `/api/v1/siakad/ta`  
> **Autentikasi**: Bearer Token (Passport / IAM)  
> **Dibuat**: 2026-10-23  
> **Diperbarui**: 2026-10-23  

Siklus menyeluruh Tugas Akhir / Skripsi mahasiswa: pengajuan proposal judul, approval kaprodi & penugasan pembimbing 1 & 2, logbook asistensi bimbingan (threshold minimal 8 ACC), penjadwalan sidang, input penilaian rubrik majelis penguji, dan finalisasi nilai akhir komposit.

## Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ (POST/PUT/PATCH) |

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/siakad/ta` | Daftar direktori Tugas Akhir mahasiswa | ✅ `siakad.ta.read` |
| POST | `/api/v1/siakad/ta` | Pengajuan proposal judul skripsi baru | ✅ `siakad.ta.create` |
| GET | `/api/v1/siakad/ta/{id}` | Detail berkas & tahapan Tugas Akhir | ✅ `siakad.ta.read` |
| PATCH | `/api/v1/siakad/ta/{id}/approve-proposal` | Approval proposal & plotting pembimbing 1 & 2 | ✅ `siakad.ta.manage` |
| POST | `/api/v1/siakad/ta/{id}/bimbingan` | Input logbook asistensi bimbingan skripsi | ✅ `siakad.ta.create` |
| PATCH | `/api/v1/siakad/ta/{id}/bimbingan/{bimbinganId}/verify` | Verifikasi status asistensi (ACC / Revisi) | ✅ `siakad.ta.manage` |
| POST | `/api/v1/siakad/ta/{id}/sidang` | Penjadwalan sidang & plotting 4 penguji | ✅ `siakad.ta.manage` |
| POST | `/api/v1/siakad/ta/sidang/{sidangId}/nilai` | Input lembar skor rubrik penguji sidang | ✅ `siakad.ta.manage` |
| POST | `/api/v1/siakad/ta/sidang/{sidangId}/finalize` | Finalisasi sidang & hitung komposit nilai akhir | ✅ `siakad.ta.manage` |

## Query Parameters (Endpoint List)

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Cari judul utama, judul inggris, bidang kajian, nama/NIM |
| `program_studi_id` | integer | ❌ | — | Filter ID program studi (`siakad_program_studi`) |
| `tahap` | string | ❌ | — | Filter tahap (`proposal`, `penelitian`, `seminar_hasil`, `sidang_skripsi`, `revisi_akhir`, `lulus`) |
| `status` | string | ❌ | — | Filter status (`draft`, `diajukan`, `disetujui_prodi`, `sedang_bimbingan`, `siap_sidang`, `revisi`, `selesai`, `ditolak`) |
| `my_advisees_only` | boolean | ❌ | true | Khusus dosen: hanya mahasiswa bimbingan 1 / 2 |
| `per_page` | integer | ❌ | 15 | Batas pagination (maks: 100) |
| `page` | integer | ❌ | 1 | Halaman data |
| `sort_order` | string | ❌ | desc | Urutan berdasarkan `created_at` (`asc` / `desc`) |

---

## 1. [GET] /api/v1/siakad/ta

### Response 200 OK
```json
{
  "status": "success",
  "message": "Daftar Tugas Akhir berhasil dimuat.",
  "data": [
    {
      "id": 1,
      "mahasiswa_id": 10,
      "tahun_akademik_id": 2,
      "program_studi_id": 1,
      "judul_utama": "Perancangan Microservices Architecture Berbasis Domain-Driven Design",
      "judul_inggris": "Design of Microservices Architecture Based on Domain-Driven Design",
      "bidang_kajian": "Software Engineering",
      "tahap": "sidang_skripsi",
      "status": "siap_sidang",
      "total_sesi_bimbingan": 10,
      "created_at": "2026-10-23T21:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```

### Response 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

### Response 403 Forbidden
```json
{
  "status": "error",
  "message": "This action is unauthorized."
}
```

---

## 2. [POST] /api/v1/siakad/ta

### Request Body
```json
{
  "tahun_akademik_id": 2,
  "judul_utama": "Sistem Terdistribusi Berbasis Event Streaming Menggunakan Apache Kafka",
  "judul_inggris": "Distributed System Based on Event Streaming Using Apache Kafka",
  "bidang_kajian": "Cloud & Distributed Systems",
  "abstrak": "Penelitian ini menganalisis efisiensi konsumsi pesan transaksi dalam arsitektur berskala besar...",
  "usulan_pembimbing_1_id": 5,
  "usulan_pembimbing_2_id": 6
}
```

### Response 201 Created
```json
{
  "status": "success",
  "message": "Pengajuan proposal Tugas Akhir berhasil disimpan dan menunggu verifikasi Program Studi.",
  "data": {
    "id": 1,
    "status": "diajukan",
    "judul_utama": "Sistem Terdistribusi Berbasis Event Streaming Menggunakan Apache Kafka"
  }
}
```

### Response 422 Unprocessable Entity
```json
{
  "status": "error",
  "message": "The judul utama field is required.",
  "errors": {
    "judul_utama": ["The judul utama field must be at least 10 characters."]
  }
}
```

---

## 3. [GET] /api/v1/siakad/ta/{id}

### Path Parameters
| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID Tugas Akhir (`siakad_tugas_akhir.id`) |

### Response 200 OK
```json
{
  "status": "success",
  "message": "Detail Tugas Akhir berhasil dimuat.",
  "data": {
    "id": 1,
    "mahasiswa_id": 10,
    "tahun_akademik_id": 2,
    "program_studi_id": 1,
    "judul_utama": "Sistem Terdistribusi Berbasis Event Streaming Menggunakan Apache Kafka",
    "judul_inggris": "Distributed System Based on Event Streaming Using Apache Kafka",
    "bidang_kajian": "Cloud & Distributed Systems",
    "abstrak": "Penelitian ini menganalisis...",
    "tahap": "proposal",
    "status": "diajukan",
    "bimbingans": [],
    "sidangs": []
  }
}
```

### Response 404 Not Found
```json
{
  "status": "error",
  "message": "Data Tugas Akhir tidak ditemukan."
}
```

---

## 4. [PATCH] /api/v1/siakad/ta/{id}/approve-proposal

### Path Parameters
| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID Tugas Akhir |

### Request Body
```json
{
  "status": "disetujui_prodi",
  "dosen_pembimbing_1_id": 5,
  "dosen_pembimbing_2_id": 6,
  "nomor_sk_pembimbing": "SK-TA/TI/2026/X/012",
  "tanggal_sk_pembimbing": "2026-10-24"
}
```

### Response 200 OK
```json
{
  "status": "success",
  "message": "Proposal Tugas Akhir disetujui dan pembimbing berhasil ditetapkan.",
  "data": {
    "id": 1,
    "status": "disetujui_prodi",
    "dosen_pembimbing_1_id": 5,
    "dosen_pembimbing_2_id": 6
  }
}
```

### Response 404 Not Found
```json
{
  "status": "error",
  "message": "Data Tugas Akhir tidak ditemukan."
}
```

### Response 422 Unprocessable Entity
```json
{
  "status": "error",
  "message": "The dosen pembimbing 1 id field is required.",
  "errors": {
    "dosen_pembimbing_1_id": ["The dosen pembimbing 1 id field is required."]
  }
}
```

---

## 5. [POST] /api/v1/siakad/ta/{id}/bimbingan

### Path Parameters
| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID Tugas Akhir |

### Request Body
```json
{
  "dosen_id": 5,
  "tanggal": "2026-11-05",
  "bab_pembahasan": "BAB III Metodologi Penelitian",
  "uraian_konsultasi": "Membahas diagram sequence pipeline pemrosesan pesan dan perancangan skema database."
}
```

### Response 201 Created
```json
{
  "status": "success",
  "message": "Log asistensi bimbingan berhasil dicatat.",
  "data": {
    "id": 1,
    "tugas_akhir_id": 1,
    "dosen_id": 5,
    "tanggal": "2026-11-05",
    "bab_pembahasan": "BAB III Metodologi Penelitian",
    "status_validasi": "menunggu"
  }
}
```

### Response 404 Not Found
```json
{
  "status": "error",
  "message": "Data Tugas Akhir tidak ditemukan."
}
```

### Response 422 Unprocessable Entity
```json
{
  "status": "error",
  "message": "The bab pembahasan field is required.",
  "errors": {
    "bab_pembahasan": ["The bab pembahasan field is required."]
  }
}
```

---

## 6. [PATCH] /api/v1/siakad/ta/{id}/bimbingan/{bimbinganId}/verify

### Path Parameters
| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID Tugas Akhir |
| `bimbinganId` | integer | ✅ | ID Log Bimbingan |

### Request Body
```json
{
  "status_validasi": "disetujui",
  "catatan_revisi": "Bab III selesai disetujui, lanjut implementasi."
}
```

### Response 200 OK
```json
{
  "status": "success",
  "message": "Catatan bimbingan Tugas Akhir berhasil diverifikasi.",
  "data": {
    "id": 1,
    "tugas_akhir_id": 1,
    "status_validasi": "disetujui",
    "catatan_revisi": "Bab III selesai disetujui, lanjut implementasi."
  }
}
```

### Response 404 Not Found
```json
{
  "status": "error",
  "message": "Data log bimbingan tidak ditemukan."
}
```

### Response 422 Unprocessable Entity
```json
{
  "status": "error",
  "message": "The status validasi field is required.",
  "errors": {
    "status_validasi": ["The selected status validasi is invalid."]
  }
}
```

---

## 7. [POST] /api/v1/siakad/ta/{id}/sidang

### Path Parameters
| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID Tugas Akhir |

### Request Body
```json
{
  "jenis_sidang": "sidang_skripsi",
  "tanggal_sidang": "2027-02-20",
  "jam_mulai": "09:00",
  "jam_selesai": "11:00",
  "ruangan_id": 1,
  "penguji": [
    { "dosen_id": 1, "peran_penguji": "ketua" },
    { "dosen_id": 2, "peran_penguji": "sekretaris" },
    { "dosen_id": 5, "peran_penguji": "penguji_1" },
    { "dosen_id": 6, "peran_penguji": "penguji_2" }
  ]
}
```

### Response 201 Created
```json
{
  "status": "success",
  "message": "Jadwal sidang dan majelis penguji berhasil ditetapkan.",
  "data": {
    "id": 1,
    "tugas_akhir_id": 1,
    "jenis_sidang": "sidang_skripsi",
    "tanggal_sidang": "2027-02-20",
    "status_kelulusan": "belum_sidang"
  }
}
```

### Response 404 Not Found
```json
{
  "status": "error",
  "message": "Data Tugas Akhir tidak ditemukan."
}
```

### Response 422 Unprocessable Entity
```json
{
  "status": "error",
  "message": "The penguji field is required.",
  "errors": {
    "penguji": ["The penguji field is required."]
  }
}
```

---

## 8. [POST] /api/v1/siakad/ta/sidang/{sidangId}/nilai

### Path Parameters
| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `sidangId` | integer | ✅ | ID Sidang (`siakad_ta_sidang.id`) |

### Request Body
```json
{
  "dosen_id": 1,
  "skor_metodologi": 86.00,
  "skor_materi_skripsi": 90.00,
  "skor_presentasi": 88.00,
  "skor_penguasaan_materi": 88.00,
  "catatan_revisi": "Perjelas arsitektur fallback pesan antrean."
}
```

### Response 200 OK
```json
{
  "status": "success",
  "message": "Penilaian penguji sidang berhasil disimpan.",
  "data": {
    "id": 1,
    "skor_total": "88.00",
    "is_submitted": true
  }
}
```

### Response 404 Not Found
```json
{
  "status": "error",
  "message": "Data sidang tidak ditemukan."
}
```

### Response 422 Unprocessable Entity
```json
{
  "status": "error",
  "message": "Sidang telah dikunci dan tidak dapat menerima penilaian baru."
}
```

---

## 9. [POST] /api/v1/siakad/ta/sidang/{sidangId}/finalize

### Path Parameters
| Parameter | Type | Required | Deskripsi |
|---|---|---|---|
| `sidangId` | integer | ✅ | ID Sidang |

### Request Body
```json
{
  "status_kelulusan": "lulus_revisi_ringan",
  "batas_waktu_revisi": "2027-03-05",
  "catatan_sidang": "Lulus dengan revisi minor pada diagram arsitektur."
}
```

### Response 200 OK
```json
{
  "status": "success",
  "message": "Sidang Tugas Akhir berhasil difinalisasi dan nilai akhir telah dikompositkan.",
  "data": {
    "sidang": {
      "id": 1,
      "status_kelulusan": "lulus_revisi_ringan",
      "is_locked": true
    },
    "tugas_akhir": {
      "id": 1,
      "status": "revisi",
      "nilai_angka_akhir": "88.00",
      "nilai_huruf_akhir": "A",
      "bobot_mutu_akhir": "4.00"
    }
  }
}
```

### Response 404 Not Found
```json
{
  "status": "error",
  "message": "Data sidang tidak ditemukan."
}
```

### Response 422 Unprocessable Entity
```json
{
  "status": "error",
  "message": "Sidang ini sudah pernah difinalisasi."
}
```
