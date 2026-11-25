<?php

namespace Tests\Feature;

use App\Jobs\SyncPddiktiKrsJob;
use App\Models\IamApiIntegration;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\IAM\ApiIntegrationSyncException;
use App\Services\IAM\KrsHistorisIntegrationSyncService;
use Database\Seeders\IAM\IamApiIntegrationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Sync KRS historis (GetKRSMahasiswa) ke siakad_krs + siakad_krs_detail tanpa kelas konkret.
 */
class PddiktiKrsHistorisSyncTest extends TestCase
{
    use RefreshDatabase;

    private int $prodiId;
    private int $pastPeriodId;
    private int $activePeriodId;
    private int $kurikulumId;
    private int $rombelId;
    /** @var array<string, int> */
    private array $students = [];
    /** @var array<string, int> */
    private array $courses = [];

    protected function setUp(): void
    {
        parent::setUp();
        app(IamApiIntegrationSeeder::class)->run();
        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');

        $now = now();
        $this->prodiId = DB::table('siakad_program_studi')->insertGetId([
            'kode_prodi' => '48401', 'nama' => 'D3 Farmasi', 'jenjang' => 'D3', 'id_feeder' => 'prodi-uuid',
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $period = fn (string $kode, int $year, bool $active) => DB::table('siakad_tahun_akademik')->insertGetId([
            'kode' => $kode, 'nama' => $kode, 'tahun_mulai' => $year, 'tahun_selesai' => $year + 1, 'id_feeder' => $kode,
            'is_active' => $active, 'mode_penilaian' => 'persentase', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->pastPeriodId = $period('20211', 2021, false);
        $this->activePeriodId = $period('20261', 2026, true);
        $this->kurikulumId = DB::table('siakad_kurikulum')->insertGetId([
            'program_studi_id' => $this->prodiId, 'kode' => 'KUR-1', 'nama' => 'Kurikulum', 'tahun_berlaku' => 2020,
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        foreach (['mk-a' => 2, 'mk-b' => 3, 'mk-c' => 1] as $uuid => $sks) {
            $this->courses[$uuid] = $this->insertCourse($uuid, strtoupper($uuid), $sks);
        }
        foreach (['reg-1', 'reg-2'] as $reg) {
            $this->students[$reg] = $this->insertStudent($reg);
        }
        $this->rombelId = DB::table('siakad_master_kelas')->insertGetId([
            'program_studi_id' => $this->prodiId, 'nama_kelas' => '21A', 'tahun_angkatan' => 2021, 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function insertCourse(string $uuid, string $code, int $sks): int
    {
        return DB::table('siakad_mata_kuliah')->insertGetId([
            'kurikulum_id' => $this->kurikulumId, 'kode_mk' => $code, 'nama' => "MK {$code}", 'sks_teori' => $sks, 'sks_praktik' => 0,
            'total_sks' => $sks, 'semester_anjuran' => 1, 'tipe' => 'wajib', 'is_active' => true, 'id_feeder' => $uuid,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function insertStudent(string $reg): int
    {
        return DB::table('siakad_mahasiswa')->insertGetId([
            'program_studi_id' => $this->prodiId, 'nim' => 'N-' . $reg, 'nama_lengkap' => "Mahasiswa {$reg}", 'angkatan' => 2021,
            'status' => 'A', 'id_feeder_riwayat' => $reg, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function integration(): IamApiIntegration
    {
        return IamApiIntegration::where('key', 'siakad_krs')->firstOrFail();
    }

    private function krsRow(string $reg, string $matkul, string $periode = '20211', string $kelas = '21A'): array
    {
        return ['id_registrasi_mahasiswa' => $reg, 'id_periode' => $periode, 'id_prodi' => 'prodi-uuid', 'nama_program_studi' => 'D3 Farmasi',
            'id_matkul' => $matkul, 'kode_mata_kuliah' => strtoupper($matkul), 'nama_mata_kuliah' => "MK {$matkul}",
            'id_kelas' => "kelas-{$matkul}-{$kelas}", 'nama_kelas_kuliah' => $kelas, 'sks_mata_kuliah' => '2.00',
            'nim' => 'N-' . $reg, 'nama_mahasiswa' => "Mahasiswa {$reg}", 'angkatan' => '2021'];
    }

    /** @param callable(string $periode, int $offset): array $rowsFor */
    private function fakeFeeder(callable $rowsFor, array &$requests = []): void
    {
        Http::fake([
            'https://feeder.example.test/ws/live2.php' => function (Request $request) use ($rowsFor, &$requests) {
                $payload = json_decode($request->body(), true);
                $act = $payload['act'] ?? null;
                if ($act === 'GetToken') {
                    return Http::response(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200);
                }
                $requests[] = $payload;
                if ($act !== 'GetKRSMahasiswa') {
                    return Http::response(['error_code' => 1, 'error_desc' => 'Action tidak diharapkan'], 200);
                }
                preg_match("/id_periode='([^']*)'/", (string) ($payload['filter'] ?? ''), $m);

                return Http::response(['error_code' => 0, 'data' => $rowsFor($m[1] ?? '', (int) ($payload['offset'] ?? 0))], 200);
            },
        ]);
    }

    private function process(array $rowsByPeriod, array &$requests = []): array
    {
        $this->fakeFeeder(fn (string $periode, int $offset) => $offset === 0 ? ($rowsByPeriod[$periode] ?? []) : [], $requests);

        return app(KrsHistorisIntegrationSyncService::class)->processPddikti($this->integration());
    }

    public function test_registry_documents_count_timeout_and_service_never_calls_the_count_action(): void
    {
        $schema = $this->integration()->source_schemas['pddikti'];
        $this->assertSame('GetKRSMahasiswa', $schema['action']);
        $this->assertSame('krs', $schema['target_entity']);
        $this->assertStringContainsString('timeout', $schema['count_action_note']);

        $requests = [];
        $this->process(['20211' => [$this->krsRow('reg-1', 'mk-a')]], $requests);

        $this->assertNotEmpty($requests);
        $this->assertSame([], array_values(array_filter($requests, fn ($r) => str_starts_with((string) $r['act'], 'GetCount'))));
    }

    public function test_imports_krs_per_student_with_course_based_details_and_no_class_rows(): void
    {
        $result = $this->process(['20211' => [
            $this->krsRow('reg-1', 'mk-a'),
            $this->krsRow('reg-1', 'mk-b'),
            $this->krsRow('reg-2', 'mk-a'),
        ]]);

        $this->assertSame(3, $result['created']);
        $this->assertSame(2, $result['krs_created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(3, $result['source_total']);

        $krs1 = DB::table('siakad_krs')->where('mahasiswa_id', $this->students['reg-1'])->where('tahun_akademik_id', $this->pastPeriodId)->first();
        $this->assertNotNull($krs1);
        $this->assertSame('dikunci', $krs1->status, 'periode lampau dikunci');
        $this->assertSame(5, (int) $krs1->total_sks_diambil, '2 + 3 SKS dari mata kuliah lokal');
        $this->assertNull($krs1->disetujui_oleh, 'persetujuan dosen tidak ditebak');
        $this->assertSame(0, (int) $krs1->locked_by_keuangan);

        $detail = DB::table('siakad_krs_detail')->where('krs_id', $krs1->id)->where('mata_kuliah_id', $this->courses['mk-a'])->first();
        $this->assertNotNull($detail);
        $this->assertNull($detail->kelas_id);
        $this->assertSame($this->rombelId, (int) $detail->master_kelas_id);
        $this->assertSame('aktif', $detail->status);
        $this->assertSame(0, DB::table('siakad_jadwal_kelas')->count(), 'jadwal kelas tidak disentuh');
        $this->assertSame(0, DB::table('siakad_nilai_mahasiswa')->count(), 'nilai bukan bagian tugas ini');
    }

    public function test_sync_is_idempotent_and_does_not_duplicate_krs_or_details(): void
    {
        $rows = ['20211' => [$this->krsRow('reg-1', 'mk-a'), $this->krsRow('reg-1', 'mk-b')]];

        $first = $this->process($rows);
        $second = $this->process($rows);

        $this->assertSame(2, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(2, $second['unchanged']);
        $this->assertSame(0, $second['krs_created']);
        $this->assertSame(1, DB::table('siakad_krs')->count());
        $this->assertSame(2, DB::table('siakad_krs_detail')->count());
    }

    public function test_reads_each_mapped_period_with_filter_compound_order_and_limit_500(): void
    {
        $requests = [];
        $this->process(['20211' => [$this->krsRow('reg-1', 'mk-a')], '20261' => [$this->krsRow('reg-2', 'mk-b', '20261', '26A')]], $requests);

        $this->assertCount(2, $requests, 'satu request per periode terpetakan (halaman pertama sudah penutup)');
        $this->assertSame(["id_periode='20211'", "id_periode='20261'"], array_column($requests, 'filter'));
        foreach ($requests as $payload) {
            $this->assertSame(500, (int) $payload['limit']);
            $this->assertSame('id_registrasi_mahasiswa,id_periode,id_matkul,id_kelas', $payload['order']);
        }
        $active = DB::table('siakad_krs')->where('mahasiswa_id', $this->students['reg-2'])->first();
        $this->assertSame($this->activePeriodId, (int) $active->tahun_akademik_id);
        $this->assertSame('disetujui', $active->status, 'periode berjalan: disetujui');
    }

    public function test_unmapped_rows_are_skipped_with_counted_reasons(): void
    {
        $result = $this->process(['20211' => [
            $this->krsRow('reg-1', 'mk-a'),
            $this->krsRow('reg-tak-dikenal', 'mk-a'),
            $this->krsRow('reg-1', 'mk-tak-dikenal'),
            $this->krsRow('reg-1', 'mk-b', '20999'),
        ]]);

        $this->assertSame(1, $result['created']);
        $this->assertSame(3, $result['skipped']);
        $reasons = $result['skipped_by_reason'];
        $this->assertSame(1, $reasons['Registrasi mahasiswa Feeder belum dipetakan ke siakad_mahasiswa.id_feeder_riwayat.']);
        $this->assertSame(1, $reasons['Mata kuliah Feeder belum dipetakan ke siakad_mata_kuliah.id_feeder.']);
        $this->assertSame(1, $reasons['Periode baris KRS tidak sesuai dengan periode yang diminta.']);
        $this->assertSame(1, DB::table('siakad_krs_detail')->count());
    }

    public function test_old_registration_of_a_person_with_another_registration_is_skipped_with_a_distinct_reason(): void
    {
        // Mahasiswa lokal ber-NIM 'N-reg-lama' memakai registrasi baru (alih bentuk); KRS lama milik registrasi lain.
        DB::table('siakad_mahasiswa')->insert([
            'program_studi_id' => $this->prodiId, 'nim' => 'N-reg-lama', 'nama_lengkap' => 'Mahasiswa Alih Bentuk', 'angkatan' => 2023,
            'status' => 'A', 'id_feeder_riwayat' => 'reg-baru', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = $this->process(['20211' => [
            $this->krsRow('reg-lama', 'mk-a'),          // NIM ada di lokal pada registrasi lain
            $this->krsRow('reg-tidak-ada', 'mk-a'),     // NIM tidak ada di lokal
            $this->krsRow('reg-1', 'mk-b'),
        ]]);

        $this->assertSame(1, $result['created']);
        $this->assertSame(2, $result['skipped']);
        $reasons = $result['skipped_by_reason'];
        $this->assertSame(1, $reasons['Registrasi lama: mahasiswa lokal ber-NIM sama memakai registrasi Feeder lain (alih bentuk/pindah prodi); tidak ditempel otomatis.']);
        $this->assertSame(1, $reasons['Registrasi mahasiswa Feeder belum dipetakan ke siakad_mahasiswa.id_feeder_riwayat.']);
        $this->assertSame(0, DB::table('siakad_krs')->where('mahasiswa_id', DB::table('siakad_mahasiswa')->where('nim', 'N-reg-lama')->value('id'))->count(),
            'KRS registrasi lain tidak boleh ditempel ke mahasiswa lokal');
    }

    public function test_existing_online_krs_is_never_overwritten_or_duplicated(): void
    {
        $classId = DB::table('siakad_jadwal_kelas')->insertGetId([
            'mata_kuliah_id' => $this->courses['mk-a'], 'tahun_akademik_id' => $this->pastPeriodId, 'program_studi_id' => $this->prodiId,
            'kode_kelas' => 'A1', 'nama_kelas' => 'A', 'kapasitas' => 40, 'kuota_krs' => 40, 'status' => 'aktif',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $krsId = DB::table('siakad_krs')->insertGetId([
            'mahasiswa_id' => $this->students['reg-1'], 'tahun_akademik_id' => $this->pastPeriodId, 'total_sks_diambil' => 2,
            'status' => 'draft', 'locked_by_keuangan' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('siakad_krs_detail')->insert([
            'krs_id' => $krsId, 'kelas_id' => $classId, 'mata_kuliah_id' => $this->courses['mk-a'], 'status' => 'aktif',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = $this->process(['20211' => [$this->krsRow('reg-1', 'mk-a'), $this->krsRow('reg-1', 'mk-b')]]);

        $this->assertSame(1, $result['created'], 'hanya mata kuliah yang belum ada');
        $this->assertSame(1, $result['unchanged'], 'mk-a sudah ada lewat KRS online');
        $this->assertSame(0, $result['krs_created']);
        $krs = DB::table('siakad_krs')->where('id', $krsId)->first();
        $this->assertSame('draft', $krs->status, 'status KRS online tidak diubah');
        $this->assertSame(1, (int) $krs->locked_by_keuangan);
        $this->assertSame(5, (int) $krs->total_sks_diambil, 'total SKS dihitung ulang dari mata kuliah pada detail aktif');
        $this->assertSame($classId, (int) DB::table('siakad_krs_detail')->where('krs_id', $krsId)->where('mata_kuliah_id', $this->courses['mk-a'])->value('kelas_id'));
    }

    public function test_missing_rombel_leaves_null_and_is_filled_when_it_appears_later(): void
    {
        $rows = ['20211' => [$this->krsRow('reg-1', 'mk-a', '20211', '21Z')]];

        $first = $this->process($rows);
        $this->assertSame(1, $first['created']);
        $this->assertSame(1, $first['without_rombel']);
        $this->assertNull(DB::table('siakad_krs_detail')->value('master_kelas_id'));

        $laterRombel = DB::table('siakad_master_kelas')->insertGetId([
            'program_studi_id' => $this->prodiId, 'nama_kelas' => '21Z', 'tahun_angkatan' => 2021, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $second = $this->process($rows);

        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['updated']);
        $this->assertSame($laterRombel, (int) DB::table('siakad_krs_detail')->value('master_kelas_id'));
        $this->assertSame(1, DB::table('siakad_master_kelas')->where('nama_kelas', '21Z')->count(), 'rombel tidak dibuat oleh sync KRS');
    }

    public function test_same_course_twice_in_one_krs_is_collapsed_into_one_detail(): void
    {
        $result = $this->process(['20211' => [
            $this->krsRow('reg-1', 'mk-a', '20211', '21A'),
            $this->krsRow('reg-1', 'mk-a', '20211', '21B'),
        ]]);

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['collapsed']);
        $this->assertSame(1, DB::table('siakad_krs_detail')->count());
    }

    public function test_each_batch_of_500_is_persisted_before_the_next_request_within_a_period(): void
    {
        $rows = [];
        for ($n = 1; $n <= 501; $n++) {
            $this->students["bulk-{$n}"] = $this->insertStudent("bulk-{$n}");
            $rows[] = $this->krsRow("bulk-{$n}", 'mk-a');
        }
        $offsets = [];
        $persistedAtSecondRequest = null;
        $this->fakeFeeder(function (string $periode, int $offset) use ($rows, &$offsets, &$persistedAtSecondRequest) {
            if ($periode !== '20211') {
                return [];
            }
            $offsets[] = $offset;
            if ($offset === 500) {
                $persistedAtSecondRequest = DB::table('siakad_krs_detail')->count();
            }

            return array_slice($rows, $offset, 500);
        });

        $result = app(KrsHistorisIntegrationSyncService::class)->processPddikti($this->integration());

        $this->assertSame([0, 500], $offsets);
        $this->assertSame(500, $persistedAtSecondRequest, 'batch pertama tersimpan sebelum batch kedua diminta');
        $this->assertSame(501, $result['created']);
        $this->assertSame(501, $result['krs_created']);
        $this->assertSame(2, $result['pages']);
    }

    public function test_only_safe_local_period_ids_are_used_in_the_feeder_filter(): void
    {
        DB::table('siakad_tahun_akademik')->insert([
            'kode' => 'BAD', 'nama' => 'Bad', 'tahun_mulai' => 2000, 'tahun_selesai' => 2001, 'id_feeder' => "20111' or '1'='1",
            'is_active' => false, 'mode_penilaian' => 'persentase', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $requests = [];
        $this->process([], $requests);

        $filters = array_column($requests, 'filter');
        $this->assertSame(["id_periode='20211'", "id_periode='20261'"], $filters);
    }

    public function test_page_cap_per_period_and_batch_size_are_explicit(): void
    {
        $reflection = new \ReflectionClass(KrsHistorisIntegrationSyncService::class);
        $this->assertSame(500, $reflection->getReflectionConstant('PDDIKTI_BATCH_SIZE')->getValue());
        $this->assertSame(200, $reflection->getReflectionConstant('MAX_PAGES_PER_PERIOD')->getValue());
    }

    public function test_upstream_rejection_fails_clearly_without_partial_success(): void
    {
        Http::fake([
            'https://feeder.example.test/ws/live2.php' => function ($request) {
                $act = json_decode($request->body(), true)['act'] ?? null;

                return $act === 'GetToken'
                    ? Http::response(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200)
                    : Http::response(['error_code' => 7, 'error_desc' => 'Ditolak'], 200);
            },
        ]);

        $this->expectException(ApiIntegrationSyncException::class);
        $this->expectExceptionMessage('menolak permintaan GetKRSMahasiswa');
        app(KrsHistorisIntegrationSyncService::class)->processPddikti($this->integration());
    }

    public function test_connection_test_is_read_only_and_validates_action(): void
    {
        $this->fakeFeeder(fn () => [$this->krsRow('reg-1', 'mk-a')]);
        $service = app(KrsHistorisIntegrationSyncService::class);

        $info = $service->testPddiktiConnection('GetKRSMahasiswa');

        $this->assertTrue($info['read_only']);
        $this->assertSame(1, $info['data_count']);
        $this->assertContains('id_registrasi_mahasiswa', $info['sample_fields']);
        $this->assertSame(0, DB::table('siakad_krs')->count());
        $this->expectException(ApiIntegrationSyncException::class);
        $service->testPddiktiConnection('GetListKelasKuliah');
    }

    public function test_sync_is_queued_and_job_completes_with_indeterminate_progress_and_lock_release(): void
    {
        $admin = User::create(['username' => 'admin_krs', 'email' => 'krs@test.com', 'password' => bcrypt('password'), 'is_active' => true, 'is_verified' => true]);
        $admin->roles()->syncWithoutDetaching([Role::firstOrCreate(['slug' => 'superadmin'], ['name' => 'Super Admin', 'is_active' => true])->id]);
        $this->setUpPassport();
        config(['queue.default' => 'database']);
        Queue::fake();
        $integration = $this->integration();

        $this->actingAs($admin, 'api')->postJson("/api/admin/api-integrations/{$integration->id}/sync", ['preview' => true])->assertStatus(422);
        $queued = $this->actingAs($admin, 'api')->postJson("/api/admin/api-integrations/{$integration->id}/sync");
        $queued->assertStatus(202)->assertJsonStructure(['data' => ['progress_id']]);
        $progressId = $queued->json('data.progress_id');
        Queue::assertPushedOn('default', SyncPddiktiKrsJob::class, fn ($job): bool => $job->progressId === $progressId);
        $this->actingAs($admin, 'api')->postJson("/api/admin/api-integrations/{$integration->id}/sync")->assertStatus(409);
        $this->actingAs($admin, 'api')->getJson("/api/admin/api-integrations/student-sync-progress/{$progressId}")
            ->assertOk()->assertJsonPath('data.status', 'queued');

        $this->fakeFeeder(fn (string $periode, int $offset) => $periode === '20211' && $offset === 0 ? [$this->krsRow('reg-1', 'mk-a')] : []);
        Queue::pushed(SyncPddiktiKrsJob::class)->first()->handle();

        $progress = Cache::get('siakad-krs-pddikti-sync:' . $progressId);
        $this->assertSame('completed', $progress['status']);
        $this->assertSame(1, $progress['result']['created']);
        $this->assertStringContainsString('periode', strtolower($progress['message']) . ' periode');
        $this->assertSame('success', $integration->fresh()->last_status);
        $lock = Cache::lock('siakad-krs-pddikti-sync:lock', 5);
        $this->assertTrue($lock->get(), 'lock dilepas setelah job selesai');
        $lock->release();
    }

    public function test_progress_has_no_fake_percentage_while_running(): void
    {
        $progressId = 'krs-progress';
        Cache::put('siakad-krs-pddikti-sync:' . $progressId, ['status' => 'running'], now()->addHour());
        $this->fakeFeeder(fn (string $periode, int $offset) => $periode === '20211' && $offset === 0 ? [$this->krsRow('reg-1', 'mk-a')] : []);

        app(KrsHistorisIntegrationSyncService::class)->processPddikti($this->integration(), $progressId);

        $progress = Cache::get('siakad-krs-pddikti-sync:' . $progressId);
        $this->assertNull($progress['total']);
        $this->assertNull($progress['progress'], 'tanpa persentase/ETA palsu karena action count timeout');
        $this->assertStringContainsString('dari 2', $progress['message'], 'posisi periode terakhir (2 dari 2)');
        $this->assertSame(2, $progress['result']['periods_done']);
    }
}
