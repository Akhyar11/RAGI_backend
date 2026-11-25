<?php

namespace Tests\Feature;

use App\Jobs\SyncPddiktiSkalaNilaiJob;
use App\Models\IamApiIntegration;
use App\Models\Role;
use App\Models\Siakad\SkalaNilai;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\IAM\SkalaNilaiIntegrationSyncService;
use Database\Seeders\IAM\IamApiIntegrationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Sync skala nilai per prodi dari Neo Feeder (GetListSkalaNilaiProdi) ke siakad_skala_nilai.
 */
class PddiktiSkalaNilaiSyncTest extends TestCase
{
    use RefreshDatabase;

    private int $prodiId;
    private const PRODI_UUID = 'prodi-farmasi-uuid';

    protected function setUp(): void
    {
        parent::setUp();
        app(IamApiIntegrationSeeder::class)->run();
        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');
        $this->prodiId = DB::table('siakad_program_studi')->insertGetId([
            'kode_prodi' => '48401', 'nama' => 'D3 Farmasi', 'jenjang' => 'D3', 'id_feeder' => self::PRODI_UUID,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function integration(): IamApiIntegration
    {
        return IamApiIntegration::where('key', 'siakad_skala_nilai')->firstOrFail();
    }

    private function row(string $id, string $huruf, string $indeks, string $min, string $max, string $mulai = '01-10-2015', string $akhir = '30-09-2030', string $prodi = self::PRODI_UUID): array
    {
        return ['id_bobot_nilai' => $id, 'id_prodi' => $prodi, 'nama_program_studi' => 'D3 Farmasi', 'nilai_huruf' => $huruf,
            'nilai_indeks' => $indeks, 'bobot_nilai_min' => $min, 'bobot_nilai_maks' => $max,
            'tanggal_mulai_efektif' => $mulai, 'tanggal_akhir_efektif' => $akhir, 'status_sync' => 'sudah sync'];
    }

    /** Skala D3 Farmasi nyata: A 85-100, B 75-84, C 60-74, D 45-54, E 0-45. */
    private function farmasiRows(): array
    {
        return [
            $this->row('b-a', 'A', '4.00', '85.00', '100.00'),
            $this->row('b-b', 'B', '3.00', '75.00', '84.00'),
            $this->row('b-c', 'C', '2.00', '60.00', '74.00'),
            $this->row('b-d', 'D', '1.00', '45.00', '54.00'),
            $this->row('b-e', 'E', '0.00', '0.00', '45.00'),
        ];
    }

    private function fakeFeeder(callable $list, ?int $count = null): void
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
                        ? Http::response(['error_code' => 1, 'error_desc' => 'tidak tersedia'], 200)
                        : Http::response(['error_code' => 0, 'data' => (string) $count], 200);
                }

                return Http::response(['error_code' => 0, 'data' => $list((int) ($payload['offset'] ?? 0))], 200);
            },
        ]);
    }

    private function runSync(array $rows): array
    {
        $this->fakeFeeder(fn () => $rows, count($rows));

        return app(SkalaNilaiIntegrationSyncService::class)->processPddikti($this->integration());
    }

    public function test_registry_defines_count_action_and_migration_adds_identity_columns(): void
    {
        $schema = $this->integration()->source_schemas['pddikti'];
        $this->assertSame('GetListSkalaNilaiProdi', $schema['action']);
        $this->assertSame('GetCountSkalaNilaiProdi', $schema['count_action']);
        $this->assertSame('skala_nilai', $schema['target_entity']);
        $this->assertSame('pddikti', $this->integration()->source_type);

        foreach (['id_feeder', 'tanggal_mulai_efektif', 'tanggal_akhir_efektif'] as $column) {
            $this->assertTrue(Schema::hasColumn('siakad_skala_nilai', $column));
        }
        $this->assertTrue(collect(Schema::getIndexes('siakad_skala_nilai'))
            ->contains(fn ($i) => ($i['unique'] ?? false) && $i['columns'] === ['id_feeder']));
    }

    public function test_integer_ranges_become_continuous_while_real_gaps_and_overlaps_are_reported_not_guessed(): void
    {
        $result = $this->runSync($this->farmasiRows());

        $this->assertSame(5, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $by = fn (string $huruf) => SkalaNilai::where('program_studi_id', $this->prodiId)->where('nilai_huruf', $huruf)->firstOrFail();
        $this->assertSame('100.00', $by('A')->batas_atas);
        $this->assertSame('84.99', $by('B')->batas_atas, '84 + 1 = 85: kontinu');
        $this->assertSame('74.99', $by('C')->batas_atas);
        $this->assertSame('54.00', $by('D')->batas_atas, 'celah 55-59 tidak ditebak');
        $this->assertSame('45.00', $by('E')->batas_atas);
        $this->assertSame('85.00', $by('A')->batas_bawah);
        $this->assertSame('4.00', $by('A')->bobot_indeks);

        $this->assertSame(2, $result['warnings']);
        $messages = collect($result['warning_items'])->pluck('message')->implode(' | ');
        $this->assertStringContainsString('tumpang tindih', $messages);
        $this->assertStringContainsString('Celah rentang', $messages);

        $this->assertSame('B', SkalaNilai::konversiNilai(84.5, $this->prodiId)?->nilai_huruf);
        $this->assertSame('D', SkalaNilai::konversiNilai(45.0, $this->prodiId)?->nilai_huruf, 'irisan: bobot tertinggi menang');
    }

    public function test_non_numeric_grade_with_empty_range_is_imported_inactive_and_never_used_for_conversion(): void
    {
        $rows = $this->farmasiRows();
        $rows[] = $this->row('b-t', 'T', '0.00', '0.00', '0.00');

        $result = $this->runSync($rows);

        $this->assertSame(6, $result['created']);
        $this->assertSame(1, $result['inactive']);
        $this->assertDatabaseHas('siakad_skala_nilai', ['id_feeder' => 'b-t', 'nilai_huruf' => 'T', 'is_active' => 0, 'is_lulus' => 0]);
        $this->assertSame('E', SkalaNilai::konversiNilai(0.0, $this->prodiId)?->nilai_huruf);
    }

    public function test_sync_is_idempotent_and_never_touches_standard_scale(): void
    {
        $globalBefore = DB::table('siakad_skala_nilai')->whereNull('program_studi_id')->orderBy('id')->get()->toJson();
        $this->assertNotSame('[]', $globalBefore);

        $first = $this->runSync($this->farmasiRows());
        $second = $this->runSync($this->farmasiRows());

        $this->assertSame(5, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(5, $second['updated']);
        $this->assertSame(5, DB::table('siakad_skala_nilai')->where('program_studi_id', $this->prodiId)->count());
        $this->assertSame($globalBefore, DB::table('siakad_skala_nilai')->whereNull('program_studi_id')->orderBy('id')->get()->toJson());
    }

    public function test_unmapped_prodi_and_invalid_rows_are_skipped_with_reasons(): void
    {
        $rows = $this->farmasiRows();
        $rows[] = $this->row('x-a', 'A', '4.00', '85.00', '100.00', '01-10-2015', '30-09-2030', 'prodi-tak-dikenal');
        $rows[] = $this->row('bad-range', 'F', '0.00', '80.00', '10.00');
        $rows[] = $this->row('bad-date', 'G', '0.00', '0.00', '10.00', '31-02-2020');

        $result = $this->runSync($rows);

        $this->assertSame(5, $result['created']);
        $this->assertSame(3, $result['skipped']);
        $reasons = collect($result['skipped_items'])->pluck('reason')->implode(' | ');
        $this->assertStringContainsString('belum dipetakan ke ID Feeder', $reasons);
        $this->assertStringContainsString('Rentang nilai atau bobot indeks', $reasons);
        $this->assertStringContainsString('Format tanggal efektif', $reasons);
        $this->assertDatabaseMissing('siakad_skala_nilai', ['id_feeder' => 'x-a']);
    }

    public function test_expired_scales_are_inactive_and_latest_start_wins_when_a_letter_has_several_versions(): void
    {
        $rows = [
            $this->row('old-a', 'A', '4.00', '80.00', '100.00', '01-01-2010', '31-12-2020'),
            $this->row('mid-a', 'A', '4.00', '82.00', '100.00', '01-01-2021', '30-09-2030'),
            $this->row('new-a', 'A', '4.00', '85.00', '100.00', '01-01-2024', '30-09-2030'),
        ];

        $result = $this->runSync($rows);

        $this->assertSame(3, $result['created']);
        $this->assertSame(2, $result['inactive']);
        $this->assertDatabaseHas('siakad_skala_nilai', ['id_feeder' => 'new-a', 'is_active' => 1, 'batas_bawah' => '85.00']);
        $this->assertDatabaseHas('siakad_skala_nilai', ['id_feeder' => 'mid-a', 'is_active' => 0]);
        $old = SkalaNilai::where('id_feeder', 'old-a')->firstOrFail();
        $this->assertFalse($old->is_active);
        $this->assertSame('2020-12-31', $old->tanggal_akhir_efektif->toDateString());
    }

    public function test_is_lulus_follows_standard_letter_on_create_and_is_preserved_on_update(): void
    {
        $this->runSync($this->farmasiRows());
        $this->assertDatabaseHas('siakad_skala_nilai', ['id_feeder' => 'b-c', 'is_lulus' => 1]);
        $this->assertDatabaseHas('siakad_skala_nilai', ['id_feeder' => 'b-d', 'is_lulus' => 0]);

        DB::table('siakad_skala_nilai')->where('id_feeder', 'b-d')->update(['is_lulus' => 1, 'keterangan' => 'Ditetapkan prodi']);
        $this->runSync($this->farmasiRows());

        $this->assertDatabaseHas('siakad_skala_nilai', ['id_feeder' => 'b-d', 'is_lulus' => 1, 'keterangan' => 'Ditetapkan prodi']);
    }

    public function test_legacy_row_without_feeder_id_is_adopted_by_prodi_and_letter(): void
    {
        $legacy = DB::table('siakad_skala_nilai')->insertGetId([
            'program_studi_id' => $this->prodiId, 'nilai_huruf' => 'A', 'bobot_indeks' => 3.9, 'batas_bawah' => 80,
            'batas_atas' => 100, 'is_lulus' => 1, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = $this->runSync($this->farmasiRows());

        $this->assertSame(4, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertDatabaseHas('siakad_skala_nilai', ['id' => $legacy, 'id_feeder' => 'b-a', 'bobot_indeks' => '4.00', 'batas_bawah' => '85.00']);
        $this->assertSame(1, DB::table('siakad_skala_nilai')->where('program_studi_id', $this->prodiId)->where('nilai_huruf', 'A')->count());
    }

    public function test_prodi_scale_split_across_batch_boundary_is_processed_together(): void
    {
        $filler = array_map(fn (int $n) => $this->row("f-{$n}", 'A', '4.00', '85.00', '100.00', '01-10-2015', '30-09-2030', "prodi-filler-{$n}"), range(1, 497));
        $farmasi = $this->farmasiRows();
        $offsets = [];
        $heldBack = null;
        $this->fakeFeeder(function (int $offset) use ($filler, $farmasi, &$offsets, &$heldBack) {
            $offsets[] = $offset;
            if ($offset === 500) {
                $heldBack = DB::table('siakad_skala_nilai')->where('program_studi_id', $this->prodiId)->count();

                return array_slice($farmasi, 3); // D dan E melanjutkan grup yang sama
            }

            return array_merge($filler, array_slice($farmasi, 0, 3)); // 497 + A, B, C = 500
        }, 502);

        $result = app(SkalaNilaiIntegrationSyncService::class)->processPddikti($this->integration());

        $this->assertSame([0, 500], $offsets);
        $this->assertSame(0, $heldBack, 'grup prodi yang terpotong ditahan sampai lengkap');
        $this->assertSame(5, $result['created']);
        $this->assertSame(497, $result['skipped']);
        $this->assertSame('84.99', SkalaNilai::where('id_feeder', 'b-b')->value('batas_atas'), 'B menghitung A yang datang di batch yang sama');
        $this->assertSame('74.99', SkalaNilai::where('id_feeder', 'b-c')->value('batas_atas'));
        $this->assertSame(2, $result['pages']);
    }

    public function test_connection_test_is_read_only_and_validates_action(): void
    {
        $this->fakeFeeder(fn () => [$this->farmasiRows()[0]], 1);
        $before = DB::table('siakad_skala_nilai')->count();

        $info = app(SkalaNilaiIntegrationSyncService::class)->testPddiktiConnection('GetListSkalaNilaiProdi');

        $this->assertTrue($info['read_only']);
        $this->assertSame(1, $info['data_count']);
        $this->assertContains('bobot_nilai_min', $info['sample_fields']);
        $this->assertSame($before, DB::table('siakad_skala_nilai')->count());
        $this->expectException(\App\Services\IAM\ApiIntegrationSyncException::class);
        app(SkalaNilaiIntegrationSyncService::class)->testPddiktiConnection('GetProdi');
    }

    public function test_sync_is_queued_and_job_completes_with_progress_status_and_lock_release(): void
    {
        $admin = User::create(['username' => 'admin_skala', 'email' => 'skala@test.com', 'password' => bcrypt('password'), 'is_active' => true, 'is_verified' => true]);
        $admin->roles()->syncWithoutDetaching([Role::firstOrCreate(['slug' => 'superadmin'], ['name' => 'Super Admin', 'is_active' => true])->id]);
        $this->setUpPassport();
        config(['queue.default' => 'database']);
        Queue::fake();
        $integration = $this->integration();

        $this->actingAs($admin, 'api')->postJson("/api/admin/api-integrations/{$integration->id}/sync", ['preview' => true])->assertStatus(422);
        $queued = $this->actingAs($admin, 'api')->postJson("/api/admin/api-integrations/{$integration->id}/sync");
        $queued->assertStatus(202)->assertJsonStructure(['data' => ['progress_id']]);
        $progressId = $queued->json('data.progress_id');
        Queue::assertPushedOn('default', SyncPddiktiSkalaNilaiJob::class, fn ($job): bool => $job->progressId === $progressId);
        $this->actingAs($admin, 'api')->postJson("/api/admin/api-integrations/{$integration->id}/sync")->assertStatus(409);
        $this->actingAs($admin, 'api')->getJson("/api/admin/api-integrations/student-sync-progress/{$progressId}")
            ->assertOk()->assertJsonPath('data.status', 'queued');

        $this->fakeFeeder(fn () => $this->farmasiRows(), 5);
        Queue::pushed(SyncPddiktiSkalaNilaiJob::class)->first()->handle();

        $progress = Cache::get('siakad-skala-pddikti-sync:' . $progressId);
        $this->assertSame('completed', $progress['status']);
        $this->assertSame(5, $progress['result']['created']);
        $this->assertSame('success', $integration->fresh()->last_status);
        $lock = Cache::lock('siakad-skala-pddikti-sync:lock', 5);
        $this->assertTrue($lock->get(), 'lock dilepas setelah job selesai');
        $lock->release();
    }

    public function test_active_scale_for_prodi_prefers_prodi_specific_rows_else_standard(): void
    {
        $this->assertSame(
            DB::table('siakad_skala_nilai')->whereNull('program_studi_id')->where('is_active', 1)->count(),
            SkalaNilai::aktifUntukProdi($this->prodiId)->count(),
            'tanpa skala khusus: skala standar'
        );

        $this->runSync($this->farmasiRows());

        $scale = SkalaNilai::aktifUntukProdi($this->prodiId);
        $this->assertSame(5, $scale->count());
        $this->assertSame(['A', 'B', 'C', 'D', 'E'], $scale->pluck('nilai_huruf')->all());
        $this->assertTrue($scale->every(fn ($row) => (int) $row->program_studi_id === $this->prodiId));
        $this->assertGreaterThan(5, SkalaNilai::aktifUntukProdi(null)->count());
    }
}
