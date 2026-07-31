# 🏛️ GLOBAL FINAL ERD — Ekosistem Kampus Terintegrasi
## Part 2 of 4: OBE | SIMPI | SIMANTA | SIMPRESKUL | SIKEU

---

## 📐 MODUL 4A: OBE (Outcome-Based Education)

### Deskripsi Arsitektur
Hierarki OBE: Profil Lulusan → CPL (Capaian Pembelajaran Lulusan) → CPMK → Sub-CPMK. Setiap Matakuliah dipetakan ke CPL tertentu. Assessment matrix menghubungkan Sub-CPMK dengan bobot penilaian. Nilai CPL mahasiswa dihitung agregat dari nilai mata kuliah yang mendukung CPL tersebut.

```mermaid
erDiagram
    profil_lulusan {
        bigint id PK
        bigint program_studi_id FK
        bigint kurikulum_id FK
        varchar kode UK
        varchar nama
        text deskripsi
        integer urutan
        timestamp created_at
        timestamp updated_at
    }

    cpl {
        bigint id PK
        bigint program_studi_id FK
        bigint kurikulum_id FK
        varchar kode UK
        varchar nama
        text deskripsi
        enum domain "sikap|pengetahuan|keterampilan_umum|keterampilan_khusus"
        integer urutan
        timestamp created_at
        timestamp updated_at
    }

    profil_lulusan_cpl {
        bigint id PK
        bigint profil_lulusan_id FK
        bigint cpl_id FK
        timestamp created_at
    }

    cpmk {
        bigint id PK
        bigint mata_kuliah_id FK
        bigint cpl_id FK
        varchar kode UK
        varchar nama
        text deskripsi
        integer urutan
        timestamp created_at
        timestamp updated_at
    }

    sub_cpmk {
        bigint id PK
        bigint cpmk_id FK
        varchar kode UK
        varchar nama
        text deskripsi
        integer urutan
        timestamp created_at
    }

    mk_cpl_mapping {
        bigint id PK
        bigint mata_kuliah_id FK
        bigint cpl_id FK
        decimal bobot_kontribusi
        timestamp created_at
    }

    rubrik_penilaian {
        bigint id PK
        bigint sub_cpmk_id FK
        bigint kelas_id FK
        varchar nama
        text deskripsi
        integer level_1_min
        integer level_1_max
        text level_1_deskripsi
        integer level_2_min
        integer level_2_max
        text level_2_deskripsi
        integer level_3_min
        integer level_3_max
        text level_3_deskripsi
        integer level_4_min
        integer level_4_max
        text level_4_deskripsi
        timestamp created_at
        timestamp updated_at
    }

    assessment_matrix {
        bigint id PK
        bigint kelas_id FK
        bigint sub_cpmk_id FK
        varchar nama_assessment "UTS|UAS|Tugas|Praktik|Kuis"
        decimal bobot_persen
        timestamp created_at
    }

    nilai_cpmk_mahasiswa {
        bigint id PK
        bigint krs_detail_id FK
        bigint cpmk_id FK
        decimal nilai
        timestamp created_at
        timestamp updated_at
    }

    nilai_cpl_mahasiswa {
        bigint id PK
        bigint mahasiswa_id FK
        bigint cpl_id FK
        bigint tahun_akademik_id FK
        decimal nilai_rata
        decimal nilai_kumulatif
        enum level_capaian "belum_tercapai|cukup|baik|sangat_baik"
        timestamp created_at
        timestamp updated_at
    }

    profil_lulusan ||--o{ profil_lulusan_cpl : "dipetakan ke"
    cpl ||--o{ profil_lulusan_cpl : "mendukung"
    cpl ||--o{ cpmk : "dijabarkan menjadi"
    cpmk ||--o{ sub_cpmk : "memiliki"
    cpmk ||--o{ nilai_cpmk_mahasiswa : "dinilai pada"
    sub_cpmk ||--o{ rubrik_penilaian : "dinilai dengan"
    sub_cpmk ||--o{ assessment_matrix : "diases melalui"
    cpl ||--o{ mk_cpl_mapping : "didukung oleh"
    cpl ||--o{ nilai_cpl_mahasiswa : "dicapai oleh"
```

---

## 🏭 MODUL 4B: SIMPI (Prestasi & Praktik Industri/Magang)

```mermaid
erDiagram
    mitra_industri_simpi {
        bigint id PK
        bigint mitra_kerjasama_id FK
        varchar nama
        varchar bidang_industri
        text alamat
        varchar kontak_person
        varchar telepon
        varchar email
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    pengajuan_magang {
        bigint id PK
        bigint mahasiswa_id FK
        bigint tahun_akademik_id FK
        bigint mitra_industri_id FK
        varchar judul_kegiatan
        text deskripsi
        date tanggal_mulai
        date tanggal_selesai
        enum tipe "kp|pkl|magang_mbkm|internasional"
        integer konversi_sks
        enum status "draft|diajukan|disetujui|ditolak|berjalan|selesai"
        bigint disetujui_oleh FK
        timestamp disetujui_at
        varchar file_proposal
        timestamp created_at
        timestamp updated_at
    }

    pembimbing_magang {
        bigint id PK
        bigint pengajuan_id FK
        bigint dosen_id FK
        enum peran "pembimbing_kampus|pembimbing_lapangan"
        varchar nama_pembimbing_lapangan
        varchar jabatan_lapangan
        timestamp created_at
    }

    logbook_magang {
        bigint id PK
        bigint pengajuan_id FK
        bigint mahasiswa_id FK
        date tanggal
        text aktivitas
        text hasil
        integer jam_kerja
        varchar file_dokumentasi
        enum status "draft|submitted|diverifikasi"
        bigint diverifikasi_oleh FK
        timestamp diverifikasi_at
        timestamp created_at
        timestamp updated_at
    }

    evaluasi_magang {
        bigint id PK
        bigint pengajuan_id FK
        enum penilai_tipe "dosen_pembimbing|supervisor_lapangan"
        bigint dosen_id FK
        decimal nilai_disiplin
        decimal nilai_kerjasama
        decimal nilai_inisiatif
        decimal nilai_kompetensi
        decimal nilai_laporan
        decimal nilai_total
        varchar nilai_huruf
        text catatan
        timestamp created_at
        timestamp updated_at
    }

    kategori_prestasi {
        bigint id PK
        varchar nama
        enum tingkat "lokal|regional|nasional|internasional"
        enum bidang "akademik|olahraga|seni|teknologi|wirausaha|lainnya"
        timestamp created_at
    }

    prestasi_mahasiswa {
        bigint id PK
        bigint mahasiswa_id FK
        bigint kategori_prestasi_id FK
        bigint tahun_akademik_id FK
        varchar nama_kegiatan
        varchar penyelenggara
        enum peringkat "juara1|juara2|juara3|harapan|peserta|lainnya"
        date tanggal_kegiatan
        varchar file_sertifikat
        boolean is_verified
        bigint diverifikasi_oleh FK
        text catatan
        timestamp created_at
        timestamp updated_at
    }

    insentif_prestasi {
        bigint id PK
        bigint prestasi_id FK
        decimal nominal_insentif
        enum status "pending|disetujui|dibayar"
        bigint disetujui_oleh FK
        timestamp dibayar_at
        timestamp created_at
    }

    mitra_industri_simpi ||--o{ pengajuan_magang : "menjadi tempat"
    pengajuan_magang ||--o{ pembimbing_magang : "dibimbing oleh"
    pengajuan_magang ||--o{ logbook_magang : "memiliki logbook"
    pengajuan_magang ||--o{ evaluasi_magang : "dievaluasi"
    kategori_prestasi ||--o{ prestasi_mahasiswa : "mengkategorikan"
    prestasi_mahasiswa ||--o| insentif_prestasi : "mendapatkan"
```

---

## 📜 MODUL 4C: SIMANTA (Tugas Akhir / Skripsi)

```mermaid
erDiagram
    topik_penelitian_ta {
        bigint id PK
        bigint program_studi_id FK
        varchar nama
        text deskripsi
        boolean is_active
        timestamp created_at
    }

    pengajuan_judul_ta {
        bigint id PK
        bigint mahasiswa_id FK
        bigint tahun_akademik_id FK
        bigint topik_id FK
        varchar judul_utama
        varchar judul_alternatif
        text latar_belakang
        text rumusan_masalah
        text tujuan
        varchar file_proposal
        integer nomor_urut_pengajuan
        enum status "draft|diajukan|revisi|disetujui|ditolak"
        text catatan_reviewer
        bigint direview_oleh FK
        timestamp direview_at
        timestamp created_at
        timestamp updated_at
    }

    kuota_pembimbing_ta {
        bigint id PK
        bigint dosen_id FK
        bigint tahun_akademik_id FK
        integer kuota_maksimal
        integer kuota_terpakai
        timestamp created_at
        timestamp updated_at
    }

    pembimbing_ta {
        bigint id PK
        bigint pengajuan_id FK
        bigint dosen_id FK
        enum urutan "pembimbing_1|pembimbing_2"
        enum status "aktif|diganti|selesai"
        date tanggal_mulai
        date tanggal_selesai
        bigint ditetapkan_oleh FK
        timestamp created_at
    }

    penguji_ta {
        bigint id PK
        bigint jadwal_sidang_id FK
        bigint dosen_id FK
        enum peran "ketua|penguji_1|penguji_2|sekretaris"
        timestamp created_at
    }

    logbook_bimbingan_ta {
        bigint id PK
        bigint pengajuan_id FK
        bigint pembimbing_id FK
        date tanggal
        text materi_bimbingan
        text catatan_pembimbing
        text tindak_lanjut
        varchar file_dokumentasi
        enum status "hadir|tidak_hadir"
        timestamp created_at
    }

    jadwal_seminar_ta {
        bigint id PK
        bigint pengajuan_id FK
        bigint ruangan_id FK
        date tanggal
        time jam_mulai
        time jam_selesai
        enum tipe "seminar_proposal|seminar_hasil"
        enum status "terjadwal|selesai|dibatalkan"
        timestamp created_at
        timestamp updated_at
    }

    jadwal_sidang_ta {
        bigint id PK
        bigint pengajuan_id FK
        bigint ruangan_id FK
        date tanggal
        time jam_mulai
        time jam_selesai
        enum status "terjadwal|selesai|ditunda|dibatalkan"
        timestamp created_at
        timestamp updated_at
    }

    nilai_ta {
        bigint id PK
        bigint jadwal_sidang_id FK
        bigint penguji_id FK
        decimal nilai_penguasaan_materi
        decimal nilai_metodologi
        decimal nilai_presentasi
        decimal nilai_penulisan
        decimal nilai_total
        text catatan
        timestamp created_at
    }

    revisi_ta {
        bigint id PK
        bigint pengajuan_id FK
        bigint penguji_id FK
        text catatan_revisi
        date batas_revisi
        boolean is_selesai
        date tanggal_selesai
        varchar file_revisi
        timestamp created_at
        timestamp updated_at
    }

    bebas_pustaka {
        bigint id PK
        bigint mahasiswa_id FK
        bigint pengajuan_id FK
        boolean bebas_perpustakaan
        boolean bebas_laboratorium
        boolean bebas_administrasi
        boolean bebas_keuangan
        boolean upload_repository
        varchar link_repository
        enum status "pending|selesai"
        timestamp created_at
        timestamp updated_at
    }

    topik_penelitian_ta ||--o{ pengajuan_judul_ta : "digunakan pada"
    pengajuan_judul_ta ||--o{ pembimbing_ta : "dibimbing"
    pembimbing_ta ||--o{ logbook_bimbingan_ta : "mencatat"
    pengajuan_judul_ta ||--o{ jadwal_seminar_ta : "memiliki seminar"
    pengajuan_judul_ta ||--o| jadwal_sidang_ta : "memiliki sidang"
    jadwal_sidang_ta ||--o{ penguji_ta : "diuji oleh"
    jadwal_sidang_ta ||--o{ nilai_ta : "menghasilkan nilai"
    pengajuan_judul_ta ||--o{ revisi_ta : "memiliki revisi"
    pengajuan_judul_ta ||--o| bebas_pustaka : "diselesaikan dengan"
```

---

## 📋 MODUL 4D: SIMPRESKUL (Presensi Kuliah)

```mermaid
erDiagram
    jurnal_perkuliahan {
        bigint id PK
        bigint kelas_id FK
        bigint dosen_id FK
        bigint tahun_akademik_id FK
        integer pertemuan_ke
        date tanggal
        time jam_mulai
        time jam_selesai
        text topik_bahasan
        text materi_disampaikan
        enum metode "tatap_muka|daring_sync|daring_async|hybrid"
        varchar link_meeting
        boolean is_pengganti
        bigint pertemuan_digantikan_id FK
        timestamp created_at
        timestamp updated_at
    }

    sesi_presensi {
        bigint id PK
        bigint jurnal_id FK
        timestamp dibuka_at
        timestamp ditutup_at
        enum metode "qr_code|manual|link"
        boolean is_active
        timestamp created_at
    }

    token_qr_presensi {
        bigint id PK
        bigint sesi_id FK
        varchar token UK
        timestamp expired_at
        integer radius_meter
        decimal latitude
        decimal longitude
        timestamp created_at
    }

    presensi_mahasiswa {
        bigint id PK
        bigint sesi_id FK
        bigint mahasiswa_id FK
        enum status "hadir|izin|sakit|alpha"
        timestamp waktu_presensi
        varchar token_digunakan
        varchar ip_address
        decimal latitude_mahasiswa
        decimal longitude_mahasiswa
        varchar file_surat_izin
        bigint diinput_oleh FK
        text catatan
        timestamp created_at
        timestamp updated_at
    }

    rekap_kehadiran {
        bigint id PK
        bigint kelas_id FK
        bigint mahasiswa_id FK
        bigint tahun_akademik_id FK
        integer total_pertemuan
        integer jumlah_hadir
        integer jumlah_izin
        integer jumlah_sakit
        integer jumlah_alpha
        decimal persentase_hadir
        boolean memenuhi_syarat_uts
        boolean memenuhi_syarat_uas
        timestamp updated_at
    }

    jurnal_perkuliahan ||--o{ sesi_presensi : "membuka"
    sesi_presensi ||--o| token_qr_presensi : "menggunakan token"
    sesi_presensi ||--o{ presensi_mahasiswa : "mencatat"
    kelas ||--o{ jurnal_perkuliahan : "memiliki"
    kelas ||--o{ rekap_kehadiran : "direkap per"
```

---

## 💰 MODUL 5: SIKEU (Sistem Informasi Keuangan)

### Deskripsi Arsitektur
Alur: Tarif UKT/SPP → Generate Tagihan → Potong Beasiswa/Diskon → Bayar via VA → Callback Gateway → Rekonsiliasi → Update Status Bayar → Unlock KRS. Tagihan terkunci ke semester, mahasiswa, dan jenis biaya.

```mermaid
erDiagram
    jenis_biaya {
        bigint id PK
        varchar kode UK
        varchar nama
        enum tipe "ukt|spp|sks|praktikum|wisuda|lainnya"
        text deskripsi
        boolean is_recurring
        boolean is_active
        timestamp created_at
    }

    tarif_ukt {
        bigint id PK
        bigint program_studi_id FK
        bigint jenis_biaya_id FK
        bigint tahun_akademik_id FK
        integer kelompok_ukt
        decimal nominal
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    beasiswa {
        bigint id PK
        varchar kode UK
        varchar nama
        enum sumber "internal|eksternal|pemerintah"
        enum tipe_potongan "persen|nominal"
        decimal nilai_potongan
        text deskripsi
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    mahasiswa_beasiswa {
        bigint id PK
        bigint mahasiswa_id FK
        bigint beasiswa_id FK
        bigint tahun_akademik_id FK
        date berlaku_mulai
        date berlaku_sampai
        enum status "aktif|nonaktif|berakhir"
        bigint ditetapkan_oleh FK
        varchar file_sk
        timestamp created_at
    }

    tagihan_mahasiswa {
        bigint id PK
        bigint mahasiswa_id FK
        bigint tahun_akademik_id FK
        varchar nomor_tagihan UK
        decimal total_tagihan
        decimal total_potongan
        decimal total_denda
        decimal total_bayar
        enum status "belum_bayar|sebagian|lunas|dispensasi"
        date jatuh_tempo
        timestamp created_at
        timestamp updated_at
    }

    detail_tagihan {
        bigint id PK
        bigint tagihan_id FK
        bigint jenis_biaya_id FK
        decimal nominal
        decimal potongan
        decimal nominal_bersih
        text keterangan
        timestamp created_at
    }

    potongan_tagihan {
        bigint id PK
        bigint tagihan_id FK
        bigint beasiswa_id FK
        enum tipe "beasiswa|diskon|subsidi|lainnya"
        decimal nominal_potongan
        text keterangan
        bigint diinput_oleh FK
        timestamp created_at
    }

    denda_tagihan {
        bigint id PK
        bigint tagihan_id FK
        enum tipe_denda "keterlambatan|lainnya"
        decimal nominal_denda
        date tanggal_denda
        text keterangan
        timestamp created_at
    }

    virtual_account {
        bigint id PK
        bigint tagihan_id FK
        varchar va_number UK
        varchar bank_kode
        varchar bank_nama
        decimal nominal
        timestamp expired_at
        enum status "aktif|kadaluarsa|dibayar"
        timestamp created_at
        timestamp updated_at
    }

    pembayaran {
        bigint id PK
        bigint tagihan_id FK
        bigint virtual_account_id FK
        varchar kode_transaksi UK
        decimal jumlah_bayar
        timestamp waktu_bayar
        varchar channel_bayar
        varchar bank_pengirim
        enum status "success|pending|failed|reversed"
        bigint diverifikasi_oleh FK
        timestamp created_at
    }

    callback_payment_gateway {
        bigint id PK
        varchar order_id UK
        varchar payment_type
        json raw_payload
        enum status "received|processed|failed"
        bigint pembayaran_id FK
        timestamp received_at
        timestamp processed_at
    }

    rekonsiliasi_pembayaran {
        bigint id PK
        date tanggal_rekonsiliasi
        varchar bank_kode
        integer total_transaksi
        decimal total_nominal_sistem
        decimal total_nominal_bank
        decimal selisih
        enum status "cocok|tidak_cocok|dalam_review"
        varchar file_laporan_bank
        bigint diproses_oleh FK
        timestamp created_at
    }

    jenis_biaya ||--o{ tarif_ukt : "memiliki tarif"
    jenis_biaya ||--o{ detail_tagihan : "dirinci pada"
    beasiswa ||--o{ mahasiswa_beasiswa : "diterima oleh"
    beasiswa ||--o{ potongan_tagihan : "memberikan potongan"
    tagihan_mahasiswa ||--o{ detail_tagihan : "berisi"
    tagihan_mahasiswa ||--o{ potongan_tagihan : "mendapat potongan"
    tagihan_mahasiswa ||--o{ denda_tagihan : "dikenai denda"
    tagihan_mahasiswa ||--o{ virtual_account : "dibayar via"
    tagihan_mahasiswa ||--o{ pembayaran : "dilunasi dengan"
    virtual_account ||--o{ pembayaran : "digunakan pada"
    pembayaran ||--o| callback_payment_gateway : "dipicu oleh"
```

---

## 🏗️ Arsitektur & Strategi (Part 2)

### Business Guards SIKEU
| Guard | Implementasi |
|---|---|
| **KRS Lock** | `tagihan.status != 'lunas'` → set `krs.locked_by_keuangan = true` via trigger/event |
| **VA Unique** | `virtual_account.va_number` — UNIQUE NOT NULL per bank |
| **Double Payment** | `callback_payment_gateway.order_id` — UNIQUE, idempotent callback handling |
| **Tarif History** | Setiap update tarif UKT → insert baru, bukan update (append-only) |

### Indexing OBE & SIMANTA
| Tabel | Index |
|---|---|
| `nilai_cpl_mahasiswa` | Composite: `(mahasiswa_id, cpl_id, tahun_akademik_id)` UNIQUE |
| `rekap_kehadiran` | Composite: `(kelas_id, mahasiswa_id)` UNIQUE |
| `pengajuan_judul_ta` | `(mahasiswa_id, tahun_akademik_id)` — prevent double submission |
| `tagihan_mahasiswa` | `(mahasiswa_id, tahun_akademik_id)` UNIQUE per semester |

> **Lanjut ke Part 3**: SIMPEG | LMS | SINAPRA | KERJASAMA | UPM
