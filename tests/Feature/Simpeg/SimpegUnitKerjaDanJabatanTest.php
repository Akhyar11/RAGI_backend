<?php

namespace Tests\Feature\Simpeg;

use App\Models\Role;
use App\Models\Simpeg\Jabatan;
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
}
