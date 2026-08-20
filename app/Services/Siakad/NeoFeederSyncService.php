<?php

namespace App\Services\Siakad;

use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\Krs;
use App\Models\Siakad\NilaiMahasiswa;
use App\Models\Siakad\FeederSyncLog;
use App\Models\Siakad\FeederMapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class NeoFeederSyncService
{
    protected NeoFeederService $feederService;

    public function __construct(NeoFeederService $feederService)
    {
        $this->feederService = $feederService;
    }

    /**
     * Batch Push Data Mahasiswa ke Neo Feeder
     */
    public function syncBatchMahasiswa($userId = null)
    {
        $mahasiswas = Mahasiswa::with('programStudi')->get();
        $total = $mahasiswas->count();
        $success = 0;
        $failed = 0;
        $details = [];

        $log = FeederSyncLog::create([
            'entity_type' => 'mahasiswa',
            'sync_type' => 'push',
            'total_records' => $total,
            'status' => 'processing',
            'synced_by' => $userId,
        ]);

        foreach ($mahasiswas as $mhs) {
            try {
                $record = [
                    'nama_mahasiswa' => $mhs->nama_lengkap,
                    'jenis_kelamin' => $mhs->jenis_kelamin,
                    'tempat_lahir' => $mhs->tempat_lahir,
                    'tanggal_lahir' => $mhs->tanggal_lahir?->format('Y-m-d'),
                    'id_agama' => $this->mapAgama($mhs->agama),
                    'nik' => $mhs->nik,
                    'nim' => $mhs->nim,
                    'id_prodi' => $mhs->programStudi?->kode_prodi_dikti ?? '55201',
                ];

                $res = $this->feederService->request('InsertMahasiswa', ['record' => $record]);
                $feederId = $res['data']['id_feeder'] ?? 'FE-MHS-' . $mhs->id;

                $mhs->update(['id_feeder' => $feederId]);

                FeederMapping::updateOrCreate(
                    ['entity_type' => 'mahasiswa', 'local_id' => $mhs->id],
                    [
                        'feeder_id' => $feederId,
                        'sync_status' => 'synced',
                        'last_synced_at' => now(),
                        'error_message' => null,
                    ]
                );

                $success++;
                $details[] = ['nim' => $mhs->nim, 'nama' => $mhs->nama_lengkap, 'status' => 'success', 'feeder_id' => $feederId];
            } catch (\Exception $e) {
                $failed++;
                $details[] = ['nim' => $mhs->nim, 'nama' => $mhs->nama_lengkap, 'status' => 'failed', 'error' => $e->getMessage()];

                FeederMapping::updateOrCreate(
                    ['entity_type' => 'mahasiswa', 'local_id' => $mhs->id],
                    [
                        'sync_status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]
                );
            }
        }

        $log->update([
            'success_count' => $success,
            'failed_count' => $failed,
            'status' => $failed === 0 ? 'success' : ($success > 0 ? 'partial' : 'failed'),
            'details' => $details,
            'completed_at' => now(),
        ]);

        return $log;
    }

    /**
     * Batch Push Data Dosen ke Neo Feeder
     */
    public function syncBatchDosen($userId = null)
    {
        $dosens = Dosen::with('programStudi')->get();
        $total = $dosens->count();
        $success = 0;
        $failed = 0;
        $details = [];

        $log = FeederSyncLog::create([
            'entity_type' => 'dosen',
            'sync_type' => 'push',
            'total_records' => $total,
            'status' => 'processing',
            'synced_by' => $userId,
        ]);

        foreach ($dosens as $d) {
            try {
                $record = [
                    'nama_dosen' => $d->nama_lengkap,
                    'nidn' => $d->nidn,
                    'nip' => $d->nip,
                    'id_prodi' => $d->programStudi?->kode_prodi_dikti ?? '55201',
                ];

                $res = $this->feederService->request('InsertDosen', ['record' => $record]);
                $feederId = $res['data']['id_feeder'] ?? 'FE-DSN-' . $d->id;

                $d->update(['id_feeder' => $feederId]);

                FeederMapping::updateOrCreate(
                    ['entity_type' => 'dosen', 'local_id' => $d->id],
                    [
                        'feeder_id' => $feederId,
                        'sync_status' => 'synced',
                        'last_synced_at' => now(),
                    ]
                );

                $success++;
                $details[] = ['nidn' => $d->nidn, 'nama' => $d->nama_lengkap, 'status' => 'success'];
            } catch (\Exception $e) {
                $failed++;
                $details[] = ['nidn' => $d->nidn, 'nama' => $d->nama_lengkap, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        $log->update([
            'success_count' => $success,
            'failed_count' => $failed,
            'status' => $failed === 0 ? 'success' : 'partial',
            'details' => $details,
            'completed_at' => now(),
        ]);

        return $log;
    }

    /**
     * Batch Push Data Mata Kuliah ke Neo Feeder
     */
    public function syncBatchMataKuliah($userId = null)
    {
        $mks = MataKuliah::with('kurikulum.programStudi')->get();
        $total = $mks->count();
        $success = 0;
        $failed = 0;
        $details = [];

        $log = FeederSyncLog::create([
            'entity_type' => 'mata_kuliah',
            'sync_type' => 'push',
            'total_records' => $total,
            'status' => 'processing',
            'synced_by' => $userId,
        ]);

        foreach ($mks as $mk) {
            try {
                $record = [
                    'kode_mata_kuliah' => $mk->kode_mk,
                    'nama_mata_kuliah' => $mk->nama,
                    'sks_mata_kuliah' => $mk->total_sks,
                    'sks_tatap_muka' => $mk->sks_teori,
                    'sks_praktek' => $mk->sks_praktik,
                    'id_prodi' => $mk->kurikulum?->programStudi?->kode_prodi_dikti ?? '55201',
                ];

                $res = $this->feederService->request('InsertMataKuliah', ['record' => $record]);
                $feederId = $res['data']['id_feeder'] ?? 'FE-MK-' . $mk->id;

                $mk->update(['id_feeder' => $feederId]);

                FeederMapping::updateOrCreate(
                    ['entity_type' => 'mata_kuliah', 'local_id' => $mk->id],
                    [
                        'feeder_id' => $feederId,
                        'sync_status' => 'synced',
                        'last_synced_at' => now(),
                    ]
                );

                $success++;
                $details[] = ['kode_mk' => $mk->kode_mk, 'nama' => $mk->nama, 'status' => 'success'];
            } catch (\Exception $e) {
                $failed++;
                $details[] = ['kode_mk' => $mk->kode_mk, 'nama' => $mk->nama, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        $log->update([
            'success_count' => $success,
            'failed_count' => $failed,
            'status' => $failed === 0 ? 'success' : 'partial',
            'details' => $details,
            'completed_at' => now(),
        ]);

        return $log;
    }

    /**
     * Batch Push Data Kelas & Nilai
     */
    public function syncBatchKelasNilai($userId = null)
    {
        $kelasList = Kelas::with(['mataKuliah', 'krsDetails.nilaiMahasiswa'])->get();
        $total = $kelasList->count();
        $success = 0;
        $failed = 0;
        $details = [];

        $log = FeederSyncLog::create([
            'entity_type' => 'kelas',
            'sync_type' => 'push',
            'total_records' => $total,
            'status' => 'processing',
            'synced_by' => $userId,
        ]);

        foreach ($kelasList as $k) {
            try {
                $record = [
                    'nama_kelas_kuliah' => $k->nama_kelas,
                    'id_mata_kuliah' => $k->mataKuliah?->id_feeder ?? 'FE-MK-' . $k->mata_kuliah_id,
                    'kapasitas' => $k->kapasitas,
                ];

                $res = $this->feederService->request('InsertKelasKuliah', ['record' => $record]);
                $feederId = $res['data']['id_feeder'] ?? 'FE-KLS-' . $k->id;
                $k->update(['id_feeder' => $feederId]);

                FeederMapping::updateOrCreate(
                    ['entity_type' => 'kelas', 'local_id' => $k->id],
                    [
                        'feeder_id' => $feederId,
                        'sync_status' => 'synced',
                        'last_synced_at' => now(),
                    ]
                );

                $success++;
                $details[] = ['kode_kelas' => $k->kode_kelas, 'nama_kelas' => $k->nama_kelas, 'status' => 'success'];
            } catch (\Exception $e) {
                $failed++;
                $details[] = ['kode_kelas' => $k->kode_kelas, 'nama_kelas' => $k->nama_kelas, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        $log->update([
            'success_count' => $success,
            'failed_count' => $failed,
            'status' => $failed === 0 ? 'success' : 'partial',
            'details' => $details,
            'completed_at' => now(),
        ]);

        return $log;
    }

    /**
     * Batch Push Penugasan Dosen & Pengajar Kelas ke Neo Feeder PDDikti
     */
    public function syncBatchPenugasanDosen($userId = null)
    {
        $penugasanList = \App\Models\Siakad\DosenPengampu::with(['kelas.mataKuliah', 'dosen'])->get();
        $total = $penugasanList->count();
        $success = 0;
        $failed = 0;
        $details = [];

        $log = FeederSyncLog::create([
            'entity_type' => 'penugasan_dosen',
            'sync_type' => 'push',
            'total_records' => $total,
            'status' => 'processing',
            'synced_by' => $userId,
        ]);

        foreach ($penugasanList as $dp) {
            try {
                $record = [
                    'id_kelas_kuliah' => $dp->kelas?->id_feeder ?? 'FE-KLS-' . $dp->kelas_id,
                    'id_dosen' => $dp->dosen?->id_feeder ?? 'FE-DSN-' . $dp->dosen_id,
                    'sks_substansi_total' => $dp->kelas?->mataKuliah?->total_sks ?? 3,
                    'rencana_tatap_muka' => 16,
                    'realisasi_tatap_muka' => 16,
                    'id_jenis_evaluasi' => 1, // Evaluasi Akademik Standar
                ];

                $res = $this->feederService->request('InsertDosenPengajarKelasKuliah', ['record' => $record]);
                $feederId = $res['data']['id_feeder'] ?? 'FE-AJAR-' . $dp->id;

                FeederMapping::updateOrCreate(
                    ['entity_type' => 'penugasan_dosen', 'local_id' => $dp->id],
                    [
                        'feeder_id' => $feederId,
                        'sync_status' => 'synced',
                        'last_synced_at' => now(),
                    ]
                );

                $success++;
                $details[] = [
                    'dosen' => $dp->dosen?->nama_lengkap,
                    'kelas' => $dp->kelas?->nama_kelas,
                    'mata_kuliah' => $dp->kelas?->mataKuliah?->nama,
                    'status' => 'success'
                ];
            } catch (\Exception $e) {
                $failed++;
                $details[] = [
                    'dosen' => $dp->dosen?->nama_lengkap,
                    'kelas' => $dp->kelas?->nama_kelas,
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
            }
        }

        $log->update([
            'success_count' => $success,
            'failed_count' => $failed,
            'status' => $failed === 0 ? 'success' : 'partial',
            'details' => $details,
            'completed_at' => now(),
        ]);

        return $log;
    }

    private function mapAgama($agama)
    {
        $map = [
            'Islam' => 1,
            'Kristen' => 2,
            'Katolik' => 3,
            'Hindu' => 4,
            'Buddha' => 5,
            'Konghucu' => 6
        ];
        return $map[$agama] ?? 1;
    }
}
