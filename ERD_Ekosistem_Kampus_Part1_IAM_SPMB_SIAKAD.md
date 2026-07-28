# 🏛️ GLOBAL FINAL ERD — Ekosistem Kampus Terintegrasi
## Part 1 of 4: IAM & Auth Center | SPMB | SIAKAD Core

---

## 📊 Statistik Keseluruhan (Preview)
| Domain | Estimasi Tabel |
|---|---|
| IAM & Auth Center | 9 tabel |
| SPMB | 14 tabel |
| SIAKAD Core | 19 tabel |
| OBE + SIMPI + SIMANTA + SIMPRESKUL | 32 tabel |
| SIKEU | 13 tabel |
| SIMPEG | 12 tabel |
| LMS | 12 tabel |
| SINAPRA | 9 tabel |
| KERJASAMA | 6 tabel |
| UPM | 11 tabel |
| **TOTAL** | **~137 tabel** |

---

## 🔐 MODUL 1: IAM & Auth Center

### Deskripsi Arsitektur
Modul ini adalah fondasi seluruh ekosistem. Semua user dari semua modul (Mahasiswa, Dosen, Tendik, Admin, Calon Mahasiswa) dikelola di sini. RBAC (Role-Based Access Control) granular memungkinkan setiap role memiliki set permission yang berbeda. SSO token memfasilitasi login satu pintu antar-aplikasi.

```mermaid
erDiagram
    users {
        bigint id PK
        varchar username UK
        varchar email UK
        varchar password_hash
        varchar phone
        enum user_type "mahasiswa|dosen|tendik|admin|calon_mhs"
        boolean is_active
        boolean is_verified
        timestamp email_verified_at
        timestamp last_login_at
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    roles {
        bigint id PK
        varchar name UK
        varchar slug UK
        text description
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    permissions {
        bigint id PK
        varchar name UK
        varchar slug UK
        varchar module
        varchar action "create|read|update|delete|approve"
        text description
        timestamp created_at
    }

    user_roles {
        bigint id PK
        bigint user_id FK
        bigint role_id FK
        bigint assigned_by FK
        date valid_from
        date valid_until
        timestamp created_at
    }

    role_permissions {
        bigint id PK
        bigint role_id FK
        bigint permission_id FK
        timestamp created_at
    }

    user_sessions {
        bigint id PK
        bigint user_id FK
        varchar token UK
        varchar ip_address
        text user_agent
        timestamp expires_at
        timestamp created_at
    }

    sso_tokens {
        bigint id PK
        bigint user_id FK
        varchar access_token UK
        varchar refresh_token UK
        varchar client_app
        timestamp access_expires_at
        timestamp refresh_expires_at
        timestamp created_at
    }

    password_resets {
        bigint id PK
        bigint user_id FK
        varchar token UK
        timestamp expires_at
        boolean is_used
        timestamp created_at
    }

    audit_logs {
        bigint id PK
        bigint user_id FK
        varchar module
        varchar action
        varchar table_name
        bigint record_id
        json old_values
        json new_values
        varchar ip_address
        text user_agent
        timestamp created_at
    }

    users ||--o{ user_roles : "memiliki"
    roles ||--o{ user_roles : "diberikan ke"
    roles ||--o{ role_permissions : "memiliki"
    permissions ||--o{ role_permissions : "dimiliki"
    users ||--o{ user_sessions : "punya sesi"
    users ||--o{ sso_tokens : "punya token SSO"
    users ||--o{ password_resets : "reset password"
    users ||--o{ audit_logs : "melakukan aksi"
```

---

## 📝 MODUL 2: SPMB (Sistem Penerimaan Mahasiswa Baru)

### Deskripsi Arsitektur
Alur bisnis SPMB: Jalur Masuk → Gelombang → Pendaftaran → Pembayaran Biaya Tes → Ujian → Hasil Seleksi → Pengumuman → Konversi ke Mahasiswa (NIM). Setiap tahap memiliki status yang terkunci secara bisnis.

```mermaid
erDiagram
    jalur_masuk {
        bigint id PK
        varchar kode UK
        varchar nama
        text deskripsi
        enum tipe "reguler|transfer|beasiswa|internasional|rpla"
        boolean ada_ujian_tulis
        boolean ada_ujian_praktik
        boolean ada_wawancara
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    gelombang_penerimaan {
        bigint id PK
        bigint jalur_masuk_id FK
        bigint tahun_akademik_id FK
        varchar nama
        date tanggal_buka
        date tanggal_tutup
        date tanggal_ujian
        date tanggal_pengumuman
        integer kuota_total
        integer kuota_terisi
        decimal biaya_pendaftaran
        enum status "draft|aktif|ditutup|selesai"
        timestamp created_at
        timestamp updated_at
    }

    pendaftaran_calon_mhs {
        bigint id PK
        bigint gelombang_id FK
        bigint user_id FK
        bigint program_studi_id FK
        bigint program_studi_pilihan2_id FK
        varchar no_pendaftaran UK
        varchar nama_lengkap
        varchar nik UK
        date tanggal_lahir
        varchar tempat_lahir
        enum jenis_kelamin "L|P"
        text alamat
        varchar asal_sekolah
        varchar jurusan_sekolah
        decimal nilai_rata_rapor
        varchar tahun_lulus
        varchar nama_wali
        varchar telepon_wali
        enum status "draft|submitted|verified|lulus_administrasi|gagal_administrasi"
        text catatan_verifikasi
        bigint diverifikasi_oleh FK
        timestamp diverifikasi_at
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    dokumen_pendaftaran {
        bigint id PK
        bigint pendaftaran_id FK
        varchar jenis_dokumen "ijazah|rapor|ktp|foto|lainnya"
        varchar file_path
        boolean is_verified
        text catatan
        timestamp created_at
    }

    pembayaran_spmb {
        bigint id PK
        bigint pendaftaran_id FK
        varchar kode_bayar UK
        decimal jumlah_tagihan
        decimal jumlah_bayar
        enum status "pending|paid|failed|refunded"
        varchar metode_bayar
        varchar va_number
        json gateway_response
        timestamp paid_at
        timestamp expired_at
        timestamp created_at
        timestamp updated_at
    }

    jadwal_ujian_spmb {
        bigint id PK
        bigint gelombang_id FK
        bigint ruangan_id FK
        varchar nama_sesi
        date tanggal
        time jam_mulai
        time jam_selesai
        integer kapasitas
        enum tipe_ujian "tulis|praktik|wawancara"
        timestamp created_at
    }

    peserta_ujian_spmb {
        bigint id PK
        bigint pendaftaran_id FK
        bigint jadwal_ujian_id FK
        varchar no_peserta UK
        varchar nomor_kursi
        boolean hadir
        timestamp created_at
    }

    kuesioner_spmb {
        bigint id PK
        bigint gelombang_id FK
        varchar judul
        text deskripsi
        boolean is_required
        boolean is_active
        timestamp created_at
    }

    pertanyaan_kuesioner_spmb {
        bigint id PK
        bigint kuesioner_id FK
        text pertanyaan
        enum tipe "text|radio|checkbox|scale"
        json opsi_jawaban
        boolean is_required
        integer urutan
    }

    jawaban_kuesioner_spmb {
        bigint id PK
        bigint pendaftaran_id FK
        bigint pertanyaan_id FK
        text jawaban
        timestamp created_at
    }

    nilai_seleksi {
        bigint id PK
        bigint pendaftaran_id FK
        varchar komponen_nilai "tulis|praktik|wawancara|rapor"
        decimal nilai
        text catatan
        bigint dinilai_oleh FK
        timestamp created_at
        timestamp updated_at
    }

    hasil_seleksi {
        bigint id PK
        bigint pendaftaran_id FK
        bigint program_studi_diterima_id FK
        decimal nilai_total
        integer peringkat
        enum status "lulus|tidak_lulus|cadangan|mengundurkan_diri"
        text catatan
        timestamp diumumkan_at
        timestamp created_at
        timestamp updated_at
    }

    konversi_mahasiswa {
        bigint id PK
        bigint pendaftaran_id FK
        bigint mahasiswa_id FK
        varchar nim_diterbitkan UK
        bigint diproses_oleh FK
        timestamp created_at
    }

    pengumuman_spmb {
        bigint id PK
        bigint gelombang_id FK
        varchar judul
        text isi
        boolean is_published
        timestamp published_at
        timestamp created_at
    }

    jalur_masuk ||--o{ gelombang_penerimaan : "memiliki"
    gelombang_penerimaan ||--o{ pendaftaran_calon_mhs : "diisi oleh"
    pendaftaran_calon_mhs ||--o{ dokumen_pendaftaran : "melampirkan"
    pendaftaran_calon_mhs ||--|| pembayaran_spmb : "memiliki"
    gelombang_penerimaan ||--o{ jadwal_ujian_spmb : "menjadwalkan"
    pendaftaran_calon_mhs ||--o{ peserta_ujian_spmb : "mengikuti"
    jadwal_ujian_spmb ||--o{ peserta_ujian_spmb : "berisi"
    gelombang_penerimaan ||--o{ kuesioner_spmb : "memiliki"
    kuesioner_spmb ||--o{ pertanyaan_kuesioner_spmb : "berisi"
    pendaftaran_calon_mhs ||--o{ jawaban_kuesioner_spmb : "menjawab"
    pertanyaan_kuesioner_spmb ||--o{ jawaban_kuesioner_spmb : "dijawab"
    pendaftaran_calon_mhs ||--o{ nilai_seleksi : "mendapatkan"
    pendaftaran_calon_mhs ||--|| hasil_seleksi : "menghasilkan"
    hasil_seleksi ||--o| konversi_mahasiswa : "dikonversi"
    gelombang_penerimaan ||--o{ pengumuman_spmb : "diumumkan"
```

---

## 🎓 MODUL 3: SIAKAD Core

### Deskripsi Arsitektur
Inti sistem akademik. Hierarki: Fakultas → Prodi → Kurikulum → Matakuliah. Mahasiswa terikat ke Prodi & Angkatan. KRS dikunci oleh status bayar SIKEU. Nilai dari LMS di-sync ke sini.

```mermaid
erDiagram
    fakultas {
        bigint id PK
        varchar kode UK
        varchar nama
        varchar nama_singkat
        bigint dekan_id FK
        varchar telepon
        varchar email
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    program_studi {
        bigint id PK
        bigint fakultas_id FK
        varchar kode_prodi UK
        varchar kode_prodi_dikti UK
        varchar nama
        enum jenjang "D3|D4|S1|S2|S3|Profesi"
        integer total_sks_lulus
        integer total_semester_normal
        varchar akreditasi
        date akreditasi_berlaku_sampai
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    kurikulum {
        bigint id PK
        bigint program_studi_id FK
        varchar kode UK
        varchar nama
        integer tahun_berlaku
        text deskripsi
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    mata_kuliah {
        bigint id PK
        bigint kurikulum_id FK
        varchar kode_mk UK
        varchar nama
        integer sks_teori
        integer sks_praktik
        integer total_sks
        integer semester_anjuran
        enum tipe "wajib|pilihan|wajib_prodi"
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    prasyarat_mk {
        bigint id PK
        bigint mata_kuliah_id FK
        bigint prasyarat_id FK
        enum tipe "lulus|pernah_ambil"
        decimal nilai_minimum
    }

    tahun_akademik {
        bigint id PK
        varchar kode UK
        varchar nama
        integer tahun_mulai
        enum semester "ganjil|genap|pendek"
        date tanggal_mulai
        date tanggal_selesai
        boolean is_active
        timestamp created_at
    }

    mahasiswa {
        bigint id PK
        bigint user_id FK
        bigint program_studi_id FK
        bigint konversi_id FK
        varchar nim UK
        varchar nama_lengkap
        varchar nik
        date tanggal_lahir
        varchar tempat_lahir
        enum jenis_kelamin "L|P"
        varchar agama
        text alamat
        varchar telepon
        integer angkatan
        date tanggal_masuk
        enum status "aktif|cuti|mangkir|dropout|lulus"
        bigint dosen_wali_id FK
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    dosen {
        bigint id PK
        bigint user_id FK
        bigint pegawai_id FK
        varchar nidn UK
        varchar nip
        varchar nama_lengkap
        varchar gelar_depan
        varchar gelar_belakang
        bigint program_studi_id FK
        varchar jabatan_akademik
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    kelas {
        bigint id PK
        bigint mata_kuliah_id FK
        bigint tahun_akademik_id FK
        bigint program_studi_id FK
        bigint ruangan_id FK
        varchar kode_kelas UK
        varchar nama_kelas
        integer kapasitas
        integer kuota_krs
        enum hari "senin|selasa|rabu|kamis|jumat|sabtu"
        time jam_mulai
        time jam_selesai
        enum status "draft|aktif|selesai"
        timestamp created_at
        timestamp updated_at
    }

    dosen_pengampu {
        bigint id PK
        bigint kelas_id FK
        bigint dosen_id FK
        enum peran "pengampu_utama|co_pengampu"
        timestamp created_at
    }

    krs {
        bigint id PK
        bigint mahasiswa_id FK
        bigint tahun_akademik_id FK
        integer total_sks_diambil
        enum status "draft|diajukan|disetujui|dikunci|dibatalkan"
        bigint disetujui_oleh FK
        timestamp disetujui_at
        boolean locked_by_keuangan
        timestamp created_at
        timestamp updated_at
    }

    krs_detail {
        bigint id PK
        bigint krs_id FK
        bigint kelas_id FK
        enum status "aktif|dibatalkan"
        timestamp created_at
    }

    nilai_mahasiswa {
        bigint id PK
        bigint krs_detail_id FK
        decimal nilai_harian
        decimal nilai_uts
        decimal nilai_uas
        decimal nilai_praktik
        decimal nilai_akhir
        varchar nilai_huruf
        integer bobot_mutu
        boolean is_final
        bigint diinput_oleh FK
        timestamp created_at
        timestamp updated_at
    }

    khs {
        bigint id PK
        bigint mahasiswa_id FK
        bigint tahun_akademik_id FK
        decimal ips
        integer total_sks_semester
        integer sks_kumulatif
        decimal ipk
        timestamp created_at
        timestamp updated_at
    }

    status_akademik_log {
        bigint id PK
        bigint mahasiswa_id FK
        enum status_lama "aktif|cuti|mangkir|dropout|lulus"
        enum status_baru "aktif|cuti|mangkir|dropout|lulus"
        text alasan
        bigint diubah_oleh FK
        timestamp created_at
    }

    cuti_mahasiswa {
        bigint id PK
        bigint mahasiswa_id FK
        bigint tahun_akademik_id FK
        text alasan
        varchar file_surat
        enum status "pending|disetujui|ditolak"
        bigint diproses_oleh FK
        timestamp created_at
    }

    kelulusan {
        bigint id PK
        bigint mahasiswa_id FK
        bigint tahun_akademik_id FK
        date tanggal_sidang
        decimal ipk_akhir
        integer total_sks
        integer masa_studi_semester
        enum predikat "memuaskan|sangat_memuaskan|cum_laude|dengan_pujian"
        varchar nomor_ijazah UK
        date tanggal_ijazah
        timestamp created_at
    }

    alumni {
        bigint id PK
        bigint mahasiswa_id FK
        bigint kelulusan_id FK
        varchar pekerjaan_sekarang
        varchar nama_perusahaan
        varchar bidang_industri
        decimal gaji_pertama
        integer tahun_kerja_pertama
        text alamat_sekarang
        timestamp created_at
        timestamp updated_at
    }

    fakultas ||--o{ program_studi : "menaungi"
    program_studi ||--o{ kurikulum : "memiliki"
    kurikulum ||--o{ mata_kuliah : "berisi"
    mata_kuliah ||--o{ prasyarat_mk : "mensyaratkan"
    mata_kuliah ||--o{ prasyarat_mk : "disyaratkan oleh"
    program_studi ||--o{ mahasiswa : "mendidik"
    mahasiswa ||--o{ krs : "mengajukan"
    tahun_akademik ||--o{ krs : "berlaku pada"
    krs ||--o{ krs_detail : "berisi"
    kelas ||--o{ krs_detail : "diikuti via"
    mata_kuliah ||--o{ kelas : "dibuka sebagai"
    tahun_akademik ||--o{ kelas : "berlaku pada"
    kelas ||--o{ dosen_pengampu : "diampu oleh"
    dosen ||--o{ dosen_pengampu : "mengampu"
    krs_detail ||--|| nilai_mahasiswa : "dinilai"
    mahasiswa ||--o{ khs : "memiliki"
    tahun_akademik ||--o{ khs : "berlaku pada"
    mahasiswa ||--o{ status_akademik_log : "memiliki riwayat"
    mahasiswa ||--o{ cuti_mahasiswa : "mengajukan"
    mahasiswa ||--o| kelulusan : "diwisuda"
    kelulusan ||--o| alumni : "menjadi"
```

---

## 🏗️ Arsitektur & Strategi (Part 1)

### Indexing Strategy
| Tabel | Index Kritis |
|---|---|
| `users` | `email`, `username` — UNIQUE |
| `audit_logs` | Composite: `(module, action, created_at)` |
| `pendaftaran_calon_mhs` | `no_pendaftaran` UNIQUE, `nik` UNIQUE |
| `mahasiswa` | `nim` UNIQUE, `angkatan` |
| `krs_detail` | Composite UNIQUE: `(krs_id, kelas_id)` |
| `nilai_mahasiswa` | `krs_detail_id` — cover index |
| `kelas` | Composite UNIQUE: `(mata_kuliah_id, tahun_akademik_id, kode_kelas)` |

### Composite Keys & Business Guards
- **KRS Lock**: `krs.locked_by_keuangan = true` → mahasiswa tidak bisa ubah KRS sampai tagihan lunas
- **NIM Unique**: `mahasiswa.nim` — UNIQUE NOT NULL, digenerate saat `konversi_mahasiswa`
- **KRS Duplicate Guard**: UNIQUE constraint `(krs_id, kelas_id)` di `krs_detail` mencegah mahasiswa ambil kelas sama dua kali
- **Prasyarat**: Tabel `prasyarat_mk` bisa rekursif (FK ke mata_kuliah sendiri), harus dicek via stored procedure/application layer

> **Lanjut ke Part 2**: OBE | SIMPI | SIMANTA | SIMPRESKUL | SIKEU
