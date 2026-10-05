# HasilSeleksiController

> **Modul**: SPMB / **Base URL**: `/api/spmb` / **Autentikasi**: Bearer Token (Sanctum) / **Dibuat/Diperbarui**: 2026-10-05

Menetapkan **hasil seleksi (kelulusan)** calon mahasiswa. Setelah status `lulus` ditetapkan, calon dapat melakukan **daftar ulang** (generate tagihan). Semua endpoint memerlukan permission granular `spmb.seleksi.read` / `spmb.seleksi.update`.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/spmb/pendaftaran/{id}/hasil-seleksi` | Lihat hasil seleksi satu pendaftaran | ✅ `spmb.seleksi.read` |
| POST | `/api/spmb/pendaftaran/{id}/tetapkan-kelulusan` | Tetapkan hasil seleksi (lulus/tidak lulus/cadangan) | ✅ `spmb.seleksi.update` |

---

## [GET] /api/spmb/pendaftaran/{id}/hasil-seleksi

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Hasil seleksi berhasil dimuat.",
    "data": {
        "id": 1,
        "pendaftaran_id": 10,
        "program_studi_diterima_id": 7,
        "nilai_total": "88.00",
        "peringkat": 12,
        "status": "lulus",
        "status_daftar_ulang": "belum",
        "catatan": null,
        "diumumkan_at": "2026-10-05T03:36:07.000000Z"
    }
}
```

> Bila belum ada hasil seleksi, `data` bernilai `null`.

### Response Error

**401 Unauthorized**
```json
{ "status": "error", "message": "Unauthenticated." }
```

**403 Forbidden** (tanpa permission `spmb.seleksi.read`)
```json
{ "status": "error", "message": "Anda tidak memiliki hak akses untuk melihat hasil seleksi." }
```

**404 Not Found** (pendaftaran tidak ditemukan)
```json
{ "status": "error", "message": "No query results for model [App\\Models\\Spmb\\PendaftaranCalonMhs] 99." }
```

---

## [POST] /api/spmb/pendaftaran/{id}/tetapkan-kelulusan

### Request Body

```json
{
    "status": "enum: lulus | tidak_lulus | cadangan",
    "program_studi_diterima_id": "integer, nullable, exists:siakad_program_studi,id (wajib bila status=lulus)",
    "nilai_total": "numeric, nullable, min:0",
    "peringkat": "integer, nullable, min:1",
    "catatan": "string, nullable, max 1000"
}
```

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Hasil seleksi berhasil ditetapkan.",
    "data": {
        "id": 1,
        "pendaftaran_id": 10,
        "program_studi_diterima_id": 7,
        "nilai_total": "88.00",
        "status": "lulus",
        "status_daftar_ulang": "belum",
        "program_studi_diterima": { "id": 7, "nama": "Sistem Informasi" }
    }
}
```

### Response Error

**401 Unauthorized**
```json
{ "status": "error", "message": "Unauthenticated." }
```

**403 Forbidden** (tanpa permission `spmb.seleksi.update`)
```json
{ "status": "error", "message": "This action is unauthorized." }
```

**404 Not Found** (pendaftaran tidak ditemukan)
```json
{ "status": "error", "message": "No query results for model [App\\Models\\Spmb\\PendaftaranCalonMhs] 99." }
```

**422 Unprocessable Entity** (mis. status `lulus` tanpa prodi diterima, atau kuota prodi penuh)
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "program_studi_diterima_id": ["Program studi diterima wajib dipilih untuk status lulus."],
        "status": ["Kuota untuk Program Studi ini sudah penuh (100)."]
    }
}
```

---

### Catatan Tambahan

> - Penetapan kelulusan dicatat pada **audit log** (`module: SPMB`, `action: update`).
> - Bila `status=lulus` dan kuota program studi tersedia, `kuota_terisi` otomatis bertambah.
> - Setelah `lulus`, calon dapat memanggil `POST /api/spmb/daftar-ulang/{id}/generate-tagihan` untuk menerbitkan tagihan daftar ulang.
