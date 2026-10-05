# QuizController (Quiz + Tryout)

> **Modul**: LMS (standalone, pisah dari SIAKAD)  
> **Base URL**: `/api/v1/lms`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-10-05  
> **Diperbarui**: 2026-10-05

Quiz per pertemuan dan tryout level kelas dengan mesin pengerjaan yang sama:
batch-loading soal (kunci tidak pernah ke client), autosave bulk, auto-grade
pilihan ganda/isian, penilaian manual uraian, dan auto-sync nilai ke OBE
(`siakad_nilai_komponen_mhs`) bila quiz di-link ke komponen penilaian.

> Desain kunci: soal quiz/tryout WAJIB mereferensikan master `siakad_bank_soal`
> (live reference `bank_soal_id`, bukan copy) — inilah sinkronisasi bank soal
> SIAKAD ↔ LMS. Struktur quiz dikunci (422) setelah attempt pertama ada;
> bank soal yang dipakai quiz tidak bisa dihapus (422 + FK restrict).

## Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ untuk POST/PUT |

## Permission

| Permission | Dipakai untuk |
|---|---|
| `siakad.kelas.manage` | Semua endpoint pengelolaan: buat quiz/tryout, update, hapus, lampir/lepas soal, daftar attempt, tambah/hapus peserta tryout |
| `siakad.kelas.read` | Endpoint baca & pengerjaan mahasiswa: agregat tryout, daftar tryout kelas, detail quiz, start, batch soal, autosave, submit |
| `siakad.nilai.manage` | Penilaian manual jawaban uraian (`PUT /attempt-jawaban/{id}/nilai`) |

Super admin (sesuai `system_settings.superadmin_role`) melewati seluruh gate.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/lms/tryout` | Daftar tryout agregat seluruh kelas yang diakses (halaman `/lms/tryout`) | ✅ `siakad.kelas.read` |
| POST | `/api/v1/lms/kelas/{kelasId}/tryout` | Buat tryout level kelas | ✅ `siakad.kelas.manage` |
| GET | `/api/v1/lms/kelas/{kelasId}/tryout` | Daftar tryout satu kelas | ✅ `siakad.kelas.read` |
| POST | `/api/v1/lms/pertemuan/{pertemuanId}/quiz` | Buat quiz per pertemuan | ✅ `siakad.kelas.manage` |
| GET | `/api/v1/lms/quiz/{quizId}/manage` | Detail quiz dosen (soal + kunci + ringkasan attempt) | ✅ `siakad.kelas.manage` |
| PUT | `/api/v1/lms/quiz/{quizId}` | Update metadata (jendela, publish, arsip, link OBE) | ✅ `siakad.kelas.manage` |
| DELETE | `/api/v1/lms/quiz/{quizId}` | Hapus quiz + attempt | ✅ `siakad.kelas.manage` |
| POST | `/api/v1/lms/quiz/{quizId}/soal` | Lampirkan soal bank soal (`bank_soal_id`, `poin`) | ✅ `siakad.kelas.manage` |
| DELETE | `/api/v1/lms/quiz-soal/{quizSoalId}` | Lepas soal (ditolak bila attempt ada) | ✅ `siakad.kelas.manage` |
| GET | `/api/v1/lms/quiz/{quizId}/attempts` | Daftar attempt (paginated) | ✅ `siakad.kelas.manage` |
| PUT | `/api/v1/lms/attempt-jawaban/{id}/nilai` | Nilai manual + recompute + OBE resync | ✅ `siakad.nilai.manage` |
| POST | `/api/v1/lms/quiz/{quizId}/peserta` | Tambah peserta eksplisit tryout | ✅ `siakad.kelas.manage` |
| DELETE | `/api/v1/lms/tryout-peserta/{id}` | Hapus peserta eksplisit | ✅ `siakad.kelas.manage` |
| GET | `/api/v1/lms/quiz/{quizId}` | Detail quiz mahasiswa (tanpa kunci) + attempt miliknya | ✅ `siakad.kelas.read` |
| POST | `/api/v1/lms/quiz/{quizId}/start` | Mulai/lanjutkan attempt (`kode_akses` bila tryout berkode) | ✅ `siakad.kelas.read` |
| GET | `/api/v1/lms/quiz/{quizId}/soal?page=` | Batch soal tanpa kunci (`batch_size` per quiz / global `lms_quiz_batch_size`) | ✅ `siakad.kelas.read` |
| POST | `/api/v1/lms/attempt/{attemptId}/autosave` | Autosave bulk (`answers[]`); kedaluwarsa → auto-submit | ✅ `siakad.kelas.read` |
| POST | `/api/v1/lms/attempt/{attemptId}/submit` | Submit + auto-grade + OBE sync | ✅ `siakad.kelas.read` |

---

## A. Tryout — Agregat Module-Level

## [GET] /api/v1/lms/tryout

Daftar tryout **agregat level modul** — menggabungkan seluruh kelas yang boleh diakses
user tanpa parameter `kelasId`, untuk halaman `/lms/tryout` di sidebar.

Endpoint ini adalah padanan module-level dari `GET /kelas/{kelasId}/tryout`. Endpoint
per-kelas tetap dipakai saat konteksnya sudah berada di dalam satu kelas.

### Query Parameters

| Parameter | Tipe | Required | Default | Keterangan |
|---|---|---|---|---|
| `page` | integer | ❌ | `1` | Nomor halaman |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. `100`) |
| `search` | string | ❌ | `null` | Pencarian pada `judul`, `deskripsi`, nama/kode kelas, dan nama/kode mata kuliah |
| `sort_by` | string | ❌ | `id` | `id`, `judul`, `durasi_menit`, `dibuka_at`, `ditutup_at`, `created_at` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `tahun_akademik_id` | integer | ❌ | `null` | Filter periode (FK ke master tahun akademik) |
| `kelas_id` | integer | ❌ | `null` | Filter satu kelas tertentu |

### Scope Data per Role

| Peran | Kelas yang terlihat |
|---|---|
| `superadmin`, `admin`, `kaprodi`, `wakil_prodi` | Seluruh kelas |
| `dosen` | Kelas yang diampu (`siakad_dosen_pengampu`) |
| `mahasiswa` | Kelas yang diambil lewat KRS **atau** tryout dengan peserta eksplisit |

Mahasiswa hanya melihat tryout `is_published = true` dan `is_archived = false`.

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar tryout berhasil diambil.",
    "data": [
        {
            "id": 1,
            "pertemuan_id": null,
            "komponen_penilaian_id": 12,
            "judul": "Tryout Ujian Tengah Semester",
            "deskripsi": "Latihan Bab 1-3",
            "durasi_menit": 60,
            "max_attempt": 2,
            "acak_soal": true,
            "acak_jawaban": true,
            "batch_size": 10,
            "dibuka_at": "2026-10-06T08:00:00.000000Z",
            "ditutup_at": "2026-10-20T23:59:00.000000Z",
            "is_published": true,
            "dibuat_oleh": 41,
            "created_at": "2026-10-05T07:48:05.000000Z",
            "updated_at": "2026-10-05T07:48:05.000000Z",
            "deleted_at": null,
            "kelas_id": 14,
            "tipe": "tryout",
            "kode_akses": null,
            "is_archived": false,
            "soal_count": 25,
            "kelas": {
                "id": 14,
                "kode_kelas": "IF3A-BASDAT",
                "nama_kelas": "Basis Data Relasional Kelas A",
                "mata_kuliah": { "id": 5, "kode_mk": "IF202", "nama": "Basis Data Relasional" }
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 8,
        "last_page": 1,
        "from": 1,
        "to": 8
    },
    "filters": {
        "search": null,
        "sort_by": "id",
        "sort_order": "desc",
        "tahun_akademik_id": null,
        "kelas_id": null
    }
}
```

`filters` selalu mengembalikan nilai filter yang benar-benar dipakai server (setelah
normalisasi), sehingga frontend dapat menampilkan ulang state filter.

`kelas_id` digabung (AND) dengan *Scope Data per Role*, bukan menggantikannya:
mengirim `kelas_id` milik kelas yang tidak diakses user menghasilkan daftar kosong,
bukan `403` dan bukan data kelas tersebut.

### Response Error

**401 Unauthorized** — token tidak ada/expired.

```json
{ "message": "Unauthenticated." }
```

**403 Forbidden** — user tidak memegang `siakad.kelas.read`.

```json
{ "status": "error", "message": "This action is unauthorized." }
```

> Parameter `sort_by` di luar whitelist diabaikan dan jatuh ke default `id`
> (tidak menghasilkan error 400).

---

## B. Tryout Level Kelas

## [POST] /api/v1/lms/kelas/{kelasId}/tryout

Buat tryout lintas pertemuan pada satu kelas. `kode_akses` opsional (maks. 20 karakter);
tryout berkode hanya bisa dimulai bila mahasiswa mengirim kode yang cocok.

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `kelasId` | integer | ✅ | ID kelas |

### Request Body

```json
{
    "judul": "Tryout UTS",
    "deskripsi": "Latihan UTS",
    "durasi_menit": 60,
    "max_attempt": 2,
    "batch_size": 10,
    "komponen_penilaian_id": 12,
    "kode_akses": "TO123",
    "is_published": true
}
```

| Field | Tipe | Required | Validasi |
|---|---|---|---|
| `judul` | string | ✅ | `max:255` |
| `deskripsi` | string | ❌ | — |
| `durasi_menit` | integer | ❌ | `min:1`, `max:1440` |
| `max_attempt` | integer | ❌ | `min:1`, `max:10` |
| `acak_soal` | boolean | ❌ | — |
| `acak_jawaban` | boolean | ❌ | — |
| `batch_size` | integer | ❌ | `min:1`, `max:200` |
| `dibuka_at` | datetime | ❌ | `after_or_equal:ditutup_at` |
| `ditutup_at` | datetime | ❌ | `after_or_equal:dibuka_at` |
| `komponen_penilaian_id` | integer | ❌ | `exists:siakad_komponen_penilaian,id` |
| `kode_akses` | string | ❌ | `max:20` |
| `is_published` | boolean | ❌ | — |

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Tryout berhasil dibuat. Lampirkan soal dari bank soal untuk melengkapinya.",
    "data": {
        "id": 1,
        "pertemuan_id": null,
        "kelas_id": 14,
        "tipe": "tryout",
        "komponen_penilaian_id": 12,
        "judul": "Tryout UTS",
        "deskripsi": "Latihan UTS",
        "durasi_menit": 60,
        "max_attempt": 2,
        "acak_soal": true,
        "acak_jawaban": true,
        "batch_size": 10,
        "dibuka_at": null,
        "ditutup_at": null,
        "is_published": true,
        "kode_akses": "TO123",
        "is_archived": false,
        "dibuat_oleh": 41,
        "created_at": "2026-10-05T08:44:08.000000Z",
        "updated_at": "2026-10-05T08:44:08.000000Z"
    }
}
```

### Response Error

**403 Forbidden**

```json
{ "status": "error", "message": "This action is unauthorized." }
```

**404 Not Found** — kelas atau komponen penilaian tidak ada.

```json
{ "message": "No query results for model [App\\Models\\Siakad\\Kelas] 999." }
```

**422 Unprocessable Entity** — validasi payload gagal.

```json
{
    "status": "error",
    "message": "The judul field is required.",
    "errors": {
        "judul": ["The judul field is required."],
        "durasi_menit": ["The durasi menit field must not be greater than 1440."]
    }
}
```

## [GET] /api/v1/lms/kelas/{kelasId}/tryout

Daftar tryout satu kelas. Dosen/kaprodi melihat seluruh tryout; mahasiswa hanya melihat
tryout `is_published = true`, tidak diarsip, dan terdaftar (lewat KRS atau peserta eksplisit).

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `kelasId` | integer | ✅ | ID kelas |

### Query Parameters

| Parameter | Tipe | Required | Default | Keterangan |
|---|---|---|---|---|
| `page` | integer | ❌ | `1` | Nomor halaman |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. `100`) |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar tryout kelas berhasil diambil.",
    "data": [
        {
            "id": 1,
            "pertemuan_id": null,
            "komponen_penilaian_id": 12,
            "judul": "Tryout UTS",
            "deskripsi": "Latihan UTS",
            "durasi_menit": 90,
            "max_attempt": 2,
            "acak_soal": true,
            "acak_jawaban": true,
            "batch_size": 10,
            "dibuka_at": null,
            "ditutup_at": null,
            "is_published": true,
            "dibuat_oleh": 41,
            "created_at": "2026-10-05T08:44:08.000000Z",
            "updated_at": "2026-10-05T08:46:12.000000Z",
            "deleted_at": null,
            "kelas_id": 14,
            "tipe": "tryout",
            "kode_akses": "TO123",
            "is_archived": false,
            "soal_count": 25,
            "komponen_penilaian": {
                "id": 12,
                "kelas_id": 14,
                "cpmk_id": null,
                "sub_cpmk_id": null,
                "nama_komponen": "Tryout UTS",
                "teknik_penilaian": "tes_tulis",
                "bobot": "20.00",
                "urutan": 1,
                "is_aktif": true
            }
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

**403 Forbidden** — tidak memegang `siakad.kelas.read`.

```json
{ "status": "error", "message": "This action is unauthorized." }
```

---

## C. Quiz Per Pertemuan

## [POST] /api/v1/lms/pertemuan/{pertemuanId}/quiz

Buat kuis yang menempel pada satu pertemuan. Soal menyusul lewat
`POST /quiz/{quizId}/soal`. Nilai tetap sync ke OBE bila `komponen_penilaian_id` diisi.

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `pertemuanId` | integer | ✅ | ID pertemuan |

### Request Body

Sama seperti body pembuatan tryout; `kode_akses` diabaikan untuk tipe `kuis`.

```json
{
    "judul": "Kuis Pertemuan 1",
    "deskripsi": "Pilihan ganda Bab 1",
    "durasi_menit": 30,
    "max_attempt": 1,
    "is_published": false
}
```

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Quiz berhasil dibuat. Lampirkan soal dari bank soal untuk melengkapinya.",
    "data": {
        "id": 2,
        "pertemuan_id": 101,
        "kelas_id": null,
        "tipe": "kuis",
        "komponen_penilaian_id": null,
        "judul": "Kuis Pertemuan 1",
        "deskripsi": "Pilihan ganda Bab 1",
        "durasi_menit": 30,
        "max_attempt": 1,
        "acak_soal": true,
        "acak_jawaban": true,
        "batch_size": null,
        "dibuka_at": null,
        "ditutup_at": null,
        "is_published": false,
        "kode_akses": null,
        "is_archived": false,
        "dibuat_oleh": 41,
        "created_at": "2026-10-05T08:44:08.000000Z",
        "updated_at": "2026-10-05T08:44:08.000000Z"
    }
}
```

Pada kuis, `kelas_id` bernilai `null` dan kelas efektifnya diturunkan dari pertemuan.

### Response Error

**403 Forbidden**

```json
{ "status": "error", "message": "This action is unauthorized." }
```

**404 Not Found** — pertemuan tidak ada.

```json
{ "message": "No query results for model [App\\Models\\Siakad\\Pertemuan] 999." }
```

**422 Unprocessable Entity** — validasi payload gagal.

```json
{
    "status": "error",
    "message": "The judul field is required.",
    "errors": {
        "judul": ["The judul field is required."],
        "durasi_menit": ["The durasi menit field must not be greater than 1440."]
    }
}
```

---

## D. Pengelolaan Quiz (Dosen)

## [GET] /api/v1/lms/quiz/{quizId}/manage

Detail quiz untuk pengelola: metadata, relasi pertemuan/kelas/komponen penilaian,
**soal lengkap dengan kunci jawaban**, daftar peserta tryout, dan ringkasan attempt.

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `quizId` | integer | ✅ | ID quiz |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Detail quiz berhasil diambil.",
    "data": {
        "quiz": {
            "id": 1,
            "pertemuan_id": null,
            "komponen_penilaian_id": 12,
            "judul": "Tryout UTS",
            "deskripsi": "Latihan UTS",
            "durasi_menit": 60,
            "max_attempt": 1,
            "acak_soal": true,
            "acak_jawaban": true,
            "batch_size": 10,
            "dibuka_at": null,
            "ditutup_at": null,
            "is_published": true,
            "dibuat_oleh": 41,
            "created_at": "2026-10-05T08:44:08.000000Z",
            "updated_at": "2026-10-05T08:44:08.000000Z",
            "deleted_at": null,
            "kelas_id": 14,
            "tipe": "tryout",
            "kode_akses": "TO123",
            "is_archived": false,
            "pertemuan": null,
            "kelas": {
                "id": 14,
                "kode_kelas": "IF3A-BASDAT",
                "nama_kelas": "Basis Data Relasional Kelas A",
                "mata_kuliah": { "id": 5, "kode_mk": "IF202", "nama": "Basis Data Relasional" }
            },
            "komponen_penilaian": {
                "id": 12,
                "kelas_id": 14,
                "nama_komponen": "Tryout UTS",
                "teknik_penilaian": "tes_tulis",
                "bobot": "20.00",
                "urutan": 1,
                "is_aktif": true
            },
            "soal": [
                {
                    "id": 31,
                    "quiz_id": 1,
                    "bank_soal_id": 45,
                    "urutan": 1,
                    "poin": "50.00",
                    "bank_soal": {
                        "id": 45,
                        "rps_id": 8,
                        "sub_cpmk_id": 3,
                        "pertanyaan": "Apa kepanjangan dari OOP?",
                        "bobot": "10.00",
                        "kunci_jawaban": null,
                        "tipe_soal": "pilihan_ganda",
                        "tingkat_kesulitan": "sedang",
                        "gambar_path": null,
                        "pembahasan": "Object-Oriented Programming",
                        "opsi": [
                            { "id": 101, "bank_soal_id": 45, "teks": "Object-Oriented Programming", "is_benar": true, "urutan": 1, "gambar_path": null },
                            { "id": 102, "bank_soal_id": 45, "teks": "Online Order Platform", "is_benar": false, "urutan": 2, "gambar_path": null }
                        ],
                        "sub_cpmk": null
                    }
                }
            ],
            "tryout_peserta": []
        },
        "total_soal": 25,
        "total_poin": 100.0,
        "attempt_summary": [
            { "status": "selesai", "total": 18, "rata_nilai": 72.5 }
        ],
        "total_attempt": 18
    }
}
```

`attempt_summary` berisi agregasi per status (`berlangsung`, `selesai`) beserta
rata-rata nilai. Untuk dasbor dosen endpoint inilah sumber angka ringkasan —
tidak perlu memanggil endpoint lain.

### Response Error

**403 Forbidden**

```json
{ "status": "error", "message": "This action is unauthorized." }
```

**404 Not Found**

```json
{ "message": "No query results for model [App\\Models\\Lms\\Quiz] 999999" }
```

## [PUT] /api/v1/lms/quiz/{quizId}

Perbarui metadata quiz. Perubahan tercatat pada audit log `LMS` tabel `lms_quiz`
(`old_values` = nilai sebelum, `new_values` = field yang benar-benar berubah).

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `quizId` | integer | ✅ | ID quiz |

### Request Body

Semua field opsional (partial update). `judul` dan `max_attempt` wajib diisi bila dikirim.

```json
{
    "judul": "Tryout UTS (revisi)",
    "durasi_menit": 90,
    "max_attempt": 2,
    "is_published": true,
    "is_archived": false
}
```

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Quiz berhasil diperbarui.",
    "data": {
        "id": 1,
        "pertemuan_id": null,
        "komponen_penilaian_id": 12,
        "judul": "Tryout UTS (revisi)",
        "deskripsi": "Latihan UTS",
        "durasi_menit": 90,
        "max_attempt": 2,
        "acak_soal": true,
        "acak_jawaban": true,
        "batch_size": 10,
        "dibuka_at": null,
        "ditutup_at": null,
        "is_published": true,
        "dibuat_oleh": 41,
        "created_at": "2026-10-05T08:44:08.000000Z",
        "updated_at": "2026-10-05T08:46:12.000000Z",
        "deleted_at": null,
        "kelas_id": 14,
        "tipe": "tryout",
        "kode_akses": "TO123",
        "is_archived": false,
        "komponen_penilaian": {
            "id": 12,
            "kelas_id": 14,
            "nama_komponen": "Tryout UTS",
            "teknik_penilaian": "tes_tulis",
            "bobot": "20.00",
            "urutan": 1,
            "is_aktif": true
        }
    }
}
```

### Response Error

**403 Forbidden**

```json
{ "status": "error", "message": "This action is unauthorized." }
```

**404 Not Found**

```json
{ "message": "No query results for model [App\\Models\\Lms\\Quiz] 999999" }
```

**422 Unprocessable Entity** — validasi payload gagal.

```json
{
    "status": "error",
    "message": "The judul field is required.",
    "errors": { "judul": ["The judul field is required."] }
}
```

## [DELETE] /api/v1/lms/quiz/{quizId}

Hapus quiz (soft delete) beserta attempt-nya. Audit log `LMS` tabel `lms_quiz` dicatat
sebelum penghapusan.

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `quizId` | integer | ✅ | ID quiz |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Quiz berhasil dihapus.",
    "data": null
}
```

### Response Error

**403 Forbidden**

```json
{ "status": "error", "message": "This action is unauthorized." }
```

**404 Not Found**

```json
{ "message": "No query results for model [App\\Models\\Lms\\Quiz] 999999" }
```

## [POST] /api/v1/lms/quiz/{quizId}/soal

Lampirkan satu soal dari bank soal master (`siakad_bank_soal`) ke quiz.
Satu bank soal hanya boleh sekali per quiz.

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `quizId` | integer | ✅ | ID quiz |

### Request Body

```json
{ "bank_soal_id": 45, "urutan": 1, "poin": 10 }
```

| Field | Tipe | Required | Validasi |
|---|---|---|---|
| `bank_soal_id` | integer | ✅ | `exists:siakad_bank_soal,id` |
| `urutan` | integer | ❌ | `min:0`; default = urutan terakhir + 1 |
| `poin` | numeric | ❌ | `min:0`, `max:1000`; default `1` |

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Soal bank soal berhasil dilampirkan ke quiz.",
    "data": {
        "id": 31,
        "quiz_id": 1,
        "bank_soal_id": 45,
        "urutan": 1,
        "poin": "50.00",
        "created_at": "2026-10-05T08:44:08.000000Z",
        "updated_at": "2026-10-05T08:44:08.000000Z",
        "bank_soal": {
            "id": 45,
            "rps_id": 8,
            "rps_mingguan_id": null,
            "sub_cpmk_id": null,
            "pertanyaan": "Apa kepanjangan dari OOP?",
            "bobot": "10.00",
            "kunci_jawaban": null,
            "dibuat_oleh": 41,
            "kategori_id": null,
            "tipe_soal": "pilihan_ganda",
            "tingkat_kesulitan": "sedang",
            "gambar_path": null,
            "pembahasan": null
        }
    }
}
```

### Response Error

**403 Forbidden**

```json
{ "status": "error", "message": "This action is unauthorized." }
```

**404 Not Found** — quiz atau bank soal tidak ada.

```json
{ "message": "No query results for model [App\\Models\\Lms\\Quiz] 999999" }
```

**422 Unprocessable Entity** — bank soal sudah terpakai di quiz ini, atau struktur quiz
sudah terkunci karena ada attempt mahasiswa.

```json
{
    "status": "error",
    "message": "Soal ini sudah ada di dalam quiz.",
    "errors": { "bank_soal_id": ["Soal ini sudah ada di dalam quiz."] }
}
```

## [DELETE] /api/v1/lms/quiz-soal/{quizSoalId}

Lepas soal dari quiz. **Ditolak 422** bila sudah ada attempt mahasiswa, supaya struktur
soal tidak berubah di tengah pengerjaan.

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `quizSoalId` | integer | ✅ | ID baris `lms_quiz_soal` |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Soal berhasil dilepas dari quiz.",
    "data": null
}
```

### Response Error

**403 Forbidden**

```json
{ "status": "error", "message": "This action is unauthorized." }
```

**404 Not Found**

```json
{ "message": "No query results for model [App\\Models\\Lms\\QuizSoal] 999." }
```

**422 Unprocessable Entity** — struktur quiz sudah terkunci.

```json
{
    "status": "error",
    "message": "Struktur quiz sudah terkunci karena sudah ada attempt mahasiswa.",
    "errors": { "quiz": ["Struktur quiz sudah terkunci karena sudah ada attempt mahasiswa."] }
}
```

## [GET] /api/v1/lms/quiz/{quizId}/attempts

Daftar attempt (mahasiswa yang sudah mengerjakan) beserta jawaban dan nilainya.

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `quizId` | integer | ✅ | ID quiz |

### Query Parameters

| Parameter | Tipe | Required | Default | Keterangan |
|---|---|---|---|---|
| `page` | integer | ❌ | `1` | Nomor halaman |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. `100`) |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar attempt quiz berhasil diambil.",
    "data": [
        {
            "id": 7,
            "quiz_id": 1,
            "mahasiswa_id": 312,
            "attempt_ke": 1,
            "status": "selesai",
            "dimulai_at": "2026-10-05T08:44:08.000000Z",
            "disubmit_at": "2026-10-05T09:12:44.000000Z",
            "nilai_akhir": "50.00",
            "butuh_penilaian_manual": false,
            "dinilai_oleh": null,
            "dinilai_at": null,
            "created_at": "2026-10-05T08:44:08.000000Z",
            "updated_at": "2026-10-05T09:12:44.000000Z",
            "mahasiswa": {
                "id": 312,
                "nim": "2026010123",
                "nama_lengkap": "Budi Santoso",
                "program_studi_id": 1,
                "angkatan": 2026,
                "status": "aktif"
            },
            "jawaban": [
                {
                    "id": 88,
                    "attempt_id": 7,
                    "quiz_soal_id": 31,
                    "bank_opsi_id": 101,
                    "jawaban_teks": null,
                    "is_benar": true,
                    "poin_didapat": "50.00",
                    "created_at": "2026-10-05T08:50:00.000000Z",
                    "updated_at": "2026-10-05T09:12:44.000000Z"
                },
                {
                    "id": 89,
                    "attempt_id": 7,
                    "quiz_soal_id": 32,
                    "bank_opsi_id": 205,
                    "jawaban_teks": null,
                    "is_benar": false,
                    "poin_didapat": "0.00",
                    "created_at": "2026-10-05T08:51:12.000000Z",
                    "updated_at": "2026-10-05T09:12:44.000000Z"
                }
            ]
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 18,
        "last_page": 2,
        "from": 1,
        "to": 15
    }
}
```

`butuh_penilaian_manual: true` menandai attempt yang masih menunggu penilaian dosen
untuk soal uraian.

### Response Error

**403 Forbidden**

```json
{ "status": "error", "message": "This action is unauthorized." }
```

**404 Not Found**

```json
{ "message": "No query results for model [App\\Models\\Lms\\Quiz] 999999" }
```

## [PUT] /api/v1/lms/attempt-jawaban/{id}/nilai

Dosen menilai manual satu jawaban (soal uraian atau koreksi). Nilai akhir attempt
dihitung ulang (`recompute`) dan langsung disinkronkan ke OBE.

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID baris `lms_quiz_attempt_jawaban` |

### Request Body

```json
{ "poin": 40 }
```

`poin` harus numerik ≥ 0 dan tidak boleh melebihi poin maksimum soal tersebut.

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Nilai manual berhasil disimpan dan disinkronkan ke OBE.",
    "data": {
        "id": 88,
        "attempt_id": 7,
        "quiz_soal_id": 31,
        "bank_opsi_id": 101,
        "jawaban_teks": "enkapsulasi",
        "is_benar": false,
        "poin_didapat": "40.00",
        "created_at": "2026-10-05T08:50:00.000000Z",
        "updated_at": "2026-10-05T09:30:00.000000Z",
        "attempt": {
            "id": 7,
            "quiz_id": 1,
            "mahasiswa_id": 312,
            "attempt_ke": 1,
            "status": "selesai",
            "dimulai_at": "2026-10-05T08:44:08.000000Z",
            "disubmit_at": "2026-10-05T09:12:44.000000Z",
            "nilai_akhir": "40.00",
            "butuh_penilaian_manual": false,
            "dinilai_oleh": 41,
            "dinilai_at": "2026-10-05T09:30:00.000000Z"
        },
        "quiz_soal": {
            "id": 31,
            "quiz_id": 1,
            "bank_soal_id": 45,
            "urutan": 1,
            "poin": "50.00",
            "bank_soal": {
                "id": 45,
                "pertanyaan": "Jelaskan konsep enkapsulasi",
                "tipe_soal": "uraian",
                "bobot": "10.00"
            }
        }
    }
}
```

`nilai_akhir` pada `attempt` adalah hasil **setelah** recompute, sehingga frontend
tidak perlu memanggil ulang endpoint daftar attempt.

### Response Error

**403 Forbidden** — tidak memegang `siakad.nilai.manage`.

```json
{ "status": "error", "message": "This action is unauthorized." }
```

**404 Not Found**

```json
{ "message": "No query results for model [App\\Models\\Lms\\QuizAttemptJawaban] 999." }
```

**422 Unprocessable Entity** — `poin` di luar rentang 0..poin soal.

```json
{
    "status": "error",
    "message": "Poin harus berada di antara 0 dan 50.",
    "errors": { "poin": ["Poin harus berada di antara 0 dan 50."] }
}
```

## [POST] /api/v1/lms/quiz/{quizId}/peserta

Tambah peserta **eksplisit** pada tryout — mahasiswa di luar KRS kelas tetap bisa mengerjakan.
Idempoten: menambahkan mahasiswa yang sudah terdaftar tidak menggandakan baris.

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `quizId` | integer | ✅ | ID quiz (wajib bertipe `tryout`) |

### Request Body

```json
{ "mahasiswa_id": 402 }
```

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Peserta tryout berhasil ditambahkan.",
    "data": {
        "id": 9,
        "quiz_id": 1,
        "mahasiswa_id": 402,
        "ditambah_oleh": 41,
        "created_at": "2026-10-05T08:47:00.000000Z",
        "updated_at": "2026-10-05T08:47:00.000000Z",
        "mahasiswa": {
            "id": 402,
            "user_id": 512,
            "nim": "2026007002",
            "nama_lengkap": "Mahasiswa Luar",
            "program_studi_id": 1,
            "angkatan": 2026,
            "status": "aktif"
        }
    }
}
```

### Response Error

**403 Forbidden**

```json
{ "status": "error", "message": "This action is unauthorized." }
```

**404 Not Found**

```json
{ "message": "No query results for model [App\\Models\\Lms\\Quiz] 999999" }
```

**422 Unprocessable Entity** — endpoint ini hanya berlaku untuk tryout, atau
`mahasiswa_id` tidak ada.

```json
{
    "status": "error",
    "message": "Peserta eksplisit hanya berlaku untuk tryout.",
    "errors": { "quiz": ["Peserta eksplisit hanya berlaku untuk tryout."] }
}
```

## [DELETE] /api/v1/lms/tryout-peserta/{id}

Hapus peserta eksplisit. Mahasiswa lose akses ke tryout bila tidak lagi terdaftar lewat KRS.

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `id` | integer | ✅ | ID baris `lms_tryout_peserta` |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Peserta tryout berhasil dihapus.",
    "data": null
}
```

### Response Error

**403 Forbidden**

```json
{ "status": "error", "message": "This action is unauthorized." }
```

**404 Not Found**

```json
{ "message": "No query results for model [App\\Models\\Lms\\TryoutPeserta] 999." }
```

---

## E. Pengerjaan Quiz (Mahasiswa)

## [GET] /api/v1/lms/quiz/{quizId}

Detail quiz untuk mahasiswa: metadata, daftar soal (**tanpa kunci**), serta status
attempt milik user sendiri. Field `kode_akses` disembunyikan dari mahasiswa.

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `quizId` | integer | ✅ | ID quiz |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Detail quiz berhasil diambil.",
    "data": {
        "quiz": {
            "id": 1,
            "pertemuan_id": null,
            "komponen_penilaian_id": 12,
            "judul": "Tryout UTS (revisi)",
            "deskripsi": "Latihan UTS",
            "durasi_menit": 90,
            "max_attempt": 2,
            "acak_soal": true,
            "acak_jawaban": true,
            "batch_size": 10,
            "dibuka_at": null,
            "ditutup_at": null,
            "is_published": true,
            "dibuat_oleh": 41,
            "created_at": "2026-10-05T08:44:08.000000Z",
            "updated_at": "2026-10-05T08:46:12.000000Z",
            "deleted_at": null,
            "kelas_id": 14,
            "tipe": "tryout",
            "is_archived": false,
            "pertemuan": null,
            "kelas": { "id": 14, "kode_kelas": "IF3A-BASDAT", "nama_kelas": "Basis Data Relasional Kelas A" },
            "soal": [
                { "id": 31, "quiz_id": 1, "bank_soal_id": 45, "urutan": 1, "poin": "50.00" },
                { "id": 32, "quiz_id": 1, "bank_soal_id": 46, "urutan": 2, "poin": "50.00" }
            ]
        },
        "total_soal": 25,
        "total_poin": 100.0,
        "dalam_jendela": true,
        "sisa_attempt": 2,
        "my_attempts": [],
        "active_attempt": null
    }
}
```

`active_attempt` berisi attempt `berlangsung` milik user bila ada (gunakan `id`-nya untuk
melanjutkan). `dalam_jendela` menyatakan apakah saat ini berada dalam rentang
`dibuka_at`–`ditutup_at`.

### Response Error

**403 Forbidden**

```json
{ "status": "error", "message": "This action is unauthorized." }
```

**404 Not Found**

```json
{ "message": "No query results for model [App\\Models\\Lms\\Quiz] 999999" }
```

**422 Unprocessable Entity** — quiz belum dipublikasikan, sudah diarsipkan, atau mahasiswa
tidak terdaftar pada kelas/tryout tersebut.

```json
{
    "status": "error",
    "message": "Anda tidak terdaftar pada kelas perkuliahan ini.",
    "errors": { "quiz": ["Anda tidak terdaftar pada kelas perkuliahan ini."] }
}
```

## [POST] /api/v1/lms/quiz/{quizId}/start

Mulai attempt baru, atau melanjutkan attempt `berlangsung` yang sudah ada (idempoten).
Wajib bagi mahasiswa terdaftar; untuk tryout berkode kirimkan `kode_akses`.

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `quizId` | integer | ✅ | ID quiz |

### Request Body

```json
{ "kode_akses": "TO123" }
```

`kode_akses` opsional dan hanya relevan bila tryout memakai kode akses.

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Attempt quiz berhasil dimulai.",
    "data": {
        "id": 7,
        "quiz_id": 1,
        "mahasiswa_id": 312,
        "attempt_ke": 1,
        "status": "berlangsung",
        "dimulai_at": "2026-10-05T08:44:08.000000Z",
        "created_at": "2026-10-05T08:44:08.000000Z",
        "updated_at": "2026-10-05T08:44:08.000000Z"
    }
}
```

### Response Error

**403 Forbidden**

```json
{ "status": "error", "message": "This action is unauthorized." }
```

**404 Not Found**

```json
{ "message": "No query results for model [App\\Models\\Lms\\Quiz] 999999" }
```

**422 Unprocessable Entity** — salah satu kondisi berikut:

| `errors` | Penyebab |
|---|---|
| `quiz` | Quiz belum dipublikasikan dosen |
| `quiz` | Tryout sudah diarsipkan |
| `quiz` | Di luar jendela pengerjaan (`dibuka_at`–`ditutup_at`) |
| `kode_akses` | Kode akses tryout tidak cocok |
| `quiz` | Quiz belum memiliki soal |
| `quiz` | Batas percobaan (`max_attempt`) sudah habis |
| `quiz` | Mahasiswa tidak terdaftar pada kelas/tryout |

```json
{
    "status": "error",
    "message": "Kode akses tryout tidak cocok.",
    "errors": { "kode_akses": ["Kode akses tryout tidak cocok."] }
}
```

## [GET] /api/v1/lms/quiz/{quizId}/soal

Ambil soal per batch **tanpa kunci jawaban**. Wajib punya attempt `berlangsung` milik
sendiri; pengacakan soal & opsi bersifat deterministik per attempt sehingga stabil saat
halaman di-refresh.

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `quizId` | integer | ✅ | ID quiz |

### Query Parameters

| Parameter | Tipe | Required | Default | Keterangan |
|---|---|---|---|---|
| `page` | integer | ❌ | `1` | Nomor batch; bukan nomor soal |

Ukuran batch memakai `quiz.batch_size` bila diisi, jika tidak fallback ke system setting
`lms_quiz_batch_size`.

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Batch soal quiz berhasil diambil.",
    "data": {
        "attempt_id": 7,
        "page": 1,
        "batch_size": 10,
        "total_soal": 25,
        "total_pages": 3,
        "data": [
            {
                "quiz_soal_id": 32,
                "urutan": 2,
                "poin": 50,
                "tipe_soal": "pilihan_ganda",
                "pertanyaan": "Apa kepanjangan dari OOP?",
                "gambar_path": null,
                "opsi": [
                    { "id": 101, "teks": "Object-Oriented Programming", "gambar_path": null, "urutan": 1 },
                    { "id": 102, "teks": "Online Order Platform", "gambar_path": null, "urutan": 2 }
                ]
            }
        ]
    }
}
```

Field `is_benar`, `kunci_jawaban`, dan `pembahasan` **tidak pernah** disertakan pada
endpoint ini maupun `GET /quiz/{quizId}`.

### Response Error

**403 Forbidden**

```json
{ "status": "error", "message": "This action is unauthorized." }
```

**422 Unprocessable Entity** — belum ada attempt `berlangsung`, attempt sudah selesai,
atau attempt milik orang lain.

```json
{
    "status": "error",
    "message": "Attempt ini bukan milik Anda.",
    "errors": { "attempt": ["Attempt ini bukan milik Anda."] }
}
```

## [POST] /api/v1/lms/attempt/{attemptId}/autosave

Simpan jawaban secara bulk (dipanggil berkala saat mengerjakan). Opsi divalidasi
**batch** terhadap bank soal milik quiz ini sehingga ID opsi dari soal lain ditolak.
Bila durasi sudah habis, jawaban tetap tersimpan lalu attempt otomatis di-submit
(`auto_submitted: true`).

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `attemptId` | integer | ✅ | ID attempt |

### Request Body

```json
{
    "answers": [
        { "quiz_soal_id": 31, "bank_opsi_id": 101 },
        { "quiz_soal_id": 32, "jawaban_teks": "enkapsulasi" }
    ]
}
```

| Field | Tipe | Required | Validasi |
|---|---|---|---|
| `answers` | array | ✅ | `min:1` |
| `answers.*.quiz_soal_id` | integer | ✅ | `exists:lms_quiz_soal,id` |
| `answers.*.bank_opsi_id` | integer | ❌ | `exists:siakad_bank_soal_opsi,id` |
| `answers.*.jawaban_teks` | string | ❌ | `max:5000` |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Progres jawaban berhasil disimpan.",
    "data": { "auto_submitted": false, "attempt_id": 7, "saved": 2 }
}
```

Saat waktu habis, `message` berubah menjadi
`"Waktu habis — jawaban tersimpan otomatis dinilai."` dengan `auto_submitted: true`.

### Response Error

**403 Forbidden**

```json
{ "status": "error", "message": "This action is unauthorized." }
```

**422 Unprocessable Entity** — attempt sudah selesai, attempt milik orang lain, atau
soal/opsi tidak valid untuk quiz ini.

```json
{
    "status": "error",
    "message": "Attempt sudah selesai dan tidak dapat diubah.",
    "errors": { "attempt": ["Attempt sudah selesai dan tidak dapat diubah."] }
}
```

```json
{
    "status": "error",
    "message": "Beberapa opsi jawaban tidak valid untuk quiz ini.",
    "errors": { "answers": ["Beberapa opsi jawaban tidak valid untuk quiz ini."] }
}
```

## [POST] /api/v1/lms/attempt/{attemptId}/submit

Submit jawaban. Penilaian otomatis untuk pilihan ganda dan isian (kunci isian dicocokkan
persis, case-insensitive); soal uraian menunggu dosen dan ditandai
`butuh_penilaian_manual: true`. Nilai akhir skala 0–100 = (poin diperoleh / total poin) × 100.
Bila quiz punya `komponen_penilaian_id`, nilai otomatis tersinkron ke OBE
(`siakad_nilai_komponen_mhs`).

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `attemptId` | integer | ✅ | ID attempt (harus milik caller) |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Quiz berhasil disubmit dan dinilai.",
    "data": {
        "id": 7,
        "quiz_id": 1,
        "mahasiswa_id": 312,
        "attempt_ke": 1,
        "status": "selesai",
        "dimulai_at": "2026-10-05T08:44:08.000000Z",
        "disubmit_at": "2026-10-05T09:12:44.000000Z",
        "nilai_akhir": "50.00",
        "butuh_penilaian_manual": false,
        "dinilai_oleh": null,
        "dinilai_at": null,
        "created_at": "2026-10-05T08:44:08.000000Z",
        "updated_at": "2026-10-05T09:12:44.000000Z",
        "jawaban": [
            {
                "id": 88,
                "attempt_id": 7,
                "quiz_soal_id": 31,
                "bank_opsi_id": 101,
                "jawaban_teks": null,
                "is_benar": true,
                "poin_didapat": "50.00",
                "quiz_soal": {
                    "id": 31,
                    "bank_soal_id": 45,
                    "urutan": 1,
                    "poin": "50.00",
                    "bank_soal": {
                        "id": 45,
                        "pertanyaan": "Apa kepanjangan dari OOP?",
                        "tipe_soal": "pilihan_ganda",
                        "bobot": "10.00"
                    }
                }
            }
        ],
        "quiz": { "id": 1, "judul": "Tryout UTS (revisi)", "kelas_id": 14, "tipe": "tryout" }
    }
}
```

### Response Error

**403 Forbidden**

```json
{ "status": "error", "message": "This action is unauthorized." }
```

**404 Not Found**

```json
{ "message": "No query results for model [App\\Models\\Lms\\QuizAttempt] 999." }
```

**422 Unprocessable Entity** — attempt sudah selesai atau bukan milik caller.

```json
{
    "status": "error",
    "message": "Attempt sudah selesai dan tidak dapat diubah.",
    "errors": { "attempt": ["Attempt sudah selesai dan tidak dapat diubah."] }
}
```

---

## Catatan Tambahan

> - `start` idempoten: attempt `berlangsung` yang ada dikembalikan (lanjutkan).
> - `max_attempt` menghitung attempt `selesai`; mulai baru setelah batas → 422.
> - Tryout diarsip (`is_archived`) tidak bisa dimulai/dilihat mahasiswa.
> - Kode akses dibandingkan timing-safe (`hash_equals`) dan tidak pernah dikirim ke mahasiswa.
> - Semua aksi tulis tercatat pada audit log modul `LMS`: `lms_quiz` (create/update/delete),
>   `lms_quiz_soal` (create/delete), `lms_tryout_peserta` (create/delete),
>   `lms_quiz_attempt` (update saat submit), `lms_quiz_attempt_jawaban` (update saat nilai manual).
> - `soal_count` pada endpoint daftartryout dihitung dengan agregasi, bukan N+1 query.