# Plan Parity SIKEU Legacy → RAG

> Dibuat: 2026-10-16 — 5 gap pilihan user, eksekusi bertahap.

## Fase 1 — DONE (backend): UMK + Tutup Kas per Unit
- `2026_10_17_000001_create_sikeu_umk_table.php` (`sikeu_umk`, decimal 15,2, softDeletes, index status/tanggal)
- `2026_10_17_000002_create_sikeu_tutup_kas_table.php` (`sikeu_tutup_kas`, unique unit_kas+tanggal)
- Model `UangMukaKerja.php`, `TutupKasUnit.php`
- Request `StoreUmkRequest`, `UpdateUmkRequest`, `StoreTutupKasRequest` (exists, tanpa in:statis master)
- Service `UmkService.php`, `TutupKasService.php` (DB::transaction, guard tutup kas)
- Observer `UmkObserver.php`, `TutupKasUnitObserver.php` + registrasi `AppServiceProvider.php:90-91`
- Controller `UmkController.php`, `TutupKasController.php` (envelope standar, paginate, RBAC existing sikeu.kaskecil.*/sikeu.kas.manage)
- Route `api.php`: `umk*`, `tutup-kas*`
- Guard kasir: `PembayaranKasirController.php` tolak bila unit terkunci
- Docs `UmkController.md`, `TutupKasController.md`, `README.md`
- Verifikasi: `php -l`, `route:list`, `migrate --pretend` / sqlite

## Fase 2 — DONE (backend): Laporan Bendahara (read-only, basis kas)
- Service `app/Services/Sikeu/LaporanBendaharaService.php` (saldo/harian/bulanan, periodik/perakun, rekap-mhs, rekap-perprodikelas maks 500, UMK aktif, koreksi negatif)
- Controller `app/Http/Controllers/Sikeu/LaporanBendaharaController.php` (9 endpoint, envelope + paginate, guard tutup bulan lalu 422, export audit log)
- Route `api.php`: `laporan-bendahara/*` (9)
- Docs `docs/api/SIKEU/LaporanBendaharaController.md`, `README.md`
- Verifikasi: `php -l`, `route:list`, test kasir/piutang hijau
- Service `LaporanBendaharaService.php`: saldo/harian/bulanan, non-tunai, periodik/perakun `:code`, rekap per-mhs + `rekapperprodikelas`, `history` — hitung dari jurnal posted + pembayaran + UMK aktif (parity `routes/laporan/laporan.js`)
- Controller `LaporanBendaharaController.php`: `saldo/harian|bulanan`, `harian|bulanan/:tanggal`, `periodik/:awal/:akhir/:code`, `perakun/...`, `rekap/:tahun/:prodi`, `history/:id`, `rekap-perprodikelas`
- Guard: wajib tutup bulan lalu (422 bila belum), koreksi negatif
- Export Excel mengikuti `PiutangMahasiswaController`
- Docs + FE `sikeu/laporan-bendahara/*`

## Fase 3 — DONE (backend): Cek Publik Kompat + DU + Email
- Service `app/Services/Sikeu/SikeuCompatService.php` + controller `SikeuCompatController.php`
  (ringkasan, tagihan-aktif, eligibilitas-ujian UTS/UAS/ALL + bypass allow_krs, biaya-lain, belum-du)
- Route `api.php`: `compat/*` (5, throttle 60/mnt)
- Email kwitansi: `app/Mail/KwitansiPembayaranMail.php`, blade `emails/sikeu/kwitansi-pembayaran`,
  `KwitansiPembayaranService.php` (non-blocking, setelah commit) + hook kasir/direct/approve-manual
- Docs `docs/api/SIKEU/SikeuCompatController.md`, `README.md`

## Fase 4 — DONE: Guard menyeluruh + FE + Test
- Guard `TutupKasService::isTerkunci` di: kas-kecil transaksi/approve, operasional pencairan
  (+ batch gaji), approve-manual, koreksi, direct-cashier (periode global ditambahkan)
- FE: `types/sikeu.types.ts` (Umk/TutupKas/Laporan), `services/sikeu.service.ts`,
  halaman `sikeu/umk/*` (list/create/edit), `sikeu/tutup-kas/*` (list/create),
  `sikeu/laporan-bendahara` (4 tab + export) — tsc 0 error, eslint 0 error
- Test: `SikeuUmkTutupKasTest` (3), `SikeuLaporanBendaharaTest` (4), `SikeuCompatTest` (4) — hijau
- Temuan terkumpul di `docs/SIKEU_TEMUAN.md` (T-01 pre-existing 301.02, T-02/T-03 fixed, T-04 disengaja)
