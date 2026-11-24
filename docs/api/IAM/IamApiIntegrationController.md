# IamApiIntegrationController

> **Modul**: IAM & Auth Center  
> **Base URL**: `/api/v1` (atau `/api`)  
> **Autentikasi**: Bearer Token (Sanctum / Passport)  
> **Dibuat**: 2026-10-12  
> **Diperbarui**: 2026-11-21

Controller ini mengelola registrasi integrasi API eksternal tingkat sistem yang dikunci oleh developer dan memberikan akses bagi Admin IAM untuk mengonfigurasi URL endpoint, kredensial token, menguji koneksi, serta menjalankan sinkronisasi data yang didukung.

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/api-integrations` | Daftar integrasi API sistem dengan filter & paginasi | ✅ Permission `iam.api_integration.read` |
| GET | `/api/api-integrations/{id}` | Detail konfigurasi & kontrak schema integrasi | ✅ Permission `iam.api_integration.read` |
| PUT | `/api/api-integrations/{id}` | Update tipe sumber, URL API, Token, Auth Type, dan Status | ✅ Permission `iam.api_integration.manage` |
| POST | `/api/api-integrations/{id}/test` | Uji koneksi sumber Custom API atau Neo Feeder PDDikti | ✅ Permission `iam.api_integration.manage` |
| POST | `/api/api-integrations/{id}/sync` | Ambil data sumber dan sinkronkan ke master yang didukung (SIAKAD/SIKEU) | ✅ Permission `iam.api_integration.manage` |
| GET | `/api/admin/api-integrations/student-sync-progress/{progressId}` | Ambil progres sinkronisasi mahasiswa/dosen/kelas PDDIKTI milik peminta | ✅ Permission `iam.api_integration.manage` |

---

## GET /api/api-integrations

Mengambil daftar seluruh integrasi API sistem yang terdaftar di database.

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `search` | string | ❌ | — | Pencarian nama, key, module, atau deskripsi |
| `module` | string | ❌ | — | Filter kode modul (misal `spmb`, `siakad`) |
| `is_active` | boolean | ❌ | — | Filter status aktif (`true` / `false`) |
| `sort_by` | string | ❌ | `created_at` | Kolom sortir (`name`, `key`, `module`, `http_method`, `is_active`, `last_synced_at`, `created_at`) |
| `sort_order` | string | ❌ | `desc` | Arah urutan (`asc` / `desc`) |
| `per_page` | integer | ❌ | `15` | Jumlah data per halaman (maks. 100) |
| `page` | integer | ❌ | `1` | Nomor halaman |

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Daftar integrasi API berhasil diambil",
    "data": [
        {
            "id": 1,
            "key": "siakad_program_studi",
            "name": "Migrasi Program Studi SIAKAD",
            "module": "siakad",
            "http_method": "GET",
            "description": "Sumber data read-only untuk migrasi program studi ke master SIAKAD",
            "expected_schema": {
                "type": "array",
                "items": {
                    "type": "object",
                    "properties": {
                        "source_id": { "type": ["string", "integer"] },
                        "kode_prodi": { "type": "string" },
                        "kode_prodi_dikti": { "type": ["string", "null"] },
                        "nama": { "type": "string" },
                        "jenjang": { "type": "string" },
                        "akreditasi": { "type": ["string", "null"] },
                        "is_active": { "type": "boolean" },
                        "fakultas": { "type": ["string", "null"] }
                    },
                    "required": ["source_id", "kode_prodi", "nama", "jenjang", "is_active"]
                }
            },
            "url_api": "https://sumber.kampus.ac.id/api/program-studi",
            "token_configured": false,
            "auth_type": "bearer",
            "is_active": true,
            "last_synced_at": null,
            "last_status": null,
            "created_at": "2026-11-12T09:00:00.000000Z",
            "updated_at": "2026-11-12T09:00:00.000000Z"
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
            "module": null,
            "is_active": null,
            "sort_by": "created_at",
            "sort_order": "desc"
        }
    }
}
```

---

## GET /api/api-integrations/{id}

Mengambil detail satu integrasi API termasuk kontrak schema format JSON yang diharapkan.

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Detail integrasi API berhasil diambil",
    "data": {
        "id": 1,
        "key": "siakad_program_studi",
        "name": "Migrasi Program Studi SIAKAD",
        "module": "siakad",
        "http_method": "GET",
        "description": "Sumber data read-only untuk migrasi program studi ke master SIAKAD",
        "expected_schema": { ... },
        "url_api": "https://sumber.kampus.ac.id/api/program-studi",
        "token_configured": false,
        "auth_type": "bearer",
        "is_active": true,
        "last_synced_at": null,
        "last_status": null
    }
}
```

---

## PUT /api/api-integrations/{id}

Mengubah URL endpoint, kredensial auth token, tipe autentikasi, serta status aktif/nonaktif. Respons hanya mengembalikan `token_configured`, bukan nilai token.

### Request Body

```json
{
    "source_type": "custom_api",
    "url_api": "https://sumber.kampus.ac.id/api/program-studi",
    "token": "my-secret-token",
    "auth_type": "bearer",
    "is_active": true
}
```

Untuk integrasi `siakad_mata_kuliah`, `source_type` dapat bernilai `pddikti` atau `custom_api`. Pada mode PDDIKTI, URL/token custom tidak diperlukan; backend memakai kredensial di Pengaturan Sistem → Koneksi Neo Feeder PDDikti dan menghapus URL/token custom saat mode tersebut disimpan.

Jika hanya mengubah pengaturan lain, hilangkan field `token` atau kirim `null`/string kosong. Token tersimpan akan dipertahankan. Kirim token non-kosong hanya untuk menggantinya.

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Konfigurasi integrasi API berhasil diperbarui",
    "data": {
        "id": 1,
        "key": "siakad_program_studi",
        "name": "Migrasi Program Studi SIAKAD",
        "url_api": "https://sumber.kampus.ac.id/api/program-studi",
        "token_configured": true,
        "auth_type": "bearer",
        "is_active": true
    }
}
```

Nilai `token_configured` hanya memberi tahu apakah kredensial sudah tersimpan. Nilai token asli tidak pernah disertakan dalam respons API.

---

## POST /api/api-integrations/{id}/test

Melakukan *live testing* ke sumber terpilih. Custom API menguji URL/token konfigurasi seperti biasa. Untuk `siakad_mata_kuliah` dengan `source_type: pddikti`, backend mengambil token dari Neo Feeder dan memanggil `GetMatkulKurikulum` dengan batas satu record; token tidak dikembalikan.

### Request Body (Opsional untuk pengujian ad-hoc)

```json
{
    "source_type": "custom_api",
    "url_api": "https://sumber.kampus.ac.id/api/program-studi",
    "token": "optional-override-token",
    "auth_type": "bearer"
}
```

Tes PDDIKTI dapat dikirim dengan body `{"source_type":"pddikti"}` atau menggunakan mode tersimpan. Jangan mengirim kredensial Neo Feeder melalui endpoint ini.

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Tes koneksi berhasil terhubung (200 OK dalam 124.5ms)",
    "data": {
        "http_status": 200,
        "is_success": true,
        "duration_ms": 124.5,
        "headers": {
            "content-type": ["application/json; charset=utf-8"]
        },
        "response_sample": [
            {
                "source_id": 101,
                "kode_prodi": "TI-D3",
                "kode_prodi_dikti": null,
                "nama": "Teknik Informatika",
                "jenjang": "D3",
                "akreditasi": null,
                "is_active": true,
                "fakultas": null
            }
        ],
        "expected_schema": { ... }
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
    "message": "This action is unauthorized."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "No query results for model [App\\Models\\IamApiIntegration] 999"
}
```

**422 Unprocessable Content**
```json
{
    "status": "error",
    "message": "URL API belum ditentukan untuk pengujian koneksi.",
    "errors": {
        "url_api": [
            "Format URL API tidak valid."
        ]
    }
}
```

**502 Bad Gateway / Connection Timeout**
```json
{
    "status": "error",
    "message": "Tes koneksi gagal terhubung ke server tujuan. Pastikan URL dapat dijangkau."
}
```

---

## POST /api/api-integrations/{id}/sync

Mengambil seluruh halaman data dari konfigurasi endpoint yang tersimpan, memvalidasi kontrak dan referensi master, lalu menerapkan data ke target yang didukung. Modul dan key yang didukung saat ini:
- `siakad_program_studi` (Modul `siakad`): sumber dapat dipilih sebagai PDDIKTI Neo Feeder (`GetProdi`) atau Custom API. Mode PDDIKTI menyimpan UUID `id_prodi` ke `siakad_program_studi.id_feeder`, sekaligus `source_id`; mode Custom API memakai validasi referensi jenjang/akreditasi yang sudah ada.
- `siakad_kurikulum` (Modul `siakad`): mode PDDIKTI menarik `GetListKurikulum`, memetakan `id_prodi` ke UUID Feeder program studi, lalu menyimpan UUID kurikulum di `siakad_kurikulum.id_feeder`.
- `siakad_mata_kuliah` (Modul `siakad`): sumber dipilih melalui `source_type`. Mode `pddikti` menarik relasi mata kuliah-kurikulum via Neo Feeder `GetMatkulKurikulum`; mode `custom_api` memakai endpoint dan kredensial manual.
- `siakad_mahasiswa` (Modul `siakad`): sumber dapat dipilih sebagai PDDIKTI Neo Feeder atau Custom API SIAKAD legacy. Mode PDDIKTI memakai action Count untuk total/progres, lalu mengambil `GetListMahasiswa` dan `GetBiodataMahasiswa` dalam batch 500; mode Custom API mempertahankan service legacy.
- `siakad_dosen` (Modul `siakad`): mode PDDIKTI menghitung total melalui `GetCountDosen`, kemudian job queue mengambil `GetListDosen` dengan limit 500 dan menyimpan setiap halaman sebelum membaca halaman berikutnya. Sync aktual membalas `202 Accepted` dengan `progress_id`; polling memakai endpoint progress yang sama dengan mahasiswa.
- `siakad_kelas` (Modul `siakad`): mode PDDIKTI menarik `GetListKelasKuliah`, lalu assignment dosen aktual dari `GetDosenPengajarKelasKuliah`. Uji koneksi read-only meminta satu record kelas. Sync aktual masuk `SyncPddiktiKelasJob` pada queue `default`, membalas `202 Accepted`, dan dipoll melalui endpoint progress yang sama. Target yang ditulis adalah `siakad_master_kelas` dan metadata pengajar pada `siakad_distribusi_mengajar`; `siakad_jadwal_kelas` (tabel penjadwalan) tidak ditulis; tidak ada peserta/mahasiswa, KRS, nilai, atau pivot peserta yang diimpor. Koordinator OBE baru dibiarkan null.
- `sikeu_transaksi_kas` (Modul `sikeu`): disinkronkan ke tabel `sikeu_jurnal_umum` dan `sikeu_detail_jurnal_umum` berimbang (Double-Entry) dengan resolusi COA dinamis via `sikeu_mig_akun_mapping`.
- `sikeu_transaksi_pembayaran` (Modul `sikeu`): disinkronkan ke tabel `sikeu_tagihan_mahasiswa` dan `sikeu_pembayaran` secara idempoten dengan verifikasi NIM mahasiswa di `siakad_mahasiswa`.

### Request Headers

| Header | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `Authorization` | string | Ya | Bearer token autentikasi |
| `Accept` | string | Ya | `application/json` |

### Request Body

Umumnya tidak memerlukan body. URL, token, tipe autentikasi, dan status aktif menggunakan konfigurasi integrasi yang tersimpan. Khusus integrasi `siakad_mahasiswa` sumber PDDIKTI, body opsional `{"preview": true}` meminta pratinjau read-only. Tanpa `preview: true`, endpoint menjadwalkan sync tulis pada queue `default` dan merespons `202 Accepted` dengan `data.progress_id`. Pastikan worker memproses queue `default`.

Untuk `siakad_dosen`, sync aktual dijalankan oleh queue worker dan mengembalikan `202 Accepted` beserta `data.progress_id`. `preview: true` ditolak; gunakan Uji Koneksi untuk pemeriksaan read-only. Proses worker juga memperbarui/membuat data pegawai SIMPEG, menyelaraskan riwayat pendidikan, serta dapat membuat atau menautkan akun SSO untuk dosen aktif. Tinjau cakupan tersebut sebelum menjalankan sync di produksi. Bila `GetCountDosen` tidak tersedia, progres menunjukkan record yang sudah diproses dengan total/persentase indeterminate.

Untuk `siakad_kelas`, sync aktual dijalankan oleh worker queue `default` dan mengembalikan `202 Accepted` dengan `progress_id`. Tidak ada `preview`; gunakan Uji Koneksi sebagai pemeriksaan read-only. `GetCountKelasKuliahWs` tercantum di User Guide Neo Feeder 2.0 tetapi request live ke Feeder kampus timeout, sehingga worker tidak memanggil action count. Worker membaca `GetListKelasKuliah` sampai batch terakhir kurang dari 500; progress menampilkan jumlah terbaca tanpa total/persentase. Jalankan worker queue default agar status bergerak dari `queued` ke status terminal.

### Response Sukses (200 OK)

```json
{
    "status": "success",
    "message": "Sinkronisasi berhasil dijalankan.",
    "data": {
        "source_total": 2,
        "created": 1,
        "updated": 1,
        "skipped": 0,
        "skipped_items": []
    }
}
```

Untuk sync tulis mahasiswa PDDIKTI, `202 Accepted` berarti proses sudah masuk antrean, bukan selesai:

```json
{
    "status": "success",
    "message": "Sinkronisasi dijadwalkan. Progres diperbarui selama proses berjalan.",
    "data": { "progress_id": "2d04ac62-fdd8-4dd5-a07c-1e48d3c264a0" }
}
```

Sync aktual dosen yang berhasil dijadwalkan mengembalikan `202 Accepted`:

```json
{
    "status": "success",
    "message": "Sinkronisasi dosen dijadwalkan. Progres diperbarui selama proses berjalan.",
    "data": { "progress_id": "e4c35b4d-dfee-49f1-bdc7-537dfd4dc7e4" }
}
```

Sync aktual kelas yang berhasil dijadwalkan mengembalikan `202 Accepted`:

```json
{
    "status": "success",
    "message": "Sinkronisasi kelas PDDIKTI dijadwalkan. Progres diperbarui selama proses berjalan.",
    "data": { "progress_id": "f4c2aaf1-5976-4b6d-a602-638e8b8b49e8" }
}
```

Polling `GET /api/admin/api-integrations/student-sync-progress/{progressId}` menerima progress mahasiswa, dosen, maupun kelas. Status terminal `completed` memuat ringkasan kelas di `data.result` (`source_total`, `processed`, `created`, `updated`, `synced`, `skipped`, `failed`, `pages`, dan maksimal 100 `skipped_items`); `failed` memuat pesan aman pada `data.message`.

### GET /api/admin/api-integrations/student-sync-progress/{progressId}

Poll endpoint ini menggunakan `progress_id` dari respons 202. Hanya peminta yang memulai sinkronisasi atau superadmin yang dapat membaca progres. Status proses adalah `queued`, `running`, `completed`, atau `failed`; fase kerja mencakup `reading`, `biodata`, dan `saving`.

**Path Parameters**:

| Parameter | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `progressId` | UUID | Ya | ID progres yang dikembalikan saat sinkronisasi dijadwalkan. |

**Response Sukses (200 OK)**:

```json
{
    "status": "success",
    "message": "Progres sinkronisasi mahasiswa berhasil diambil.",
    "data": {
        "progress_id": "2d04ac62-fdd8-4dd5-a07c-1e48d3c264a0",
        "integration_id": 12,
        "status": "running",
        "phase": "saving",
        "progress": 73,
        "processed": 3100,
        "total": 8405,
        "work_processed": 19600,
        "work_total": 26800,
        "message": "Memproses 3100 dari 8405 record mahasiswa.",
        "started_at": "2026-11-19T10:00:00.000000Z",
        "result": {
            "read_count": 8405,
            "created": 120,
            "updated": 2860,
            "synced": 2980,
            "skipped": 120,
            "failed": 0,
            "skipped_items": []
        }
    }
}
```

**Response Tidak Ditemukan / ID Tidak Valid (404 Not Found)**:

```json
{
    "status": "error",
    "message": "Proses sinkronisasi tidak ditemukan atau sudah kedaluwarsa."
}
```

**Response Tanpa Akses (403 Forbidden)**:

```json
{
    "status": "error",
    "message": "Anda tidak memiliki akses ke progres sinkronisasi ini."
}
```

Selama fase `reading`, jumlah total belum diketahui dan field `total`/`work_total` bernilai `null`; UI menampilkan jumlah record yang sudah dibaca tanpa membuat estimasi palsu. Setelah paging selesai, backend menetapkan total kerja dan UI menghitung ETA dari throughput polling aktual. Cache progress tersedia selama dua jam. Jika queue worker tidak aktif, status tetap `queued`.

### Perilaku dan Batasan

- Respons sumber dapat berupa array JSON, `{ "data": [...] }`, atau respons paginator dengan `meta.last_page`; proses dibatasi sampai 100 halaman.
- Pada mode PDDIKTI, tarik prodi Neo Feeder terlebih dahulu sehingga `siakad_program_studi.id_feeder` cocok dengan `id_prodi`. Kurikulum dicocokkan memakai `siakad_kurikulum.id_feeder`; jika belum ada mapping, nama kurikulum harus cocok persis dan unik pada prodi tersebut. Kurikulum tidak dibuat otomatis.
- Program Studi PDDIKTI menggunakan `GetProdi` dengan `id_prodi`, `kode_program_studi`, `nama_program_studi`, `nama_jenjang_pendidikan`, dan `status`. Sinkronisasi mengambil batch 500 (maksimum 100 batch), memvalidasi jenjang terhadap master referensi aktif, dan menyimpan UUID sumber sebagai mapping Feeder. Fakultas tidak diturunkan atau ditebak dari data PDDIKTI.
- Urutan impor yang dianjurkan adalah prodi → kurikulum (`GetListKurikulum`) → mata kuliah (`GetMatkulKurikulum`). Sync kurikulum membuat kode lokal deterministik `PDDIKTI-{id_kurikulum}`, mengambil tahun dari `semester_mulai_berlaku` (fallback awal `id_semester`), serta menyimpan jumlah SKS lulus.
- Integrasi `siakad_tahun_akademik` menarik daftar semester melalui `GetSemester`. `id_semester` hanya disimpan ke `siakad_tahun_akademik.id_feeder` jika cocok persis dengan `siakad_tahun_akademik.kode`. Periode baru tidak dibuat otomatis dan nama, kalender, serta status lokal tidak ditimpa. Sync melaporkan jumlah sumber, mapping, dan item yang dilewati; maksimum 100 halaman × 500 baris.
- Integrasi `siakad_dosen` menguji `GetListDosen` secara read-only dengan limit 1. Registry mendefinisikan count action `GetCountDosen` yang sudah diverifikasi pada Feeder kampus; sync dijalankan melalui `SyncPddiktiDosenJob` pada queue `default`. Worker meminta halaman `GetListDosen` dengan `limit=500`; homebase dan riwayat pendidikan difilter hanya untuk ID dosen pada halaman tersebut. Setiap halaman disimpan dalam satu transaksi dengan savepoint per dosen untuk isolasi baris gagal, lalu log/progres diperbarui setelah commit dan sebelum offset berikutnya. Batas maksimum 100 halaman; overflow/error upstream menandai job gagal. Jika count action gagal/tidak didukung, worker tetap memproses halaman 500 dan progres tidak menampilkan total atau persentase palsu. Proses mempertahankan perilaku pencocokan dosen serta dapat menyelaraskan SIMPEG, riwayat pendidikan, dan akun SSO aktif; review log pada `siakad_feeder_sync_logs`.
- Integrasi `siakad_kelas` menguji `GetListKelasKuliah` secara read-only dengan limit 1. Karena `GetCountKelasKuliahWs` timeout pada koneksi live, sync hanya membaca `GetListKelasKuliah` dengan `limit=500`, `order=id_kelas_kuliah`, dan offset deterministik sampai batch terakhir kurang dari 500. Progress indeterminate diperbarui setelah setiap commit. Setiap batch disimpan sebelum request berikutnya; setiap row diisolasi dengan transaksi. Batas maksimum 100 batch (50.000 record); overflow/upstream error menandai job gagal.
- Setelah kelas selesai dibaca, worker mengambil `GetDosenPengajarKelasKuliah` dengan `limit=500`, offset deterministik dan batas 100 batch. `id_kelas_kuliah`, `id_dosen`, dan `id_registrasi_dosen` wajib cocok ke mapping kelas, dosen, dan penugasan Feeder lokal; `sks_substansi_total` menjadi SKS anggota; `kelas_ids` berisi Master Kelas yang diperoleh dari hasil pembacaan `GetListKelasKuliah` pada job yang sama. Assignment yang tidak terpetakan dilewati dengan alasan.
- Distribusi dibuat/diperbarui berdasarkan prodi, periode, mata kuliah, dan semester kurikulum. Detail anggota Feeder ditandai `source=pddikti`; anggota/SKS manual serta koordinator yang sudah ada dipertahankan. Koordinator tidak pernah dipilih otomatis dari urutan response Feeder; distribusi baru menyimpan koordinator null.
- Action count `GetCountDosenPengajarKelasKuliah` tercantum di manual tetapi belum diverifikasi dapat dipanggil pada koneksi live; karena itu progress assignment tidak menampilkan total atau persentase dan berhenti pada batch akhir kurang dari 500. Hasil mencakup counter distribution created/updated, assignment synced/skipped, dan maksimal 100 alasan skip.
- `id_prodi`, `id_semester`, dan `id_matkul` harus cocok unik dan aktif pada `id_feeder` milik `siakad_program_studi`, `siakad_tahun_akademik`, dan `siakad_mata_kuliah`. Mapping kosong, ambigu, soft-deleted, format label kelas tidak valid, dan konflik kunci lokal dilewati dengan alasan; maksimal 100 detail disimpan pada `skipped_items`.
- `id_kelas_kuliah` tidak disimpan ke `siakad_jadwal_kelas` (sebelumnya `siakad_kelas`); ia hanya ditahan di cache sementara (kedaluwarsa 2 jam) selama job berjalan agar fase dosen pengajar dapat memetakan kelas ke prodi, semester, mata kuliah, dan Master Kelas. Master Kelas dicari/dibuat berdasarkan prodi + label kelas + angkatan, lintas semester; `26A` menghasilkan angkatan 2026. Format label tidak dibatasi (maks. 20 karakter, spasi dirapikan, huruf besar); label tanpa awalan tahun dua digit + huruf (mis. `B`, `01`) menghasilkan angkatan `null` dan dicari dengan `tahun_angkatan IS NULL`, sehingga tetap satu Master Kelas per prodi + nama. `dosen_pa_id` Master Kelas baru selalu null.
- Sync tidak membuat atau mengubah `siakad_jadwal_kelas` karena tabel itu dipakai untuk penjadwalan. Counter `created`/`updated` pada fase kelas menghitung Master Kelas baru/yang sudah ada. Sync hanya menulis `siakad_master_kelas` (dan distribusi pada fase dosen); tidak mengisi `siakad_kelas_rombel`, pengampu, peserta, mahasiswa, KRS, atau nilai.
- `http_method` registry `GET` menjelaskan sumber integrasi; koneksi internal ke Neo Feeder tetap menggunakan POST dengan payload `act=GetListKurikulum`, token runtime, `limit`, dan `offset`.
- Kurikulum dengan UUID sumber yang sama diperbarui idempoten. Baris tanpa prodi tujuan yang memiliki ID Feeder atau tanpa tahun semester yang dapat dibaca dilewati; kurikulum tidak dihapus otomatis saat tidak lagi ada di respons sumber.
- PDDIKTI mengirim `id_matkul`, `id_kurikulum`, `id_prodi`, `kode_mata_kuliah`, `nama_mata_kuliah`, `semester`, `sks_mata_kuliah`, `sks_tatap_muka`, `sks_praktek`, dan `apakah_wajib`. ID mata kuliah disimpan pada `source_id`/`id_feeder`; `apakah_wajib = 1` dipetakan ke `wajib`, nilai lainnya ke `pilihan`.
- Pada mode Custom API, item wajib berisi `source_id`, `kurikulum_kode`, `kode_mk`, `nama`, `sks_teori`, `sks_praktik`, `total_sks`, `semester_anjuran`, `tipe`, dan `is_active`. `kategori` serta `jumlah_pertemuan` opsional. Nilai `tipe`: `wajib`, `pilihan`, `wajib_prodi`.
- Relasi prodi/kurikulum tak ditemukan atau ambigu, kode duplikat, dan konflik mapping dilewati dengan alasan di `skipped_items`. Sync idempoten, tidak menghapus data yang hilang dari sumber, dan menampilkan jumlah serta maksimal 100 detail skip.
- Data dicocokkan berdasarkan `source_id` yang disimpan pada `siakad_program_studi.source_id`; data lama tanpa mapping external memakai fallback `kode_prodi`. `source_kode_prodi` menyimpan kode terakhir dari sumber. Jika admin mengganti `kode_prodi` dari kode sumber tersebut, sync selanjutnya mempertahankan kode target manual sambil tetap memperbarui `source_kode_prodi` dan field sumber lainnya. Jika target masih mengikuti kode sumber, perubahan kode baru dari sumber tetap diterapkan. Status yang diedit manual juga dipertahankan saat sync.
- Jenjang dan akreditasi harus cocok dengan item aktif pada master referensi SIAKAD. Item tidak valid masuk ke `skipped_items` beserta alasan.
- `fakultas_id` baru tetap null jika fakultas tidak dikirim. Fakultas yang dikirim harus cocok dengan kode atau nama fakultas aktif; relasi fakultas existing tidak dihapus ketika sumber tidak mengirim fakultas.
- Program studi yang sudah soft-delete tidak dibuat ulang otomatis.
- Respons sukses memuat maksimal 100 rincian `skipped_items`; hitungan `skipped` tetap total keseluruhan.
- Sinkronisasi mahasiswa PDDIKTI memakai `GetCountMahasiswa` untuk total/progres, lalu `GetListMahasiswa` per batch 500 dan menyimpan setiap batch. Setelah daftar selesai, `GetCountBiodataMahasiswa` menghitung total biodata; `GetBiodataMahasiswa` diambil per batch 500.
- Setelah tiap batch 500 selesai, array batch dilepas dari memori. Cache token, lock, dan progres tidak dihapus agar koneksi efisien, sinkronisasi tetap eksklusif, dan progress bar tetap dapat dipolling.
- NIM sumber dipertahankan persis. Status `AKTIF` dipetakan ke `aktif` dan `Lulus` ke `lulus`; status lain seperti `Mutasi`, `Mengajukan pengunduran diri`, `Putus Studi`, dan `Dikeluarkan` menunggu persetujuan kamus status dan sementara dilewati.
- `id_periode` hanya diterima jika berbentuk `YYYY` diikuti digit semester; empat digit tahun menjadi `angkatan`. `id_registrasi_mahasiswa` disimpan pada `id_feeder_riwayat`, sedangkan UUID `id_mahasiswa` disimpan pada `id_feeder` dan `id_feeder_biodata`.
- `preview: true` mengembalikan jumlah kandidat create/update/skip tanpa menulis tabel mahasiswa dan tanpa meminta biodata. Preview tidak mengunci snapshot; sync berikutnya membaca ulang sumber dan menghitung ulang hasil.
- Sync tidak membuat atau mengubah akun `core_users`, role, password, atau email login. Field biodata sumber kosong tidak menimpa field lokal. Record tidak dihapus ketika absen dari respons sumber.

### Contoh Respons dengan Baris Dilewati

```json
{
    "status": "success",
    "message": "Sinkronisasi berhasil dijalankan.",
    "data": {
        "source_total": 1,
        "created": 0,
        "updated": 0,
        "skipped": 1,
        "skipped_items": [
            {
                "source_id": 12,
                "kode_prodi": "TI-D3",
                "row": 1,
                "reason": "Kode jenjang tidak tersedia pada master referensi SIAKAD."
            }
        ]
    }
}
```
### Response Error

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
    "message": "This action is unauthorized."
}
```

**404 Not Found**
```json
{
    "status": "error",
    "message": "Data integrasi API tidak ditemukan."
}
```

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "URL API belum dikonfigurasi."
}
```

**502 Bad Gateway**
```json
{
    "status": "error",
    "message": "Tidak dapat terhubung ke API sumber."
}
```
