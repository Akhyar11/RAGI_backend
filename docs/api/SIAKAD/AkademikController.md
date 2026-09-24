# AkademikController (SIAKAD)

> **Modul**: SIAKAD — Master Data Akademik
> **Base URL**: `/api/v1/siakad/akademik`
> **Autentikasi**: Bearer Token (Sanctum)
> **Dibuat**: 2026-09-25
> **Diperbarui**: 2026-09-25

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| GET | `/api/v1/siakad/akademik/referensi-options?tipe={tipe}` | Opsi dropdown master akademik dari database | ✅ |

---

## GET /api/v1/siakad/akademik/referensi-options

> Sumber tunggal opsi dropdown form master SIAKAD (jenjang prodi, akreditasi, tipe MK, kriteria prasyarat, mode penilaian). frontend WAJIB mengambil dari sini, bukan literal kode.

### Headers

| Key | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | ✅ |
| `Accept` | `application/json` | ✅ |

### Query Parameters

| Parameter | Type | Required | Default | Deskripsi |
|---|---|---|---|---|
| `tipe` | string | ✅ | — | Kode tipe referensi: `jenjang_prodi`, `akreditasi_prodi`, `tipe_mk`, `tipe_prasyarat_mk`, `mode_penilaian` |

### Response Sukses

**200 OK**
```json
{
    "status": "success",
    "message": "Opsi referensi akademik berhasil dimuat.",
    "data": [
        { "kode": "S1", "nama": "Sarjana (S1)", "urutan": 3 },
        { "kode": "S2", "nama": "Magister (S2)", "urutan": 4 }
    ]
}
```

### Response Error

**422 Unprocessable Entity**
```json
{
    "status": "error",
    "message": "Data yang diberikan tidak valid.",
    "errors": {
        "tipe": ["Tipe wajib diisi."]
    }
}
```

### Catatan Tambahan

> - Hanya item `is_active = true` modul `siakad`/`global`, terurut `urutan`, `nama`.
> - Jika tipe belum punya item, `data` berupa array kosong — frontend tampilkan empty state.
> - Kode item diselaraskan enum validasi backend (PDDIKTI / Neo Feeder): `wajib,pilihan,wajib_prodi`, `lulus,pernah_ambil`, `full_obe,semi_obe,konvensional`.

---

## Validasi Master Referensi (Form Request)

Endpoint store/update yang memakai opsi di atas **tidak lagi** memakai `in:STATIS`.
Validasi via Form Request + rule `App\Rules\MasterReferensiExists`
(cek `kode` di `spmb_master_referensi` untuk pasangan modul + tipe yang aktif):

| Form Request | Endpoint | Field → tipe referensi |
|---|---|---|
| `StoreMataKuliahRequest` | `POST /matakuliah` | `tipe` → `tipe_mk` |
| `UpdateMataKuliahRequest` | `PUT /matakuliah/{id}` | `tipe` → `tipe_mk` |
| `StorePrasyaratMkRequest` | `POST /prasyarat-mk` | `tipe` → `tipe_prasyarat_mk` |
| `UpdateModePenilaianRequest` | `PATCH /tahun-akademik/{id}/mode-penilaian` | `mode_penilaian` → `mode_penilaian` |
| `StoreProgramStudiRequest` | `POST /prodi` | `jenjang` → `jenjang_prodi`, `akreditasi` → `akreditasi_prodi` |
| `UpdateProgramStudiRequest` | `PUT /prodi/{id}` | `jenjang` → `jenjang_prodi`, `akreditasi` → `akreditasi_prodi` |
| `StoreCplRequest` | `POST /cpl` (Obe) | `kategori` → `kategori_cpl` |
| `StoreKelasKomponenRequest` | `POST /kelas/{kelasId}/komponen` (Obe) | `teknik_penilaian` → `teknik_penilaian` (wajib kecuali mode `full_obe`) |
| `StoreKelasRequest` | `POST /perkuliahan/kelas` | `hari` → `hari_kuliah` |
| `UpdateKelasRequest` | `PUT /perkuliahan/kelas/{id}` | `hari` → `hari_kuliah` |
| `StoreAbsensiRequest` | `POST /perkuliahan/pertemuan/{id}/absensi` | `absensi.*.status` → `status_absensi` |
| `StoreKelulusanRequest` | `POST /kelulusan` | `predikat` → `predikat_kelulusan` |

> Catatan: kolom `spmb_master_program_studi.akreditasi` dilebarkan 20 → 50
> agar menampung `Terakreditasi Sementara`.
> Status workflow (`draft/diajukan/disetujui/...`) dan status mahasiswa
> SENGAJA tetap `in:` — di-branching di logika bisnis ±15 titik
> (KrsService, PerkuliahanController, dsb.) sehingga bukan data referensi.

---

## Bukti End-to-End (`tests/Feature/SiakadMasterReferensiFlowTest.php`)

5 test, 37 assertions — opsi dibaca DARI database (bukan literal),
dikirim via API, `assertDatabaseHas` membuktikan baris tersimpan,
kode ngawur dibuktikan 422:

| Test | Alur |
|---|---|
| referensi options | `GET referensi-options?tipe=jenjang_prodi` tidak kosong |
| prodi | buat fakultas → buat prodi (jenjang+akreditasi dari master) → row ada; `STRATA-NGAWUR` → 422 |
| matakuliah | buat kurikulum → buat MK (tipe baris ke-3 master) → row ada + `total_sks` = 3; `wajib-ngawur` → 422 |
| mode penilaian | buat periode → PATCH mode dari master → row berubah; `mode-ngawur` → 422 |
| buka kelas | MK + periode → buat kelas (hari dari master) → `siakad_kelas.hari` tersimpan |
