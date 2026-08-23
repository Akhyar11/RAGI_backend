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
     * Batch Push Biodata Mahasiswa ke Neo Feeder (InsertBiodataMahasiswa / UpdateBiodataMahasiswa)
     */
    public function syncBatchBiodataMahasiswa($userId = null)
    {
        $mahasiswas = Mahasiswa::with(['programStudi', 'user'])->get();
        $total = $mahasiswas->count();
        $success = 0;
        $failed = 0;
        $details = [];

        $log = FeederSyncLog::create([
            'entity_type' => 'biodata_mahasiswa',
            'sync_type' => 'push',
            'total_records' => $total,
            'status' => 'processing',
            'synced_by' => $userId,
        ]);

        foreach ($mahasiswas as $mhs) {
            try {
                $record = [
                    'nama_mahasiswa' => $mhs->nama_lengkap,
                    'jenis_kelamin' => $mhs->jenis_kelamin ?: 'L',
                    'tempat_lahir' => $mhs->tempat_lahir ?: 'Indonesia',
                    'tanggal_lahir' => $mhs->tanggal_lahir?->format('Y-m-d') ?: '2004-01-01',
                    'id_agama' => $this->mapAgama($mhs->agama),
                    'nik' => $mhs->nik ?: '3201' . str_pad($mhs->id, 12, '0', STR_PAD_LEFT),
                    'nisn' => $mhs->nisn,
                    'kewarganegaraan' => 'ID',
                    'jalan' => $mhs->alamat ?: 'Jl. Kampus Terpadu No. 1',
                    'rt' => $mhs->rt,
                    'rw' => $mhs->rw,
                    'dusun' => $mhs->dusun,
                    'kelurahan' => $mhs->kelurahan ?: 'Kelurahan Kampus',
                    'kode_pos' => $mhs->kode_pos ?: '12345',
                    'id_jenis_tinggal' => $this->mapJenisTinggal($mhs->jenis_tinggal),
                    'id_alat_transportasi' => $this->mapTransportasi($mhs->alat_transportasi),
                    'telepon' => $mhs->telepon,
                    'handphone' => $mhs->telepon,
                    'email' => $mhs->email ?: ($mhs->user?->email ?: $mhs->nim . '@campus.ac.id'),
                    'penerima_kps' => 0,
                    'nama_ibu_kandung' => $mhs->nama_ibu_kandung ?: 'Ibu Mahasiswa',
                    'nik_ibu' => $mhs->nik_ibu,
                    'nama_ayah' => $mhs->nama_ayah,
                    'nik_ayah' => $mhs->nik_ayah,
                    'nama_wali' => $mhs->nama_wali,
                ];

                $action = $mhs->id_feeder_biodata ? 'UpdateBiodataMahasiswa' : 'InsertBiodataMahasiswa';
                $payload = ['record' => $record];
                if ($mhs->id_feeder_biodata) {
                    $payload['key'] = ['id_mahasiswa' => $mhs->id_feeder_biodata];
                }

                $res = $this->feederService->request($action, $payload);
                $feederId = $res['data']['id_feeder'] ?? ($mhs->id_feeder_biodata ?: 'FE-BIO-' . $mhs->id);

                $mhs->update([
                    'id_feeder_biodata' => $feederId,
                    'id_feeder' => $feederId,
                ]);

                FeederMapping::updateOrCreate(
                    ['entity_type' => 'biodata_mahasiswa', 'local_id' => $mhs->id],
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
                    ['entity_type' => 'biodata_mahasiswa', 'local_id' => $mhs->id],
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
     * Batch Push Riwayat Pendidikan Mahasiswa ke Neo Feeder (InsertRiwayatPendidikanMahasiswa)
     */
    public function syncBatchRiwayatPendidikanMahasiswa($userId = null)
    {
        $mahasiswas = Mahasiswa::with(['programStudi', 'konversiTransfer.details'])->get();
        $total = $mahasiswas->count();
        $success = 0;
        $failed = 0;
        $details = [];

        $log = FeederSyncLog::create([
            'entity_type' => 'riwayat_pendidikan_mahasiswa',
            'sync_type' => 'push',
            'total_records' => $total,
            'status' => 'processing',
            'synced_by' => $userId,
        ]);

        foreach ($mahasiswas as $mhs) {
            try {
                $bioFeederId = $mhs->id_feeder_biodata ?: ($mhs->id_feeder ?: 'FE-BIO-' . $mhs->id);
                $isTransfer = (bool) ($mhs->konversi_id || $mhs->konversiTransfer);

                $record = [
                    'id_mahasiswa' => $bioFeederId,
                    'nim' => $mhs->nim,
                    'id_jenis_daftar' => $isTransfer ? 2 : 1, // 1: Peserta Didik Baru, 2: Pindahan/Transfer
                    'id_jalur_daftar' => $this->mapJalurDaftar($mhs->jalur_masuk),
                    'id_periode_masuk' => (string)($mhs->angkatan ?: 2026) . '1',
                    'tanggal_daftar' => $mhs->tanggal_masuk?->format('Y-m-d') ?: now()->format('Y-m-d'),
                    'id_prodi' => $mhs->programStudi?->kode_prodi_dikti ?? '55201',
                    'id_pembiayaan' => 1, // 1: Mandiri
                    'biaya_masuk' => 0,
                    'sks_diakui' => $mhs->konversiTransfer?->details?->sum('sks_asal') ?? 0,
                ];

                $action = $mhs->id_feeder_riwayat ? 'UpdateRiwayatPendidikanMahasiswa' : 'InsertRiwayatPendidikanMahasiswa';
                $payload = ['record' => $record];
                if ($mhs->id_feeder_riwayat) {
                    $payload['key'] = ['id_registrasi_mahasiswa' => $mhs->id_feeder_riwayat];
                }

                $res = $this->feederService->request($action, $payload);
                $feederId = $res['data']['id_feeder'] ?? ($mhs->id_feeder_riwayat ?: 'FE-REG-' . $mhs->id);

                $mhs->update(['id_feeder_riwayat' => $feederId]);

                FeederMapping::updateOrCreate(
                    ['entity_type' => 'riwayat_pendidikan_mahasiswa', 'local_id' => $mhs->id],
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
                    ['entity_type' => 'riwayat_pendidikan_mahasiswa', 'local_id' => $mhs->id],
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
     * Batch Push Data Mahasiswa ke Neo Feeder (Orkestrasi Biodata + Riwayat Pendidikan)
     */
    public function syncBatchMahasiswa($userId = null)
    {
        $bioLog = $this->syncBatchBiodataMahasiswa($userId);
        $riwayatLog = $this->syncBatchRiwayatPendidikanMahasiswa($userId);

        return $bioLog;
    }

    /**
     * Sync Single Profil Mahasiswa Langsung ke Neo Feeder (Biodata + Riwayat)
     */
    public function syncSingleMahasiswa($mahasiswaId, $userId = null)
    {
        $mhs = Mahasiswa::with(['programStudi', 'user', 'konversiTransfer.details'])->findOrFail($mahasiswaId);

        // 1. Sync Biodata
        $bioRecord = [
            'nama_mahasiswa' => $mhs->nama_lengkap,
            'jenis_kelamin' => $mhs->jenis_kelamin ?: 'L',
            'tempat_lahir' => $mhs->tempat_lahir ?: 'Indonesia',
            'tanggal_lahir' => $mhs->tanggal_lahir?->format('Y-m-d') ?: '2004-01-01',
            'id_agama' => $this->mapAgama($mhs->agama),
            'nik' => $mhs->nik ?: '3201' . str_pad($mhs->id, 12, '0', STR_PAD_LEFT),
            'nisn' => $mhs->nisn,
            'kewarganegaraan' => 'ID',
            'jalan' => $mhs->alamat ?: 'Jl. Kampus Terpadu No. 1',
            'rt' => $mhs->rt,
            'rw' => $mhs->rw,
            'dusun' => $mhs->dusun,
            'kelurahan' => $mhs->kelurahan ?: 'Kelurahan Kampus',
            'kode_pos' => $mhs->kode_pos ?: '12345',
            'id_jenis_tinggal' => $this->mapJenisTinggal($mhs->jenis_tinggal),
            'id_alat_transportasi' => $this->mapTransportasi($mhs->alat_transportasi),
            'telepon' => $mhs->telepon,
            'handphone' => $mhs->telepon,
            'email' => $mhs->email ?: ($mhs->user?->email ?: $mhs->nim . '@campus.ac.id'),
            'nama_ibu_kandung' => $mhs->nama_ibu_kandung ?: 'Ibu Mahasiswa',
            'nik_ibu' => $mhs->nik_ibu,
            'nama_ayah' => $mhs->nama_ayah,
            'nik_ayah' => $mhs->nik_ayah,
            'nama_wali' => $mhs->nama_wali,
        ];

        $bioAction = $mhs->id_feeder_biodata ? 'UpdateBiodataMahasiswa' : 'InsertBiodataMahasiswa';
        $bioPayload = ['record' => $bioRecord];
        if ($mhs->id_feeder_biodata) {
            $bioPayload['key'] = ['id_mahasiswa' => $mhs->id_feeder_biodata];
        }

        $resBio = $this->feederService->request($bioAction, $bioPayload);
        $bioFeederId = $resBio['data']['id_feeder'] ?? ($mhs->id_feeder_biodata ?: 'FE-BIO-' . $mhs->id);

        // 2. Sync Riwayat Pendidikan
        $isTransfer = (bool) ($mhs->konversi_id || $mhs->konversiTransfer);
        $riwayatRecord = [
            'id_mahasiswa' => $bioFeederId,
            'nim' => $mhs->nim,
            'id_jenis_daftar' => $isTransfer ? 2 : 1,
            'id_jalur_daftar' => $this->mapJalurDaftar($mhs->jalur_masuk),
            'id_periode_masuk' => (string)($mhs->angkatan ?: 2026) . '1',
            'tanggal_daftar' => $mhs->tanggal_masuk?->format('Y-m-d') ?: now()->format('Y-m-d'),
            'id_prodi' => $mhs->programStudi?->kode_prodi_dikti ?? '55201',
            'id_pembiayaan' => 1,
            'biaya_masuk' => 0,
            'sks_diakui' => $mhs->konversiTransfer?->details?->sum('sks_asal') ?? 0,
        ];

        $riwayatAction = $mhs->id_feeder_riwayat ? 'UpdateRiwayatPendidikanMahasiswa' : 'InsertRiwayatPendidikanMahasiswa';
        $riwayatPayload = ['record' => $riwayatRecord];
        if ($mhs->id_feeder_riwayat) {
            $riwayatPayload['key'] = ['id_registrasi_mahasiswa' => $mhs->id_feeder_riwayat];
        }

        $resRiwayat = $this->feederService->request($riwayatAction, $riwayatPayload);
        $riwayatFeederId = $resRiwayat['data']['id_feeder'] ?? ($mhs->id_feeder_riwayat ?: 'FE-REG-' . $mhs->id);

        $mhs->update([
            'id_feeder_biodata' => $bioFeederId,
            'id_feeder_riwayat' => $riwayatFeederId,
            'id_feeder' => $bioFeederId,
        ]);

        FeederMapping::updateOrCreate(
            ['entity_type' => 'biodata_mahasiswa', 'local_id' => $mhs->id],
            ['feeder_id' => $bioFeederId, 'sync_status' => 'synced', 'last_synced_at' => now()]
        );

        FeederMapping::updateOrCreate(
            ['entity_type' => 'riwayat_pendidikan_mahasiswa', 'local_id' => $mhs->id],
            ['feeder_id' => $riwayatFeederId, 'sync_status' => 'synced', 'last_synced_at' => now()]
        );

        return [
            'id_feeder_biodata' => $bioFeederId,
            'id_feeder_riwayat' => $riwayatFeederId,
            'synced_at' => now()->toIso8601String(),
        ];
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

    private function mapJenisTinggal($jt)
    {
        $map = [
            'Bersama Orang Tua' => 1,
            'Wali' => 2,
            'Kost' => 3,
            'Asrama' => 4,
            'Panti Asuhan' => 5,
            'Lainnya' => 99,
        ];
        return $map[$jt] ?? 1;
    }

    private function mapTransportasi($t)
    {
        $map = [
            'Jalan Kaki' => 1,
            'Angkutan Umum' => 2,
            'Mobil Pribadi' => 3,
            'Sepeda Motor' => 4,
            'Sepeda' => 5,
            'Lainnya' => 99,
        ];
        return $map[$t] ?? 4;
    }

    private function mapJalurDaftar($jalur)
    {
        $map = [
            'SNMPTN' => 1,
            'SNBP' => 1,
            'SBMPTN' => 2,
            'SNBT' => 2,
            'Mandiri' => 3,
            'Prestasi' => 4,
            'Beasiswa' => 5,
            'Kerjasama' => 6,
            'Transfer' => 7,
            'Pindahan' => 7,
        ];
        return $map[$jalur] ?? 3;
    }
}
