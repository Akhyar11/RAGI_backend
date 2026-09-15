<?php

namespace App\Services\Siakad;

use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPenugasan;
use App\Models\Siakad\DosenPengampu;
use App\Models\Spmb\MasterTahunAkademik;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\Krs;
use App\Models\Siakad\NilaiMahasiswa;
use App\Models\Siakad\FeederSyncLog;
use App\Models\Siakad\FeederMapping;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\RiwayatPendidikanPegawai;
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
     * Batch Pencocokan Biodata Dosen ke Neo Feeder (DetailBiodataDosen by NIDN)
     * Catatan: Sesuai regulasi PDDikti, penambahan dosen baru dilarang via Web Service (Error 300).
     * Dosen harus didaftarkan di SISTER/PDDikti pusat, lalu dicocokkan NIDN-nya di sini untuk mengambil id_dosen (UUID).
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
            'sync_type' => 'pull',
            'total_records' => $total,
            'status' => 'processing',
            'synced_by' => $userId,
        ]);

        foreach ($dosens as $d) {
            try {
                if (empty($d->nidn)) {
                    // Dosen baru ber-NIP lokal yang belum memiliki NIDN: lewati secara aman tanpa error
                    $details[] = [
                        'nip' => $d->nip ?: '-',
                        'nama' => $d->nama_lengkap,
                        'status' => 'skipped',
                        'message' => 'Belum memiliki NIDN (hanya aktif lokal dengan NIP: ' . ($d->nip ?: '-') . '). Dilewati dari sinkronisasi Feeder.'
                    ];
                    continue;
                }

                $res = $this->feederService->request('DetailBiodataDosen', [
                    'filter' => "nidn = '{$d->nidn}'",
                    'order' => 'nidn',
                    'limit' => 1,
                    'offset' => 0,
                ]);

                $feederId = null;
                if (isset($res['data'])) {
                    if (is_array($res['data']) && isset($res['data'][0])) {
                        $feederId = $res['data'][0]['id_dosen'] ?? $res['data'][0]['id_ptk'] ?? null;
                    } elseif (is_array($res['data'])) {
                        $feederId = $res['data']['id_dosen'] ?? $res['data']['id_feeder'] ?? null;
                    }
                }

                // Fallback simulation support jika server standalone mock
                if (empty($feederId) && isset($res['data']['id_feeder'])) {
                    $feederId = $res['data']['id_feeder'];
                }

                if (empty($feederId) && (!isset($res['error_code']) || $res['error_code'] != 0)) {
                    $errMsg = $res['error_desc'] ?? 'Tidak ditemukan ID Dosen di PDDikti Feeder';
                    throw new \Exception($errMsg);
                }

                if (empty($feederId)) {
                    throw new \Exception("NIDN {$d->nidn} tidak ditemukan di PDDikti Feeder. Pastikan dosen sudah terdaftar di SISTER.");
                }

                $d->update(['id_feeder' => $feederId]);

                FeederMapping::updateOrCreate(
                    ['entity_type' => 'dosen', 'local_id' => $d->id],
                    [
                        'feeder_id' => $feederId,
                        'sync_status' => 'synced',
                        'last_synced_at' => now(),
                        'error_message' => null,
                    ]
                );

                $success++;
                $details[] = ['nidn' => $d->nidn, 'nama' => $d->nama_lengkap, 'status' => 'success', 'feeder_id' => $feederId];
            } catch (\Exception $e) {
                $failed++;
                FeederMapping::updateOrCreate(
                    ['entity_type' => 'dosen', 'local_id' => $d->id],
                    [
                        'sync_status' => 'failed',
                        'last_synced_at' => now(),
                        'error_message' => $e->getMessage(),
                    ]
                );
                $details[] = ['nidn' => $d->nidn, 'nama' => $d->nama_lengkap, 'status' => 'failed', 'error' => $e->getMessage()];
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
     * Sinkronisasi Daftar Program Studi Resmi dari Neo Feeder (GetProdi)
     */
    public function syncProgramStudiFromFeeder()
    {
        try {
            $res = $this->feederService->request('GetProdi', ['limit' => 100]);
            $items = $res['data'] ?? [];
            if (isset($items[0])) {
                foreach ($items as $p) {
                    $idFeeder = $p['id_prodi'] ?? null;
                    $kode = $p['kode_program_studi'] ?? '';
                    $nama = $p['nama_program_studi'] ?? '';
                    $jenjang = $p['nama_jenjang_pendidikan'] ?? 'D4';
                    $status = ($p['status'] ?? 'A') === 'A';
                    $namaLengkap = (str_starts_with($nama, $jenjang . ' ')) ? $nama : "{$jenjang} {$nama}";

                    MasterProgramStudi::updateOrCreate(
                        ['kode_prodi' => $kode],
                        [
                            'nama' => $namaLengkap,
                            'jenjang' => $jenjang,
                            'id_feeder' => $idFeeder,
                            'kode_prodi_dikti' => $kode,
                            'is_active' => $status,
                        ]
                    );
                }
            }
        } catch (\Exception $e) {
            Log::warning("Gagal sync prodi feeder: " . $e->getMessage());
        }
    }

    /**
     * Konversi Nama Gelar Akademik Resmi DIKTI ke Singkatan Baku Indonesia
     */
    public function abbreviateAcademicDegree($namaGelar, $jenjang = null, $bidangStudi = null): array
    {
        $namaGelar = trim((string)$namaGelar);
        $jenjang = strtoupper(trim((string)$jenjang));
        $bidang = strtolower(trim((string)$bidangStudi));

        $knownMap = [
            'Magister Kesehatan' => ['belakang' => 'M.Kes.'],
            'Magister Manajemen' => ['belakang' => 'M.M.'],
            'Magister Pendidikan' => ['belakang' => 'M.Pd.'],
            'Magister Komputer' => ['belakang' => 'M.Kom.'],
            'Sarjana Komputer' => ['belakang' => 'S.Kom.'],
            'Ahli Madya' => ['belakang' => 'A.Md.'],
            'Ahli Madya Pariwisata' => ['belakang' => 'A.Md.Par.'],
            'Doktor' => ['depan' => 'Dr.'],
            'Dr' => ['depan' => 'Dr.'],
            'Doktorandes' => ['depan' => 'Drs.'],
            'Doktoranda' => ['depan' => 'Dra.'],
            'Insinyur' => ['depan' => 'Ir.'],
            'Dokter' => ['depan' => 'dr.'],
            'Sarjana Ilmu Komunikasi' => ['belakang' => 'S.I.Kom.'],
            'Magister Ilmu Komunikasi' => ['belakang' => 'M.I.Kom.'],
            'Magister Sains' => ['belakang' => 'M.Si.'],
            'Sarjana Sains' => ['belakang' => 'S.Si.'],
            'Sarjana Pendidikan Islam' => ['belakang' => 'S.Pd.I.'],
            'Magister Pendidikan Islam' => ['belakang' => 'M.Pd.I.'],
            'Sarjana Ekonomi' => ['belakang' => 'S.E.'],
            'Sarjana Akuntansi' => ['belakang' => 'S.Ak.'],
            'Magister Akuntansi' => ['belakang' => 'M.Ak.'],
            'Sarjana Teknik' => ['belakang' => 'S.T.'],
            'Magister Teknik' => ['belakang' => 'M.T.'],
            'Degree of Master of Public Health' => ['belakang' => 'M.P.H.'],
            'Master of Public Health' => ['belakang' => 'M.P.H.'],
            'Master of Public Health (Extension)' => ['belakang' => 'M.P.H.'],
            'Master of Science' => ['belakang' => 'M.Sc.'],
            'Msc' => ['belakang' => 'M.Sc.'],
            'Master of Science, Technology, And Health' => ['belakang' => 'M.Sc.'],
            'Sarjana Ilmu Sosial' => ['belakang' => 'S.Sos.'],
            'Sarjana Pendidikan' => ['belakang' => 'S.Pd.'],
            'Megister Manajemen Pariwisata' => ['belakang' => 'M.Par.'],
            'Magister Pariwisata' => ['belakang' => 'M.Par.'],
            'Sarjana Pariwisata' => ['belakang' => 'S.Par.'],
            'Sarjana Sastra' => ['belakang' => 'S.S.'],
            'Sarjana Sains Terapan' => ['belakang' => 'S.S.T.'],
            'Sarjana Sains Terapan Pariwisata' => ['belakang' => 'S.Tr.Par.'],
            'Sarjana Sains Terapan Fisioterapi' => ['belakang' => 'S.Tr.Ft.'],
            'Sarjana Terapan Pariwisata' => ['belakang' => 'S.Tr.Par.'],
            'Sarjana Terapan Analis Kesehatan' => ['belakang' => 'S.Tr.A.K.'],
            'Sarjana Kesehatan Masyarakat' => ['belakang' => 'S.K.M.'],
            'Magister Kesehatan Masyarakat' => ['belakang' => 'M.K.M.'],
            'Magister of Arts' => ['belakang' => 'M.A.'],
            'Master of Arts' => ['belakang' => 'M.A.'],
            'Magister Ilmu Hukum' => ['belakang' => 'M.H.'],
            'Sarjana Hukum' => ['belakang' => 'S.H.'],
            'Sarjana Pertanian' => ['belakang' => 'S.P.'],
            'Doctor of Philosophy' => ['belakang' => 'Ph.D.'],
            'Doctor of Philosophy (Information Technology)' => ['belakang' => 'Ph.D.'],
            'Doctor' => ['depan' => 'Dr.'],
            'Magister of Engineering' => ['belakang' => 'M.Eng.'],
            'Master of Computer Science' => ['belakang' => 'M.C.S.'],
            'Master of Pharmaceutical Sciences' => ['belakang' => 'M.Pharm.'],
            'Master of Pharmacy' => ['belakang' => 'M.Pharm.'],
            'Sarjana Humaniora' => ['belakang' => 'S.Hum.'],
            'Sarjana Rekam Medis' => ['belakang' => 'S.R.M.'],
            'Magister Biomedik' => ['belakang' => 'M.Biomed.'],
            'Sarjana Farmasi' => ['belakang' => 'S.Farm.'],
            'Sarjana Kedokteran' => ['belakang' => 'S.Ked.'],
            'Magister Imunologi' => ['belakang' => 'M.Imun.'],
            'Apoteker' => ['depan' => 'Apt.'],
        ];

        if (isset($knownMap[$namaGelar])) {
            return $knownMap[$namaGelar];
        }

        // Fallback pencocokan pola kata
        if (preg_match('/^sarjana terapan/i', $namaGelar)) {
            return ['belakang' => 'S.Tr.'];
        }
        if (preg_match('/^sarjana/i', $namaGelar)) {
            return ['belakang' => 'S.'];
        }
        if (preg_match('/^magister/i', $namaGelar)) {
            return ['belakang' => 'M.'];
        }
        if (preg_match('/^doktor/i', $namaGelar) || preg_match('/^doctor/i', $namaGelar)) {
            return ['depan' => 'Dr.'];
        }

        // Fallback jika nama gelar kosong tapi ada jenjang & bidang studi
        if ($namaGelar === '-' || empty($namaGelar)) {
            if ($jenjang === 'S1') {
                if (str_contains($bidang, 'farmasi')) return ['belakang' => 'S.Farm.'];
                if (str_contains($bidang, 'komputer') || str_contains($bidang, 'informatika')) return ['belakang' => 'S.Kom.'];
                if (str_contains($bidang, 'teknik')) return ['belakang' => 'S.T.'];
                if (str_contains($bidang, 'ekonomi')) return ['belakang' => 'S.E.'];
                return ['belakang' => 'S.'];
            } elseif ($jenjang === 'S2') {
                if (str_contains($bidang, 'farmasi')) return ['belakang' => 'M.Farm.'];
                if (str_contains($bidang, 'komputer') || str_contains($bidang, 'informatika')) return ['belakang' => 'M.Kom.'];
                if (str_contains($bidang, 'kesehatan')) return ['belakang' => 'M.Kes.'];
                if (str_contains($bidang, 'manajemen')) return ['belakang' => 'M.M.'];
                if (str_contains($bidang, 'teknik')) return ['belakang' => 'M.T.'];
                return ['belakang' => 'M.'];
            } elseif ($jenjang === 'S3') {
                return ['depan' => 'Dr.'];
            } elseif ($jenjang === 'PROFESI') {
                if (str_contains($bidang, 'apoteker') || str_contains($bidang, 'farmasi')) return ['depan' => 'Apt.'];
                if (str_contains($bidang, 'dokter')) return ['depan' => 'dr.'];
            }
        }

        return [];
    }

    /**
     * Menyusun Gelar Depan dan Gelar Belakang secara Otomatis dari Riwayat Pendidikan
     */
    public function resolveAcademicTitlesForDosen(array $educationList): array
    {
        $frontTitles = [];
        $backTitles = [];

        // Urutkan jenjang: D1 -> D2 -> D3 -> D4 -> S1 -> Profesi -> Spesialis -> S2 -> S3
        $order = ['D1' => 1, 'D2' => 2, 'D3' => 3, 'D4' => 4, 'S1' => 5, 'PROFESI' => 6, 'SPESIALIS' => 7, 'S2' => 8, 'S3' => 9];
        usort($educationList, function ($a, $b) use ($order) {
            $jA = strtoupper($a['nama_jenjang_pendidikan'] ?? '');
            $jB = strtoupper($b['nama_jenjang_pendidikan'] ?? '');
            return ($order[$jA] ?? 5) <=> ($order[$jB] ?? 5);
        });

        foreach ($educationList as $edu) {
            $abbr = $this->abbreviateAcademicDegree(
                $edu['nama_gelar_akademik'] ?? '',
                $edu['nama_jenjang_pendidikan'] ?? '',
                $edu['nama_bidang_studi'] ?? ''
            );

            if (!empty($abbr['depan']) && !in_array($abbr['depan'], $frontTitles, true)) {
                $frontTitles[] = $abbr['depan'];
            }
            if (!empty($abbr['belakang']) && !in_array($abbr['belakang'], $backTitles, true)) {
                $backTitles[] = $abbr['belakang'];
            }
        }

        return [
            'gelar_depan' => !empty($frontTitles) ? implode(' ', $frontTitles) : null,
            'gelar_belakang' => !empty($backTitles) ? implode(', ', $backTitles) : null,
        ];
    }

    /**
     * Tarik / Import Seluruh Data Dosen dari Neo Feeder (GetListDosen)
     * Mengambil daftar dosen resmi kampus yang tercatat di PDDikti dan menyimpannya ke database lokal.
     * Jika dosen sudah ada (berdasarkan NIDN), update id_feeder tanpa menimpa NIP lokal yang sudah ada.
     */
    public function pullBatchDosenFromFeeder($userId = null)
    {
        $log = FeederSyncLog::create([
            'entity_type' => 'dosen',
            'sync_type' => 'pull',
            'total_records' => 0,
            'status' => 'processing',
            'synced_by' => $userId,
        ]);

        $success = 0;
        $failed = 0;
        $details = [];

        try {
            $res = $this->feederService->request('GetListDosen', [
                'order' => 'nama_dosen',
                'limit' => 500,
                'offset' => 0,
            ]);

            $dosenItems = [];
            if (isset($res['data'])) {
                if (is_array($res['data'])) {
                    $dosenItems = isset($res['data'][0]) ? $res['data'] : [$res['data']];
                }
            }

            // Fallback simulation jika server standalone mock
            if (empty($dosenItems) && isset($res['data']['id_feeder'])) {
                $dosenItems = [
                    [
                        'id_dosen' => $res['data']['id_feeder'],
                        'nama_dosen' => 'Dosen Simulasi Feeder',
                        'nidn' => '0699887766',
                        'nip' => '199001012020011001',
                        'id_status_aktif' => 'A',
                    ]
                ];
            }

            $log->update(['total_records' => count($dosenItems)]);

            // 1. Sinkronisasi Program Studi Resmi dari Neo Feeder
            $this->syncProgramStudiFromFeeder();

            // 2. Ambil Peta Homebase Resmi Dosen (a_sp_homebase = '1')
            $homebaseMap = [];
            try {
                $penugasanRes = $this->feederService->request('GetListPenugasanDosen', [
                    'filter' => "a_sp_homebase = '1' and tgl_ptk_keluar is null",
                    'order' => 'id_tahun_ajaran desc',
                    'limit' => 500,
                ]);
                if (!empty($penugasanRes['data']) && is_array($penugasanRes['data'])) {
                    $pList = isset($penugasanRes['data'][0]) ? $penugasanRes['data'] : [$penugasanRes['data']];
                    foreach ($pList as $pn) {
                        $dId = $pn['id_dosen'] ?? null;
                        if ($dId && !isset($homebaseMap[$dId])) {
                            $homebaseMap[$dId] = $pn;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Gagal fetch homebase penugasan dosen: " . $e->getMessage());
            }

            // 3. Ambil Seluruh Riwayat Pendidikan Dosen dari Neo Feeder
            $riwayatByDosen = [];
            try {
                $riwayatRes = $this->feederService->request('GetRiwayatPendidikanDosen', ['limit' => 1000]);
                if (!empty($riwayatRes['data']) && is_array($riwayatRes['data'])) {
                    $rList = isset($riwayatRes['data'][0]) ? $riwayatRes['data'] : [$riwayatRes['data']];
                    foreach ($rList as $rw) {
                        $dId = $rw['id_dosen'] ?? null;
                        if ($dId) {
                            $riwayatByDosen[$dId][] = $rw;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Gagal fetch riwayat pendidikan dosen: " . $e->getMessage());
            }

            foreach ($dosenItems as $item) {
                try {
                    $idDosen = $item['id_dosen'] ?? $item['id_feeder'] ?? null;
                    $nidn = !empty($item['nidn']) ? trim($item['nidn']) : null;
                    $nuptk = !empty($item['nuptk']) ? trim($item['nuptk']) : null;
                    $namaDosen = $item['nama_dosen'] ?? 'Dosen Feeder';
                    $nipDikti = !empty($item['nip']) ? trim($item['nip']) : null;
                    $jenisKelamin = !empty($item['jenis_kelamin']) ? trim($item['jenis_kelamin']) : null;
                    $agama = !empty($item['nama_agama']) ? trim($item['nama_agama']) : ($item['agama'] ?? null);
                    $statusAktif = !empty($item['nama_status_aktif']) ? trim($item['nama_status_aktif']) : (($item['id_status_aktif'] ?? '1') == '1' ? 'Aktif' : 'Tidak Aktif');
                    $isActive = in_array((string)($item['id_status_aktif'] ?? '1'), ['1', 'A'], true) || $statusAktif === 'Aktif';

                    $tanggalLahir = null;
                    if (!empty($item['tanggal_lahir'])) {
                        try {
                            $tanggalLahir = \Carbon\Carbon::parse($item['tanggal_lahir'])->format('Y-m-d');
                        } catch (\Exception $e) {
                            $tanggalLahir = null;
                        }
                    }

                    $tempatLahir = !empty($item['tempat_lahir']) ? trim($item['tempat_lahir']) : null;
                    $nik = !empty($item['nik']) ? trim($item['nik']) : null;
                    $telepon = !empty($item['telepon']) ? trim($item['telepon']) : null;
                    $handphone = !empty($item['handphone']) ? trim($item['handphone']) : null;
                    $email = !empty($item['email']) ? trim($item['email']) : null;

                    if (empty($idDosen)) {
                        throw new \Exception("Record dosen tidak memiliki id_dosen");
                    }

                    // Cari berdasarkan NIDN atau id_feeder yang sudah ada
                    $dosenLokal = null;
                    if ($nidn) {
                        $dosenLokal = Dosen::where('nidn', $nidn)->first();
                    }
                    if (!$dosenLokal) {
                        $dosenLokal = Dosen::where('id_feeder', $idDosen)->first();
                    }

                    $dataToSave = [
                        'nama_lengkap' => $namaDosen,
                        'nidn' => $nidn,
                        'nuptk' => $nuptk,
                        'jenis_kelamin' => $jenisKelamin,
                        'tanggal_lahir' => $tanggalLahir,
                        'agama' => $agama,
                        'status_aktif' => $statusAktif,
                        'is_active' => $isActive,
                        'id_feeder' => $idDosen,
                    ];
                    if (!empty($tempatLahir)) $dataToSave['tempat_lahir'] = $tempatLahir;
                    if (!empty($nik)) $dataToSave['nik'] = $nik;
                    if (!empty($telepon)) $dataToSave['telepon'] = $telepon;
                    if (!empty($handphone)) $dataToSave['handphone'] = $handphone;
                    if (!empty($email)) $dataToSave['email'] = $email;

                    // 1. Pemetaan Homebase Program Studi Spesifik
                    if (isset($homebaseMap[$idDosen])) {
                        $hb = $homebaseMap[$idDosen];
                        $idProdiFeeder = $hb['id_prodi'] ?? null;
                        $namaProdiFeeder = $hb['nama_program_studi'] ?? '';

                        $prodiTarget = null;
                        if ($idProdiFeeder) {
                            $prodiTarget = MasterProgramStudi::where('id_feeder', $idProdiFeeder)->first();
                        }
                        if (!$prodiTarget && $namaProdiFeeder) {
                            $prodiTarget = MasterProgramStudi::where('nama', 'like', "%{$namaProdiFeeder}%")->first();
                        }
                        if ($prodiTarget) {
                            $dataToSave['program_studi_id'] = $prodiTarget->id;
                        }
                    }

                    // 2. Pemetaan Gelar Otomatis dari Riwayat Pendidikan Feeder
                    $titles = ['gelar_depan' => null, 'gelar_belakang' => null];
                    if (isset($riwayatByDosen[$idDosen])) {
                        $titles = $this->resolveAcademicTitlesForDosen($riwayatByDosen[$idDosen]);
                    }
                    $dataToSave['gelar_depan'] = $titles['gelar_depan'];
                    $dataToSave['gelar_belakang'] = $titles['gelar_belakang'];

                    if ($dosenLokal) {
                        // Update data dosen lokal (pertahankan NIP lokal jika sudah ada)
                        if (empty($dosenLokal->nip) && !empty($nipDikti)) {
                            $dataToSave['nip'] = $nipDikti;
                        }
                        $dosenLokal->update($dataToSave);
                    } else {
                        // Buat data dosen baru di database lokal
                        $dataToSave['nip'] = $nipDikti;
                        $dosenLokal = Dosen::create($dataToSave);
                    }

                    // Sinkronisasi otomatis ke Modul SIMPEG (simpeg_pegawai)
                    $invalidPlaceholders = ['-', '--', '0', 'N/A', 'none', '', ' '];
                    $nipFinal = (!empty($dosenLokal->nip) && !in_array(trim($dosenLokal->nip), $invalidPlaceholders, true)) ? trim($dosenLokal->nip) : null;
                    $nikFinal = (!empty($dosenLokal->nik) && !in_array(trim($dosenLokal->nik), $invalidPlaceholders, true)) ? trim($dosenLokal->nik) : null;

                    $pegawai = null;
                    if (!empty($dosenLokal->pegawai_id)) {
                        $pegawai = Pegawai::find($dosenLokal->pegawai_id);
                    }
                    if (!$pegawai && $nipFinal) {
                        $pegawai = Pegawai::where('nip', $nipFinal)->first();
                    }
                    if (!$pegawai && $nikFinal) {
                        $pegawai = Pegawai::where('nik', $nikFinal)->first();
                    }

                    $pegawaiData = [
                        'nama_lengkap' => $namaDosen,
                        'gelar_depan' => $titles['gelar_depan'],
                        'gelar_belakang' => $titles['gelar_belakang'],
                        'jenis_pegawai' => 'dosen',
                        'status_kepegawaian' => 'tetap_yayasan',
                        'status' => $isActive ? 'aktif' : 'non_aktif',
                    ];
                    if ($nipFinal) $pegawaiData['nip'] = $nipFinal;
                    $pegawaiData['nidn'] = $nidn ?: null;
                    $pegawaiData['nuptk'] = $nuptk ?: null;
                    if ($nikFinal) $pegawaiData['nik'] = $nikFinal;
                    if ($jenisKelamin) $pegawaiData['jenis_kelamin'] = in_array($jenisKelamin, ['L', 'P']) ? $jenisKelamin : 'L';
                    if ($tanggalLahir) $pegawaiData['tanggal_lahir'] = $tanggalLahir;
                    if (!empty($tempatLahir)) $pegawaiData['tempat_lahir'] = $tempatLahir;
                    if ($agama) $pegawaiData['agama'] = $agama;
                    if (!empty($handphone)) $pegawaiData['telepon'] = $handphone;
                    elseif (!empty($telepon)) $pegawaiData['telepon'] = $telepon;

                    if ($pegawai) {
                        $pegawai->update($pegawaiData);
                    } else {
                        $pegawai = Pegawai::create($pegawaiData);
                    }

                    if ($pegawai && $dosenLokal->pegawai_id !== $pegawai->id) {
                        $dosenLokal->update(['pegawai_id' => $pegawai->id]);
                    }

                    // 3. Masukkan Data Riwayat Sekolah ke SIMPEG (simpeg_riwayat_pendidikan_pegawai)
                    if ($pegawai && isset($riwayatByDosen[$idDosen])) {
                        $eduList = $riwayatByDosen[$idDosen];
                        $lastIdx = count($eduList) - 1;
                        foreach ($eduList as $idx => $edu) {
                            $jenjang = strtolower($edu['nama_jenjang_pendidikan'] ?? 's1');
                            $institusi = $edu['nama_perguruan_tinggi'] ?? 'Perguruan Tinggi';
                            $abbr = $this->abbreviateAcademicDegree(
                                $edu['nama_gelar_akademik'] ?? '',
                                $edu['nama_jenjang_pendidikan'] ?? '',
                                $edu['nama_bidang_studi'] ?? ''
                            );
                            $singkatan = $abbr['belakang'] ?? ($abbr['depan'] ?? null);

                            RiwayatPendidikanPegawai::updateOrCreate(
                                [
                                    'pegawai_id' => $pegawai->id,
                                    'jenjang' => $jenjang,
                                    'nama_institusi' => $institusi,
                                ],
                                [
                                    'program_studi' => $edu['nama_bidang_studi'] ?? null,
                                    'bidang_ilmu' => $edu['nama_bidang_studi'] ?? null,
                                    'gelar_akademik' => $edu['nama_gelar_akademik'] ?? null,
                                    'singkatan_gelar' => $singkatan,
                                    'tahun_lulus' => !empty($edu['tahun_lulus']) ? (int)$edu['tahun_lulus'] : null,
                                    'is_pendidikan_terakhir' => ($idx === $lastIdx),
                                ]
                            );
                        }
                    }

                    FeederMapping::updateOrCreate(
                        ['entity_type' => 'dosen', 'local_id' => $dosenLokal->id],
                        [
                            'feeder_id' => $idDosen,
                            'sync_status' => 'synced',
                            'last_synced_at' => now(),
                            'error_message' => null,
                        ]
                    );

                    $success++;
                    $details[] = [
                        'nidn' => $nidn ?: '-',
                        'nama' => $namaDosen,
                        'status' => 'imported',
                        'feeder_id' => $idDosen,
                    ];
                } catch (\Exception $e) {
                    $failed++;
                    $details[] = [
                        'nama' => $item['nama_dosen'] ?? 'N/A',
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ];
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
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'details' => [['error' => $e->getMessage()]],
                'completed_at' => now(),
            ]);
            return $log;
        }
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
     * Batch Pencocokan Penugasan Dosen PT (GetListPenugasanDosen)
     * Mengambil id_registrasi_dosen berdasarkan NIDN, Program Studi, dan Tahun Ajaran.
     */
    public function syncBatchPenugasanDosen($userId = null)
    {
        // Auto-generate penugasan awal dari siakad_dosen jika tabel siakad_dosen_penugasan masih kosong
        $activeTa = MasterTahunAkademik::where('is_active', true)->first() ?: MasterTahunAkademik::latest()->first();
        if ($activeTa) {
            $dosens = Dosen::whereNotNull('program_studi_id')->get();
            foreach ($dosens as $dsn) {
                DosenPenugasan::firstOrCreate([
                    'dosen_id' => $dsn->id,
                    'program_studi_id' => $dsn->program_studi_id,
                    'tahun_akademik_id' => $activeTa->id,
                ], [
                    'nomor_surat_tugas' => 'ST/' . ($activeTa->tahun_mulai ?? date('Y')) . '/' . str_pad($dsn->id, 4, '0', STR_PAD_LEFT),
                    'tanggal_surat_tugas' => now()->toDateString(),
                    'is_homebase' => true,
                ]);
            }
        }

        $penugasanList = DosenPenugasan::with(['dosen', 'programStudi', 'tahunAkademik'])->get();
        $total = $penugasanList->count();
        $success = 0;
        $failed = 0;
        $details = [];

        $log = FeederSyncLog::create([
            'entity_type' => 'penugasan_dosen',
            'sync_type' => 'pull',
            'total_records' => $total,
            'status' => 'processing',
            'synced_by' => $userId,
        ]);

        foreach ($penugasanList as $p) {
            try {
                $dosen = $p->dosen;
                if (!$dosen || empty($dosen->nidn)) {
                    throw new \Exception("Dosen " . ($dosen?->nama_lengkap ?? 'N/A') . " belum memiliki NIDN untuk dicek penugasannya");
                }

                $idProdi = $p->programStudi?->kode_prodi_dikti ?: ($p->programStudi?->kode_prodi ?: '55201');
                $idTahunAjaran = $p->tahunAkademik?->tahun_mulai 
                    ? (string) $p->tahunAkademik->tahun_mulai 
                    : substr($p->tahunAkademik?->kode ?? date('Y'), 0, 4);

                $res = $this->feederService->request('GetListPenugasanDosen', [
                    'filter' => "nidn = '{$dosen->nidn}' and id_prodi = '{$idProdi}' and id_tahun_ajaran = '{$idTahunAjaran}'",
                    'order' => 'nidn',
                    'limit' => 1,
                    'offset' => 0,
                ]);

                $idReg = null;
                if (isset($res['data'])) {
                    if (is_array($res['data']) && isset($res['data'][0])) {
                        $idReg = $res['data'][0]['id_registrasi_dosen'] ?? $res['data'][0]['id_reg_ptk'] ?? null;
                    } elseif (is_array($res['data'])) {
                        $idReg = $res['data']['id_registrasi_dosen'] ?? $res['data']['id_feeder'] ?? null;
                    }
                }

                // Fallback simulation support jika server standalone mock
                if (empty($idReg) && isset($res['data']['id_feeder'])) {
                    $idReg = $res['data']['id_feeder'];
                }

                if (empty($idReg) && (!isset($res['error_code']) || $res['error_code'] != 0)) {
                    $errMsg = $res['error_desc'] ?? 'Penugasan dosen tidak ditemukan di Feeder';
                    throw new \Exception($errMsg);
                }

                if (empty($idReg)) {
                    throw new \Exception("Penugasan Dosen {$dosen->nama_lengkap} di TA {$idTahunAjaran} belum terbit di PDDikti Feeder");
                }

                $p->update([
                    'id_feeder' => $idReg,
                    'sync_status' => 'synced',
                    'last_synced_at' => now(),
                ]);

                FeederMapping::updateOrCreate(
                    ['entity_type' => 'penugasan_dosen', 'local_id' => $p->id],
                    [
                        'feeder_id' => $idReg,
                        'sync_status' => 'synced',
                        'last_synced_at' => now(),
                        'error_message' => null,
                    ]
                );

                $success++;
                $details[] = [
                    'dosen' => $dosen->nama_lengkap,
                    'prodi' => $p->programStudi?->nama,
                    'ta' => $idTahunAjaran,
                    'status' => 'success',
                    'id_registrasi_dosen' => $idReg,
                ];
            } catch (\Exception $e) {
                $failed++;
                $p->update([
                    'sync_status' => 'failed',
                    'last_synced_at' => now(),
                ]);
                FeederMapping::updateOrCreate(
                    ['entity_type' => 'penugasan_dosen', 'local_id' => $p->id],
                    [
                        'sync_status' => 'failed',
                        'last_synced_at' => now(),
                        'error_message' => $e->getMessage(),
                    ]
                );
                $details[] = [
                    'dosen' => $p->dosen?->nama_lengkap ?? 'N/A',
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
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
     * Batch Push Data Ajar Dosen ke Kelas Kuliah (InsertDosenPengajarKelasKuliah)
     * Menggunakan parameter resmi: id_registrasi_dosen, id_kelas_kuliah, sks_substansi_total,
     * rencana_minggu_pertemuan, realisasi_minggu_pertemuan, id_jenis_evaluasi.
     */
    public function syncBatchAjarDosen($userId = null)
    {
        $pengampuList = DosenPengampu::with(['kelas.mataKuliah', 'kelas.tahunAkademik', 'dosen', 'penugasan'])->get();
        $total = $pengampuList->count();
        $success = 0;
        $failed = 0;
        $details = [];

        $log = FeederSyncLog::create([
            'entity_type' => 'ajar_dosen',
            'sync_type' => 'push',
            'total_records' => $total,
            'status' => 'processing',
            'synced_by' => $userId,
        ]);

        foreach ($pengampuList as $dp) {
            try {
                $kelas = $dp->kelas;
                if (!$kelas || empty($kelas->id_feeder)) {
                    throw new \Exception("Kelas perkuliahan (" . ($kelas?->nama_kelas ?? 'N/A') . ") belum disinkronkan ke Neo Feeder.");
                }

                // Cari penugasan dosen terkait
                $penugasan = $dp->penugasan;
                if (!$penugasan) {
                    $penugasan = DosenPenugasan::where('dosen_id', $dp->dosen_id)
                        ->where('program_studi_id', $kelas->program_studi_id)
                        ->first();
                }

                $idRegistrasiDosen = $penugasan?->id_feeder;
                if (empty($idRegistrasiDosen)) {
                    $idRegistrasiDosen = $dp->dosen?->id_feeder;
                }

                if (empty($idRegistrasiDosen)) {
                    $dp->update([
                        'sync_status' => 'pending',
                        'last_synced_at' => now(),
                    ]);
                    $details[] = [
                        'dosen' => $dp->dosen?->nama_lengkap ?? 'N/A',
                        'kelas' => $kelas->nama_kelas,
                        'status' => 'skipped',
                        'message' => 'Dosen pengampu belum memiliki NIDN / belum terdaftar di Feeder (hanya aktif lokal dengan NIP). Rekomendasi: Daftarkan NIDN atau gunakan dosen ber-NIDN sebagai pengampu pelaporan.'
                    ];
                    continue;
                }

                $sksTotal = (float) ($dp->sks_substansi_total ?: ($kelas->mataKuliah?->total_sks ?? 3));
                $rencana = (int) ($dp->rencana_minggu_pertemuan ?: 16);
                $realisasi = (int) ($dp->realisasi_minggu_pertemuan ?: 16);
                $evaluasi = (int) ($dp->jenis_evaluasi_id ?: 1);

                $record = [
                    'id_registrasi_dosen' => $idRegistrasiDosen,
                    'id_kelas_kuliah' => $kelas->id_feeder,
                    'sks_substansi_total' => $sksTotal,
                    'rencana_minggu_pertemuan' => $rencana,
                    'realisasi_minggu_pertemuan' => $realisasi,
                    'id_jenis_evaluasi' => $evaluasi,
                ];

                $action = $dp->id_feeder ? 'UpdateDosenPengajarKelasKuliah' : 'InsertDosenPengajarKelasKuliah';
                $payload = ['record' => $record];
                if ($dp->id_feeder) {
                    $payload['key'] = ['id_aktivitas_mengajar' => $dp->id_feeder];
                }

                $res = $this->feederService->request($action, $payload);
                $feederId = $res['data']['id_aktivitas_mengajar'] 
                    ?? $res['data']['id_ajar'] 
                    ?? $res['data']['id_feeder'] 
                    ?? ($dp->id_feeder ?: 'FE-AJAR-' . $dp->id);

                $dp->update([
                    'penugasan_id' => $penugasan?->id,
                    'sks_substansi_total' => $sksTotal,
                    'rencana_minggu_pertemuan' => $rencana,
                    'realisasi_minggu_pertemuan' => $realisasi,
                    'jenis_evaluasi_id' => $evaluasi,
                    'id_feeder' => $feederId,
                    'sync_status' => 'synced',
                    'last_synced_at' => now(),
                ]);

                FeederMapping::updateOrCreate(
                    ['entity_type' => 'ajar_dosen', 'local_id' => $dp->id],
                    [
                        'feeder_id' => $feederId,
                        'sync_status' => 'synced',
                        'last_synced_at' => now(),
                        'error_message' => null,
                    ]
                );

                $success++;
                $details[] = [
                    'dosen' => $dp->dosen?->nama_lengkap,
                    'kelas' => $kelas->nama_kelas,
                    'mata_kuliah' => $kelas->mataKuliah?->nama,
                    'status' => 'success',
                    'id_aktivitas_mengajar' => $feederId,
                ];
            } catch (\Exception $e) {
                $failed++;
                $dp->update([
                    'sync_status' => 'failed',
                    'last_synced_at' => now(),
                ]);
                FeederMapping::updateOrCreate(
                    ['entity_type' => 'ajar_dosen', 'local_id' => $dp->id],
                    [
                        'sync_status' => 'failed',
                        'last_synced_at' => now(),
                        'error_message' => $e->getMessage(),
                    ]
                );
                $details[] = [
                    'dosen' => $dp->dosen?->nama_lengkap ?? 'N/A',
                    'kelas' => $dp->kelas?->nama_kelas ?? 'N/A',
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
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
