<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpegPegawaiRoleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected UnitKerja $unitKerja;
    protected Role $roleDosen;
    protected Role $roleKaprodi;
    protected Role $roleTendik;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $this->admin = User::factory()->create([
            'id' => 1,
            'username' => 'superadmin',
            'email' => 'admin@campus.ac.id',
        ]);

        $this->unitKerja = UnitKerja::create([
            'nama' => 'Fakultas Teknik',
            'kode' => 'FT',
            'tipe' => 'fakultas',
            'is_active' => true,
        ]);

        $this->roleDosen = Role::firstOrCreate(['slug' => 'dosen'], ['name' => 'Dosen Pengajar', 'is_active' => true]);
        $this->roleKaprodi = Role::firstOrCreate(['slug' => 'kaprodi'], ['name' => 'Ketua Program Studi', 'is_active' => true]);
        $this->roleTendik = Role::firstOrCreate(['slug' => 'tendik'], ['name' => 'Tenaga Kependidikan', 'is_active' => true]);
    }

    public function test_can_get_available_roles()
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/simpeg/pegawai/roles');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertNotEmpty($response->json('data'));
    }

    public function test_can_create_pegawai_with_multiple_roles()
    {
        $payload = [
            'nama_lengkap' => 'Dr. Bambang Sutrisno, M.T.',
            'nip' => '198501012010121001',
            'nik' => '3271010101850001',
            'email' => 'bambang.sutrisno@campus.ac.id',
            'unit_kerja_id' => $this->unitKerja->id,
            'role_ids' => [$this->roleDosen->id, $this->roleKaprodi->id],
            'status_kepegawaian' => 'tetap_yayasan',
            'status' => 'aktif',
            'jenis_kelamin' => 'L',
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/pegawai', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $pegawai = Pegawai::where('nip', '198501012010121001')->first();
        $this->assertNotNull($pegawai);
        $this->assertCount(2, $pegawai->roles);
        $this->assertTrue($pegawai->roles->contains($this->roleDosen));
        $this->assertTrue($pegawai->roles->contains($this->roleKaprodi));

        // SSO User sync check
        $user = User::where('username', '198501012010121001')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->roles->contains($this->roleDosen));
        $this->assertTrue($user->roles->contains($this->roleKaprodi));
    }

    public function test_can_update_pegawai_roles_and_sync()
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Siti Nurhaliza, S.E.',
            'nip' => '199202022019022002',
            'jenis_kelamin' => 'P',
            'status_kepegawaian' => 'tetap_yayasan',
            'status' => 'aktif',
            'jenis_pegawai' => 'dosen',
        ]);
        $pegawai->roles()->attach([$this->roleDosen->id]);

        $updatePayload = [
            'nama_lengkap' => 'Siti Nurhaliza, S.E., M.M.',
            'role_ids' => [$this->roleDosen->id, $this->roleKaprodi->id, $this->roleTendik->id],
            'status_kepegawaian' => 'tetap_yayasan',
            'status' => 'aktif',
            'jenis_kelamin' => 'P',
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->putJson("/api/simpeg/pegawai/{$pegawai->id}", $updatePayload);

        $response->assertStatus(200);

        $pegawai->refresh();
        $this->assertCount(3, $pegawai->roles);
        $this->assertTrue($pegawai->roles->contains($this->roleTendik));
    }

    public function test_can_filter_pegawai_by_role_id()
    {
        $pegawai1 = Pegawai::create([
            'nama_lengkap' => 'Dosen Satu',
            'nip' => '1111111111',
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'tetap_yayasan',
            'status' => 'aktif',
            'jenis_pegawai' => 'dosen',
        ]);
        $pegawai1->roles()->attach([$this->roleDosen->id]);

        $pegawai2 = Pegawai::create([
            'nama_lengkap' => 'Tendik Satu',
            'nip' => '2222222222',
            'jenis_kelamin' => 'P',
            'status_kepegawaian' => 'tetap_yayasan',
            'status' => 'aktif',
            'jenis_pegawai' => 'tendik',
        ]);
        $pegawai2->roles()->attach([$this->roleTendik->id]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson("/api/simpeg/pegawai?role_id={$this->roleDosen->id}");

        $response->assertStatus(200);
        $items = $response->json('data.data');
        $this->assertCount(1, $items);
        $this->assertEquals('Dosen Satu', $items[0]['nama_lengkap']);
    }

    public function test_creating_pegawai_with_dosen_status_automatically_syncs_to_siakad_dosen()
    {
        $payload = [
            'nama_lengkap' => 'Prof. Dr. Hendra Gunawan, M.Sc.',
            'nip' => '197508102000031001',
            'nik' => '3271011008750001',
            'nidn' => '0410087501',
            'jenis_kelamin' => 'L',
            'role_ids' => [$this->roleDosen->id],
            'unit_kerja_id' => $this->unitKerja->id,
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/pegawai', $payload);

        $response->assertStatus(201);
        $pegawaiId = $response->json('data.id');

        // Pastikan otomatis tercatat di tabel siakad_dosen
        $this->assertDatabaseHas('siakad_dosen', [
            'pegawai_id' => $pegawaiId,
            'nidn' => '0410087501',
            'nama_lengkap' => 'Prof. Dr. Hendra Gunawan, M.Sc.',
            'is_active' => true,
        ]);
    }

    public function test_sso_username_priority_scales_nidn_nuptk_nip()
    {
        // 1. Dosen dengan 3 data (NIDN, NUPTK, NIP) -> Username WAJIB NIDN
        $resA = $this->actingAs($this->admin, 'api')->postJson('/api/simpeg/pegawai', [
            'nama_lengkap' => 'Dosen Tiga Identitas',
            'nidn' => '0401019001',
            'nuptk' => '8888777766665555',
            'nip' => '199001012015011001',
            'jenis_kelamin' => 'L',
            'role_ids' => [$this->roleDosen->id],
        ]);
        $resA->assertStatus(201);
        $userA = User::find($resA->json('data.user_id'));
        $this->assertNotNull($userA);
        $this->assertEquals('0401019001', $userA->username); // Prioritas 1: NIDN

        // 2. Dosen dengan NUPTK dan NIP (tanpa NIDN) -> Username WAJIB NUPTK
        $resB = $this->actingAs($this->admin, 'api')->postJson('/api/simpeg/pegawai', [
            'nama_lengkap' => 'Dosen Dua Identitas',
            'nuptk' => '9999888877776666',
            'nip' => '199202022018021002',
            'jenis_kelamin' => 'P',
            'role_ids' => [$this->roleDosen->id],
        ]);
        $resB->assertStatus(201);
        $userB = User::find($resB->json('data.user_id'));
        $this->assertNotNull($userB);
        $this->assertEquals('9999888877776666', $userB->username); // Prioritas 2: NUPTK

        // 3. Pegawai/Dosen hanya dengan NIP -> Username WAJIB NIP
        $resC = $this->actingAs($this->admin, 'api')->postJson('/api/simpeg/pegawai', [
            'nama_lengkap' => 'Pegawai Satu Identitas',
            'nip' => '199505052020011003',
            'jenis_kelamin' => 'L',
            'role_ids' => [$this->roleTendik->id],
        ]);
        $resC->assertStatus(201);
        $userC = User::find($resC->json('data.user_id'));
        $this->assertNotNull($userC);
        $this->assertEquals('199505052020011003', $userC->username); // Prioritas 3: NIP
    }
}
