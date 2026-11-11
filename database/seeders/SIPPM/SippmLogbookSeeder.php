<?php

namespace Database\Seeders\SIPPM;

use Illuminate\Database\Seeder;
use App\Models\Sippm\PeriodeHibah;
use App\Models\Sippm\SkemaKegiatan;
use App\Models\Sippm\ProposalKegiatan;
use App\Models\Sippm\LogbookKegiatan;
use App\Models\Simpeg\Pegawai;

class SippmLogbookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $proposal = ProposalKegiatan::first();

        if (!$proposal) {
            $periode = PeriodeHibah::firstOrCreate(
                ['tahun_anggaran' => (string) date('Y')],
                [
                    'nama_periode' => 'Hibah Internal Tahun ' . date('Y'),
                    'tgl_buka' => now()->startOfYear(),
                    'tgl_tutup' => now()->endOfYear(),
                    'is_aktif' => true,
                ]
            );

            $skema = SkemaKegiatan::firstOrCreate(
                ['kode_skema' => 'PDP-01'],
                [
                    'nama_skema' => 'Penelitian Dosen Pemula (PDP)',
                    'jenis' => 'penelitian',
                    'biaya_maksimal' => 20000000,
                    'is_aktif' => true,
                ]
            );

            $pegawai = Pegawai::first();
            $pegawaiId = $pegawai ? $pegawai->id : 1;

            $proposal = ProposalKegiatan::firstOrCreate(
                ['kode_proposal' => 'PROP-2026-001'],
                [
                    'periode_id' => $periode->id,
                    'skema_id' => $skema->id,
                    'ketua_pegawai_id' => $pegawaiId,
                    'judul' => 'Pengembangan Sistem Deteksi Citra Berbasis AI',
                    'abstrak' => 'Implementasi algoritma computer vision untuk pengenalan pola.',
                    'rumpun_ilmu' => 'Ilmu Komputer',
                    'target_tkt' => 5,
                    'anggaran_diajukan' => 15000000,
                    'anggaran_disetujui' => 15000000,
                    'file_proposal' => 'proposal_dummy.pdf',
                    'status' => 'lolos',
                ]
            );
        }

        LogbookKegiatan::updateOrCreate(
            [
                'proposal_id' => $proposal->id,
                'tgl_kegiatan' => '2026-11-10',
            ],
            [
                'persentase_capaian' => 45.00,
                'uraian_kegiatan' => 'Penyusunan instrumen pengambilan data dan instalasi baseline sistem.',
                'hambatan' => 'Ketersediaan perangkat uji coba pada laboratorium.',
                'status_verifikasi' => 'verified',
            ]
        );
    }
}
