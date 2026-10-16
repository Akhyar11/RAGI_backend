# Temuan Audit & Paritas SIKEU (dikumpulkan, belum diproses)

> Dibuat: 2026-10-17 — dikumpulkan selama Fase 1–4, diproses setelah semua fase berakhir.

## T-01: COA `301.02` hilang dari seeder (test gagal, pre-existing)
- Bukti: `tests/Feature/SikeuAkuntansiTest.php:106` (`test_tutup_buku_mengunci_periode_dan_menutup_saldo`)
  memanggil `AkunKeuangan::where('kode_akun', '301.02')->first()->id` → null.
- Verifikasi: `grep 301.02 database/seeders` → tidak ada di seeder mana pun.
- Tidak terkait Fase 1–4 (tidak menyentuh COA/seeder).
- Status: **FIXED** — akun `301.02 Laba Ditahan (ekuitas/kredit)` ditambahkan ke
  `database/seeders/Sikeu/SikeuAkuntansiSeeder.php` (idempoten `updateOrCreate`).
  `SikeuAkuntansiTest` kini 6/6 hijau. Tanpa akun ini, `tutupPeriode` produksi
  juga akan melempar `Akun Laba Ditahan (301.02) belum dikonfigurasi.`

## T-02: Migrasi Fase 1 sempat mereferensi tabel `users` (sudah diperbaiki)
- Bukti: `database/migrations/2026_10_17_000001_create_sikeu_umk_table.php:20`,
  `..._000002_create_sikeu_tutup_kas_table.php:20` awalnya `constrained('users')`.
- Tabel user proyek ini `core_users` (`app/Models/User.php:32`).
- Status: **FIXED** di file migrasi (belum pernah migrate ke DB persisten; test `:memory:` hijau).
- Tindak lanjut: bila ada environment yang telanjur migrate file lama, buat migrasi
  perbaikan FK ke `core_users`.

## T-03: `directCashierPayment` tanpa guard periode global (sudah diperbaiki)
- Bukti: `app/Http/Controllers/Sikeu/PembayaranKasirController.php:1142` (sebelum Fase 4)
  langsung `DB::beginTransaction()` tanpa cek `PeriodeAkuntansi`.
- Status: **FIXED** — guard periode + tutup kas per unit ditambahkan di Fase 4.

## T-04: Endpoint legacy tanpa padanan 1:1 (disengaja, documented)
- `GET /foto/:no_pend`, `GET /log/file`, template cetak statis `public/*.html` legacy
  tidak dimigrasi (diganti invoice JSON, validasi QR publik, export Excel).
- `transaksi-kas-invoice` eksternal (`nim=KAS`) belum ada; tercakup `tagihan/external` bila dibutuhkan.
