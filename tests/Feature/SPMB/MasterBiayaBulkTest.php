<?php

namespace Tests\Feature\SPMB;

use App\Models\Arsip\NomorSurat;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Siakad\ProgramStudi;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\MasterBiaya;
use App\Models\Spmb\MasterKomponenBiaya;
use App\Models\User;
use App\Services\Spmb\MasterBiayaService;
use App\Services\Storage\FileStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MasterBiayaBulkTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private JalurMasuk $jalurA;

    private JalurMasuk $jalurB;

    private ProgramStudi $prodi1;

    private ProgramStudi $prodi2;

    private ProgramStudi $prodi3;

    private MasterKomponenBiaya $komponenAwal;

    private MasterKomponenBiaya $komponenUkt;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');
        $this->setUpPassport();

        $role = Role::firstOrCreate(['slug' => 'admin_spmb'], ['name' => 'Admin SPMB', 'is_active' => true]);
        $permission = Permission::firstOrCreate(
            ['slug' => 'spmb.manage'],
            ['name' => 'Kelola Admin SPMB', 'module' => 'spmb', 'action' => 'update'],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $this->admin = User::create([
            'username' => 'admin_biaya', 'email' => 'admin_biaya@test.com',
            'password' => bcrypt('password'), 'is_active' => true, 'is_verified' => true,
        ]);
        $this->admin->roles()->syncWithoutDetaching([$role->id]);

        $this->jalurA = JalurMasuk::create(['kode' => 'JA', 'nama' => 'Jalur A', 'is_active' => true]);
        $this->jalurB = JalurMasuk::create(['kode' => 'JB', 'nama' => 'Jalur B', 'is_active' => true]);
        $this->prodi1 = ProgramStudi::create(['kode_prodi' => 'P1', 'nama' => 'Farmasi', 'jenjang' => 'D3', 'is_active' => true]);
        $this->prodi2 = ProgramStudi::create(['kode_prodi' => 'P2', 'nama' => 'Perhotelan', 'jenjang' => 'D3', 'is_active' => true]);
        $this->prodi3 = ProgramStudi::create(['kode_prodi' => 'P3', 'nama' => 'Produksi Media', 'jenjang' => 'D4', 'is_active' => true]);
        $this->komponenAwal = MasterKomponenBiaya::create(['kode' => 'REG-AWAL', 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran']);
        $this->komponenUkt = MasterKomponenBiaya::create(['kode' => 'UKT', 'nama' => 'UKT Semester 1', 'kategori' => 'daftar_ulang']);

        NomorSurat::create([
            'nomor_surat' => '101/SK/OK/X/2026', 'nomor_urut' => 101, 'kode_unit' => 'TI', 'kode_klasifikasi' => 'SK',
            'bulan_romawi' => 'X', 'tahun' => 2026, 'tanggal_surat' => '2026-10-01', 'perihal' => 'SK Biaya',
            'status' => 'terpakai', 'file_path' => 'arsip/dokumen-surat/sk-biaya.pdf', 'created_by' => $this->admin->id,
        ]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'evidence_type' => 'arsip',
            'nomor_sk' => '101/SK/OK/X/2026',
            'is_active' => true,
            'items' => [
                ['komponen_biaya_id' => $this->komponenAwal->id, 'nominal' => 200000, 'dibebankan_saat_pendaftaran' => true],
                ['komponen_biaya_id' => $this->komponenUkt->id, 'nominal' => 3000000, 'dibebankan_saat_pendaftaran' => false],
            ],
        ], $override);
    }

    public function test_bulk_creates_one_row_per_jalur_and_prodi_combination_with_the_same_items(): void
    {
        $response = $this->actingAs($this->admin, 'api')->postJson('/api/spmb/master/biaya', $this->payload([
            'jalur_masuk_ids' => [$this->jalurA->id, $this->jalurB->id],
            'program_studi_ids' => [$this->prodi1->id, $this->prodi2->id, $this->prodi3->id],
        ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.ringkasan', ['total' => 6, 'dibuat' => 6, 'dilewati' => 0])
            ->assertJsonCount(6, 'data.dibuat');

        $this->assertDatabaseCount('spmb_master_biaya', 6);
        $this->assertDatabaseCount('spmb_master_biaya_item', 12);
        foreach (MasterBiaya::all() as $row) {
            $this->assertEquals(3200000, (float) $row->total_biaya);
            $this->assertSame('101/SK/OK/X/2026', $row->nomor_sk);
        }
        $this->assertCount(6, MasterBiaya::query()->get()->unique(fn ($r) => $r->jalur_masuk_id.'-'.$r->program_studi_id));
        $this->assertDatabaseHas('core_audit_logs', ['action' => 'master_biaya_dibuat_massal']);
    }

    public function test_bulk_skips_existing_combinations_including_soft_deleted_without_overwriting(): void
    {
        $existing = MasterBiaya::create(['jalur_masuk_id' => $this->jalurA->id, 'program_studi_id' => $this->prodi1->id, 'total_biaya' => 999, 'is_active' => true]);
        $trashed = MasterBiaya::create(['jalur_masuk_id' => $this->jalurA->id, 'program_studi_id' => $this->prodi2->id, 'total_biaya' => 111, 'is_active' => true]);
        $trashed->delete();

        $response = $this->actingAs($this->admin, 'api')->postJson('/api/spmb/master/biaya', $this->payload([
            'jalur_masuk_ids' => [$this->jalurA->id],
            'program_studi_ids' => [$this->prodi1->id, $this->prodi2->id, $this->prodi3->id],
        ]));

        $response->assertStatus(201)->assertJsonPath('data.ringkasan', ['total' => 3, 'dibuat' => 1, 'dilewati' => 2]);
        $alasan = collect($response->json('data.dilewati'))->pluck('alasan', 'program_studi_id');
        $this->assertSame('sudah_ada', $alasan[$this->prodi1->id]);
        $this->assertSame('terhapus', $alasan[$this->prodi2->id]);
        $this->assertSame('Jalur A', $response->json('data.dilewati.0.jalur'));

        $this->assertEquals(999, (float) $existing->fresh()->total_biaya, 'baris yang ada tidak boleh ditimpa');
        $this->assertEquals(111, (float) MasterBiaya::withTrashed()->find($trashed->id)->total_biaya);
        $this->assertDatabaseHas('spmb_master_biaya', ['jalur_masuk_id' => $this->jalurA->id, 'program_studi_id' => $this->prodi3->id]);
    }

    public function test_bulk_fails_when_every_combination_already_exists(): void
    {
        MasterBiaya::create(['jalur_masuk_id' => $this->jalurA->id, 'program_studi_id' => $this->prodi1->id, 'total_biaya' => 1, 'is_active' => true]);

        $this->actingAs($this->admin, 'api')->postJson('/api/spmb/master/biaya', $this->payload([
            'jalur_masuk_ids' => [$this->jalurA->id], 'program_studi_ids' => [$this->prodi1->id],
        ]))->assertStatus(422)->assertJsonValidationErrors('program_studi_ids');

        $this->assertDatabaseCount('spmb_master_biaya', 1);
    }

    public function test_bulk_validates_input_and_limits(): void
    {
        $post = fn (array $extra) => $this->actingAs($this->admin, 'api')->postJson('/api/spmb/master/biaya', $this->payload($extra));

        $post(['jalur_masuk_ids' => [], 'program_studi_ids' => [$this->prodi1->id]])->assertStatus(422)->assertJsonValidationErrors('jalur_masuk_ids');
        $post(['jalur_masuk_ids' => [$this->jalurA->id]])->assertStatus(422)->assertJsonValidationErrors(['program_studi_id']);
        $post(['jalur_masuk_ids' => [$this->jalurA->id, $this->jalurA->id], 'program_studi_ids' => [$this->prodi1->id]])
            ->assertStatus(422)->assertJsonValidationErrors('jalur_masuk_ids.0');
        $post(['jalur_masuk_ids' => [99999], 'program_studi_ids' => [$this->prodi1->id]])
            ->assertStatus(422)->assertJsonValidationErrors('jalur_masuk_ids.0');

        $this->assertDatabaseCount('spmb_master_biaya', 0);
    }

    public function test_bulk_requires_admin_permission(): void
    {
        $user = User::create(['username' => 'biasa_b', 'email' => 'biasa_b@test.com', 'password' => bcrypt('x'), 'is_active' => true, 'is_verified' => true]);

        $this->actingAs($user, 'api')->postJson('/api/spmb/master/biaya', $this->payload([
            'jalur_masuk_ids' => [$this->jalurA->id], 'program_studi_ids' => [$this->prodi1->id],
        ]))->assertStatus(403);
    }

    public function test_single_combination_mode_keeps_working_as_before(): void
    {
        $response = $this->actingAs($this->admin, 'api')->postJson('/api/spmb/master/biaya', $this->payload([
            'jalur_masuk_id' => $this->jalurA->id, 'program_studi_id' => $this->prodi1->id,
        ]));

        $response->assertStatus(201)->assertJsonPath('data.jalur_masuk_id', $this->jalurA->id)->assertJsonMissingPath('data.ringkasan');
        $this->assertDatabaseCount('spmb_master_biaya', 1);
    }

    public function test_shared_evidence_file_is_only_deleted_when_no_other_row_references_it(): void
    {
        $shared = 'spmb/master-biaya/sk-bersama.pdf';
        $a = MasterBiaya::create(['jalur_masuk_id' => $this->jalurA->id, 'program_studi_id' => $this->prodi1->id, 'dokumen_pendukung_path' => $shared, 'total_biaya' => 0, 'is_active' => true]);
        $b = MasterBiaya::create(['jalur_masuk_id' => $this->jalurA->id, 'program_studi_id' => $this->prodi2->id, 'dokumen_pendukung_path' => $shared, 'total_biaya' => 0, 'is_active' => true]);
        $replaceWithArsip = ['evidence_type' => 'arsip', 'nomor_sk' => '101/SK/OK/X/2026'];

        // Baris A mengganti bukti, tetapi berkas masih dipakai baris B: tidak boleh dihapus.
        $this->mock(FileStorageService::class, fn ($mock) => $mock->shouldReceive('delete')->never());
        app(MasterBiayaService::class)->updateBiaya($a, $replaceWithArsip);
        $this->assertNull($a->fresh()->dokumen_pendukung_path);
        $this->assertSame($shared, $b->fresh()->dokumen_pendukung_path);

        // Baris B adalah pemakai terakhir: berkas sekarang boleh dihapus.
        $this->mock(FileStorageService::class, fn ($mock) => $mock->shouldReceive('delete')->once()->with($shared, null, true));
        app(MasterBiayaService::class)->updateBiaya($b->fresh(), $replaceWithArsip);
        $this->assertNull($b->fresh()->dokumen_pendukung_path);
    }
}
