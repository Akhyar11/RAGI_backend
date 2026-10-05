# ForumController (Forum Diskusi Kelas)

> **Modul**: LMS (standalone, pisah dari SIAKAD)  
> **Base URL**: `/api/v1/lms`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-10-05  
> **Diperbarui**: 2026-10-05

Forum diskusi per kelas (opsional dikaitkan ke satu pertemuan). Balasan **satu level**.
Nama penulis di-snapshot ke baris pesan agar riwayat tetap bermakna bila akun
dihapus. Semua endpoint wajib anggota kelas (KRS untuk mahasiswa, pengampu untuk
dosen, pengelola/super admin bebas).

## Headers

| Header | Nilai | Wajib |
|---|---|---|
| `Authorization` | `Bearer <access_token>` | ✅ |
| `Accept` | `application/json` | ✅ |
| `Content-Type` | `application/json` | ✅ untuk POST/PUT |

## Permission

Forum memakai permission **milik modul LMS sendiri** — bukan permission SIAKAD —
agar aksi baca dan aksi tulis tidak tercampur dalam satu izin.

| Permission | Diberikan ke | Dipakai untuk |
|---|---|---|
| `lms.forum.read` | mahasiswa, dosen, kaprodi, wakil_prodi, admin | GET topik & pesan |
| `lms.forum.create` | mahasiswa, dosen, kaprodi, wakil_prodi, admin | POST pesan/balasan, hapus pesan **milik sendiri** |
| `lms.forum.manage` | dosen, kaprodi, wakil_prodi, admin | Buat/hapus topik, moderasi (hapus) pesan **siapa pun** |

Otorisasi ditulis sebagai Policy, bukan pengecekan ad-hoc di controller:

| Ability | Policy |
|---|---|
| `viewAny` | `ForumTopikPolicy` / `ForumPostPolicy` → `lms.forum.read` |
| `create` (topik) | `ForumTopikPolicy` → `lms.forum.manage` |
| `create` (pesan) | `ForumPostPolicy` → `lms.forum.create` |
| `delete` (topik) | `ForumTopikPolicy` → `lms.forum.manage` |
| `delete` (pesan) | `ForumPostPolicy` → `lms.forum.manage` **atau** penulis pesan itu sendiri |

Pemeriksaan kepemilikan baris (`$post->user_id`) hanya ada di dalam Policy — satu
tempat, tidak tersebar di controller/service.

Route di `routes/lms.php` memakai guard middleware dengan slug yang sama
(`can:lms.forum.read`, `can:lms.forum.create`, `can:lms.forum.manage`) sehingga
permission tidak hanya dideklarasikan, tetapi juga menjadi syarat sebelum controller
dipanggil. `DELETE /forum-post/{postId}` sengaja tidak memakai `can:lms.forum.manage`
di route — pemilik pesan memakai `lms.forum.create`, dan pemilihan keduanya tetap
diserahkan ke `ForumPostPolicy::delete()`.

Selain permission, tiap endpoint juga memverifikasi **keanggotaan kelas** di
service: mahasiswa harus punya `siakad_krs_detail` pada kelas tersebut; dosen harus
tercatat pada `siakad_dosen_pengampu`; pengelola kelas dan super admin lolos tanpa
pengecekan. Pelanggaran keanggotaan menghasilkan **422**, bukan 403, karena user
memang punya permission tetapi bukan anggota kelas.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/lms/forum` | Daftar topik agregat seluruh kelas yang diakses (halaman `/lms/forum`) | ✅ `lms.forum.read` |
| GET | `/api/v1/lms/kelas/{kelasId}/forum` | Daftar topik satu kelas (auto-buat "Diskusi Umum" bila kosong) | ✅ `lms.forum.read` |
| POST | `/api/v1/lms/kelas/{kelasId}/forum` | Buat topik (pin opsional) | ✅ `lms.forum.manage` |
| DELETE | `/api/v1/lms/forum/{topikId}` | Hapus topik + seluruh pesannya | ✅ `lms.forum.manage` |
| GET | `/api/v1/lms/forum/{topikId}/post` | Daftar pesan + balasan (paginated) | ✅ `lms.forum.read` |
| POST | `/api/v1/lms/forum/{topikId}/post` | Kirim pesan / balasan (`parent_id`) | ✅ `lms.forum.create` |
| DELETE | `/api/v1/lms/forum-post/{postId}` | Hapus pesan (penulis sendiri atau pengelola kelas) | ✅ `lms.forum.create` (pemilik) / `lms.forum.manage` |

---

## A. Agregat Module-Level

## [GET] /api/v1/lms/forum

Daftar topik forum **agregat level modul** — menggabungkan seluruh kelas yang boleh diakses
user tanpa parameter `kelasId`, untuk halaman `/lms/forum` di sidebar.

> **Endpoint ini read-only.** Berbeda dengan `GET /kelas/{kelasId}/forum`, endpoint
> agregat **tidak** membuat topik "Diskusi Umum" otomatis. Endpoint `GET` tidak boleh
> menulis data, dan pembuatan otomatis tersebut membuat pemanggil berulang kali
> (*page refresh*) terus-menulis. Kelas yang belum punya topik tidak muncul di daftar.

### Query Parameters

| Parameter | Tipe | Required | Default | Keterangan |
|---|---|---|---|---|
| `page` | integer | ❌ | `1` | Nomor halaman |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (nilai dibatasi 1–100) |
| `search` | string | ❌ | `null` | Pencarian pada `judul` topik, nama/kode kelas, dan nama/kode mata kuliah |
| `sort_by` | string | ❌ | `id` | `id`, `judul`, `is_pinned`, `created_at` |
| `sort_order` | string | ❌ | `desc` | `asc` / `desc` |
| `tahun_akademik_id` | integer | ❌ | `null` | Filter periode (FK ke master tahun akademik) |
| `kelas_id` | integer | ❌ | `null` | Filter satu kelas tertentu |

### Scope Data per Role

| Peran | Kelas yang terlihat |
|---|---|
| `superadmin`, `admin`, `kaprodi`, `wakil_prodi` | Seluruh kelas |
| `dosen` | Kelas yang diampu (`siakad_dosen_pengampu`) |
| `mahasiswa` | Kelas yang diambil lewat KRS (`siakad_krs_detail`) |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar topik forum berhasil diambil.",
    "data": [
        {
            "id": 2,
            "kelas_id": 14,
            "pertemuan_id": null,
            "judul": "Tanya Jawab UAS",
            "dibuat_oleh": 41,
            "is_pinned": true,
            "created_at": "2026-10-05T07:30:00.000000Z",
            "updated_at": "2026-10-05T07:30:00.000000Z",
            "posts_count": 0,
            "kelas": {
                "id": 14,
                "mata_kuliah_id": 5,
                "tahun_akademik_id": 2,
                "program_studi_id": 1,
                "kode_kelas": "IF3A-BASDAT",
                "nama_kelas": "Basis Data Relasional Kelas A",
                "kapasitas": 30,
                "status": "berjalan",
                "is_gabungan": false,
                "mata_kuliah": {
                    "id": 5,
                    "kurikulum_id": 3,
                    "kode_mk": "IF202",
                    "nama": "Basis Data Relasional",
                    "total_sks": 4
                },
                "tahun_akademik": {
                    "id": 2,
                    "kode": "20261",
                    "nama": "2026/2027 Ganjil",
                    "semester": "ganjil",
                    "is_active": true
                }
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1,
        "from": 1,
        "to": 1,
        "filters": {
            "search": null,
            "sort_by": "id",
            "sort_order": "desc",
            "tahun_akademik_id": null,
            "kelas_id": null
        }
    }
}
```

Topik ber-`is_pinned` selalu didahulukan (urutan `is_pinned DESC` lalu `sort_by`),
sehingga topic terkunci tidak tenggelam di halaman akhir.

`meta.filters` mengembalikan nilai filter yang benar-benar dipakai server (setelah
normalisasi), sehingga frontend dapat menampilkan ulang state filter.

`kelas_id` TIDAK membuka akses ke kelas di luar hak user: nilainya tetap
di-intersect dengan daftar kelas yang boleh diakses (lihat *Scope Data per Role*),
sehingga mengarang `kelas_id` milik kelas lain hanya menghasilkan daftar kosong,
bukan data kelas tersebut.

### Response Error

**401 Unauthorized** — token tidak ada/expired.

```json
{ "message": "Unauthenticated." }
```

**403 Forbidden** — user tidak memegang `lms.forum.read`.

```json
{ "status": "error", "message": "This action is unauthorized." }
```

> Parameter `sort_by` di luar whitelist diabaikan dan jatuh ke default `id`
> (tidak menghasilkan error 400).

---

## B. Topik Forum per Kelas

## [GET] /api/v1/lms/kelas/{kelasId}/forum

Daftar topik satu kelas. Bila kelas **belum memiliki topik sama sekali**, endpoint ini
membuat satu topik otomatis bernama `Diskusi Umum Kelas {nama mata kuliah}` agar
mahasiswa selalu punya tempat berdiskusi — itulah perbedaan perilaku terhadap endpoint
agregat yang read-only.

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `kelasId` | integer | ✅ | ID kelas |

### Query Parameters

| Parameter | Tipe | Required | Default | Keterangan |
|---|---|---|---|---|
| `page` | integer | ❌ | `1` | Nomor halaman |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (nilai dibatasi 1–100) |
| `sort_by` | string | ❌ | `id` | `id`, `judul`, `is_pinned`, `created_at` |
| `sort_order` | string | ❌ | `asc` | `asc` / `desc` |

Kunci pertama pengurutan selalu `is_pinned DESC` supaya topik terkunci tidak
tenggelam, lalu kunci `sort_by`, lalu `id` sebagai penentu stabil bila nilainya
sama. Paginasi dijalankan di level query builder (satu COUNT + satu SELECT per
halaman), bukan mengambil seluruh baris topik lalu memotongnya di memori.

> `sort_by` di luar whitelist diabaikan dan jatuh ke default `id` (tidak error 400).

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar topik forum kelas berhasil diambil.",
    "data": [
        {
            "id": 1,
            "judul": "Diskusi Umum Kelas Basis Data Relasional",
            "pertemuan_id": null,
            "is_pinned": false,
            "total_post": 2,
            "post_terakhir": {
                "nama_penulis": "Dosen Pengampu",
                "isi": "Ya, open book.",
                "created_at": "2026-10-05T08:50:38+00:00"
            }
        },
        {
            "id": 2,
            "judul": "Tanya Jawab UAS",
            "pertemuan_id": null,
            "is_pinned": true,
            "total_post": 0,
            "post_terakhir": null
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 2,
        "last_page": 1,
        "from": 1,
        "to": 2,
        "filters": {
            "sort_by": "id",
            "sort_order": "asc"
        }
    }
}
```

`post_terakhir.isi` dipotong maksimal 120 karakter untuk preview daftar.
`total_post` menghitung pesan **dan** balasan. `meta.filters` mengembalikan nilai
sorting yang benar-benar dipakai query, sehingga frontend dapat menampilkan ulang
state pengurutan.

### Response Error

**403 Forbidden** — tidak memegang `lms.forum.read`.

```json
{ "status": "error", "message": "This action is unauthorized." }
```

**404 Not Found** — kelas tidak ada.

```json
{ "message": "No query results for model [App\\Models\\Siakad\\Kelas] 999" }
```

**422 Unprocessable Entity** — user bukan anggota kelas.

```json
{
    "status": "error",
    "message": "Anda tidak terdaftar pada kelas perkuliahan ini.",
    "errors": { "kelas": ["Anda tidak terdaftar pada kelas perkuliahan ini."] }
}
```

## [POST] /api/v1/lms/kelas/{kelasId}/forum

Buat topik baru. Hanya pengelola kelas (dosen pengampu/admin) yang boleh;
mahasiswa mendapat 403 walau memiliki `lms.forum.create`, karena membuat topik
menuntut `lms.forum.manage`.

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `kelasId` | integer | ✅ | ID kelas |

### Request Body

```json
{
    "judul": "Tanya Jawab UAS",
    "pertemuan_id": 101,
    "is_pinned": true
}
```

| Field | Tipe | Required | Validasi |
|---|---|---|---|
| `judul` | string | ✅ | `max:255` |
| `pertemuan_id` | integer | ❌ | `exists:siakad_pertemuan,id` **dibatasi pada `kelas_id` yang sama** |
| `is_pinned` | boolean | ❌ | — |

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Topik forum berhasil dibuat.",
    "data": {
        "id": 2,
        "kelas_id": 14,
        "pertemuan_id": 101,
        "judul": "Tanya Jawab UAS",
        "dibuat_oleh": 41,
        "is_pinned": true,
        "created_at": "2026-10-05T07:30:00.000000Z",
        "updated_at": "2026-10-05T07:30:00.000000Z"
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
{ "message": "No query results for model [App\\Models\\Siakad\\Kelas] 999" }
```

**422 Unprocessable Entity** — validasi payload gagal atau user bukan anggota kelas.

```json
{
    "status": "error",
    "message": "The judul field is required.",
    "errors": { "judul": ["The judul field is required."] }
}
```

Validasi `pertemuan_id` dibatasi pada kelas yang sedang dipakai, sehingga `pertemuan_id`
dari kelas lain menghasilkan:

```json
{
    "status": "error",
    "message": "The selected pertemuan id is invalid.",
    "errors": { "pertemuan_id": ["The selected pertemuan id is invalid."] }
}
```

## [DELETE] /api/v1/lms/forum/{topikId}

Hapus topik beserta seluruh pesan dan balasannya (cascade di database). Audit log
modul `LMS` tabel `lms_forum_topik` ditulis **setelah** penghapusan berhasil, dengan
`old_values` snapshot yang diambil sebelum delete.

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `topikId` | integer | ✅ | ID topik |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Topik forum berhasil dihapus.",
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
{ "message": "No query results for model [App\\Models\\Lms\\ForumTopik] 999999" }
```

---

## C. Pesan & Balasan

## [GET] /api/v1/lms/forum/{topikId}/post

Daftar pesan pada satu topik. Hanya pesan tingkat atas yang menjadi item paginasi;
balasan ikut menyertainya di field `balasan`.

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `topikId` | integer | ✅ | ID topik |

### Query Parameters

| Parameter | Tipe | Required | Default | Keterangan |
|---|---|---|---|---|
| `page` | integer | ❌ | `1` | Nomor halaman |
| `per_page` | integer | ❌ | `15` | Jumlah pesan tingkat atas per halaman (nilai dibatasi 1–100) |
| `sort_by` | string | ❌ | `id` | `id`, `created_at` |
| `sort_order` | string | ❌ | `asc` | `asc` / `desc` |

Balasan (field `balasan`) tidak dipaginasikan — seluruh balasan sebuah pesan
ikut diambil dalam satu query.

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar pesan forum berhasil diambil.",
    "data": [
        {
            "id": 1,
            "topik_id": 1,
            "parent_id": null,
            "user_id": 57,
            "nama_penulis": "Citra Ayu",
            "isi": "Apakah UTS open book?",
            "created_at": "2026-10-05T08:50:38.000000Z",
            "updated_at": "2026-10-05T08:50:38.000000Z",
            "balasan": [
                {
                    "id": 2,
                    "topik_id": 1,
                    "parent_id": 1,
                    "user_id": 41,
                    "nama_penulis": "Dosen Pengampu",
                    "isi": "Ya, open book.",
                    "created_at": "2026-10-05T08:52:10.000000Z",
                    "updated_at": "2026-10-05T08:52:10.000000Z"
                }
            ]
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1,
        "from": 1,
        "to": 1,
        "filters": {
            "sort_by": "id",
            "sort_order": "asc"
        }
    }
}
```

### Response Error

**403 Forbidden** — tidak memegang `lms.forum.read`.

```json
{ "status": "error", "message": "This action is unauthorized." }
```

**404 Not Found** — topik tidak ada.

```json
{ "message": "No query results for model [App\\Models\\Lms\\ForumTopik] 999999" }
```

**422 Unprocessable Entity** — user bukan anggota kelas pemilik topik.

```json
{
    "status": "error",
    "message": "Anda tidak terdaftar pada kelas perkuliahan ini.",
    "errors": { "kelas": ["Anda tidak terdaftar pada kelas perkuliahan ini."] }
}
```

## [POST] /api/v1/lms/forum/{topikId}/post

Kirim pesan baru atau membalas pesan yang ada. Balasan **maksimal satu level**:
`parent_id` harus menunjuk pesan tingkat atas pada topik yang sama.

Endpoint ini adalah mutasi data, jadi menuntut `lms.forum.create` — user yang
hanya bisa membaca (`lms.forum.read`) tetap mendapat 403.

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `topikId` | integer | ✅ | ID topik |

### Request Body

```json
{ "isi": "Apakah UTS open book?", "parent_id": null }
```

Balasan:

```json
{ "isi": "Ya, open book.", "parent_id": 1 }
```

| Field | Tipe | Required | Validasi |
|---|---|---|---|
| `isi` | string | ✅ | `max:5000` |
| `parent_id` | integer | ❌ | `exists:lms_forum_post,id` |

`nama_penulis` diisi otomatis oleh server dari data mahasiswa/dosen/user — tidak
diperoleh dari payload.

### Response Sukses (201 Created)

```json
{
    "status": "success",
    "message": "Pesan forum berhasil dikirim.",
    "data": {
        "id": 1,
        "topik_id": 1,
        "parent_id": null,
        "user_id": 57,
        "nama_penulis": "Citra Ayu",
        "isi": "Apakah UTS open book?",
        "created_at": "2026-10-05T08:50:38.000000Z",
        "updated_at": "2026-10-05T08:50:38.000000Z",
        "balasan": []
    }
}
```

### Response Error

**403 Forbidden**

```json
{ "status": "error", "message": "This action is unauthorized." }
```

**404 Not Found** — topik atau `parent_id` tidak ada.

```json
{ "message": "No query results for model [App\\Models\\Lms\\ForumTopik] 999999" }
```

**422 Unprocessable Entity** — salah satu kondisi berikut:

| `errors` | Penyebab |
|---|---|
| `isi` | Wajib diisi / melebihi 5000 karakter |
| `parent_id` | Balasan hanya satu level dan harus dalam topik yang sama |
| `kelas` | User bukan anggota kelas pemilik topik |

```json
{
    "status": "error",
    "message": "Balasan hanya satu level dan harus dalam topik yang sama.",
    "errors": { "parent_id": ["Balasan hanya satu level dan harus dalam topik yang sama."] }
}
```

## [DELETE] /api/v1/lms/forum-post/{postId}

Hapus satu pesan. Otorisasi tingkat resource ditangani `ForumPostPolicy::delete()`:
**penulis pesan itu sendiri** (dengan `lms.forum.create`) atau **pengelola kelas**
(dengan `lms.forum.manage`). Mahasiswa yang mencoba menghapus pesan orang lain
mendapat 403.

### Path Parameters

| Parameter | Tipe | Required | Deskripsi |
|---|---|---|---|
| `postId` | integer | ✅ | ID pesan (`lms_forum_post`) |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Pesan forum berhasil dihapus.",
    "data": null
}
```

Audit log modul `LMS` tabel `lms_forum_post` ditulis **setelah** penghapusan
berhasil, dengan `old_values` snapshot yang diambil sebelum delete — sehingga
aksi yang gagal tidak pernah tercatat sebagai `delete`. Menghapus pesan yang
memiliki balasan ikut menghapus balasannya (cascade).

### Response Error

**403 Forbidden** — bukan penulis pesan dan tidak memegang `lms.forum.manage`.

```json
{ "status": "error", "message": "This action is unauthorized." }
```

**404 Not Found**

```json
{ "message": "No query results for model [App\\Models\\Lms\\ForumPost] 999999" }
```

---

## Catatan Tambahan

> - `is_pinned` pada `GET /kelas/{kelasId}/forum` dapat bernilai `null` untuk topik
>   yang dibuat otomatis, karena kolom tersebut belum di-set saat baris dibuat —
>   perlakukan `null` sebagai `false` di frontend.
> - Hanya `POST /kelas/{kelasId}/forum`, `POST /forum/{topikId}/post`,
>   `DELETE /forum/{topikId}`, dan `DELETE /forum-post/{postId}` yang menulis audit
>   log. Pesan yang dihapus tercatat lengkap di `core_audit_logs` sehingga diskusi
>   bermasalah tetap dapat ditelusuri.
> - Permission forum (`lms.forum.*`) didefinisikan di `PermissionSeeder` dan
>   disinkronkan ke database oleh migrasi `2026_10_05_073000_add_lms_forum_permissions`
>   agar database lama tanpa seeding ulang tetap konsisten.
> - `GET /kelas/{kelasId}/forum` menulis (membuat topik otomatis), jadi tidak
>   aman dipanggil bersamaan dari banyak tab; endpoint agregat justru disediakan
>   untuk kebutuhan list-only seperti sidebar.
> - Update/rename topik dan pesan **belum** tersedia; forum pada fase ini
>   create-read-delete saja.