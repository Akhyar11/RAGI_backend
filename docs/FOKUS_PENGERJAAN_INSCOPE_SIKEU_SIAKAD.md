# FOKUS PENGERJAAN IN-SCOPE
## Modul SIKEU (Sistem Informasi Keuangan) & SIAKAD (Sistem Informasi Akademik)

Dokumen ini merinci fokus pengerjaan (*In-Scope*) dua modul inti Sistem RAGI mengikuti format cakupan modul SIMPEG, dan menjadi rujukan pengerjaan sejalan dengan **Lampiran I (Scope of Work)** pada `KONTRAK_PKS_RAGI_POLITEKNIK_INDONUSA.md`.

---

# I. MODUL SIKEU — KEUANGAN KAMPUS

### Manajemen Master & Tarif Keuangan
- Master biaya dan jenis biaya (katalog tagihan dinamis, tanpa hardcode).
- Master tarif UKT/SPP dengan parameter `tahun_angkatan` dan `jalur_kelas` (Reguler, Karyawan, Internasional), termasuk skema golongan UKT.
- Master tarif SPMB per jalur dan gelombang beserta endpoint tarif *real-time* untuk integrasi SPMB.
- Setting tarif per angkatan, prodi, dan semester (multi-tarif).
- Master beasiswa, penerima beasiswa, dan potongan khusus mahasiswa.
- Master pendukung: unit kas, jalur kelas, gaji pegawai & transport, referensi dan tipe referensi.
- Penetapan tipe tagihan mahasiswa lengkap dengan catatan perubahan dan sinkronisasi mahasiswa dari SIAKAD/SPMB.

### Manajemen Tagihan Mahasiswa
- Pembuatan tagihan massal semester dan tagihan per mahasiswa beserta potongan ad-hoc.
- Approval tagihan bertingkat oleh pimpinan (multi-tahap).
- Portal tagihan mahasiswa: rincian tagihan berjalan, Surat Invoice resmi bernomor Virtual Account (BNI/Mandiri/BRI), riwayat pembayaran, dan cetak massal.
- Penyesuaian keuangan akibat perubahan prodi/jenis pendaftaran (dampak, approve, apply, histori audit).

### Manajemen Pembayaran
- Pembayaran kasir offline beserta koreksi pembayaran dan alur approve/reject manual.
- Pembayaran online: *pay-bills*, Virtual Account/H2H, konfigurasi *payment gateway* (Xendit, cek saldo, webhook/callback, simulasi lokal).
- Upload bukti pembayaran manual dan verifikasi bukti oleh petugas.
- Koreksi alokasi pembayaran antar tagihan (draft → simulate → approve → apply).
- Piutang mahasiswa beserta ekspor Excel.

### Dispensasi & Pengajuan Keuangan
- Pengajuan dispensasi pembayaran: pencarian autocomplete NIM/Nama, pengecekan otomatis dispensasi belum dilunasi, approval pimpinan, cetak bukti resmi dengan *digital signature* pejabat, dan validasi QR publik.
- Pengajuan kas unit dengan workflow approve/reject.
- Kas kecil (*petty cash*): transaksi, pengajuan approve/reject, dan scoping PJ kas kecil.
- Pengajuan operasional & panjar SIMPEG: antrean terpadu, batch gaji, pencairan, LPJ, reimburse, dan tutup LPJ.

### Pemasukan, Pengeluaran & Pajak
- Pemasukan kampus eksternal (hibah SIPPM, donatur, kerjasama).
- Pengeluaran kampus, vendor, dan referral pencairan (approve → cairkan).
- UMK (Usaha Milik Kampus) beserta workflow status.
- Pajak kampus (PPh 21/23, PPN 11%) dan pencatatan setor NTPN.

### Akuntansi & Pembukuan (basis akrual)
- Bagan Akun (CoA) beserta konvensi prefix akun; kode baru ditambah melalui migrasi.
- Jurnal otomatis (penerbitan tagihan, pembayaran, koreksi, pembatalan, potongan, dispensasi memo) wajib melalui `JurnalSikeuService`, dengan nomor prefix yang dapat diatur via menu Pengaturan.
- Buku besar, laporan keuangan (neraca/laba-rugi/arus kas) yang dihitung dari buku jurnal *posted*, bukan tabel operasional.
- Tutup periode (jurnal `JRN-TUTUP` → ekuitas) dengan penguncian rentang tanggal; edit/hapus jurnal manual di luar periode tertutup dan koreksi jurnal otomatis via reversal.
- Tutup kas harian beserta guard efek.

### Laporan & Dashboard
- Laporan bendahara: saldo harian/bulanan, periodik, per-akun, rekap mahasiswa, rekap per prodi/kelas, dan ekspor Excel.
- Dashboard *executive summary* dengan *live balance* dan role-scoping petugas.

### Integrasi Antar-Modul
- Penerimaan penggajian batch dari SIMPEG (`payroll/submit-to-sikeu`) beserta jurnal penggajian otomatis.
- Integrasi tagihan calon mahasiswa dari SPMB (callback) dan pencairan dana SIPPM.
- Kompatibilitas legacy SIAKAD/SPMB: ringkasan pembayaran, kelayakan ujian, status belum daftar ulang.
- Mendengar event perubahan prodi dari SIAKAD untuk penyesuaian tagihan.

### Kepatuhan & Dokumentasi
- Setiap aksi transaksi keuangan wajib mencatat `AuditLogService` modul `SIKEU`.
- Dokumentasi API `backend/docs/api/SIKEU/*.md` wajib diperbarui pada setiap perubahan endpoint.
- UI mengikuti standar SIKEU: form > 5 input pada halaman terpisah (`/create`, `/[id]/edit`), tombol aksi *outline* di kiri Filter, tanggal diformat `formatDate/formatDateTime` (id-ID).

---

# II. MODUL SIAKAD — AKADEMIK & OBE

### Master Data Akademik
- Master tahun akademik (set-active, mode penilaian), fakultas, dan program studi.
- Master kurikulum dan mata kuliah beserta prasyarat.
- Master skala nilai (*grading*), referensi, dan tipe referensi akademik.
- Master dosen dan pengaturan Admin Prodi (CRUD, impersonate, plotting role-menu Admin OBE).
- Master kelas perkuliahan beserta pemetaan mahasiswa dan referensi ruangan SINAPRA.

### Manajemen Mahasiswa & Civitas
- CRUD mahasiswa, generate/impor/ekspor NIM, buku induk, dan pembaruan status.
- Sinkronisasi mahasiswa dari SPMB dan sinkronisasi profil ke Feeder.
- Konversi mahasiswa (pindahan/masuk) dengan status workflow dan aksi bulk.
- Kelola beasiswa mahasiswa (BAAK).
- Biodata dan data dosen.

### KRS & Paket KRS
- Pengisian KRS: daftar kelas, *add/drop*, submit, approve, bulk-approve, reopen, dan monitoring per prodi.
- Paket KRS/Paket MK per rombel: penetapan MK oleh BAAK/Kaprodi, bulk-sync, hak akses per rombel, dan pengambilan paket mahasiswa.

### Perkuliahan & Absensi
- Kelas perkuliahan beserta CRUD pertemuan.
- Absensi per pertemuan dan rekap absensi per kelas.

### Nilai, KHS & Dokumen Akademik
- Input dan pembaruan nilai (satuan/bulk), transkrip, dan mode penilaian.
- KHS beserta *lock*/*bulk-lock*.
- Penerbitan dokumen resmi KHS & Transkrip dengan validasi QR publik.
- Kartu ujian UTS/UAS: cetak, monitoring, syarat lunas tagihan SIKEU, dan validasi QR publik.

### OBE — CPMK, RPS & Evaluasi
- CPL prodi, jenis CPL SN-DIKTI, CPMK/Sub-CPMK, dan pemetaan CPL–CPMK–MK (sinkronisasi).
- Profil lulusan, bahan kajian, matrix CPL–BK–MK, profesi/karir, rumpun MK, rubrik penilaian, dan distribusi mengajar.
- RPS berbasis OBE: referensi bentuk/metode/kriteria/komponen, sesi per RPS, submit/approve, duplikasi, penguncian edit dosen, dan cetak.
- Nilai per kelas: komponen nilai, sinkronisasi dari OBE, rekap XLSX, grafik ketercapaian CPL/CPMK, audit pemetaan, kepatuhan input nilai dosen, dan portofolio mahasiswa.
- Bank soal dan kategori soal.

### Administrasi Akademik
- Pindah prodi dengan workflow: analisis dampak, cek ketentuan keuangan, approve/reject/apply.
- Status akademik: cuti kuliah (workflow) dan log status.
- Kelulusan/Yudisium: *sensing*, cek syarat, dan penetapan kelulusan.
- Penempatan Dosen PA: assign bulk, auto-distribute, assign per kelas.

### Bimbingan PA (SIMPA)
- Daftar mahasiswa bimbingan, kehadiran, prestasi, mahasiswa berisiko, dan *alerts*.
- Catatan bimbingan, jadwal & booking konsultasi, laporan, dan laporan bulanan (cetak PDF).
- PKL/PL/magang, insiden beserta eskalasi/konseling, dan aktivitas per kelas.

### Sinkronisasi Feeder (Neo Feeder)
- Konfigurasi koneksi, manajemen token, pemicuan sinkronisasi, log, mapping, dan staging data.

### LMS (modul terpisah pada route `/v1/lms`)
- Kelas *overview*, materi beserta berkas, tugas beserta pengumpulan dan penilaian.
- Presensi token (*rotate*/*tutup-presensi*/izin), quiz (*start*, autosave, submit, percobaan, kolaborator dosen), dan tryout.
- Forum diskusi (topik/post), bank soal, impor materi antar kelas, rekap absensi & ketercapaian.

### Kepatuhan & Dokumentasi
- Dokumentasi API `backend/docs/api/SIAKAD/*.md` wajib diperbarui pada setiap perubahan endpoint.
- Akses modul mengikuti RBAC berbasis role & permission (tanpa atribut `user_type` statis).
- Tanggal pada UI wajib diformat `formatDate/formatDateTime` (id-ID).

---

# III. DI LUAR CAKUPAN (OUT-OF-SCOPE)

| Modul | Tidak Termasuk Pengerjaan |
|---|---|
| **SIKEU** | Sambungan langsung *host-to-host* (H2H) Core Banking yang membutuhkan lisensi perbankan khusus; rekonsiliasi mutasi bank otomatis tanpa API penyedia perbankan. |
| **SIAKAD** | Integrasi otomatis feeder PD-DIKTI secara mandiri tanpa kredensial resmi Neo Feeder; modul ujian CBT *online* berbasis pengawasan video/AI *proctoring*. |

> Setiap permintaan di luar butir *In-Scope* di atas dikenakan prosedur **Change Request (CR)** sesuai Pasal 5 kontrak.
