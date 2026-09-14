<?php

namespace Database\Seeders\Simpeg;

use App\Models\Simpeg\MasterJenisCuti;
use App\Models\Simpeg\PengajuanCuti;
use Illuminate\Database\Seeder;

class MasterJenisCutiSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'nama' => 'Cuti Tahunan',
                'kode' => 'CUTI_TAHUNAN',
                'tipe_durasi' => 'fleksibel',
                'durasi_hari' => 0,
                'satuan' => 'hari',
                'lampiran_wajib' => false,
                'keterangan' => 'Cuti tahunan reguler bagi dosen dan tenaga kependidikan (maksimal 12 hari kerja per tahun).',
                'is_active' => true,
            ],
            [
                'nama' => 'Izin Sakit',
                'kode' => 'IZIN_SAKIT',
                'tipe_durasi' => 'fleksibel',
                'durasi_hari' => 0,
                'satuan' => 'hari',
                'lampiran_wajib' => true,
                'keterangan' => 'Izin karena sakit. Wajib melampirkan surat keterangan dokter atau hasil pemeriksaan medis.',
                'is_active' => true,
            ],
            [
                'nama' => 'Izin Menikah',
                'kode' => 'IZIN_MENIKAH',
                'tipe_durasi' => 'ditetapkan',
                'durasi_hari' => 14,
                'satuan' => 'hari',
                'lampiran_wajib' => false,
                'keterangan' => 'Izin melangsungkan pernikahan pegawai. Durasi ditetapkan baku 14 hari (2 minggu) kalender.',
                'is_active' => true,
            ],
            [
                'nama' => 'Cuti Melahirkan',
                'kode' => 'CUTI_MELAHIRKAN',
                'tipe_durasi' => 'ditetapkan',
                'durasi_hari' => 90,
                'satuan' => 'hari',
                'lampiran_wajib' => true,
                'keterangan' => 'Cuti bersalin / melahirkan bagi pegawai perempuan. Durasi ditetapkan baku 90 hari (3 bulan).',
                'is_active' => true,
            ],
            [
                'nama' => 'Cuti Alasan Penting',
                'kode' => 'CUTI_ALASAN_PENTING',
                'tipe_durasi' => 'fleksibel',
                'durasi_hari' => 0,
                'satuan' => 'hari',
                'lampiran_wajib' => false,
                'keterangan' => 'Cuti untuk keperluan mendesak/penting keluarga inti (keluarga sakit keras, musibah, dll).',
                'is_active' => true,
            ],
            [
                'nama' => 'Cuti Besar',
                'kode' => 'CUTI_BESAR',
                'tipe_durasi' => 'fleksibel',
                'durasi_hari' => 0,
                'satuan' => 'hari',
                'lampiran_wajib' => false,
                'keterangan' => 'Cuti besar yang diberikan setelah masa pengabdian kerja minimal 5 tahun secara terus-menerus.',
                'is_active' => true,
            ],
        ];

        foreach ($data as $item) {
            MasterJenisCuti::updateOrCreate(
                ['kode' => $item['kode']],
                $item
            );
        }

        // Sinkronisasi data lama pengajuan cuti jika ada
        $mapping = [
            'tahunan' => 'CUTI_TAHUNAN',
            'sakit' => 'IZIN_SAKIT',
            'melahirkan' => 'CUTI_MELAHIRKAN',
            'alasan_penting' => 'CUTI_ALASAN_PENTING',
            'besar' => 'CUTI_BESAR',
        ];

        foreach ($mapping as $oldEnum => $kode) {
            $master = MasterJenisCuti::where('kode', $kode)->first();
            if ($master) {
                PengajuanCuti::where('jenis_cuti', $oldEnum)
                    ->whereNull('master_jenis_cuti_id')
                    ->update(['master_jenis_cuti_id' => $master->id]);
            }
        }
    }
}
