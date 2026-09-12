# Panduan Operasional SIKEU (Sistem Informasi Keuangan Kampus)

> **Versi**: 1.0  
> **Terakhir Diperbarui**: 10 September 2026  
> **Modul**: SIKEU — Sistem Informasi Keuangan, Akuntansi & Pajak  
> **Proyek**: RAG (Sistem Informasi Terpadu Kampus)

---

## 1. Daftar Akun Test & Cara Login

Gunakan akun berikut untuk mengakses modul SIKEU saat pengujian:

| Email | Username | Password | Role | Akses Utama |
|---|---|---|---|---|
| `kasir.sikeu@kampus.ac.id` | `kasir_sikeu` | `password` | Operator SIKEU | Master, Tagihan, Pembayaran, Piutang, Dispensasi |
| `kabag.keuangan@kampus.ac.id` | `kabag_keuangan` | `password` | Kabag Keuangan | Semua menu + Kas Utama + Tutup Buku |
| `pimpinan@kampus.ac.id` | `pimpinan` | `password` | Pimpinan | Approval Tagihan & Dispensasi |
| `mahasiswa.test@kampus.ac.id` | `mahasiswa_test` | `password` | Mahasiswa | Portal Tagihan Mandiri + Cetak Invoice |
| `superadmin@kampus.ac.id` | `superadmin` | `password` | Super Admin | Seluruh akses tanpa batas |

**Cara Login**:
1. Buka halaman SSO Campus: `http://localhost:3000`
2. Masukkan email/username + password
3. Setelah login, navigasi ke menu **SIKEU** di sidebar

---

## 2. Panduan Setting Master Biaya

Master Biaya mendefinisikan **jenis-jenis komponen biaya** yang berlaku di seluruh sistem (SPP, Praktikum, Wisuda, dll.).

### Langkah-langkah:
1. Buka **`/sikeu/master`** → Klik tab **"Jenis Biaya"**
2. Klik **"+ Tambah Jenis Biaya"**
3. Isi formulir:
   - **Kode** (unik, contoh: `UKT_REG`, `PRAK_TI`, `WISUDA_FEE`)
   - **Nama** (contoh: "Uang Kuliah Tunggal Reguler")
   - **Tipe**: `spp`, `sks`, `praktikum`, `wisuda`, `spmb_adm`, `lainnya`
   - **Nominal Standar** (opsional, nilai default)
   - **Delegasi Modul**: Centang modul mana saja yang menggunakan biaya ini (SIKEU, SIAKAD, SPMB)
4. Klik **"Simpan"**

> ⚠️ **Penting**: Master biaya yang sudah digunakan dalam tagihan **tidak dapat dihapus**. Anda hanya bisa menonaktifkannya.

---

## 3. Panduan Setting Tarif per Angkatan/Prodi/Semester

Setting Tarif menentukan **berapa nominal biaya** yang dikenakan untuk kombinasi tertentu (Angkatan + Prodi + Semester + Jalur Kelas).

### Langkah-langkah:
1. Buka **`/sikeu/master`** → Klik tab **"Setting Tarif"**
2. Klik **"+ Tambah Setting Tarif"**
3. Isi formulir:
   - **Jenis Biaya**: Pilih dari master biaya (contoh: UKT Reguler)
   - **Tahun Angkatan**: `2025`
   - **Program Studi**: Teknik Informatika (opsional, kosongkan = berlaku semua prodi)
   - **Semester**: `3` (opsional, kosongkan = berlaku semua semester)
   - **Jalur Kelas**: `Reguler` / `Karyawan` / `Internasional`
   - **Nominal**: `3.500.000`
   - **Keterangan**: (opsional)
4. Klik **"Simpan"**

### Contoh Konfigurasi:

| Jenis Biaya | Angkatan | Prodi | Semester | Jalur | Nominal |
|---|---|---|---|---|---|
| UKT Reguler | 2025 | Teknik Informatika | - | Reguler | Rp 3.500.000 |
| UKT Reguler | 2025 | Teknik Informatika | - | Karyawan | Rp 5.500.000 |
| Praktikum | 2025 | Teknik Informatika | 3 | Reguler | Rp 750.000 |
| UKT Reguler | 2024 | Manajemen | - | Reguler | Rp 3.000.000 |

> ⚠️ **Kombinasi unik**: Satu jenis biaya + angkatan + prodi + semester + jalur hanya boleh ada satu record. Sistem akan menolak duplikat.

---

## 4. Panduan Generate Tagihan Masal & Paket Semester ("Wajib Bayar")

Tagihan masal digunakan untuk menerbitkan tagihan semester sekaligus untuk seluruh mahasiswa dalam satu angkatan/jalur berdasarkan paket komponen biaya yang telah ditetapkan.

### Prasyarat:
- ✅ **Setting Tarif** sudah dikonfigurasi untuk Angkatan, Semester (1-8), dan Jalur Kelas target di menu Master.
- ✅ **Tipe Tagihan Mahasiswa** sudah tersinkron dari SPMB/SIAKAD (dapat diklik tombol *"Sinkronisasi SIAKAD/SPMB"* di tab Student Types).

### Langkah-langkah:
1. Buka **`/sikeu/tagihan`**
2. Klik tombol **"Aktifkan Tagihan Masal"** (ikon Sparkles ✨)
3. Isi formulir:
   - **Target Angkatan**: `2024` / `2025`
   - **Target Jalur Kelas**: `Reguler` / `Karyawan` / `Internasional`
   - **Target Program Studi**: Pilih prodi spesifik atau kosongkan untuk semua prodi
   - **Semester Aktif**: `Semester Ganjil 2026/2027`
   - **Batas Jatuh Tempo**: `2026-08-31`
4. Klik **"Terbitkan Tagihan Masal"**
5. Sistem akan:
   - Mengambil seluruh paket setting tarif yang cocok (contoh Semester 5: UKT 5 + Uji Kompetensi + Biaya Magang).
   - Mengecek apakah mahasiswa memiliki beasiswa aktif di `sikeu_mahasiswa_beasiswa`, lalu otomatis memotong nominal tagihan secara proporsional.
   - Menerbitkan Nomor Virtual Account Bank BNI otomatis (`88012` + NIM) untuk setiap tagihan.
   - Melewati mahasiswa yang tagihannya sudah pernah diterbitkan (mencegah duplikasi invoice).

---

## 5. Panduan Pembayaran Kasir / Loket (Pelunasan Multi-Bill)

Menu ini digunakan oleh **Kasir Kampus** untuk memproses pembayaran langsung di loket (offline).

### Langkah-langkah:
1. Buka **`/sikeu/tagihan/create`**
2. **Step 1 — Cari Mahasiswa**: Ketik NIM atau nama mahasiswa di kolom pencarian
3. Pilih mahasiswa dari dropdown autocomplete
4. **Step 2 — Pilih Komponen Tagihan**:
   - Sistem akan menampilkan seluruh daftar tagihan tertunggak dari mahasiswa tersebut.
   - Kasir dapat mencentang **satu atau lebih tagihan** (misal: UKT + Uji Kompetensi) untuk dilunasi sekaligus.
   - Total tagihan gabungan terhitung otomatis secara real-time.
   - Kasir dapat menginput potongan/keringanan tambahan khusus kasir jika ada memo pimpinan.
5. **Step 3 — Metode Pembayaran**:
   - **Virtual Account BNI**: Terbitkan nomor VA untuk transfer ATM/Mobile Banking.
   - **Bayar Tunai Loket Kasir**: Pelunasan tunai langsung di tempat.
6. Isi **Catatan Transaksi** (opsional)
7. Klik **"Proses Pembayaran Loket"**
8. Setelah berhasil:
   - Sistem mengalokasikan pembayaran ke seluruh tagihan terpilih secara proporsional tanpa error kelebihan sisa.
   - Mengupdate status tagihan menjadi `lunas` atau `sebagian`.
   - Men-generate jurnal akuntansi otomatis (Debet Kas / Kredit Pendapatan).
   - Menampilkan kuitansi resmi lengkap dengan rincian seluruh komponen yang dilunasi.
   - Klik **"Cetak Kuitansi"** untuk mencetak bukti kuitansi loket.

### Koreksi Pembayaran:
Jika terjadi salah input, gunakan fitur **Koreksi Transaksi**:
1. Buka halaman **Riwayat Pembayaran** (`/sikeu/pembayaran`)
2. Cari transaksi yang ingin dikoreksi
3. Klik **"Koreksi"**
4. Isi alasan koreksi (min. 10 karakter)
5. Sistem akan:
   - Membalik status pembayaran ke `reversed`
   - Mengurangi total bayar pada tagihan
   - Membuat jurnal koreksi pembalik

---

## 6. Panduan Penerbitan Virtual Account

### Alur Otomatis (Saat Tagihan Dibuat):
- Jika tagihan **tidak memerlukan approval** (`requires_approval = false`), VA langsung ter-generate otomatis saat tagihan diterbitkan
- Terhubung dengan **Xendit API** (jika konfigurasi Payment Gateway aktif)

### Alur Manual (Kasir):
1. Buka **`/sikeu/tagihan/create`**
2. Pilih mahasiswa → Pilih komponen → Pilih **"Virtual Account BNI"**
3. Klik **"Terbitkan Nomor VA"**
4. Salin nomor VA yang ditampilkan dan berikan ke mahasiswa

### Portal Mandiri Mahasiswa:
1. Mahasiswa login dengan akun sendiri
2. Buka **`/sikeu/mahasiswa/tagihan`**
3. Lihat tagihan → Klik **"Cetak Invoice & VA"**
4. Invoice resmi dengan Nomor VA akan tampil dan bisa dicetak

---

## 7. Panduan Dispensasi Pembayaran & Bypass Lock SIAKAD

Dispensasi diberikan kepada mahasiswa yang belum bisa melunasi tagihan tepat waktu namun memerlukan izin untuk pengisian KRS di SIAKAD.

### Pengajuan:
1. Buka **`/sikeu/dispensasi`**
2. Klik **"Pengajuan Dispensasi Baru"**
3. Cari mahasiswa (NIM/Nama)
4. ⚠️ **Validasi Tunggakan Lama**: Sistem secara otomatis mengecek dan menampilkan peringatan jika mahasiswa masih memiliki riwayat tunggakan dispensasi sebelumnya yang belum dilunasi.
5. Isi formulir:
   - **Tipe Dispensasi**: Penundaan Tanggal Jatuh Tempo / Skema Pembayaran Cicilan / Permohonan Keringanan Khusus
   - **Batas Tanggal Jatuh Tempo Baru**: Tanggal perpanjangan
   - **Nominal Per Cicilan**: Besaran nominal cicilan yang disepakati
   - **Bypass KRS SIAKAD**: Centang *"Izinkan Pengisian KRS di SIAKAD"* agar kunci akademik mahasiswa dibuka otomatis di sistem SIAKAD
   - **Alasan**: Deskripsi kendala finansial/pertimbangan dispensasi
6. Klik **"Kirim Pengajuan"**

### Approval oleh Pimpinan:
1. Login sebagai **pimpinan** / **kabag_keuangan**
2. Buka **`/sikeu/approval`**
3. Lihat daftar dispensasi pending
4. Klik **"Approve"** atau **"Reject"** + catatan

### Cetak Surat Keterangan Dispensasi Resmi:
- Di halaman `/sikeu/dispensasi`, klik **"Lihat & Cetak"**
- Sistem menampilkan **Surat Keterangan Dispensasi Resmi** lengkap dengan Kop Universitas, Nomor Surat, Rincian Tangguhan, Klausul Bypass Lock SIAKAD, dan Tanda Tangan Digital Pejabat Keuangan.

---

## 8. Panduan Piutang & Export Laporan Excel (.XLS / .XLSX)

1. Buka **`/sikeu/piutang`**
2. Gunakan filter:
   - **Angkatan**: Filter per tahun angkatan (2023, 2024, 2025, 2026)
   - **Program Studi**: Filter per program studi
   - **Cutoff Date**: Filter rincian tagihan dan pembayaran hingga tanggal cutoff tertentu
   - **Status**: Piutang (belum lunas), Belum Bayar, Sebagian, Dispensasi, Lunas, Semua
   - **Pencarian**: NIM atau nama mahasiswa
3. Lihat ringkasan KPI di atas tabel:
   - Total Tagihan, Total Potongan, Total Denda, Total Bayar, Total Sisa Piutang
   - Total Mahasiswa Tunggakan
4. Klik **"Download Excel"** untuk mengunduh laporan dalam format **Spreadsheet Excel terformat rapi (.xls)** lengkap dengan border, header korporat, format mata uang standar akuntansi, dan baris summary total.

---

## 9. Panduan Tutup Buku Periode Akuntansi

Tutup buku mengunci periode akuntansi sehingga transaksi pada periode tersebut **tidak bisa diinput atau dikoreksi** lagi.

### Langkah-langkah:
1. Buka **`/sikeu/akuntansi`**
2. Navigasi ke tab/menu **Periode Akuntansi**
3. Cari periode yang ingin ditutup (contoh: "Periode Agustus 2026")
4. Ubah status dari **Terbuka** ke **Ditutup**
5. Konfirmasi tutup buku

### Dampak:
- ❌ Pembayaran kasir dengan tanggal dalam periode tersebut akan **ditolak**
- ❌ Koreksi pembayaran dalam periode tersebut akan **ditolak**
- ✅ Laporan keuangan periode tersebut sudah **final**

---

## 10. Peta Menu SIKEU per Role

| Menu / Halaman | Operator SIKEU | Kabag Keuangan | Pimpinan | Mahasiswa |
|---|:---:|:---:|:---:|:---:|
| Dashboard SIKEU | ✅ | ✅ | ✅ | ❌ |
| Master Biaya & Gaji | ✅ | ✅ | ❌ | ❌ |
| Setting Tarif & Matriks Semester | ✅ | ✅ | ❌ | ❌ |
| Jalur Kelas & Beasiswa | ✅ | ✅ | ❌ | ❌ |
| Student Billing Types & Sync | ✅ | ✅ | ❌ | ❌ |
| Tarif SPMB | ✅ | ✅ | ❌ | ❌ |
| Unit Kas & Mutasi | ❌ | ✅ | ❌ | ❌ |
| Set Tagihan & Invoice | ✅ | ✅ | ❌ | ❌ |
| Generate Tagihan Masal | ✅ | ✅ | ❌ | ❌ |
| Pembayaran Kasir Loket (Multi-Bill) | ✅ | ✅ | ❌ | ❌ |
| Riwayat Pembayaran & Koreksi | ✅ | ✅ | ❌ | ❌ |
| Piutang Mahasiswa & Cutoff | ✅ | ✅ | ❌ | ❌ |
| Export Piutang Excel (.xls) | ✅ | ✅ | ❌ | ❌ |
| Dispensasi & Bypass KRS SIAKAD | ✅ | ✅ | ❌ | ❌ |
| Approval Tagihan & Dispensasi | ❌ | ⚠️ | ✅ | ❌ |
| Akuntansi (COA, Jurnal, Buku Besar) | ❌ | ✅ | ❌ | ❌ |
| Pemasukan Kampus | ❌ | ✅ | ❌ | ❌ |
| Pengeluaran Kampus | ❌ | ✅ | ❌ | ❌ |
| Pajak Kampus | ❌ | ✅ | ❌ | ❌ |
| Payment Gateway (Xendit) | ❌ | ✅ | ❌ | ❌ |
| Kas Kabag Keuangan | ❌ | ✅ | ❌ | ❌ |
| Portal Tagihan Mandiri | ❌ | ❌ | ❌ | ✅ |
| Cetak Invoice & VA | ❌ | ❌ | ❌ | ✅ |

> ⚠️ = Akses terbatas / kondisional (bisa review tapi approval utama di Pimpinan)

---

## 11. FAQ & Troubleshooting

### Q1: Kenapa tidak bisa input pembayaran di kasir?
**A**: Pastikan periode akuntansi untuk bulan berjalan berstatus **Terbuka**. Cek di `/sikeu/akuntansi` → Periode. Jika sudah ditutup, hubungi Kabag Keuangan untuk membuka kembali.

### Q2: Mahasiswa tidak bisa mengisi KRS di SIAKAD, kenapa?
**A**: Sistem SIAKAD melakukan pengecekan ke SIKEU. Mahasiswa yang memiliki tunggakan tidak dapat mengisi KRS kecuali memiliki surat dispensasi aktif dengan opsi *"Bypass KRS SIAKAD"* yang telah disetujui. Cek di `/sikeu/dispensasi` atau `/sikeu/piutang`.

### Q3: Bagaimana kasir melunasi lebih dari 1 tagihan mahasiswa sekaligus?
**A**: Buka `/sikeu/tagihan/create`, cari mahasiswa, centang seluruh tagihan yang ingin dilunasi di Step 2, lalu pilih metode Tunai Loket Kasir dan klik *"Proses Pembayaran Loket"*. Sistem akan secara otomatis membagi pembayaran ke seluruh tagihan terpilih dan mencetak kuitansi gabungan.

### Q4: Apakah admin keuangan harus input tipe tagihan mahasiswa satu per satu?
**A**: Tidak. Mahasiswa baru yang lulus SPMB otomatis tersinkron ke SIKEU saat konversi mahasiswa baru. Anda juga dapat menekan tombol *"Sinkronisasi SIAKAD / SPMB"* di menu Master → Tab Student Types untuk menyinkronkan seluruh mahasiswa secara masal.

### Q5: Bagaimana cara menerapkan beasiswa mahasiswa?
**A**: Buka `/sikeu/master` → Tab "Beasiswa" → Daftarkan program beasiswa, lalu mapping ke mahasiswa di tab "Mapping Beasiswa". Saat tagihan masal diterbitkan, sistem otomatis memotong total tagihan mahasiswa sesuai nilai beasiswa.

### Q6: Bagaimana cara mengekspor piutang mahasiswa dalam format spreadsheet Excel?
**A**: Buka `/sikeu/piutang` → Atur filter (Angkatan, Prodi, Cutoff Tanggal, Status) → Klik tombol **"Download Excel"**. File `.xls` berformat spreadsheet Excel profesional akan otomatis terunduh.
