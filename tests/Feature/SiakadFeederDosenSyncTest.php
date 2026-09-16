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
                            [
                                'id_dosen' => 'UUID-PULL-DOSEN-INACTIVE',
                                'nama_dosen' => 'Dosen Feeder Status Keluar',
                                'nidn' => '0699001122',
                                'nuptk' => '3560763664230111',
                                'nip' => '198001012010011003',
                                'jenis_kelamin' => 'P',
                                'nama_agama' => 'Islam',
                                'tanggal_lahir' => '01-01-1980',
                                'id_status_aktif' => 'N',
                                'nama_status_aktif' => 'Keluar',
                            ],
                        ]
                    ];
                }
                return ['error_code' => 0, 'data' => []];
            });

        $service = new NeoFeederSyncService($mockFeeder);
        $log = $service->pullBatchDosenFromFeeder();

        $this->assertEquals('dosen', $log->entity_type);
        $this->assertEquals(3, $log->success_count);

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

        // Verifikasi otomatis dibuatkan akun SSO dengan username skala prioritas NIDN (hanya untuk yang aktif)
        $this->assertNotNull($pegawai->user_id);
        $this->assertEquals($pegawai->user_id, $dosenBaru->user_id);
        $ssoUser = \App\Models\User::find($pegawai->user_id);
        $this->assertNotNull($ssoUser);
        $this->assertEquals('0611223344', $ssoUser->username); // Prioritas 1: NIDN
        $this->assertTrue($ssoUser->roles->contains('slug', 'dosen'));

        // Verifikasi dosen tidak aktif TIDAK dibuatkan akun SSO
        $dosenInactive = Dosen::where('nidn', '0699001122')->first();
        $this->assertNotNull($dosenInactive);
        $this->assertEquals('Keluar', $dosenInactive->status_aktif);
        $this->assertFalse((bool)$dosenInactive->is_active);
        $this->assertNull($dosenInactive->user_id);
        $pegawaiInactive = \App\Models\Simpeg\Pegawai::find($dosenInactive->pegawai_id);
        $this->assertNotNull($pegawaiInactive);
        $this->assertEquals('non_aktif', $pegawaiInactive->status);
        $this->assertNull($pegawaiInactive->user_id);
        $this->assertDatabaseMissing('core_users', ['username' => '0699001122']);
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

    public function test_list_dosen_is_pure_read_only_and_does_not_mutate_database()
    {
        $pegawaiDosen = \App\Models\Simpeg\Pegawai::create([
            'nama_lengkap' => 'Dr. Unsynced Pegawai, M.Kom.',
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

        // Verifikasi bahwa GET request MURNI read-only, TIDAK melakukan operasi tulis ke siakad_dosen
        $this->assertDatabaseMissing('siakad_dosen', [
            'nidn' => '0609098901',
        ]);
    }

    public function test_store_dosen_protects_against_mass_assignment_injection()
    {
        $admin = User::factory()->create();

        $payload = [
            'nama_lengkap' => 'Dosen Whitelist Test, S.Kom.',
            'nidn' => '0655443322',
            'nip' => '199101012020011002',
            'program_studi_id' => $this->prodi->id,
            'jabatan_akademik' => 'Asisten Ahli',
            // Field injeksi ilegal yang TIDAK boleh ter-assign
            'user_id' => 88888,
            'id_feeder' => 'MALICIOUS-FEEDER-UUID',
            'feeder_raw' => ['injected' => true],
        ];

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/siakad/akademik/dosen', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $dosen = Dosen::where('nidn', '0655443322')->first();
        $this->assertNotNull($dosen);
        $this->assertEquals('Dosen Whitelist Test, S.Kom.', $dosen->nama_lengkap);

        // Pastikan field injeksi tidak tersimpan (mass-assignment tertutup)
        $this->assertNull($dosen->user_id);
        $this->assertNull($dosen->id_feeder);
        $this->assertNull($dosen->feeder_raw);
    }

    public function test_update_dosen_allows_same_nidn_and_rejects_duplicate_nidn()
    {
        $admin = User::factory()->create();

        $dosenA = Dosen::create([
            'nama_lengkap' => 'Dosen A Awal',
            'nidn' => '0611112222',
            'program_studi_id' => $this->prodi->id,
            'is_active' => true,
        ]);

        $dosenB = Dosen::create([
            'nama_lengkap' => 'Dosen B',
            'nidn' => '0633334444',
            'program_studi_id' => $this->prodi->id,
            'is_active' => true,
        ]);

        // 1. Update Dosen A dengan NIDN yang sama persis (Rule::unique ignore self) harus BERHASIL (200)
        $resSelf = $this->actingAs($admin, 'api')
            ->putJson("/api/v1/siakad/akademik/dosen/{$dosenA->id}", [
                'nama_lengkap' => 'Dosen A Diperbarui',
                'nidn' => '0611112222',
                'program_studi_id' => $this->prodi->id,
            ]);

        $resSelf->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $dosenA->refresh();
        $this->assertEquals('Dosen A Diperbarui', $dosenA->nama_lengkap);

        // 2. Update Dosen A menggunakan NIDN milik Dosen B harus DITOLAK (422)
        $resConflict = $this->actingAs($admin, 'api')
            ->putJson("/api/v1/siakad/akademik/dosen/{$dosenA->id}", [
                'nama_lengkap' => 'Dosen A Tabrakan',
                'nidn' => '0633334444', // Duplikat dari Dosen B
                'program_studi_id' => $this->prodi->id,
            ]);

        $resConflict->assertStatus(422)
            ->assertJsonValidationErrors(['nidn']);
    }

    public function test_pull_dosen_restores_soft_deleted_dosen_and_pegawai_without_duplication()
    {
        $pegawai = \App\Models\Simpeg\Pegawai::create([
            'nama_lengkap' => 'Dosen Soft Deleted',
            'nidn' => '0677889900',
            'nip' => '198701012015011009',
            'jenis_pegawai' => 'dosen',
            'status' => 'aktif',
        ]);

        $dosen = Dosen::create([
            'pegawai_id' => $pegawai->id,
            'nama_lengkap' => 'Dosen Soft Deleted',
            'nidn' => '0677889900',
            'nip' => '198701012015011009',
            'is_active' => true,
            'status_aktif' => 'Aktif',
        ]);

        // Soft delete both
        $dosen->delete();
        $pegawai->delete();

        $this->assertSoftDeleted('siakad_dosen', ['id' => $dosen->id]);
        $this->assertSoftDeleted('simpeg_pegawai', ['id' => $pegawai->id]);

        $mockFeeder = $this->createMock(NeoFeederService::class);
        $mockFeeder->expects($this->any())
            ->method('request')
            ->willReturnCallback(function ($act, $p) {
                if ($act === 'GetListDosen') {
                    return [
                        'error_code' => 0,
                        'data' => [
                            [
                                'id_dosen' => 'UUID-RESTORE-TEST',
                                'nama_dosen' => 'Dosen Soft Deleted (Restored)',
                                'nidn' => '0677889900',
                                'id_status_aktif' => '1',
                                'nama_status_aktif' => 'Aktif',
                            ]
                        ]
                    ];
                }
                return ['error_code' => 0, 'data' => []];
            });

        $service = new NeoFeederSyncService($mockFeeder);
        $log = $service->pullBatchDosenFromFeeder();

        $this->assertEquals(1, $log->success_count);

        // Verifikasi dosen dan pegawai dipulihkan (restore) dan tidak ada duplikasi
        $dosen->refresh();
        $this->assertFalse($dosen->trashed());
        $this->assertEquals('Dosen Soft Deleted (Restored)', $dosen->nama_lengkap);
        $this->assertEquals('UUID-RESTORE-TEST', $dosen->id_feeder);

        $pegawai->refresh();
        $this->assertFalse($pegawai->trashed());
        $this->assertEquals(1, Dosen::where('nidn', '0677889900')->count());
        $this->assertEquals(1, \App\Models\Simpeg\Pegawai::where('nidn', '0677889900')->count());
    }

    public function test_pull_dosen_matches_by_nuptk_when_nidn_empty()
    {
        $dosenNuptk = Dosen::create([
            'nama_lengkap' => 'Dosen NUPTK Only',
            'nuptk' => '9876543210123456',
            'nidn' => null,
            'is_active' => true,
        ]);

        $mockFeeder = $this->createMock(NeoFeederService::class);
        $mockFeeder->expects($this->any())
            ->method('request')
            ->willReturnCallback(function ($act, $p) {
                if ($act === 'GetListDosen') {
                    return [
                        'error_code' => 0,
                        'data' => [
                            [
                                'id_dosen' => 'UUID-NUPTK-TEST',
                                'nama_dosen' => 'Dosen NUPTK Only Updated',
                                'nuptk' => '9876543210123456',
                                'nidn' => null,
                                'id_status_aktif' => '1',
                                'nama_status_aktif' => 'Aktif',
                            ]
                        ]
                    ];
                }
                return ['error_code' => 0, 'data' => []];
            });

        $service = new NeoFeederSyncService($mockFeeder);
        $log = $service->pullBatchDosenFromFeeder();

        $this->assertEquals(1, $log->success_count);
        $this->assertEquals(1, Dosen::where('nuptk', '9876543210123456')->count());
        $dosenNuptk->refresh();
        $this->assertEquals('UUID-NUPTK-TEST', $dosenNuptk->id_feeder);
        $this->assertEquals('Dosen NUPTK Only Updated', $dosenNuptk->nama_lengkap);
    }

    public function test_pull_dosen_leaves_prodi_null_when_no_exact_match_found()
    {
        $mockFeeder = $this->createMock(NeoFeederService::class);
        $mockFeeder->expects($this->any())
            ->method('request')
            ->willReturnCallback(function ($act, $p) {
                if ($act === 'GetListDosen') {
                    return [
                        'error_code' => 0,
                        'data' => [
                            [
                                'id_dosen' => 'UUID-UNKNOWN-PRODI',
                                'nama_dosen' => 'Dosen Prodi Antah Berantah',
                                'nidn' => '0655443322',
                                'id_status_aktif' => '1',
                            ]
                        ]
                    ];
                }
                if ($act === 'GetListPenugasanDosen') {
                    return [
                        'error_code' => 0,
                        'data' => [
                            [
                                'id_dosen' => 'UUID-UNKNOWN-PRODI',
                                'id_prodi' => 'UNKNOWN-UUID-PRODI-999',
                                'nama_program_studi' => 'Program Studi Yang Tidak Terdaftar Di Lokal',
                            ]
                        ]
                    ];
                }
                return ['error_code' => 0, 'data' => []];
            });

        $service = new NeoFeederSyncService($mockFeeder);
        $log = $service->pullBatchDosenFromFeeder();

        $this->assertEquals(1, $log->success_count);
        $dosen = Dosen::where('nidn', '0655443322')->first();
        $this->assertNotNull($dosen);
        // Harus null dan TIDAK fallback ke MasterProgramStudi::first() atau buat PRODI-DEFAULT
        $this->assertNull($dosen->program_studi_id);
        $this->assertDatabaseMissing('spmb_master_program_studi', ['kode_prodi' => 'PRODI-DEFAULT']);
    }

    public function test_pull_dosen_pagination_loops_beyond_500_records()
    {
        $mockFeeder = $this->createMock(NeoFeederService::class);
        $mockFeeder->expects($this->any())
            ->method('request')
            ->willReturnCallback(function ($act, $p) {
                if ($act === 'GetListDosen') {
                    $offset = $p['offset'] ?? 0;
                    if ($offset === 0) {
                        // Page 1: generate 500 dosen
                        $items = [];
                        for ($i = 1; $i <= 500; $i++) {
                            $items[] = [
                                'id_dosen' => "UUID-PAGE1-{$i}",
                                'nama_dosen' => "Dosen Page 1 No {$i}",
                                'nidn' => sprintf('061000%04d', $i),
                                'id_status_aktif' => '1',
                            ];
                        }
                        return ['error_code' => 0, 'data' => $items];
                    } elseif ($offset === 500) {
                        // Page 2: generate 2 dosen
                        return [
                            'error_code' => 0,
                            'data' => [
                                [
                                    'id_dosen' => 'UUID-PAGE2-1',
                                    'nama_dosen' => 'Dosen Page 2 No 1',
                                    'nidn' => '0620000001',
                                    'id_status_aktif' => '1',
                                ],
                                [
                                    'id_dosen' => 'UUID-PAGE2-2',
                                    'nama_dosen' => 'Dosen Page 2 No 2',
                                    'nidn' => '0620000002',
                                    'id_status_aktif' => '1',
                                ],
                            ]
                        ];
                    }
                    return ['error_code' => 0, 'data' => []];
                }
                return ['error_code' => 0, 'data' => []];
            });

        $service = new NeoFeederSyncService($mockFeeder);
        $log = $service->pullBatchDosenFromFeeder();

        // Total harus 502 (tidak terpotong di 500)
        $this->assertEquals(502, $log->total_records);
        $this->assertEquals(502, $log->success_count);
        $this->assertDatabaseHas('siakad_dosen', ['nidn' => '0620000001']);
        $this->assertDatabaseHas('siakad_dosen', ['nidn' => '0620000002']);
    }

    public function test_pull_dosen_transaction_isolation_allows_valid_records_to_succeed_even_if_one_fails()
    {
        $mockFeeder = $this->createMock(NeoFeederService::class);
        $mockFeeder->expects($this->any())
            ->method('request')
            ->willReturnCallback(function ($act, $p) {
                if ($act === 'GetListDosen') {
                    return [
                        'error_code' => 0,
                        'data' => [
                            [
                                'id_dosen' => null, // ini akan memicu exception "Record dosen tidak memiliki id_dosen"
                                'nama_dosen' => 'Dosen Gagal Missing ID',
                                'nidn' => '0699990001',
                                'id_status_aktif' => '1',
                            ],
                            [
                                'id_dosen' => 'UUID-SUKSES-ISO',
                                'nama_dosen' => 'Dosen Sukses Isolasi',
                                'nidn' => '0699990002',
                                'id_status_aktif' => '1',
                            ],
                        ]
                    ];
                }
                return ['error_code' => 0, 'data' => []];
            });

        $service = new NeoFeederSyncService($mockFeeder);
        $log = $service->pullBatchDosenFromFeeder();

        $this->assertEquals(2, $log->total_records);
        $this->assertEquals(1, $log->success_count);
        $this->assertEquals(1, $log->failed_count);
        $this->assertEquals('partial', $log->status);

        $this->assertDatabaseMissing('siakad_dosen', ['nidn' => '0699990001']);
        $this->assertDatabaseHas('siakad_dosen', ['nidn' => '0699990002']);
    }

    public function test_pull_dosen_maps_real_pddikti_status_and_tanggal_keluar_to_simpeg()
    {
        $mockFeeder = $this->createMock(NeoFeederService::class);
        $mockFeeder->expects($this->any())
            ->method('request')
            ->willReturnCallback(function ($act, $p) {
                if ($act === 'GetListDosen') {
                    return [
                        'error_code' => 0,
                        'data' => [
                            [
                                'id_dosen' => 'UUID-DOSEN-PENSIUN',
                                'nama_dosen' => 'Prof. Pensiun Feeder',
                                'nidn' => '0633445566',
                                'id_status_aktif' => 'P',
                                'nama_status_aktif' => 'Pensiun',
                                'nama_ikatan_kerja' => 'PNS DPK',
                                'tanggal_keluar' => '2025-06-30',
                            ],
                            [
                                'id_dosen' => 'UUID-DOSEN-MENINGGAL',
                                'nama_dosen' => 'Dosen Meninggal Feeder',
                                'nidn' => '0644556677',
                                'id_status_aktif' => 'M',
                                'nama_status_aktif' => 'Meninggal Dunia',
                                'status_kepegawaian' => 'Dosen Tetap Yayasan',
                                'tanggal_keluar' => '2024-12-15',
                            ],
                            [
                                'id_dosen' => 'UUID-DOSEN-KONTRAK-KELUAR',
                                'nama_dosen' => 'Dosen Kontrak Keluar',
                                'nidn' => '0655667788',
                                'id_status_aktif' => 'K',
                                'nama_status_aktif' => 'Mengundurkan Diri',
                                'status_kepegawaian' => 'Perjanjian Kerja Waktu Tertentu',
                                'tanggal_keluar' => '2026-01-10',
                            ],
                        ]
                    ];
                }
                return ['error_code' => 0, 'data' => []];
            });

        $service = new NeoFeederSyncService($mockFeeder);
        $log = $service->pullBatchDosenFromFeeder();

        $this->assertEquals(3, $log->success_count);

        // 1. Verifikasi Dosen Pensiun
        $dosenPensiun = Dosen::where('nidn', '0633445566')->first();
        $this->assertNotNull($dosenPensiun);
        $this->assertEquals('Pensiun', $dosenPensiun->status_aktif);
        $this->assertFalse((bool)$dosenPensiun->is_active);
        $pegawaiPensiun = \App\Models\Simpeg\Pegawai::find($dosenPensiun->pegawai_id);
        $this->assertNotNull($pegawaiPensiun);
        $this->assertEquals('pensiun', $pegawaiPensiun->status);
        $this->assertEquals('pns', $pegawaiPensiun->status_kepegawaian);
        $this->assertEquals('2025-06-30', $pegawaiPensiun->tanggal_keluar?->format('Y-m-d'));

        // 2. Verifikasi Dosen Meninggal
        $dosenMeninggal = Dosen::where('nidn', '0644556677')->first();
        $this->assertNotNull($dosenMeninggal);
        $this->assertEquals('Meninggal', $dosenMeninggal->status_aktif);
        $this->assertFalse((bool)$dosenMeninggal->is_active);
        $pegawaiMeninggal = \App\Models\Simpeg\Pegawai::find($dosenMeninggal->pegawai_id);
        $this->assertNotNull($pegawaiMeninggal);
        $this->assertEquals('meninggal', $pegawaiMeninggal->status);
        $this->assertEquals('tetap_yayasan', $pegawaiMeninggal->status_kepegawaian);
        $this->assertEquals('2024-12-15', $pegawaiMeninggal->tanggal_keluar?->format('Y-m-d'));

        // 3. Verifikasi Dosen Kontrak Mengundurkan Diri
        $dosenKeluar = Dosen::where('nidn', '0655667788')->first();
        $this->assertNotNull($dosenKeluar);
        $this->assertFalse((bool)$dosenKeluar->is_active);
        $pegawaiKeluar = \App\Models\Simpeg\Pegawai::find($dosenKeluar->pegawai_id);
        $this->assertNotNull($pegawaiKeluar);
        $this->assertEquals('non_aktif', $pegawaiKeluar->status);
        $this->assertEquals('kontrak', $pegawaiKeluar->status_kepegawaian);
        $this->assertEquals('2026-01-10', $pegawaiKeluar->tanggal_keluar?->format('Y-m-d'));
    }

    public function test_pull_dosen_preserves_multi_degree_and_determines_highest_education_level()
    {
        $mockFeeder = $this->createMock(NeoFeederService::class);
        $mockFeeder->expects($this->any())
            ->method('request')
            ->willReturnCallback(function ($act, $p) {
                if ($act === 'GetListDosen') {
                    return [
                        'error_code' => 0,
                        'data' => [
                            [
                                'id_dosen' => 'UUID-DOSEN-MULTIDEGREE',
                                'nama_dosen' => 'Dr. Multi Degree, S.Kom., M.Kom., M.M.',
                                'nidn' => '0677112233',
                                'id_status_aktif' => '1',
                            ]
                        ]
                    ];
                }
                if ($act === 'GetRiwayatPendidikanDosen') {
                    // Sengaja acak urutannya: S1 di paling akhir, S2 ada 2 gelar dari kampus yang sama
                    return [
                        'error_code' => 0,
                        'data' => [
                            [
                                'id_dosen' => 'UUID-DOSEN-MULTIDEGREE',
                                'nama_jenjang_pendidikan' => 'S2',
                                'nama_perguruan_tinggi' => 'Universitas Indonesia',
                                'nama_bidang_studi' => 'Ilmu Komputer',
                                'nama_gelar_akademik' => 'Magister Komputer',
                                'tahun_lulus' => '2018',
                            ],
                            [
                                'id_dosen' => 'UUID-DOSEN-MULTIDEGREE',
                                'nama_jenjang_pendidikan' => 'S3',
                                'nama_perguruan_tinggi' => 'Institut Teknologi Bandung',
                                'nama_bidang_studi' => 'Informatika',
                                'nama_gelar_akademik' => 'Doktor',
                                'tahun_lulus' => '2024',
                            ],
                            [
                                'id_dosen' => 'UUID-DOSEN-MULTIDEGREE',
                                'nama_jenjang_pendidikan' => 'S2',
                                'nama_perguruan_tinggi' => 'Universitas Indonesia',
                                'nama_bidang_studi' => 'Manajemen',
                                'nama_gelar_akademik' => 'Magister Manajemen',
                                'tahun_lulus' => '2021',
                            ],
                            [
                                'id_dosen' => 'UUID-DOSEN-MULTIDEGREE',
                                'nama_jenjang_pendidikan' => 'S1',
                                'nama_perguruan_tinggi' => 'Universitas Gadjah Mada',
                                'nama_bidang_studi' => 'Ilmu Komputer',
                                'nama_gelar_akademik' => 'Sarjana Komputer',
                                'tahun_lulus' => '2015',
                            ],
                        ]
                    ];
                }
                return ['error_code' => 0, 'data' => []];
            });

        $service = new NeoFeederSyncService($mockFeeder);
        $log = $service->pullBatchDosenFromFeeder();

        $this->assertEquals(1, $log->success_count);

        $dosen = Dosen::where('nidn', '0677112233')->first();
        $this->assertNotNull($dosen);
        $pegawai = \App\Models\Simpeg\Pegawai::find($dosen->pegawai_id);
        $this->assertNotNull($pegawai);

        // Verifikasi ke-4 gelar tersimpan semua (termasuk 2 gelar S2 dari kampus yang sama tidak saling timpa)
        $riwayat = \App\Models\Simpeg\RiwayatPendidikanPegawai::where('pegawai_id', $pegawai->id)->get();
        $this->assertCount(4, $riwayat);

        $s2List = $riwayat->where('jenjang', 's2');
        $this->assertCount(2, $s2List);
        $this->assertTrue($s2List->contains('program_studi', 'Ilmu Komputer'));
        $this->assertTrue($s2List->contains('program_studi', 'Manajemen'));

        // Verifikasi hanya S3 yang merupakan jenjang tertinggi yang ditandai is_pendidikan_terakhir = true
        $terakhir = $riwayat->where('is_pendidikan_terakhir', true);
        $this->assertCount(1, $terakhir);
        $this->assertEquals('s3', $terakhir->first()->jenjang);
        $this->assertEquals(2024, $terakhir->first()->tahun_lulus);
    }

    public function test_abbreviate_degree_does_not_generate_generic_s_or_m()
    {
        $mockFeeder = $this->createMock(NeoFeederService::class);
        $service = new NeoFeederSyncService($mockFeeder);

        // Uji gelar generik tanpa bidang studi
        $resSarjana = $service->abbreviateAcademicDegree('Sarjana', 'S1', 'Bidang Tidak Diketahui');
        $this->assertArrayNotHasKey('belakang', $resSarjana);

        $resMagister = $service->abbreviateAcademicDegree('Magister', 'S2', 'Bidang Tidak Diketahui');
        $this->assertArrayNotHasKey('belakang', $resMagister);

        // Uji gelar valid terstandarisasi tetap bekerja
        $resKomputer = $service->abbreviateAcademicDegree('Magister Komputer', 'S2', 'Teknik Informatika');
        $this->assertEquals(['belakang' => 'M.Kom.'], $resKomputer);
    }

    public function test_pull_dosen_protects_local_corrections_from_blind_overwrites_and_saves_feeder_raw_snapshot()
    {
        // 1. Dosen sudah pernah disinkronkan dari Feeder dengan nama awal 'Budi Hartono'
        $feederId = 'UUID-DOSEN-SNAPSHOT-01';
        $dosen = Dosen::create([
            'id_feeder' => $feederId,
            'nidn' => '0688991122',
            'nip' => '198001012010121001',
            'nama_lengkap' => 'Budi Hartono',
            'tempat_lahir' => 'Solo',
            'telepon' => '081111111111',
            'gelar_depan' => null,
            'gelar_belakang' => 'M.Kom.',
            'is_active' => true,
            'status_aktif' => 'Aktif',
            'feeder_raw' => [
                'id_dosen' => $feederId,
                'nama_dosen' => 'Budi Hartono',
                'nidn' => '0688991122',
                'tempat_lahir' => 'Solo',
                'telepon' => '081111111111',
            ],
        ]);

        $pegawai = \App\Models\Simpeg\Pegawai::create([
            'nidn' => '0688991122',
            'nip' => '198001012010121001',
            'nama_lengkap' => 'Budi Hartono',
            'tempat_lahir' => 'Solo',
            'telepon' => '081111111111',
            'jenis_pegawai' => 'dosen',
            'status' => 'aktif',
        ]);
        $dosen->update(['pegawai_id' => $pegawai->id]);

        // 2. Admin kampus melakukan perbaikan manual di SIMPEG/SIAKAD (nama resmi, gelar, tempat lahir, HP)
        $dosen->update([
            'nama_lengkap' => 'Prof. Dr. Ir. Budi Hartono, M.Sc., Ph.D.',
            'gelar_depan' => 'Prof. Dr. Ir.',
            'gelar_belakang' => 'M.Sc., Ph.D.',
            'tempat_lahir' => 'Surakarta',
            'telepon' => '081299998888',
        ]);
        $pegawai->update([
            'nama_lengkap' => 'Prof. Dr. Ir. Budi Hartono, M.Sc., Ph.D.',
            'gelar_depan' => 'Prof. Dr. Ir.',
            'gelar_belakang' => 'M.Sc., Ph.D.',
            'tempat_lahir' => 'Surakarta',
            'telepon' => '081299998888',
        ]);

        // 3. Feeder ditarik ulang dengan data mentah PDDikti yang belum terupdate
        $mockFeeder = $this->createMock(NeoFeederService::class);
        $mockFeeder->expects($this->any())
            ->method('request')
            ->willReturnCallback(function ($act, $p) use ($feederId) {
                if ($act === 'GetListDosen') {
                    return [
                        'error_code' => 0,
                        'data' => [
                            [
                                'id_dosen' => $feederId,
                                'nama_dosen' => 'Budi Hartono', // Data mentah di Feeder masih nama lama
                                'nidn' => '0688991122',
                                'tempat_lahir' => 'Solo',
                                'telepon' => '081111111111',
                                'id_status_aktif' => '1',
                                'nama_status_aktif' => 'Aktif',
                            ]
                        ]
                    ];
                }
                return ['error_code' => 0, 'data' => []];
            });

        $service = new NeoFeederSyncService($mockFeeder);
        $log = $service->pullBatchDosenFromFeeder();

        $this->assertEquals(1, $log->success_count);

        // 4. Verifikasi data lokal TIDAK tertimpa secara buta!
        $dosen->refresh();
        $pegawai->refresh();

        $this->assertEquals('Prof. Dr. Ir. Budi Hartono, M.Sc., Ph.D.', $dosen->nama_lengkap);
        $this->assertEquals('Prof. Dr. Ir.', $dosen->gelar_depan);
        $this->assertEquals('M.Sc., Ph.D.', $dosen->gelar_belakang);
        $this->assertEquals('Surakarta', $dosen->tempat_lahir);
        $this->assertEquals('081299998888', $dosen->telepon);

        $this->assertEquals('Prof. Dr. Ir. Budi Hartono, M.Sc., Ph.D.', $pegawai->nama_lengkap);
        $this->assertEquals('Surakarta', $pegawai->tempat_lahir);
        $this->assertEquals('081299998888', $pegawai->telepon);

        // 5. Verifikasi snapshot mentah Feeder tersimpan di feeder_raw & siakad_feeder_mappings.raw_data
        $this->assertNotNull($dosen->feeder_raw);
        $this->assertEquals('Budi Hartono', $dosen->feeder_raw['nama_dosen']);

        $mapping = \App\Models\Siakad\FeederMapping::where('entity_type', 'dosen')
            ->where('local_id', $dosen->id)
            ->first();
        $this->assertNotNull($mapping);
        $this->assertNotNull($mapping->raw_data);
        $this->assertEquals('Budi Hartono', $mapping->raw_data['nama_dosen']);
    }

    public function test_ensure_sso_user_does_not_hijack_existing_account_via_fabricated_email()
    {
        // 1. Buat akun user yang sudah ada sebelumnya (misal mahasiswa atau admin)
        // yang secara kebetulan memiliki email 0699001122@campus.ac.id
        $victimUser = User::create([
            'username' => 'mahasiswa_lama',
            'email' => '0699001122@campus.ac.id',
            'password' => \Illuminate\Support\Facades\Hash::make('secretpassword123'),
            'is_active' => true,
            'is_verified' => true,
        ]);
        $victimRole = \App\Models\Role::firstOrCreate(['slug' => 'mahasiswa'], ['name' => 'Mahasiswa']);
        $victimUser->roles()->attach($victimRole->id);

        // 2. Dosen baru ditarik/dibuat dengan NIDN 0699001122 (email kosong di Feeder, sistem resolve ke 0699001122@campus.ac.id)
        $pegawaiDosen = \App\Models\Simpeg\Pegawai::create([
            'nidn' => '0699001122',
            'nama_lengkap' => 'Dosen Peneliti Baru',
            'jenis_pegawai' => 'dosen',
            'status' => 'aktif',
        ]);

        $pegawaiService = app(\App\Services\Simpeg\PegawaiService::class);
        $ssoUser = $pegawaiService->ensureSsoUserForPegawai($pegawaiDosen);

        // 3. Verifikasi Akun Korban TIDAK DIBAJAK!
        $this->assertNotEquals($victimUser->id, $ssoUser->id);
        $this->assertNotEquals($victimUser->id, $pegawaiDosen->user_id);

        // User korban tidak boleh tertular role 'dosen'
        $victimUser->refresh();
        $this->assertFalse($victimUser->hasRole('dosen'));
        $this->assertEquals('mahasiswa_lama', $victimUser->username);

        // Akun dosen baru dibuat tersendiri dengan username sesuai NIDN
        $this->assertEquals('0699001122', $ssoUser->username);
        $this->assertTrue($ssoUser->hasRole('dosen'));
        $this->assertNotEquals($victimUser->email, $ssoUser->email); // Email tidak tabrakan
    }

    public function test_sso_user_creation_uses_default_indonusa_password_and_sets_verified()
    {
        $pegawaiDosen = \App\Models\Simpeg\Pegawai::create([
            'nidn' => '0612345679',
            'nama_lengkap' => 'Dosen SSO',
            'jenis_pegawai' => 'dosen',
            'status' => 'aktif',
        ]);

        $pegawaiService = app(\App\Services\Simpeg\PegawaiService::class);
        $user = $pegawaiService->ensureSsoUserForPegawai($pegawaiDosen);

        // 1. Password default 'indonusa'
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('indonusa', $user->password));

        // 2. is_verified = true
        $this->assertTrue($user->is_verified);
    }
}
