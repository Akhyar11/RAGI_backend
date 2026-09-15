<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPenugasan;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\MataKuliah;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\Spmb\MasterTahunAkademik;
use App\Services\Siakad\NeoFeederService;
use App\Services\Siakad\NeoFeederSyncService;
use Laravel\Passport\Passport;

class SiakadFeederDosenSyncTest extends TestCase
{
    use RefreshDatabase;

    private NeoFeederService $mockFeederService;
    private NeoFeederSyncService $syncService;
    private MasterProgramStudi $prodi;
    private MasterTahunAkademik $tahunAkademik;
    private Dosen $dosen;
    private Kelas $kelas;
    private DosenPengampu $dosenPengampu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prodi = MasterProgramStudi::firstOrCreate([
            'kode_prodi' => 'TI-TEST',
        ], [
            'kode_prodi_dikti' => '55201',
            'nama' => 'Teknik Informatika Test',
            'jenjang' => 'S1',
        ]);

        $this->tahunAkademik = MasterTahunAkademik::firstOrCreate([
            'kode' => '20261-TEST',
        ], [
            'nama' => '2026/2027 Ganjil Test',
            'tahun_mulai' => 2026,
            'tahun_selesai' => 2027,
            'is_active' => true,
        ]);

        $this->dosen = Dosen::firstOrCreate([
            'nidn' => '0612345678',
        ], [
            'nama_lengkap' => 'Dr. Test Dosen, M.Kom',
            'program_studi_id' => $this->prodi->id,
            'is_active' => true,
        ]);

        $kurikulum = Kurikulum::firstOrCreate([
            'kode' => 'KUR-TEST-2026',
        ], [
            'program_studi_id' => $this->prodi->id,
            'nama' => 'Kurikulum Test 2026',
            'tahun_berlaku' => 2026,
        ]);

        $mataKuliah = MataKuliah::firstOrCreate([
            'kode_mk' => 'MK-TEST-001',
        ], [
            'kurikulum_id' => $kurikulum->id,
            'nama' => 'Pemrograman Web Lanjut',
            'total_sks' => 3,
            'sks_teori' => 2,
            'sks_praktik' => 1,
        ]);

        $this->kelas = Kelas::firstOrCreate([
            'mata_kuliah_id' => $mataKuliah->id,
            'tahun_akademik_id' => $this->tahunAkademik->id,
            'kode_kelas' => 'KLS-TI-A',
        ], [
            'program_studi_id' => $this->prodi->id,
            'nama_kelas' => 'TI-2026-A',
            'kapasitas' => 30,
            'status' => 'aktif',
            'id_feeder' => 'FE-KLS-UUID-12345',
        ]);

        $this->dosenPengampu = DosenPengampu::firstOrCreate([
            'kelas_id' => $this->kelas->id,
            'dosen_id' => $this->dosen->id,
        ], [
            'peran' => 'pengampu_utama',
            'sks_substansi_total' => 3.00,
            'rencana_minggu_pertemuan' => 16,
            'realisasi_minggu_pertemuan' => 16,
            'jenis_evaluasi_id' => 1,
        ]);
    }

    public function test_sync_batch_dosen_matches_nidn_via_detail_biodata_dosen()
    {
        $mockFeeder = $this->createMock(NeoFeederService::class);
        $mockFeeder->expects($this->once())
            ->method('request')
            ->with(
                'DetailBiodataDosen',
                $this->callback(function ($payload) {
                    return isset($payload['filter']) && str_contains($payload['filter'], $this->dosen->nidn);
                })
            )
            ->willReturn([
                'error_code' => 0,
                'data' => [
                    [
                        'id_dosen' => 'UUID-DOSEN-FEEDER-001',
                        'nama_dosen' => $this->dosen->nama_lengkap,
                        'nidn' => $this->dosen->nidn,
                    ]
                ]
            ]);

        $service = new NeoFeederSyncService($mockFeeder);
        $log = $service->syncBatchDosen();

        $this->assertEquals('dosen', $log->entity_type);
        $this->assertGreaterThanOrEqual(1, $log->success_count);

        $this->dosen->refresh();
        $this->assertEquals('UUID-DOSEN-FEEDER-001', $this->dosen->id_feeder);
    }

    public function test_sync_batch_penugasan_dosen_retrieves_id_registrasi_dosen()
    {
        $mockFeeder = $this->createMock(NeoFeederService::class);
        $mockFeeder->expects($this->atLeastOnce())
            ->method('request')
            ->with(
                'GetListPenugasanDosen',
                $this->callback(function ($payload) {
                    return isset($payload['filter']) && str_contains($payload['filter'], $this->dosen->nidn);
                })
            )
            ->willReturn([
                'error_code' => 0,
                'data' => [
                    [
                        'id_registrasi_dosen' => 'UUID-REG-DOSEN-PT-789',
                        'id_dosen' => 'UUID-DOSEN-FEEDER-001',
                    ]
                ]
            ]);

        $service = new NeoFeederSyncService($mockFeeder);
        $log = $service->syncBatchPenugasanDosen();

        $this->assertEquals('penugasan_dosen', $log->entity_type);
        $this->assertGreaterThanOrEqual(1, $log->success_count);

        $penugasan = DosenPenugasan::where('dosen_id', $this->dosen->id)->first();
        $this->assertNotNull($penugasan);
        $this->assertEquals('UUID-REG-DOSEN-PT-789', $penugasan->id_feeder);
        $this->assertEquals('synced', $penugasan->sync_status);
    }

    public function test_sync_batch_ajar_dosen_posts_with_valid_feeder_columns()
    {
        $penugasan = DosenPenugasan::updateOrCreate([
            'dosen_id' => $this->dosen->id,
            'program_studi_id' => $this->prodi->id,
            'tahun_akademik_id' => $this->tahunAkademik->id,
        ], [
            'id_feeder' => 'UUID-REG-DOSEN-PT-789',
            'sync_status' => 'synced',
        ]);

        $this->dosenPengampu->update([
            'penugasan_id' => $penugasan->id,
            'sks_substansi_total' => 3.00,
            'rencana_minggu_pertemuan' => 16,
            'realisasi_minggu_pertemuan' => 16,
        ]);

        $mockFeeder = $this->createMock(NeoFeederService::class);
        $mockFeeder->expects($this->atLeastOnce())
            ->method('request')
            ->with(
                'InsertDosenPengajarKelasKuliah',
                $this->callback(function ($payload) {
                    $record = $payload['record'] ?? [];
                    return isset($record['id_registrasi_dosen']) &&
                           $record['id_registrasi_dosen'] === 'UUID-REG-DOSEN-PT-789' &&
                           isset($record['id_kelas_kuliah']) &&
                           $record['id_kelas_kuliah'] === 'FE-KLS-UUID-12345' &&
                           isset($record['rencana_minggu_pertemuan']) &&
                           $record['rencana_minggu_pertemuan'] === 16 &&
                           isset($record['realisasi_minggu_pertemuan']) &&
                           $record['realisasi_minggu_pertemuan'] === 16;
                })
            )
            ->willReturn([
                'error_code' => 0,
                'data' => [
                    'id_aktivitas_mengajar' => 'UUID-AJAR-FEEDER-999',
                ]
            ]);

        $service = new NeoFeederSyncService($mockFeeder);
        $log = $service->syncBatchAjarDosen();

        $this->assertEquals('ajar_dosen', $log->entity_type);
        $this->assertGreaterThanOrEqual(1, $log->success_count);

        $this->dosenPengampu->refresh();
        $this->assertEquals('UUID-AJAR-FEEDER-999', $this->dosenPengampu->id_feeder);
        $this->assertEquals('synced', $this->dosenPengampu->sync_status);
    }

    public function test_controller_trigger_sync_validates_entity_types()
    {
        $admin = User::factory()->create();
        Passport::actingAs($admin);

        // Invalid entity type should fail validation
        $responseInvalid = $this->postJson('/api/v1/siakad/feeder-sync/trigger', [
            'entity_type' => 'invalid_entity_type',
        ]);
        $responseInvalid->assertStatus(422);

        // Valid entity types should be accepted
        $responseDosen = $this->postJson('/api/v1/siakad/feeder-sync/trigger', [
            'entity_type' => 'dosen',
        ]);
        $responseDosen->assertStatus(200);

        $responsePenugasan = $this->postJson('/api/v1/siakad/feeder-sync/trigger', [
            'entity_type' => 'penugasan_dosen',
        ]);
        $responsePenugasan->assertStatus(200);

        $responseAjar = $this->postJson('/api/v1/siakad/feeder-sync/trigger', [
            'entity_type' => 'ajar_dosen',
        ]);
        $responseAjar->assertStatus(200);

        $responsePull = $this->postJson('/api/v1/siakad/feeder-sync/trigger', [
            'entity_type' => 'pull_dosen',
        ]);
        $responsePull->assertStatus(200);
    }

    public function test_sync_batch_dosen_gracefully_skips_lecturers_with_only_nip()
    {
        $dosenBaru = Dosen::create([
            'nip' => 'NIP-INTERNAL-2026-001',
            'nidn' => null,
            'nama_lengkap' => 'Dosen Baru Hanya NIP, S.Kom',
            'program_studi_id' => $this->prodi->id,
            'is_active' => true,
        ]);

        $mockFeeder = $this->createMock(NeoFeederService::class);
        $mockFeeder->expects($this->any())
            ->method('request')
            ->willReturn([
                'error_code' => 0,
                'data' => [
                    ['id_dosen' => 'UUID-TEST-001', 'nidn' => $this->dosen->nidn]
                ]
            ]);

        $service = new NeoFeederSyncService($mockFeeder);
        $log = $service->syncBatchDosen();

        $this->assertNotEquals('failed', $log->status);
        $dosenBaru->refresh();
        $this->assertNull($dosenBaru->nidn);
        $this->assertEquals('NIP-INTERNAL-2026-001', $dosenBaru->nip);
    }

    public function test_pull_batch_dosen_from_feeder_imports_and_updates_local_lecturers()
    {
        $dosenExisting = Dosen::create([
            'nip' => 'NIP-KAMPUS-TETAP-999',
            'nidn' => '0688776655',
            'nama_lengkap' => 'Dosen Lama Lokal',
            'program_studi_id' => $this->prodi->id,
            'is_active' => true,
        ]);

        $mockFeeder = $this->createMock(NeoFeederService::class);
        $mockFeeder->method('request')
            ->willReturnCallback(function ($act) {
                if ($act === 'GetListDosen') {
                    return [
                        'error_code' => 0,
                        'data' => [
                            [
                                'id_dosen' => 'UUID-PULL-DOSEN-EXISTING',
                                'nama_dosen' => 'Dosen Lama Lokal (Update Dikti)',
                                'nidn' => '0688776655',
                                'nip' => 'NIP-DIKTI-BEDA',
                                'id_status_aktif' => 'A',
                            ],
                            [
                                'id_dosen' => 'UUID-PULL-DOSEN-BARU',
                                'nama_dosen' => 'Dosen Baru Impor Dikti',
                                'nidn' => '0611223344',
                                'nuptk' => '3560763664230999',
                                'nip' => '199501012025011002',
                                'jenis_kelamin' => 'L',
                                'nama_agama' => 'Islam',
                                'tanggal_lahir' => '15-05-1995',
                                'id_status_aktif' => '1',
                                'nama_status_aktif' => 'Aktif',
                            ],
                        ]
                    ];
                }
                return ['error_code' => 0, 'data' => []];
            });

        $service = new NeoFeederSyncService($mockFeeder);
        $log = $service->pullBatchDosenFromFeeder();

        $this->assertEquals('dosen', $log->entity_type);
        $this->assertEquals(2, $log->success_count);

        // Verifikasi dosen existing: NIP lokal tetap dipertahankan, id_feeder diperbarui
        $dosenExisting->refresh();
        $this->assertEquals('UUID-PULL-DOSEN-EXISTING', $dosenExisting->id_feeder);
        $this->assertEquals('NIP-KAMPUS-TETAP-999', $dosenExisting->nip);

        // Verifikasi dosen baru dari Feeder berhasil di-create beserta biodatanya
        $dosenBaru = Dosen::where('nidn', '0611223344')->first();
        $this->assertNotNull($dosenBaru);
        $this->assertEquals('UUID-PULL-DOSEN-BARU', $dosenBaru->id_feeder);
        $this->assertEquals('Dosen Baru Impor Dikti', $dosenBaru->nama_lengkap);
        $this->assertEquals('3560763664230999', $dosenBaru->nuptk);
        $this->assertEquals('L', $dosenBaru->jenis_kelamin);
        $this->assertEquals('Islam', $dosenBaru->agama);
        $this->assertEquals('Aktif', $dosenBaru->status_aktif);
        $this->assertEquals('1995-05-15', $dosenBaru->tanggal_lahir?->format('Y-m-d'));

        // Verifikasi terhubung ke modul SIMPEG (simpeg_pegawai)
        $this->assertNotNull($dosenBaru->pegawai_id);
        $pegawai = \App\Models\Simpeg\Pegawai::find($dosenBaru->pegawai_id);
        $this->assertNotNull($pegawai);
        $this->assertEquals('Dosen Baru Impor Dikti', $pegawai->nama_lengkap);
        $this->assertEquals('dosen', $pegawai->jenis_pegawai);
        $this->assertEquals('aktif', $pegawai->status);
    }

    public function test_sync_batch_ajar_dosen_gracefully_handles_non_nidn_lecturers()
    {
        $dosenNonNidn = Dosen::create([
            'nip' => 'NIP-BARU-AJAR-002',
            'nidn' => null,
            'nama_lengkap' => 'Dosen Mengajar Tanpa NIDN',
            'program_studi_id' => $this->prodi->id,
            'is_active' => true,
        ]);

        $kelasBaru = Kelas::create([
            'mata_kuliah_id' => $this->kelas->mata_kuliah_id,
            'tahun_akademik_id' => $this->tahunAkademik->id,
            'program_studi_id' => $this->prodi->id,
            'kode_kelas' => 'KLS-TI-B',
            'nama_kelas' => 'TI-2026-B',
            'kapasitas' => 30,
            'status' => 'aktif',
            'id_feeder' => 'FE-KLS-UUID-99999',
        ]);

        $pengampuNonNidn = DosenPengampu::create([
            'kelas_id' => $kelasBaru->id,
            'dosen_id' => $dosenNonNidn->id,
            'peran' => 'pengampu_utama',
        ]);

        $mockFeeder = $this->createMock(NeoFeederService::class);
        // Pastikan kelas ini di-skip tanpa melempar exception fatal
        $service = new NeoFeederSyncService($mockFeeder);
        $log = $service->syncBatchAjarDosen();

        $this->assertEquals('ajar_dosen', $log->entity_type);
        $pengampuNonNidn->refresh();
        $this->assertEquals('pending', $pengampuNonNidn->sync_status);
        $this->assertNull($pengampuNonNidn->id_feeder);
    }

    public function test_list_dosen_automatically_syncs_unsynced_simpeg_dosen()
    {
        $pegawaiDosen = \App\Models\Simpeg\Pegawai::create([
            'nama_lengkap' => 'Dr. Siakad Sync Dosen, M.Kom.',
            'nip' => '198909092020011005',
            'nidn' => '0609098901',
            'jenis_pegawai' => 'dosen',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);

        $admin = User::factory()->create(['id' => 9999]);
        $response = $this->actingAs($admin, 'api')
            ->getJson('/api/v1/siakad/akademik/dosen?search=0609098901');

        $response->assertStatus(200);
        $items = $response->json('data');
        $this->assertNotEmpty($items);
        $this->assertEquals('0609098901', $items[0]['nidn']);

        $this->assertDatabaseHas('siakad_dosen', [
            'pegawai_id' => $pegawaiDosen->id,
            'nidn' => '0609098901',
            'is_active' => true,
        ]);
    }
}
