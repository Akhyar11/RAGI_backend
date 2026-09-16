# PayrollController

> **Modul**: SIMPEG (Sub-Sistem Penggajian & Sinkronisasi Kas SIKEU)  
> **Base URL**: `/api/simpeg`  
> **Autentikasi**: Bearer Token (Sanctum)  
> **Dibuat**: 2026-09-16  
> **Diperbarui**: 2026-09-16  

Controller ini mengelola siklus penggajian fleksibel terintegrasi: Master Komponen Gaji dinamis, kalkulator payroll terpadu (mengakomodasi Tunjangan Fungsional, Honor SKS Mengajar SIAKAD, Insentif Kehadiran Presensi, dan PPh 21 TER), rincian slip gaji, pengajuan ke SIKEU, hingga pencairan kas dan posting jurnal akuntansi seimbang otomatis di modul Keuangan (SIKEU).

---

## Daftar Endpoint

| Method | Endpoint | Fungsi | Auth / Permission |
|---|---|---|---|
| GET | `/api/simpeg/payroll` | Daftar rekapan payroll bulanan | `simpeg.payroll.read` / `view` |
| GET | `/api/simpeg/payroll/{id}` | Detail mendalam slip gaji & rincian butir | `simpeg.payroll.read` / `view` |
| POST | `/api/simpeg/payroll/generate` | Hitung kalkulasi payroll dari master, presensi & SIAKAD | `simpeg.payroll.create` / `manage` |
| POST | `/api/simpeg/payroll/submit-to-sikeu` | Mengajukan rekapan payroll ke modul SIKEU | `simpeg.payroll.create` / `manage` |
| POST | `/api/simpeg/payroll/{id}/process-payment` | Eksekusi pencairan gaji, posting jurnal & pajak SIKEU | `simpeg.payroll.manage` |
| GET | `/api/simpeg/payroll/komponen` | Daftar master komponen gaji (Pendapatan & Potongan) | `simpeg.payroll.read` / `manage` |
| POST | `/api/simpeg/payroll/komponen` | Tambah master komponen gaji baru | `simpeg.payroll.manage` |
| PUT | `/api/simpeg/payroll/komponen/{id}` | Ubah master komponen gaji | `simpeg.payroll.manage` |
| DELETE | `/api/simpeg/payroll/komponen/{id}` | Hapus master komponen gaji | `simpeg.payroll.manage` |
| GET | `/api/simpeg/payroll/pegawai/{pegawaiId}/komponen` | Ambil konfigurasi komponen gaji milik pegawai | `simpeg.payroll.read` / `manage` |
| POST | `/api/simpeg/payroll/pegawai/{pegawaiId}/komponen` | Simpan kustomisasi komponen gaji pegawai | `simpeg.payroll.manage` |

---

## 1. POST /api/simpeg/payroll/generate

Menjalankan kalkulasi payroll bulanan terpadu untuk periode tertentu (YYYY-MM). Mengkombinasikan gaji pokok, tunjangan fungsional dari riwayat jabatan dosen, honor SKS dari jadwal kelas aktif SIAKAD, insentif kehadiran presensi tepat waktu, potongan BPJS, dan estimasi pajak PPh 21.

### Request Body
```json
{
  "periode": "2026-09",
  "pegawai_id": 1
}
```

### Response Sukses (200 OK)
```json
{
  "status": "success",
  "message": "Kalkulasi payroll periode 2026-09 berhasil diproses untuk 1 pegawai!",
  "data": {
    "periode": "2026-09",
    "total_pegawai": 1,
    "payrolls": [
      {
        "id": 1,
        "pegawai_id": 1,
        "periode_bulan_tahun": "2026-09",
        "gaji_pokok": 4500000,
        "tunjangan_tetap": 2050000,
        "total_biaya_transport": 1000000,
        "total_honor_sks": 600000,
        "total_sks_diampu": 12,
        "total_tunjangan_fungsional": 1000000,
        "total_tunjangan": 3650000,
        "total_potongan": 420000,
        "total_pph21": 120000,
        "total_bpjs": 250000,
        "gaji_bersih": 7730000,
        "status_transfer": "draft"
      }
    ]
  }
}
```

---

## 2. POST /api/simpeg/payroll/{id}/process-payment

Mengeksekusi pembayaran gaji pegawai di modul Keuangan (SIKEU). Secara otomatis:
1. Mengubah status transfer menjadi `paid`.
2. Menerbitkan Jurnal Umum Seimbang di SIKEU (Debet Beban Gaji & Tunjangan, Kredit Kas/Bank Operasional, Kredit Utang PPh 21, Kredit Utang BPJS).
3. Mengurangi saldo kas operasional di `sikeu_unit_kas`.
4. Mencatatkan kewajiban pajak PPh 21 ke `sikeu_pengeluaran_kampus` agar terintegrasi dengan pelaporan NTPN di menu Pajak Kampus SIKEU.

### Response Sukses (200 OK)
```json
{
  "status": "success",
  "message": "Pembayaran gaji Dr. Budi Santoso periode 2026-09 berhasil dibayarkan dan jurnal SIKEU diterbitkan!",
  "data": {
    "id": 1,
    "status_transfer": "paid",
    "tanggal_transfer": "2026-09-16T11:15:00.000000Z",
    "jurnal_id": 14,
    "pengeluaran_kampus_id": 8
  },
  "sikeu_journal": {
    "nomor_jurnal": "JRN-SIMPEG-20260916-0001",
    "jurnal_id": 14,
    "pengeluaran_kampus_id": 8,
    "total_debet": 8150000,
    "status": "POSTED_TO_SIKEU",
    "integrated_at": "2026-09-16T11:15:00+07:00"
  }
}
```
