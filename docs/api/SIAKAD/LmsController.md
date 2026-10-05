# LmsController — PINDAH KE MODUL LMS

> **Modul**: LMS (standalone, pisah dari SIAKAD)
> **Base URL baru**: `/api/v1/lms`
> **Dokumen aktif**: [docs/api/LMS/LmsController.md](../LMS/LmsController.md)

Dokumen ini dipertahankan sebagai penanda pindah. Jangan tambah endpoint baru di sini.
UI pindah dari `/siakad/lms` ke `/lms`. Otorisasi tetap Gate `siakad.kelas.*` + `siakad.nilai.manage`.
