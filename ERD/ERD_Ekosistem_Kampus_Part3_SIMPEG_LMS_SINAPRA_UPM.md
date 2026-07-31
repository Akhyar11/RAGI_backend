# 🏛️ GLOBAL FINAL ERD — Ekosistem Kampus Terintegrasi
## Part 3 of 4: SIMPEG | LMS | SINAPRA | KERJASAMA | UPM

---

## 👔 MODUL 6: SIMPEG (Sistem Informasi Kepegawaian)

### Deskripsi Arsitektur
`pegawai` adalah entitas induk untuk semua SDM kampus. Dosen memiliki entri di tabel `dosen` (SIAKAD) yang ber-FK ke `pegawai`. NIDN/NIDK dari NIDN disimpan di `dosen`. Penggajian menggunakan komponen dinamis agar fleksibel. BKD (Beban Kerja Dosen) memenuhi standar pelaporan ke PDDikti.

```mermaid
erDiagram
    unit_kerja {
        bigint id PK
        bigint induk_id FK
        varchar kode UK
        varchar nama
        enum tipe "rektorat|fakultas|prodi|lp3m|biro|unit"
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    jabatan {
        bigint id PK
        bigint unit_kerja_id FK
        varchar nama
        enum tipe "struktural|fungsional|teknis"
        integer level_jabatan
        boolean is_active
        timestamp created_at
    }

    jabatan_fungsional_akademik {
        bigint id PK
        varchar nama UK
        integer angka_kredit_min
        integer angka_kredit_max
        enum golongan "asisten_ahli|lektor|lektor_kepala|guru_besar"
        timestamp created_at
    }

    pegawai {
        bigint id PK
        bigint user_id FK
        bigint unit_kerja_id FK
        varchar nip UK
        varchar nik UK
        varchar nama_lengkap
        date tanggal_lahir
        varchar tempat_lahir
        enum jenis_kelamin "L|P"
        varchar agama
        enum jenis_pegawai "dosen|tendik|honorer"
        enum status_kepegawaian "pns|non_pns|kontrak|tetap_yayasan"
        date tanggal_masuk
        date tanggal_keluar
        enum status "aktif|non_aktif|pensiun|meninggal"
        text alamat
        varchar telepon
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    riwayat_jabatan {
        bigint id PK
        bigint pegawai_id FK
        bigint jabatan_id FK
        bigint jabatan_fungsional_id FK
        date mulai_jabatan
        date selesai_jabatan
        varchar sk_nomor
        date sk_tanggal
        varchar file_sk
        boolean is_active
        timestamp created_at
    }

    riwayat_pendidikan_pegawai {
        bigint id PK
        bigint pegawai_id FK
        enum jenjang "sma|d3|d4|s1|s2|s3"
        varchar nama_institusi
        varchar program_studi
        varchar bidang_ilmu
        integer tahun_masuk
        integer tahun_lulus
        varchar nomor_ijazah
        varchar file_ijazah
        boolean is_pendidikan_terakhir
        timestamp created_at
    }

    komponen_gaji {
        bigint id PK
        varchar kode UK
        varchar nama
        enum tipe "pendapatan|potongan"
        enum kategori "pokok|tunjangan|insentif|bpjs|pajak|lainnya"
        boolean is_fixed
        boolean is_active
        timestamp created_at
    }

    penggajian {
        bigint id PK
        bigint pegawai_id FK
        integer bulan
        integer tahun
        decimal total_pendapatan
        decimal total_potongan
        decimal gaji_bersih
        enum status "draft|disetujui|dibayar"
        date tanggal_bayar
        bigint disetujui_oleh FK
        timestamp created_at
        timestamp updated_at
    }

    detail_penggajian {
        bigint id PK
        bigint penggajian_id FK
        bigint komponen_id FK
        decimal nominal
        text keterangan
        timestamp created_at
    }

    presensi_pegawai {
        bigint id PK
        bigint pegawai_id FK
        date tanggal
        time jam_masuk
        time jam_keluar
        enum metode "fingerprint|manual|mobile"
        decimal latitude
        decimal longitude
        enum status "hadir|izin|sakit|cuti|alpha"
        varchar file_surat
        text keterangan
        bigint diverifikasi_oleh FK
        timestamp created_at
    }

    beban_kerja_dosen {
        bigint id PK
        bigint dosen_id FK
        bigint tahun_akademik_id FK
        integer semester
        decimal sks_pengajaran
        decimal sks_penelitian
        decimal sks_pengabdian
        decimal sks_penunjang
        decimal total_sks
        decimal sks_minimum
        decimal sks_maksimum
        boolean memenuhi_bkd
        enum status "draft|dilaporkan|diverifikasi"
        bigint diverifikasi_oleh FK
        timestamp created_at
        timestamp updated_at
    }

    detail_bkd {
        bigint id PK
        bigint bkd_id FK
        enum kategori "pengajaran|penelitian|pengabdian|penunjang"
        varchar uraian_kegiatan
        decimal sks_ekuivalen
        varchar bukti_dokumen
        timestamp created_at
    }

    unit_kerja ||--o{ unit_kerja : "memiliki sub-unit"
    unit_kerja ||--o{ jabatan : "memiliki jabatan"
    unit_kerja ||--o{ pegawai : "menaungi"
    pegawai ||--o{ riwayat_jabatan : "memiliki riwayat"
    jabatan ||--o{ riwayat_jabatan : "diisi oleh"
    jabatan_fungsional_akademik ||--o{ riwayat_jabatan : "dicapai oleh"
    pegawai ||--o{ riwayat_pendidikan_pegawai : "memiliki"
    pegawai ||--o{ penggajian : "menerima"
    penggajian ||--o{ detail_penggajian : "berisi komponen"
    komponen_gaji ||--o{ detail_penggajian : "digunakan pada"
    pegawai ||--o{ presensi_pegawai : "mencatat"
    pegawai ||--o{ beban_kerja_dosen : "dilaporkan via"
    beban_kerja_dosen ||--o{ detail_bkd : "dirinci"
```

---

## 📚 MODUL 7: LMS (Learning Management System)

### Deskripsi Arsitektur
LMS terintegrasi dengan SIAKAD. `kelas_lms` disinkronisasi dari `kelas` SIAKAD. Nilai harian dari LMS di-sync ke `nilai_mahasiswa` SIAKAD. Bank soal bisa dipakai lintas kelas. Forum diskusi mendukung threading.

```mermaid
erDiagram
    kelas_lms {
        bigint id PK
        bigint kelas_id FK
        varchar lms_course_code UK
        varchar nama_kelas
        text deskripsi
        boolean is_published
        timestamp created_at
        timestamp updated_at
    }

    modul_pembelajaran {
        bigint id PK
        bigint kelas_lms_id FK
        varchar judul
        text deskripsi
        integer urutan
        date tersedia_mulai
        date tersedia_sampai
        boolean is_published
        timestamp created_at
        timestamp updated_at
    }

    materi_pembelajaran {
        bigint id PK
        bigint modul_id FK
        varchar judul
        enum tipe "pdf|video|link|text|ppt|zip"
        varchar file_path
        varchar url_eksternal
        text konten_text
        integer durasi_menit
        integer urutan
        boolean is_published
        timestamp created_at
    }

    bank_soal {
        bigint id PK
        bigint program_studi_id FK
        bigint mata_kuliah_id FK
        bigint dibuat_oleh FK
        varchar nama
        text deskripsi
        timestamp created_at
        timestamp updated_at
    }

    soal {
        bigint id PK
        bigint bank_soal_id FK
        text pertanyaan
        enum tipe "pilihan_ganda|essay|benar_salah|isian"
        varchar file_gambar
        enum tingkat_kesulitan "mudah|sedang|sulit"
        text pembahasan
        decimal bobot_poin
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    pilihan_jawaban {
        bigint id PK
        bigint soal_id FK
        varchar label "A|B|C|D|E"
        text teks_jawaban
        varchar file_gambar
        boolean is_benar
        integer urutan
    }

    kuis_ujian {
        bigint id PK
        bigint kelas_lms_id FK
        bigint bank_soal_id FK
        varchar judul
        text instruksi
        integer durasi_menit
        integer max_attempt
        decimal passing_grade
        boolean acak_soal
        boolean acak_pilihan
        boolean tampilkan_jawaban
        timestamp dibuka_at
        timestamp ditutup_at
        boolean is_published
        timestamp created_at
    }

    soal_kuis {
        bigint id PK
        bigint kuis_id FK
        bigint soal_id FK
        integer urutan
        decimal bobot_poin_override
    }

    attempt_kuis {
        bigint id PK
        bigint kuis_id FK
        bigint mahasiswa_id FK
        integer nomor_attempt
        decimal skor
        decimal skor_maksimal
        timestamp dimulai_at
        timestamp diselesaikan_at
        enum status "ongoing|selesai|timeout"
        timestamp created_at
    }

    jawaban_attempt {
        bigint id PK
        bigint attempt_id FK
        bigint soal_id FK
        bigint pilihan_id FK
        text jawaban_essay
        boolean is_benar
        decimal poin_didapat
        timestamp created_at
    }

    assignment {
        bigint id PK
        bigint kelas_lms_id FK
        bigint sub_cpmk_id FK
        varchar judul
        text deskripsi
        varchar file_soal
        date deadline
        decimal nilai_maksimal
        boolean allow_late_submission
        integer late_penalty_persen
        timestamp created_at
        timestamp updated_at
    }

    submission_tugas {
        bigint id PK
        bigint assignment_id FK
        bigint mahasiswa_id FK
        text catatan
        varchar file_submission
        varchar url_submission
        boolean is_late
        decimal nilai
        text feedback_dosen
        bigint dinilai_oleh FK
        timestamp dinilai_at
        timestamp submitted_at
        timestamp created_at
    }

    forum_diskusi {
        bigint id PK
        bigint kelas_lms_id FK
        varchar judul
        text deskripsi
        boolean is_wajib
        timestamp created_at
    }

    thread_forum {
        bigint id PK
        bigint forum_id FK
        bigint user_id FK
        varchar judul
        text konten
        varchar file_lampiran
        boolean is_pinned
        integer total_reply
        timestamp created_at
        timestamp updated_at
    }

    reply_forum {
        bigint id PK
        bigint thread_id FK
        bigint parent_reply_id FK
        bigint user_id FK
        text konten
        varchar file_lampiran
        timestamp created_at
    }

    sync_nilai_lms {
        bigint id PK
        bigint kelas_lms_id FK
        bigint nilai_mahasiswa_id FK
        decimal nilai_harian_lms
        enum status "pending|synced|failed"
        text error_message
        timestamp synced_at
        timestamp created_at
    }

    kelas_lms ||--o{ modul_pembelajaran : "berisi"
    modul_pembelajaran ||--o{ materi_pembelajaran : "memiliki materi"
    kelas_lms ||--o{ kuis_ujian : "memiliki kuis"
    bank_soal ||--o{ soal : "berisi"
    bank_soal ||--o{ kuis_ujian : "digunakan pada"
    soal ||--o{ pilihan_jawaban : "memiliki pilihan"
    kuis_ujian ||--o{ soal_kuis : "berisi soal"
    soal ||--o{ soal_kuis : "dimasukkan ke"
    kuis_ujian ||--o{ attempt_kuis : "dikerjakan"
    attempt_kuis ||--o{ jawaban_attempt : "berisi"
    kelas_lms ||--o{ assignment : "memiliki tugas"
    assignment ||--o{ submission_tugas : "dikumpulkan"
    kelas_lms ||--o{ forum_diskusi : "memiliki forum"
    forum_diskusi ||--o{ thread_forum : "memiliki thread"
    thread_forum ||--o{ reply_forum : "dibalas"
    kelas_lms ||--o{ sync_nilai_lms : "mensinkronkan"
```

---

## 🏢 MODUL 8: SINAPRA (Sarana & Prasarana)

```mermaid
erDiagram
    gedung {
        bigint id PK
        varchar kode UK
        varchar nama
        integer jumlah_lantai
        text alamat
        integer tahun_bangun
        decimal luas_m2
        enum status "aktif|renovasi|tidak_aktif"
        timestamp created_at
        timestamp updated_at
    }

    ruangan {
        bigint id PK
        bigint gedung_id FK
        varchar kode UK
        varchar nama
        integer lantai
        enum tipe "kelas|lab|aula|kantor|gudang|toilet|lainnya"
        integer kapasitas
        boolean ada_ac
        boolean ada_proyektor
        boolean ada_wifi
        enum status "aktif|maintenance|tidak_aktif"
        timestamp created_at
        timestamp updated_at
    }

    kategori_aset {
        bigint id PK
        bigint induk_id FK
        varchar kode UK
        varchar nama
        integer masa_manfaat_tahun
        decimal tarif_penyusutan_persen
        timestamp created_at
    }

    aset {
        bigint id PK
        bigint kategori_id FK
        bigint ruangan_id FK
        varchar kode_aset UK
        varchar nama
        varchar merk
        varchar model
        varchar serial_number
        date tanggal_perolehan
        decimal harga_perolehan
        decimal nilai_buku
        enum kondisi "baik|rusak_ringan|rusak_berat|hilang"
        enum status "tersedia|dipinjam|maintenance|dihapus"
        timestamp created_at
        timestamp updated_at
    }

    peminjaman_ruangan {
        bigint id PK
        bigint ruangan_id FK
        bigint user_id FK
        varchar keperluan
        date tanggal
        time jam_mulai
        time jam_selesai
        enum status "pending|disetujui|ditolak|selesai|dibatalkan"
        bigint disetujui_oleh FK
        text catatan_penolakan
        timestamp created_at
        timestamp updated_at
    }

    peminjaman_aset {
        bigint id PK
        bigint aset_id FK
        bigint user_id FK
        varchar keperluan
        date tanggal_pinjam
        date tanggal_kembali_rencana
        date tanggal_kembali_aktual
        enum kondisi_kembali "baik|rusak_ringan|rusak_berat|hilang"
        enum status "pending|dipinjam|kembali|terlambat"
        bigint disetujui_oleh FK
        timestamp created_at
        timestamp updated_at
    }

    maintenance_log {
        bigint id PK
        bigint aset_id FK
        bigint ruangan_id FK
        varchar judul
        text deskripsi_kerusakan
        enum prioritas "rendah|sedang|tinggi|darurat"
        date tanggal_lapor
        date tanggal_mulai
        date tanggal_selesai
        decimal biaya
        text hasil_perbaikan
        enum status "dilaporkan|dijadwalkan|dalam_proses|selesai"
        bigint teknisi_id FK
        timestamp created_at
        timestamp updated_at
    }

    pengajuan_pengadaan {
        bigint id PK
        bigint unit_kerja_id FK
        bigint diajukan_oleh FK
        varchar judul
        text alasan_kebutuhan
        date tanggal_pengajuan
        decimal estimasi_anggaran
        enum status "draft|diajukan|disetujui|ditolak|proses_pengadaan|selesai"
        bigint disetujui_oleh FK
        timestamp created_at
        timestamp updated_at
    }

    detail_pengadaan {
        bigint id PK
        bigint pengajuan_id FK
        bigint kategori_aset_id FK
        varchar nama_barang
        varchar spesifikasi
        integer jumlah
        varchar satuan
        decimal harga_satuan_estimasi
        decimal total_estimasi
        timestamp created_at
    }

    gedung ||--o{ ruangan : "memiliki"
    kategori_aset ||--o{ aset : "mengkategorikan"
    ruangan ||--o{ aset : "menampung"
    ruangan ||--o{ peminjaman_ruangan : "dipinjam"
    aset ||--o{ peminjaman_aset : "dipinjam"
    aset ||--o{ maintenance_log : "dirawat"
    ruangan ||--o{ maintenance_log : "dirawat"
    pengajuan_pengadaan ||--o{ detail_pengadaan : "merinci"
```

---

## 🤝 MODUL 9: KERJASAMA (Kemitraan)

```mermaid
erDiagram
    mitra {
        bigint id PK
        varchar nama
        enum tipe "dudi|kampus|pemerintah|lsm|internasional"
        varchar negara
        varchar kota
        text alamat
        varchar website
        varchar kontak_person
        varchar telepon
        varchar email
        text profil
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    dokumen_kerjasama {
        bigint id PK
        bigint mitra_id FK
        varchar nomor_dokumen UK
        varchar judul
        enum tipe "mou|moa|ia|perjanjian_lainnya"
        text ruang_lingkup
        date tanggal_ttd
        date berlaku_mulai
        date berlaku_sampai
        integer durasi_bulan
        enum status "draft|aktif|kadaluarsa|dihentikan|diperpanjang"
        varchar file_dokumen
        bigint ditandatangani_oleh FK
        timestamp created_at
        timestamp updated_at
    }

    perpanjangan_kerjasama {
        bigint id PK
        bigint dokumen_id FK
        bigint dokumen_baru_id FK
        date tanggal_perpanjangan
        date berlaku_sampai_baru
        text catatan
        timestamp created_at
    }

    implementation_arrangement {
        bigint id PK
        bigint dokumen_id FK
        varchar nomor UK
        varchar judul
        text deskripsi_program
        date tanggal_mulai
        date tanggal_selesai
        bigint penanggung_jawab_id FK
        enum status "aktif|selesai|dibatalkan"
        timestamp created_at
        timestamp updated_at
    }

    kegiatan_kerjasama {
        bigint id PK
        bigint arrangement_id FK
        varchar nama_kegiatan
        text deskripsi
        date tanggal_kegiatan
        varchar lokasi
        integer jumlah_peserta
        text hasil
        varchar file_dokumentasi
        timestamp created_at
    }

    output_kerjasama {
        bigint id PK
        bigint dokumen_id FK
        enum tipe "penelitian|publikasi|pengabdian|magang|beasiswa|lainnya"
        varchar judul
        text deskripsi
        date tanggal
        varchar file_bukti
        timestamp created_at
    }

    mitra ||--o{ dokumen_kerjasama : "memiliki dokumen"
    dokumen_kerjasama ||--o{ perpanjangan_kerjasama : "diperpanjang"
    dokumen_kerjasama ||--o{ implementation_arrangement : "diimplementasi"
    implementation_arrangement ||--o{ kegiatan_kerjasama : "menghasilkan kegiatan"
    dokumen_kerjasama ||--o{ output_kerjasama : "menghasilkan output"
```

---

## ✅ MODUL 10: UPM (Unit Penjaminan Mutu & SPMI)

```mermaid
erDiagram
    kategori_dokumen_mutu {
        bigint id PK
        varchar kode UK
        varchar nama
        text deskripsi
        timestamp created_at
    }

    dokumen_mutu {
        bigint id PK
        bigint kategori_id FK
        bigint unit_kerja_id FK
        varchar kode_dokumen UK
        varchar judul
        integer versi
        text deskripsi
        varchar file_path
        enum status "draft|review|berlaku|kadaluarsa|dicabut"
        bigint disahkan_oleh FK
        date tanggal_berlaku
        date tanggal_kadaluarsa
        timestamp created_at
        timestamp updated_at
    }

    revisi_dokumen_mutu {
        bigint id PK
        bigint dokumen_id FK
        integer versi_lama
        integer versi_baru
        text ringkasan_perubahan
        varchar file_lama
        bigint direvisi_oleh FK
        timestamp created_at
    }

    kuesioner_edom {
        bigint id PK
        bigint tahun_akademik_id FK
        varchar judul
        text deskripsi
        date periode_mulai
        date periode_selesai
        boolean is_active
        timestamp created_at
    }

    pertanyaan_edom {
        bigint id PK
        bigint kuesioner_id FK
        text pertanyaan
        enum tipe "skala|pilihan|text"
        json opsi
        integer bobot
        integer urutan
        timestamp created_at
    }

    jawaban_edom {
        bigint id PK
        bigint kuesioner_id FK
        bigint pertanyaan_id FK
        bigint kelas_id FK
        bigint mahasiswa_id FK
        text jawaban
        integer nilai_skala
        timestamp created_at
    }

    rekap_edom {
        bigint id PK
        bigint kuesioner_id FK
        bigint kelas_id FK
        bigint dosen_id FK
        decimal rata_rata_nilai
        integer total_responden
        integer total_mahasiswa
        decimal persentase_respon
        enum kategori "sangat_baik|baik|cukup|kurang"
        timestamp created_at
        timestamp updated_at
    }

    audit_mutu_internal {
        bigint id PK
        bigint unit_kerja_id FK
        bigint tahun_akademik_id FK
        varchar nomor_ami
        varchar judul
        date tanggal_audit
        bigint ketua_auditor_id FK
        enum status "perencanaan|pelaksanaan|pelaporan|tindak_lanjut|selesai"
        varchar file_instrumen
        varchar file_laporan
        timestamp created_at
        timestamp updated_at
    }

    tim_auditor_ami {
        bigint id PK
        bigint ami_id FK
        bigint pegawai_id FK
        enum peran "ketua|anggota|pengamat"
        timestamp created_at
    }

    temuan_ami {
        bigint id PK
        bigint ami_id FK
        enum tipe "kts_mayor|kts_minor|observasi|peluang_perbaikan"
        text uraian_temuan
        text standar_acuan
        text bukti_temuan
        bigint ditemukan_oleh FK
        timestamp created_at
    }

    tindak_lanjut_ami {
        bigint id PK
        bigint temuan_id FK
        text rencana_tindak_lanjut
        date target_selesai
        bigint penanggung_jawab_id FK
        text realisasi
        date tanggal_realisasi
        varchar file_bukti
        enum status "open|in_progress|closed|verifikasi"
        bigint diverifikasi_oleh FK
        timestamp created_at
        timestamp updated_at
    }

    indikator_kinerja_utama {
        bigint id PK
        varchar kode UK
        varchar nama
        text deskripsi
        enum perspektif "iku1|iku2|iku3|iku4|iku5|iku6|iku7|iku8"
        varchar satuan
        decimal target_nasional
        boolean is_active
        timestamp created_at
    }

    nilai_iku {
        bigint id PK
        bigint iku_id FK
        bigint program_studi_id FK
        bigint tahun_akademik_id FK
        decimal nilai_target
        decimal nilai_capaian
        decimal persentase_capaian
        text keterangan
        varchar file_bukti
        bigint diinput_oleh FK
        timestamp created_at
        timestamp updated_at
    }

    dokumen_akreditasi {
        bigint id PK
        bigint program_studi_id FK
        enum lembaga "ban_pt|lamdik|lam_lainnya"
        varchar nama_instrumen
        varchar kode_instrumen
        date tanggal_pengajuan
        date tanggal_visitasi
        date tanggal_keputusan
        varchar nomor_sk
        varchar peringkat_lama
        varchar peringkat_baru
        date berlaku_sampai
        varchar file_led
        varchar file_lkps
        varchar file_sk_akreditasi
        enum status "persiapan|diajukan|visitasi|keputusan|selesai"
        timestamp created_at
        timestamp updated_at
    }

    kategori_dokumen_mutu ||--o{ dokumen_mutu : "mengkategorikan"
    dokumen_mutu ||--o{ revisi_dokumen_mutu : "direvisi"
    kuesioner_edom ||--o{ pertanyaan_edom : "berisi"
    kuesioner_edom ||--o{ jawaban_edom : "menerima jawaban"
    pertanyaan_edom ||--o{ jawaban_edom : "dijawab"
    kuesioner_edom ||--o{ rekap_edom : "direkap"
    audit_mutu_internal ||--o{ tim_auditor_ami : "melibatkan"
    audit_mutu_internal ||--o{ temuan_ami : "menghasilkan"
    temuan_ami ||--o{ tindak_lanjut_ami : "ditindaklanjuti"
    indikator_kinerja_utama ||--o{ nilai_iku : "dicapai oleh"
    program_studi ||--o{ nilai_iku : "memiliki"
    program_studi ||--o{ dokumen_akreditasi : "memiliki"
```

---

## 🏗️ Arsitektur & Strategi (Part 3)

### BKD Integration Guard
| Field | Constraint |
|---|---|
| `beban_kerja_dosen.(dosen_id, tahun_akademik_id, semester)` | **UNIQUE** — satu laporan per dosen per semester |
| `sks_pengajaran` | Dihitung otomatis dari `dosen_pengampu` join `kelas.total_sks` |
| `memenuhi_bkd` | Computed: `total_sks BETWEEN sks_minimum AND sks_maksimum` |

### LMS ↔ SIAKAD Sync Guard
- `sync_nilai_lms.status = 'pending'` → job queue memproses dan update `nilai_mahasiswa.nilai_harian`
- Idempotent sync: jika sudah `synced`, tidak di-overwrite kecuali ada flag `force_sync`

### EDOM Anonymity Guard
- `jawaban_edom` menyimpan `mahasiswa_id` untuk mencegah double submission
- Rekap hanya diakses via `rekap_edom` (aggregated view) — detail jawaban hanya bisa diakses Penjaminan Mutu

> **Lanjut ke Part 4**: Master ERD Overview + Integration Map + Full Architecture Notes
