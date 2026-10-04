<?php

namespace Tests\Feature\Simpeg;

use App\Models\Role;
use App\Models\Simpeg\Jabatan;
use App\Models\Simpeg\MasterGolonganPangkat;
use App\Models\Simpeg\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpegUnitKerjaDanJabatanTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $roleSuper = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Administrator', 'is_active' => true]);

        $this->admin = User::factory()->create([
            'id' => 1,
            'username' => 'superadmin',
            'email' => 'admin@campus.ac.id',
        ]);
        $this->admin->roles()->attach($roleSuper->id);

        foreach ([
            ['kode' => 'tenaga_pengajar', 'nama' => 'Tenaga Pengajar', 'urutan' => 1, 'is_active' => true],
            ['kode' => 'asisten_ahli', 'nama' => 'Asisten Ahli', 'urutan' => 2, 'is_active' => true],
            ['kode' => 'lektor', 'nama' => 'Lektor', 'urutan' => 3, 'is_active' => true],
            ['kode' => 'lektor_kepala', 'nama' => 'Lektor Kepala', 'urutan' => 4, 'is_active' => true],
            ['kode' => 'guru_besar', 'nama' => 'Guru Besar', 'urutan' => 5, 'is_active' => true],
        ] as $gp) {
            MasterGolonganPangkat::firstOrCreate(['kode' => $gp['kode']], $gp);
        }
    }

    public function test_can_create_unit_kerja_successfully()
    {
        $payload = [
            'kode' => 'REK-01',
            'nama' => 'Rektorat Utama',
            'tipe' => 'rektorat',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/unit-kerja', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.kode', 'REK-01');

        $this->assertDatabaseHas('simpeg_unit_kerja', [
            'kode' => 'REK-01',
            'nama' => 'Rektorat Utama',
        ]);
    }

    public function test_duplicate_kode_unit_kerja_returns_validation_error()
    {
        UnitKerja::create([
            'kode' => 'REK-01',
            'nama' => 'Rektorat Lama',
            'tipe' => 'rektorat',
            'is_active' => true,
        ]);

        $payload = [
            'kode' => 'REK-01',
            'nama' => 'Rektorat Baru',
            'tipe' => 'rektorat',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/unit-kerja', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['kode']);
    }

    public function test_can_update_unit_kerja_keeping_same_kode()
    {
        $unit = UnitKerja::create([
            'kode' => 'FK-01',
            'nama' => 'Fakultas Kedokteran',
            'tipe' => 'fakultas',
            'is_active' => true,
        ]);

        $payload = [
            'kode' => 'FK-01',
            'nama' => 'Fakultas Kedokteran dan Kesehatan',
            'tipe' => 'fakultas',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->putJson("/api/simpeg/unit-kerja/{$unit->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('simpeg_unit_kerja', [
            'id' => $unit->id,
            'nama' => 'Fakultas Kedokteran dan Kesehatan',
        ]);
    }

    public function test_can_create_child_unit_kerja_with_valid_induk_id()
    {
        $parent = UnitKerja::create([
            'kode' => 'FT-01',
            'nama' => 'Fakultas Teknik',
            'tipe' => 'fakultas',
            'is_active' => true,
        ]);

        $payload = [
            'induk_id' => $parent->id,
            'kode' => 'TI-01',
            'nama' => 'Prodi Teknik Informatika',
            'tipe' => 'prodi',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/unit-kerja', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('simpeg_unit_kerja', [
            'kode' => 'TI-01',
            'induk_id' => $parent->id,
        ]);
    }

    public function test_cannot_create_jabatan_with_invalid_unit_kerja_id()
    {
        $payload = [
            'unit_kerja_id' => 999999,
            'nama' => 'Staff Keuangan',
            'tipe' => 'teknis',
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/jabatan', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['unit_kerja_id']);
    }

    public function test_can_create_jabatan_with_valid_unit_kerja_id()
    {
        $unit = UnitKerja::create([
            'kode' => 'LP-01',
            'nama' => 'LP3M',
            'tipe' => 'lp3m',
            'is_active' => true,
        ]);

        $payload = [
            'unit_kerja_id' => $unit->id,
            'nama' => 'Ketua LP3M',
            'tipe' => 'struktural',
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/jabatan', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('simpeg_jabatan', [
            'unit_kerja_id' => $unit->id,
            'nama' => 'Ketua LP3M',
        ]);
    }

    public function test_can_create_jabatan_fungsional_successfully()
    {
        $payload = [
            'nama' => 'Tenaga Pengajar',
            'angka_kredit_min' => 0,
            'angka_kredit_max' => 100,
            'golongan' => 'asisten_ahli',
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/jabatan-fungsional', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.nama', 'Tenaga Pengajar');

        $this->assertDatabaseHas('simpeg_jabatan_fungsional_akademik', [
            'nama' => 'Tenaga Pengajar',
            'golongan' => 'asisten_ahli',
        ]);
    }

    public function test_can_create_jabatan_fungsional_with_golongan_tenaga_pengajar()
    {
        $payload = [
            'nama' => 'Tenaga Pengajar Non-Jafung',
            'angka_kredit_min' => 0,
            'angka_kredit_max' => 99,
            'golongan' => 'tenaga_pengajar',
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/jabatan-fungsional', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.golongan', 'tenaga_pengajar');

        $this->assertDatabaseHas('simpeg_jabatan_fungsional_akademik', [
            'nama' => 'Tenaga Pengajar Non-Jafung',
            'golongan' => 'tenaga_pengajar',
        ]);
    }

    public function test_duplicate_nama_jabatan_fungsional_returns_validation_error()
    {
        \App\Models\Simpeg\JabatanFungsionalAkademik::create([
            'nama' => 'Tenaga Pengajar',
            'angka_kredit_min' => 0,
            'angka_kredit_max' => 100,
            'golongan' => 'asisten_ahli',
        ]);

        $payload = [
            'nama' => 'Tenaga Pengajar',
            'angka_kredit_min' => 50,
            'angka_kredit_max' => 150,
            'golongan' => 'lektor',
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/jabatan-fungsional', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nama']);
    }

    public function test_can_delete_unit_kerja_when_not_in_use()
    {
        $unit = UnitKerja::create([
            'kode' => 'UNIT-DEL-01',
            'nama' => 'Unit Sementara',
            'tipe' => 'unit',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/simpeg/unit-kerja/{$unit->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('simpeg_unit_kerja', [
            'id' => $unit->id,
        ]);
    }

    public function test_cannot_delete_unit_kerja_when_has_children()
    {
        $parent = UnitKerja::create([
            'kode' => 'PARENT-01',
            'nama' => 'Fakultas Induk',
            'tipe' => 'fakultas',
            'is_active' => true,
        ]);

        UnitKerja::create([
            'induk_id' => $parent->id,
            'kode' => 'CHILD-01',
            'nama' => 'Prodi Cabang',
            'tipe' => 'prodi',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/simpeg/unit-kerja/{$parent->id}");

        $response->assertStatus(422);

        $this->assertDatabaseHas('simpeg_unit_kerja', [
            'id' => $parent->id,
        ]);
    }

    public function test_can_delete_jabatan_when_not_in_use()
    {
        $jabatan = Jabatan::create([
            'nama' => 'Koordinator Lab IT',
            'tipe' => 'struktural',
            'level_jabatan' => 3,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/simpeg/jabatan/{$jabatan->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('simpeg_jabatan', [
            'id' => $jabatan->id,
        ]);
    }

    public function test_cannot_delete_jabatan_when_used_in_riwayat_jabatan()
    {
        $jabatan = Jabatan::create([
            'nama' => 'Kepala Biro Kepegawaian',
            'tipe' => 'struktural',
            'level_jabatan' => 2,
            'is_active' => true,
        ]);

        $pegawai = \App\Models\Simpeg\Pegawai::create([
            'user_id' => $this->admin->id,
            'nip' => 'PEG-TEST-001',
            'nama_lengkap' => 'Dr. Test Pegawai',
            'is_active' => true,
        ]);

        \App\Models\Simpeg\RiwayatJabatan::create([
            'pegawai_id' => $pegawai->id,
            'jabatan_id' => $jabatan->id,
            'mulai_jabatan' => '2026-01-01',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/simpeg/jabatan/{$jabatan->id}");

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseHas('simpeg_jabatan', [
            'id' => $jabatan->id,
        ]);
    }
}
