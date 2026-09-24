# TODO Lanjutan — Validasi Backend exists: + Form Request (SIAKAD)

## Selesai
- [x] Audit `in:` di controller SIAKAD (16 titik; 4 titik data-master, sisanya enum workflow statis — dibiarkan)
- [x] Migrasi seed master referensi siakad (18 item, 5 tipe) + endpoint referensi-options + frontend hook DB

## Selesai (bukti end-to-end)
- [x] `tests/Feature/SiakadMasterReferensiFlowTest.php`: 5 test, 37 assertions, HIJAU
- [x] Temuan sampingan: `tahun_mulai` NOT NULL di DB tapi validasi `nullable` (form selalu kirim; test disesuaikan)

## TIDAK diubah (keputusan sadar)
- Status workflow (KRS/mahasiswa/approval): di-branching ±15 titik logika + diizinkan eksplisit oleh skill → tetap `in:`.

## Keputusan
- `kategori CPL`, `teknik_penilaian` (OBE), `hari`, `status` workflow, `predikat`, absensi: enum statis sah per skill → TIDAK diubah.
- `jenjang` prodi (string bebas, tanpa `in:`) → tidak diubah (Feeder sync menulis langsung; pengetatan berisiko).
