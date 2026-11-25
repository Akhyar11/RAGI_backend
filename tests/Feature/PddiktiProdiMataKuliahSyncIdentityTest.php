<?php

namespace Tests\Feature;

use App\Models\IamApiIntegration;
use App\Models\SystemSetting;
use App\Services\IAM\MataKuliahIntegrationSyncService;
use App\Services\IAM\ProgramStudiIntegrationSyncService;
use Database\Seeders\IAM\IamApiIntegrationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sync PDDIKTI memakai UUID Feeder sebagai identitas (bukan kode):
 * kode prodi/mata kuliah dapat sama untuk UUID berbeda, SKS null berarti 0, dan satu id_matkul = satu baris lokal.
 */
class PddiktiProdiMataKuliahSyncIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(IamApiIntegrationSeeder::class)->run();
        SystemSetting::set('feeder_url', 'https://feeder.example.test/ws/live2.php');
        SystemSetting::set('feeder_username', 'kampus-test');
        SystemSetting::set('feeder_password', 'credential-is-stored-centrally');
        DB::table('spmb_master_referensi')->insert([
            'modul' => 'siakad', 'tipe' => 'jenjang_prodi', 'kode' => 'D3', 'nama' => 'Diploma 3',
            'urutan' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function fakeFeeder(array $responsesByAction): void
    {
        Http::fake([
            'https://feeder.example.test/ws/live2.php' => function ($request) use ($responsesByAction) {
                $act = json_decode($request->body(), true)['act'] ?? null;
                if ($act === 'GetToken') {
                    return Http::response(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200);
                }

                return Http::response(['error_code' => 0, 'data' => $responsesByAction[$act] ?? []], 200);
            },
        ]);
    }

    private function integration(string $key): IamApiIntegration
    {
        $integration = IamApiIntegration::where('key', $key)->firstOrFail();
        $integration->update(['source_type' => 'pddikti']);

        return $integration->fresh();
    }

    private function prodiRow(string $uuid, string $code, string $name, string $status = 'A'): array
    {
        return ['id_prodi' => $uuid, 'kode_program_studi' => $code, 'nama_program_studi' => $name,
            'nama_jenjang_pendidikan' => 'D3', 'status' => $status];
    }

    public function test_two_feeder_prodi_sharing_a_code_are_both_synced_by_uuid(): void
    {
        $existingId = DB::table('siakad_program_studi')->insertGetId([
            'kode_prodi' => '21403', 'nama' => 'D3 Mesin Otomotif', 'jenjang' => 'D3', 'id_feeder' => 'uuid-mesin',
            'source_id' => 'uuid-mesin', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->fakeFeeder(['GetProdi' => [
            $this->prodiRow('uuid-mesin', '21403', 'Mesin Otomotif'),
            $this->prodiRow('uuid-teknologi-otomotif', '21403', 'Teknologi Otomotif'),
        ]]);

        $result = app(ProgramStudiIntegrationSyncService::class)->processPddikti($this->integration('siakad_program_studi'));

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['skipped']);
        $this->assertDatabaseHas('siakad_program_studi', ['id' => $existingId, 'kode_prodi' => '21403', 'id_feeder' => 'uuid-mesin']);
        $new = DB::table('siakad_program_studi')->where('id_feeder', 'uuid-teknologi-otomotif')->first();
        $this->assertNotNull($new);
        $this->assertSame('21403-uuid-tek', $new->kode_prodi, 'kode lokal dibedakan agar tetap unik');
        $this->assertSame('21403', $new->kode_prodi_dikti, 'kode resmi Feeder tetap tersimpan');
    }

    public function test_prodi_sync_repeats_without_duplicates_and_keeps_disambiguated_code(): void
    {
        DB::table('siakad_program_studi')->insert([
            'kode_prodi' => '21403', 'nama' => 'D3 Mesin Otomotif', 'jenjang' => 'D3', 'id_feeder' => 'uuid-mesin',
            'source_id' => 'uuid-mesin', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->fakeFeeder(['GetProdi' => [
            $this->prodiRow('uuid-mesin', '21403', 'Mesin Otomotif'),
            $this->prodiRow('uuid-teknologi-otomotif', '21403', 'Teknologi Otomotif'),
        ]]);
        $integration = $this->integration('siakad_program_studi');

        app(ProgramStudiIntegrationSyncService::class)->processPddikti($integration);
        $second = app(ProgramStudiIntegrationSyncService::class)->processPddikti($integration);

        $this->assertSame(0, $second['created']);
        $this->assertSame(2, $second['updated']);
        $this->assertSame(0, $second['skipped']);
        $this->assertSame(2, DB::table('siakad_program_studi')->count());
        $this->assertSame('21403-uuid-tek', DB::table('siakad_program_studi')->where('id_feeder', 'uuid-teknologi-otomotif')->value('kode_prodi'));
    }

    public function test_prodi_row_without_identity_is_still_adopted_by_code(): void
    {
        $legacyId = DB::table('siakad_program_studi')->insertGetId([
            'kode_prodi' => '48401', 'nama' => 'Farmasi Lama', 'jenjang' => 'D3', 'id_feeder' => null,
            'source_id' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->fakeFeeder(['GetProdi' => [$this->prodiRow('uuid-farmasi', '48401', 'Farmasi')]]);

        $result = app(ProgramStudiIntegrationSyncService::class)->processPddikti($this->integration('siakad_program_studi'));

        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['created']);
        $this->assertDatabaseHas('siakad_program_studi', ['id' => $legacyId, 'id_feeder' => 'uuid-farmasi', 'kode_prodi' => '48401']);
        $this->assertSame(1, DB::table('siakad_program_studi')->count());
    }

    /** @return array{int, int, int} prodi, kurikulum lama, kurikulum baru */
    private function seedProdiAndCurricula(): array
    {
        $prodi = DB::table('siakad_program_studi')->insertGetId([
            'kode_prodi' => '48401', 'nama' => 'D3 Farmasi', 'jenjang' => 'D3', 'id_feeder' => 'prodi-uuid',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $curriculum = fn (string $kode, int $year, string $uuid) => DB::table('siakad_kurikulum')->insertGetId([
            'program_studi_id' => $prodi, 'kode' => $kode, 'nama' => $kode, 'tahun_berlaku' => $year,
            'id_feeder' => $uuid, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$prodi, $curriculum('KUR-2018', 2018, 'kur-uuid-2018'), $curriculum('KUR-2023', 2023, 'kur-uuid-2023')];
    }

    private function matkulRow(string $uuid, string $code, string $kurikulumUuid, array $override = []): array
    {
        return array_merge([
            'id_kurikulum' => $kurikulumUuid, 'id_matkul' => $uuid, 'kode_mata_kuliah' => $code,
            'nama_mata_kuliah' => 'Praktikum Kimia Farmasi', 'id_prodi' => 'prodi-uuid', 'semester' => '1',
            'sks_mata_kuliah' => '2.00', 'sks_tatap_muka' => null, 'sks_praktek' => '2.00',
            'sks_praktek_lapangan' => null, 'sks_simulasi' => null, 'apakah_wajib' => '1',
        ], $override);
    }

    public function test_null_sks_components_are_treated_as_zero_instead_of_dropping_the_course(): void
    {
        [, $kur2018] = $this->seedProdiAndCurricula();
        $this->fakeFeeder(['GetMatkulKurikulum' => [
            $this->matkulRow('mk-1', 'MPB1205', 'kur-uuid-2018'),
            $this->matkulRow('mk-2', 'FMIK1101', 'kur-uuid-2018', ['sks_tatap_muka' => '2.00', 'sks_praktek' => null, 'nama_mata_kuliah' => 'Teori']),
        ]]);

        $result = app(MataKuliahIntegrationSyncService::class)->processPddikti($this->integration('siakad_mata_kuliah'));

        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertDatabaseHas('siakad_mata_kuliah', ['id_feeder' => 'mk-1', 'kurikulum_id' => $kur2018, 'sks_teori' => 0, 'sks_praktik' => 2, 'total_sks' => 2]);
        $this->assertDatabaseHas('siakad_mata_kuliah', ['id_feeder' => 'mk-2', 'sks_teori' => 2, 'sks_praktik' => 0, 'total_sks' => 2]);
    }

    public function test_same_code_with_different_uuid_in_different_curricula_creates_separate_courses(): void
    {
        [, $kur2018, $kur2023] = $this->seedProdiAndCurricula();
        $this->fakeFeeder(['GetMatkulKurikulum' => [
            $this->matkulRow('mk-old', 'MPB1205', 'kur-uuid-2018'),
            $this->matkulRow('mk-new', 'MPB1205', 'kur-uuid-2023'),
        ]]);

        $result = app(MataKuliahIntegrationSyncService::class)->processPddikti($this->integration('siakad_mata_kuliah'));

        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertDatabaseHas('siakad_mata_kuliah', ['id_feeder' => 'mk-old', 'kurikulum_id' => $kur2018, 'kode_mk' => 'MPB1205']);
        $this->assertDatabaseHas('siakad_mata_kuliah', ['id_feeder' => 'mk-new', 'kurikulum_id' => $kur2023, 'kode_mk' => 'MPB1205']);
    }

    public function test_course_in_several_curricula_becomes_one_row_in_latest_curriculum(): void
    {
        [, , $kur2023] = $this->seedProdiAndCurricula();
        $this->fakeFeeder(['GetMatkulKurikulum' => [
            $this->matkulRow('mk-shared', 'FMIK1208', 'kur-uuid-2018'),
            $this->matkulRow('mk-shared', 'FMIK1208', 'kur-uuid-2023'),
        ]]);

        $result = app(MataKuliahIntegrationSyncService::class)->processPddikti($this->integration('siakad_mata_kuliah'));

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(1, $result['multi_curriculum']);
        $this->assertSame(1, DB::table('siakad_mata_kuliah')->where('id_feeder', 'mk-shared')->count());
        $this->assertDatabaseHas('siakad_mata_kuliah', ['id_feeder' => 'mk-shared', 'kurikulum_id' => $kur2023]);
    }

    public function test_course_sync_is_idempotent_and_keeps_local_curriculum_placement(): void
    {
        [, $kur2018, $kur2023] = $this->seedProdiAndCurricula();
        $this->fakeFeeder(['GetMatkulKurikulum' => [$this->matkulRow('mk-1', 'MPB1205', 'kur-uuid-2018')]]);
        $integration = $this->integration('siakad_mata_kuliah');
        app(MataKuliahIntegrationSyncService::class)->processPddikti($integration);

        // Feeder kini juga mencantumkan mata kuliah yang sama di kurikulum yang lebih baru.
        $this->fakeFeeder(['GetMatkulKurikulum' => [
            $this->matkulRow('mk-1', 'MPB1205', 'kur-uuid-2018'),
            $this->matkulRow('mk-1', 'MPB1205', 'kur-uuid-2023'),
        ]]);
        $second = app(MataKuliahIntegrationSyncService::class)->processPddikti($integration);

        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['updated']);
        $this->assertSame(1, DB::table('siakad_mata_kuliah')->count());
        $this->assertDatabaseHas('siakad_mata_kuliah', ['id_feeder' => 'mk-1', 'kurikulum_id' => $kur2018]);
        $this->assertNotSame($kur2023, (int) DB::table('siakad_mata_kuliah')->value('kurikulum_id'));
    }

    public function test_legacy_course_without_feeder_id_is_adopted_by_code_within_same_curriculum(): void
    {
        [, $kur2018] = $this->seedProdiAndCurricula();
        $legacyId = DB::table('siakad_mata_kuliah')->insertGetId([
            'kurikulum_id' => $kur2018, 'kode_mk' => 'MPB1205', 'nama' => 'Nama Lama', 'sks_teori' => 0, 'sks_praktik' => 0,
            'total_sks' => 0, 'semester_anjuran' => 1, 'tipe' => 'wajib', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->fakeFeeder(['GetMatkulKurikulum' => [$this->matkulRow('mk-1', 'MPB1205', 'kur-uuid-2018')]]);

        $result = app(MataKuliahIntegrationSyncService::class)->processPddikti($this->integration('siakad_mata_kuliah'));

        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['created']);
        $this->assertSame(1, DB::table('siakad_mata_kuliah')->count(), 'tidak boleh membuat baris kedua');
        $this->assertDatabaseHas('siakad_mata_kuliah', ['id' => $legacyId, 'id_feeder' => 'mk-1', 'total_sks' => 2]);
    }

    public function test_two_feeder_courses_with_same_code_in_same_curriculum_do_not_overwrite_each_other(): void
    {
        $this->seedProdiAndCurricula();
        $this->fakeFeeder(['GetMatkulKurikulum' => [
            $this->matkulRow('mk-a', 'MPB1205', 'kur-uuid-2018'),
            $this->matkulRow('mk-b', 'MPB1205', 'kur-uuid-2018'),
        ]]);

        $result = app(MataKuliahIntegrationSyncService::class)->processPddikti($this->integration('siakad_mata_kuliah'));

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['skipped']);
        $this->assertStringContainsString('sudah dipakai mata kuliah Feeder lain', $result['skipped_items'][0]['reason']);
        $this->assertSame(1, DB::table('siakad_mata_kuliah')->count());
    }

    public function test_course_code_is_unique_per_curriculum_at_database_level(): void
    {
        [, $kur2018, $kur2023] = $this->seedProdiAndCurricula();
        $insert = fn (int $kurikulum, string $feeder) => DB::table('siakad_mata_kuliah')->insert([
            'kurikulum_id' => $kurikulum, 'kode_mk' => 'DUP1', 'nama' => 'Dup', 'sks_teori' => 1, 'sks_praktik' => 0,
            'total_sks' => 1, 'semester_anjuran' => 1, 'tipe' => 'wajib', 'is_active' => true, 'id_feeder' => $feeder,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $insert($kur2018, 'f-1');
        $insert($kur2023, 'f-2');
        $this->assertSame(2, DB::table('siakad_mata_kuliah')->where('kode_mk', 'DUP1')->count());

        $this->expectException(\Illuminate\Database\QueryException::class);
        $insert($kur2018, 'f-3');
    }

    public function test_course_fetch_retries_transport_timeout_on_the_same_offset(): void
    {
        $this->seedProdiAndCurricula();
        $calls = 0;
        Http::fake([
            'https://feeder.example.test/ws/live2.php' => function ($request) use (&$calls) {
                $act = json_decode($request->body(), true)['act'] ?? null;
                if ($act === 'GetToken') {
                    return Http::response(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200);
                }
                if ($act !== 'GetMatkulKurikulum') {
                    return Http::response(['error_code' => 0, 'data' => '1'], 200);
                }
                $calls++;
                if ($calls === 1) {
                    throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out');
                }

                return Http::response(['error_code' => 0, 'data' => [$this->matkulRow('mk-1', 'MPB1205', 'kur-uuid-2018')]], 200);
            },
        ]);

        $result = app(MataKuliahIntegrationSyncService::class)->processPddikti($this->integration('siakad_mata_kuliah'));

        $this->assertSame(2, $calls, 'Timeout pertama diulang tepat sekali');
        $this->assertSame(1, $result['created']);
    }

    public function test_course_fetch_gives_up_after_retries_with_a_clear_error(): void
    {
        $this->seedProdiAndCurricula();
        $calls = 0;
        Http::fake([
            'https://feeder.example.test/ws/live2.php' => function ($request) use (&$calls) {
                $act = json_decode($request->body(), true)['act'] ?? null;
                if ($act === 'GetToken') {
                    return Http::response(['error_code' => 0, 'data' => ['token' => 'runtime-feeder-token']], 200);
                }
                if ($act !== 'GetMatkulKurikulum') {
                    return Http::response(['error_code' => 0, 'data' => '1'], 200);
                }
                $calls++;
                throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out');
            },
        ]);

        try {
            app(MataKuliahIntegrationSyncService::class)->processPddikti($this->integration('siakad_mata_kuliah'));
            $this->fail('Sync harus gagal setelah retry habis.');
        } catch (\App\Services\IAM\ApiIntegrationSyncException $e) {
            $this->assertSame(3, $calls, '1 percobaan awal + 2 retry');
            $this->assertStringContainsString('Gagal mengambil data mata kuliah', $e->getMessage());
        }
        $this->assertSame(0, DB::table('siakad_mata_kuliah')->count());
    }
}
