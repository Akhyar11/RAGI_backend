<?php

namespace Tests\Feature;

use App\Jobs\SyncPddiktiMataKuliahJob;
use App\Jobs\SyncPddiktiProgramStudiJob;
use App\Models\IamApiIntegration;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\IAM\MataKuliahIntegrationSyncService;
use App\Services\IAM\ProgramStudiIntegrationSyncService;
use Database\Seeders\IAM\IamApiIntegrationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Sync PDDIKTI prodi dan mata kuliah berjalan di worker queue: lock, progress, batch 500, persist per batch, cleanup.
 */
class PddiktiCatalogQueuedSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        $this->admin = User::create([
            'username' => 'admin_queue', 'email' => 'admin-queue@test.com', 'password' => bcrypt('password'),
            'is_active' => true, 'is_verified' => true,
        ]);
        $role = Role::firstOrCreate(['slug' => 'superadmin'], ['name' => 'Super Admin', 'is_active' => true]);
        $this->admin->roles()->syncWithoutDetaching([$role->id]);

        app(IamApiIntegrationSeeder::class)->run();
        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');
        DB::table('spmb_master_referensi')->insert([
            'modul' => 'siakad', 'tipe' => 'jenjang_prodi', 'kode' => 'D3', 'nama' => 'Diploma 3',
            'urutan' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function integration(string $key): IamApiIntegration
    {
        $integration = IamApiIntegration::where('key', $key)->firstOrFail();
        $integration->update(['source_type' => 'pddikti']);

        return $integration->fresh();
    }

    private function lockIsFree(string $key): bool
    {
        $lock = Cache::lock($key, 5);
        $free = $lock->get();
        if ($free) {
            $lock->release();
        }

        return $free;
    }

    private function prodiRow(int $n): array
    {
        return ['id_prodi' => "prodi-uuid-{$n}", 'kode_program_studi' => sprintf('K%05d', $n),
            'nama_program_studi' => "Prodi {$n}", 'nama_jenjang_pendidikan' => 'D3', 'status' => 'A'];
    }

    /** Fake Feeder: GetToken, action count, dan daftar per offset melalui callback $list(offset, limit). */
    private function fakeFeeder(callable $list, callable|int|null $count = null): void
    {
        Http::fake([
            'https://feeder.example.test/ws/live2.php' => function ($request) use ($list, $count) {
                $payload = json_decode($request->body(), true);
                $act = $payload['act'] ?? null;
                if ($act === 'GetToken') {
                    return Http::response(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200);
                }
                if (str_starts_with((string) $act, 'GetCount')) {
                    return $count === null
                        ? Http::response(['error_code' => 1, 'error_desc' => 'Action tidak tersedia'], 200)
                        : Http::response(['error_code' => 0, 'data' => is_callable($count) ? $count() : (string) $count], 200);
                }

                return Http::response(['error_code' => 0, 'data' => $list((int) ($payload['offset'] ?? 0), (int) ($payload['limit'] ?? 0))], 200);
            },
        ]);
    }

    public function test_prodi_and_course_sync_are_queued_with_202_and_progress_for_requester(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();

        foreach ([
            ['siakad_program_studi', SyncPddiktiProgramStudiJob::class, 'siakad-prodi-pddikti-sync:'],
            ['siakad_mata_kuliah', SyncPddiktiMataKuliahJob::class, 'siakad-matkul-pddikti-sync:'],
        ] as [$key, $jobClass, $prefix]) {
            $integration = $this->integration($key);

            $this->actingAs($this->admin, 'api')
                ->postJson("/api/admin/api-integrations/{$integration->id}/sync", ['preview' => true])
                ->assertStatus(422);

            $queued = $this->actingAs($this->admin, 'api')->postJson("/api/admin/api-integrations/{$integration->id}/sync");
            $queued->assertStatus(202)->assertJsonStructure(['data' => ['progress_id']]);
            $progressId = $queued->json('data.progress_id');

            Queue::assertPushedOn('default', $jobClass, fn ($job): bool => $job->progressId === $progressId
                && $job->requestedBy === $this->admin->id && $job->integrationId === $integration->id);
            $this->assertSame('queued', Cache::get($prefix . $progressId)['status']);
            $this->assertSame('processing', $integration->fresh()->last_status);
            $this->assertFalse($this->lockIsFree(rtrim($prefix, ':') . ':lock'), 'lock ditahan selama sync berjalan');

            // Sync kedua saat masih berjalan ditolak.
            $this->actingAs($this->admin, 'api')
                ->postJson("/api/admin/api-integrations/{$integration->id}/sync")
                ->assertStatus(409);

            $this->actingAs($this->admin, 'api')
                ->getJson("/api/admin/api-integrations/student-sync-progress/{$progressId}")
                ->assertOk()
                ->assertJsonPath('data.status', 'queued');
        }
    }

    public function test_sync_requires_queue_worker_and_does_not_import_in_the_http_request(): void
    {
        config(['queue.default' => 'sync']);
        $integration = $this->integration('siakad_program_studi');
        Http::fake();

        $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$integration->id}/sync")
            ->assertStatus(503);

        Http::assertNothingSent();
        $this->assertSame(0, DB::table('siakad_program_studi')->count());
    }

    public function test_prodi_job_completes_updates_status_and_releases_lock(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();
        $integration = $this->integration('siakad_program_studi');
        $progressId = $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$integration->id}/sync")->json('data.progress_id');
        $job = Queue::pushed(SyncPddiktiProgramStudiJob::class)->first();
        $this->fakeFeeder(fn () => [$this->prodiRow(1), $this->prodiRow(2)], 2);

        $job->handle();

        $progress = Cache::get('siakad-prodi-pddikti-sync:' . $progressId);
        $this->assertSame('completed', $progress['status']);
        $this->assertSame(100, $progress['progress']);
        $this->assertSame(2, $progress['result']['created']);
        $this->assertSame(2, $progress['result']['synced']);
        $this->assertSame('success', $integration->fresh()->last_status);
        $this->assertSame(2, DB::table('siakad_program_studi')->count());
        $this->assertTrue($this->lockIsFree('siakad-prodi-pddikti-sync:lock'));
    }

    public function test_course_job_failure_marks_failed_with_safe_message_and_releases_lock(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();
        $integration = $this->integration('siakad_mata_kuliah');
        $progressId = $this->actingAs($this->admin, 'api')
            ->postJson("/api/admin/api-integrations/{$integration->id}/sync")->json('data.progress_id');
        $job = Queue::pushed(SyncPddiktiMataKuliahJob::class)->first();
        Http::fake([
            'https://feeder.example.test/ws/live2.php' => function ($request) {
                $act = json_decode($request->body(), true)['act'] ?? null;

                return $act === 'GetToken'
                    ? Http::response(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200)
                    : Http::response(['error_code' => 5, 'error_desc' => 'Ditolak'], 200);
            },
        ]);

        $job->handle();

        $progress = Cache::get('siakad-matkul-pddikti-sync:' . $progressId);
        $this->assertSame('failed', $progress['status']);
        $this->assertStringContainsString('menolak permintaan', $progress['message']);
        $this->assertStringNotContainsString('runtime-feeder-token', json_encode($progress));
        $this->assertSame('failed', $integration->fresh()->last_status);
        $this->assertTrue($this->lockIsFree('siakad-matkul-pddikti-sync:lock'));
    }

    public function test_prodi_sync_persists_each_batch_of_500_before_requesting_the_next_and_uses_count_for_progress(): void
    {
        $offsets = [];
        $limits = [];
        $firstBatchPersistedBeforeSecondRequest = null;
        $this->fakeFeeder(function (int $offset, int $limit) use (&$offsets, &$limits, &$firstBatchPersistedBeforeSecondRequest) {
            $offsets[] = $offset;
            $limits[] = $limit;
            if ($offset === 500) {
                $firstBatchPersistedBeforeSecondRequest = DB::table('siakad_program_studi')->count() === 500;

                return [$this->prodiRow(501)];
            }

            return array_map(fn (int $n) => $this->prodiRow($n), range(1, 500));
        }, 501);
        $service = app(ProgramStudiIntegrationSyncService::class);
        Cache::put('siakad-prodi-pddikti-sync:batch', ['status' => 'running'], now()->addHour());

        $result = $service->processPddikti($this->integration('siakad_program_studi'), 'batch');

        $this->assertSame([0, 500], $offsets);
        $this->assertSame([500, 500], $limits, 'limit tepat 500');
        $this->assertTrue($firstBatchPersistedBeforeSecondRequest, 'batch pertama sudah tersimpan sebelum batch kedua diminta');
        $this->assertSame(501, $result['processed']);
        $this->assertSame(501, $result['created']);
        $this->assertSame(2, $result['pages']);
        $progress = Cache::get('siakad-prodi-pddikti-sync:batch');
        $this->assertSame(501, $progress['total'], 'total dari GetCountProdi');
        $this->assertSame(99, $progress['progress'], 'persen maksimum 99 sampai job menandai selesai');
    }

    public function test_progress_is_indeterminate_when_count_action_is_unavailable(): void
    {
        $this->fakeFeeder(fn () => [$this->prodiRow(1)], null);
        Cache::put('siakad-prodi-pddikti-sync:nocount', ['status' => 'running'], now()->addHour());

        app(ProgramStudiIntegrationSyncService::class)->processPddikti($this->integration('siakad_program_studi'), 'nocount');

        $progress = Cache::get('siakad-prodi-pddikti-sync:nocount');
        $this->assertNull($progress['total']);
        $this->assertNull($progress['progress'], 'tanpa persentase/ETA palsu');
        $this->assertSame(1, $progress['processed']);
    }

    public function test_page_cap_constants_are_explicit_and_batch_size_is_500(): void
    {
        foreach ([ProgramStudiIntegrationSyncService::class, MataKuliahIntegrationSyncService::class] as $class) {
            $reflection = new \ReflectionClass($class);
            $this->assertSame(100, $reflection->getReflectionConstant('MAX_SOURCE_PAGES')->getValue(), "{$class}: batas halaman eksplisit");
            $this->assertSame(500, $reflection->getReflectionConstant('PDDIKTI_BATCH_SIZE')->getValue(), "{$class}: batch tepat 500");
        }
    }

    public function test_course_id_split_across_batch_boundary_is_merged_and_earlier_rows_persist_first(): void
    {
        $prodi = DB::table('siakad_program_studi')->insertGetId([
            'kode_prodi' => '48401', 'nama' => 'D3 Farmasi', 'jenjang' => 'D3', 'id_feeder' => 'prodi-uuid',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $kur = fn (string $kode, int $year, string $uuid) => DB::table('siakad_kurikulum')->insertGetId([
            'program_studi_id' => $prodi, 'kode' => $kode, 'nama' => $kode, 'tahun_berlaku' => $year,
            'id_feeder' => $uuid, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $kur2018 = $kur('KUR-2018', 2018, 'kur-uuid-2018');
        $kur2023 = $kur('KUR-2023', 2023, 'kur-uuid-2023');
        $row = fn (string $uuid, string $code, string $kurUuid) => [
            'id_kurikulum' => $kurUuid, 'id_matkul' => $uuid, 'kode_mata_kuliah' => $code, 'nama_mata_kuliah' => "MK {$code}",
            'id_prodi' => 'prodi-uuid', 'semester' => '1', 'sks_mata_kuliah' => '2.00', 'sks_tatap_muka' => '2.00',
            'sks_praktek' => null, 'sks_praktek_lapangan' => null, 'sks_simulasi' => null, 'apakah_wajib' => '1',
        ];

        $offsets = [];
        $stateAtSecondRequest = null;
        $this->fakeFeeder(function (int $offset) use ($row, &$offsets, &$stateAtSecondRequest) {
            $offsets[] = $offset;
            if ($offset === 500) {
                $stateAtSecondRequest = [
                    'filler' => DB::table('siakad_mata_kuliah')->where('id_feeder', 'like', 'mk-filler-%')->count(),
                    'split' => DB::table('siakad_mata_kuliah')->where('id_feeder', 'mk-split')->count(),
                ];

                return [$row('mk-split', 'SPLIT1', 'kur-uuid-2023')];
            }
            $batch = array_map(fn (int $n) => $row("mk-filler-{$n}", sprintf('F%04d', $n), 'kur-uuid-2018'), range(1, 499));
            $batch[] = $row('mk-split', 'SPLIT1', 'kur-uuid-2018'); // id_matkul yang sama berlanjut di batch berikutnya

            return $batch;
        }, 501);

        $result = app(MataKuliahIntegrationSyncService::class)->processPddikti($this->integration('siakad_mata_kuliah'));

        $this->assertSame([0, 500], $offsets);
        $this->assertSame(['filler' => 499, 'split' => 0], $stateAtSecondRequest, 'batch pertama tersimpan, id_matkul terpotong ditahan');
        $this->assertSame(500, $result['distinct_courses']);
        $this->assertSame(500, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(1, $result['multi_curriculum']);
        $this->assertSame(1, DB::table('siakad_mata_kuliah')->where('id_feeder', 'mk-split')->count());
        $this->assertDatabaseHas('siakad_mata_kuliah', ['id_feeder' => 'mk-split', 'kurikulum_id' => $kur2023]);
        $this->assertSame(0, DB::table('siakad_mata_kuliah')->where('id_feeder', 'mk-filler-1')->where('kurikulum_id', $kur2023)->count());
        $this->assertNotSame($kur2018, $kur2023);
    }
}
