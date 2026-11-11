# ReviewerKegiatanController

> **Modul**: SIPPM (Sistem Informasi Penelitian dan Pengabdian Masyarakat)  
> **Base URL**: `/api/sippm`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-11-11  
> **Diperbarui**: 2026-11-11  

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/sippm/reviewer/assigned` | Mengambil daftar usulan yang ditugaskan ke reviewer login | ✅ Bearer |
| POST | `/api/sippm/reviewer/{id}/penilaian` | Menyimpan penilaian desk evaluation reviewer | ✅ Bearer |
| POST | `/api/sippm/proposal/{id}/finalize` | Menetapkan status akhir proposal (lolos/ditolak) | ✅ Admin LPPM |

---

## 1. GET /api/sippm/reviewer/assigned

> Mengambil daftar penugasan evaluasi proposal yang dibebankan kepada reviewer yang sedang login.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `reviewer_pegawai_id` | integer | ❌ | — | Filter ID pegawai reviewer |
| `sort_by` | string | ❌ | `tgl_penugasan` | Kolom pengurutan data |
| `sort_order` | string | ❌ | `desc` | Arah urutan (`asc` / `desc`) |
| `per_page` | integer | ❌ | `15` | Pagination limit |
| `page` | integer | ❌ | `1` | Nomor halaman |

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Daftar penugasan reviewer berhasil dimuat.",
  "data": [
    {
      "id": 1,
      "proposal_id": 1,
      "reviewer_pegawai_id": 2,
      "peran_reviewer": "reviewer_1",
      "status_penilaian": "belum_dinilai",
      "proposal": {
        "id": 1,
        "judul": "Pengembangan Sistem Cerdas Pengenalan Pola",
        "abstrak": "Penelitian implementasi sistem cerdas...",
        "anggaran_diajukan": 20000000
      }
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

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Permintaan tidak valid atau parameter tidak sesuai."
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
  "message": "Anda tidak memiliki otorisasi sebagai reviewer penugasan."
}
```

**404 Not Found**
```json
{
  "status": "error",
  "message": "Data penugasan reviewer tidak ditemukan."
}
```

---

## 2. POST /api/sippm/reviewer/{id}/penilaian

> Menyimpan penilaian desk evaluation oleh reviewer.

### Request Body

```json
{
  "skor_rekam_jejak": 85,
  "skor_substansi": 90,
  "skor_rencana_anggaran": 80,
  "rekomendasi": "diterima",
  "catatan_revisi": "Proposal sangat baik dan memenuhi kriteria pendanaan."
}
```

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Penilaian reviewer berhasil disimpan.",
  "data": {
    "id": 1,
    "reviewer_kegiatan_id": 1,
    "skor_rekam_jejak": 85,
    "skor_substansi": 90,
    "skor_rencana_anggaran": 80,
    "skor_total": 86.25,
    "rekomendasi": "diterima",
    "catatan_revisi": "Proposal sangat baik dan memenuhi kriteria pendanaan.",
    "created_at": "2026-11-11T10:00:00.000000Z",
    "updated_at": "2026-11-11T10:00:00.000000Z"
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Permintaan tidak valid atau format data salah."
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
  "message": "Anda tidak memiliki otorisasi untuk menilai proposal ini."
}
```

**404 Not Found**
```json
{
  "status": "error",
  "message": "Data penugasan reviewer tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
  "status": "error",
  "message": "Validasi gagal.",
  "errors": {
    "skor_substansi": ["Skor substansi wajib diisi dan berupa angka 0-100."],
    "rekomendasi": ["Rekomendasi wajib dipilih (diterima/ditolak/revisi)."]
  }
}
```

---

## 3. POST /api/sippm/proposal/{id}/finalize

> Menetapkan status keputusan akhir proposal (lolos/ditolak) serta alokasi anggaran disetujui.

### Request Body

```json
{
  "status_final": "lolos",
  "dana_disetujui": 18000000,
  "catatan_komite": "Disetujui dengan penyesuaian anggaran komponen sewa alat."
}
```

### Response Sukses (200 OK)

```json
{
  "status": "success",
  "message": "Keputusan akhir proposal berhasil disimpan.",
  "data": {
    "id": 1,
    "status": "lolos",
    "anggaran_disetujui": 18000000,
    "updated_at": "2026-11-11T10:30:00.000000Z"
  }
}
```

### Response Error

**400 Bad Request**
```json
{
  "status": "error",
  "message": "Permintaan tidak valid atau status proposal tidak sesuai."
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
  "message": "Anda tidak memiliki hak akses untuk memfinalisasi proposal."
}
```

**404 Not Found**
```json
{
  "status": "error",
  "message": "Proposal kegiatan tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
  "status": "error",
  "message": "Validasi gagal.",
  "errors": {
    "status_final": ["Status final wajib berupa lolos atau ditolak."],
    "dana_disetujui": ["Dana disetujui wajib diisi jika proposal dinyatakan lolos."]
  }
}
```
