<?php

namespace Database\Seeders\Sikeu;

use Illuminate\Database\Seeder;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\DetailTagihan;
use App\Models\Sikeu\DispensasiTagihan;
use App\Models\Sikeu\PemasukanKampus;
use App\Models\Sikeu\PengeluaranKampus;
use App\Models\Sikeu\UnitKas;
use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\DetailJurnalUmum;
use App\Models\Sikeu\Pembayaran;

class SikeuDummySeeder extends Seeder
{
    public function run(): void
    {
        $unitKas = UnitKas::first();
        $akunKas = AkunKeuangan::where('kode_akun', '102.01')->first() ?? AkunKeuangan::first();
        $akunUkt = AkunKeuangan::where('kode_akun', '401.01')->first() ?? AkunKeuangan::first();
        $akunHibah = AkunKeuangan::where('kode_akun', '402.01')->first() ?? AkunKeuangan::first();
        $akunBeban = AkunKeuangan::where('kode_akun', '5.1.01.01')->first() ?? AkunKeuangan::first();

        // 1. Dummy Tagihan Mahasiswa Lunas
        $tagihanLunas = TagihanMahasiswa::firstOrCreate(
            ['nomor_tagihan' => 'INV-SIAKAD-DUMMY-001'],
            [
                'mahasiswa_id' => 101,
                'tahun_akademik_id' => 1,
                'total_tagihan' => 5000000,
                'total_bayar' => 5000000,
                'status' => 'lunas',
                'source_system' => 'SIAKAD',
                'jatuh_tempo' => now()->addDays(30)->toDateString(),
            ]
        );

        Pembayaran::firstOrCreate(
            ['kode_transaksi' => 'TRX-DUMMY-001'],
            [
                'tagihan_id' => $tagihanLunas->id,
                'jumlah_bayar' => 5000000,
                'waktu_bayar' => now(),
                'channel_bayar' => 'BNI_VA',
                'status' => 'success',
            ]
        );

        // 2. Dummy Tagihan Pending Approval
        TagihanMahasiswa::firstOrCreate(
            ['nomor_tagihan' => 'INV-SIAKAD-DUMMY-002'],
            [
                'mahasiswa_id' => 102,
                'tahun_akademik_id' => 1,
                'total_tagihan' => 4500000,
                'total_bayar' => 0,
                'status' => 'pending_approval',
                'requires_approval' => true,
                'status_approval' => 'pending',
                'source_system' => 'SIAKAD',
                'jatuh_tempo' => now()->addDays(15)->toDateString(),
            ]
        );

        // 3. Dummy Dispensasi Pembayaran
        $tagihanDisp = TagihanMahasiswa::firstOrCreate(
            ['nomor_tagihan' => 'INV-SIAKAD-DUMMY-003'],
            [
                'mahasiswa_id' => 103,
                'tahun_akademik_id' => 1,
                'total_tagihan' => 3500000,
                'total_bayar' => 0,
                'status' => 'dispensasi',
                'source_system' => 'SIAKAD',
                'jatuh_tempo' => now()->addDays(45)->toDateString(),
            ]
        );

        DispensasiTagihan::firstOrCreate(
            ['tagihan_id' => $tagihanDisp->id],
            [
                'mahasiswa_id' => 103,
                'tipe_dispensasi' => 'penundaan_jatuh_tempo',
                'jatuh_tempo_baru' => now()->addDays(45)->toDateString(),
                'alasan' => 'Permohonan penundaan karena proses beasiswa pemerintah daerah',
                'status' => 'approved',
                'tanggal_persetujuan' => now()->toDateString(),
            ]
        );

        // 4. Dummy Pemasukan Hibah Riset
        PemasukanKampus::firstOrCreate(
            ['nomor_transaksi' => 'INC-DUMMY-HIB-001'],
            [
                'sumber_pemasukan' => 'hibah_sippm',
                'unit_kas_id' => $unitKas?->id,
                'akun_pendapatan_id' => $akunHibah?->id,
                'nominal' => 25000000,
                'tanggal_terima' => now()->toDateString(),
                'nama_donor_instansi' => 'Kemdikbudristek (DRTPM)',
                'nomor_kontrak_ref' => '045/SPK/LPPM/2026',
                'keterangan' => 'Pencairan Hibah Penelitian Unggulan Perguruan Tinggi 2026',
                'created_by' => 1,
            ]
        );

        // 5. Dummy Pengeluaran Operasional
        PengeluaranKampus::firstOrCreate(
            ['nomor_transaksi' => 'EXP-DUMMY-OP-001'],
            [
                'kategori' => 'operasional',
                'akun_beban_id' => $akunBeban?->id,
                'akun_kas_id' => $akunKas?->id,
                'nominal' => 15000000,
                'keterangan' => 'Pengadaan Lisensi Software Lab TI & Server Cloud',
                'tanggal_transaksi' => now()->toDateString(),
                'nama_vendor' => 'PT Solusi Terpadu Kampus',
                'npwp_vendor' => '01.234.567.8-901.000',
                'jenis_pajak' => 'pph_23',
                'tarif_pajak_persen' => 2,
                'nominal_pajak' => 300000,
                'net_dibayarkan' => 14700000,
                'status_pembayaran' => 'lunas',
                'created_by' => 1,
            ]
        );

        // 6. Dummy Jurnal Akuntansi
        $jurnal = JurnalUmum::firstOrCreate(
            ['nomor_jurnal' => 'JRN-DUMMY-001'],
            [
                'tanggal_jurnal' => now()->toDateString(),
                'jenis_sumber' => 'pemasukan_hibah',
                'referensi_id' => 1,
                'keterangan' => 'Penerimaan Dana Hibah Riset Kemdikbudristek 2026',
                'status_posting' => 'posted',
                'total_debet' => 25000000,
                'total_kredit' => 25000000,
                'created_by' => 1,
                'posted_by' => 1,
                'posted_at' => now(),
            ]
        );

        DetailJurnalUmum::firstOrCreate(
            ['jurnal_id' => $jurnal->id, 'akun_id' => $akunKas->id],
            [
                'debet' => 25000000,
                'kredit' => 0,
                'keterangan' => 'Penerimaan kas/bank hibah riset',
            ]
        );

        DetailJurnalUmum::firstOrCreate(
            ['jurnal_id' => $jurnal->id, 'akun_id' => $akunHibah->id],
            [
                'debet' => 0,
                'kredit' => 25000000,
                'keterangan' => 'Pengakuan pendapatan hibah riset',
            ]
        );
    }
}
