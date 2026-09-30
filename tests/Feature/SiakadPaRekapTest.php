<?php

namespace Tests\Feature;

use App\Models\Siakad\Dosen;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\PaCatatan;
use App\Models\Siakad\ProgramStudi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SiakadPaRekapTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Dosen $dosenA;
    protected Dosen $dosenB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $prodi = ProgramStudi::create([
            'kode_prodi' => 'IF-PA',
            'nama'       => 'Informatika PA',
            'jenjang'    => 'S1',
        ]);

        $this->admin = User::factory()->create(['username' => 'admin.pa']);
        $roleAdmin = \App\Models\Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $this->admin->roles()->attach($roleAdmin->id);

        $this->dosenA = Dosen::create([
            'user_id'          => User::factory()->create(['username' => 'dosen.a'])->id,
            'program_studi_id' => $prodi->id,
            'nidn'             => '0033003300',
            'nama_lengkap'     => 'Dosen A Pembimbing',
            'status_aktif'     => 'aktif',
        ]);

        $this->dosenB = Dosen::create([
            'user_id'          => User::factory()->create(['username' => 'dosen.b'])->id,
            'program_studi_id' => $prodi->id,
            'nidn'             => '0044004400',
            'nama_lengkap'     => 'Dosen B Pembimbing',
            'status_aktif'     => 'aktif',
        ]);

        $mhsA = Mahasiswa::create([
            'user_id'          => User::factory()->create(['username' => 'mhs.pa.a'])->id,
            'program_studi_id' => $prodi->id,
            'nim'              => '2026008001',
            'nama_lengkap'     => 'Mhs Bimbingan A',
            'angkatan'         => 2026,
            'status_akademik'  => 'aktif',
            'dosen_wali_id'    => $this->dosenA->id,
        ]);

        $mhsB = Mahasiswa::create([
            'user_id'          => User::factory()->create(['username' => 'mhs.pa.b'])->id,
            'program_studi_id' => $prodi->id,
            'nim'              => '2026008002',
            'nama_lengkap'     => 'Mhs Bimbingan B',
            'angkatan'         => 2026,
            'status_akademik'  => 'aktif',
            'dosen_wali_id'    => $this->dosenB->id,
        ]);

        // Dosen A: 1 sesi September + 1 sesi Agustus
        PaCatatan::create([
            'dosen_id'          => $this->dosenA->id,
            'mahasiswa_id'      => $mhsA->id,
            'tanggal_bimbingan' => '2026-09-10',
            'kategori'          => 'akademik',
            'isi'               => 'Bimbingan KRS September.',
        ]);
        PaCatatan::create([
            'dosen_id'          => $this->dosenA->id,
            'mahasiswa_id'      => $mhsA->id,
            'tanggal_bimbingan' => '2026-08-05',
            'kategori'          => 'akademik',
            'isi'               => 'Bimbingan awal Agustus.',
        ]);

        // Dosen B: 1 sesi September
        PaCatatan::create([
            'dosen_id'          => $this->dosenB->id,
            'mahasiswa_id'      => $mhsB->id,
            'tanggal_bimbingan' => '2026-09-15',
            'kategori'          => 'krs',
            'isi'               => 'Bimbingan KRS September.',
        ]);
    }

    public function test_rekap_tanpa_filter_menampilkan_semua_sesi(): void
    {
        Passport::actingAs($this->admin);

        $res = $this->getJson('/api/v1/siakad/bimbingan/rekap');
        $res->assertStatus(200)->assertJsonPath('status', 'success');

        $rows = collect($res->json('data'));
        $this->assertEquals(2, $rows->firstWhere('dosen_id', $this->dosenA->id)['total_bimbingan']);
        $this->assertEquals(1, $rows->firstWhere('dosen_id', $this->dosenB->id)['total_bimbingan']);
    }

    public function test_rekap_filter_rentang_tanggal_membatasi_sesi(): void
    {
        Passport::actingAs($this->admin);

        $res = $this->getJson('/api/v1/siakad/bimbingan/rekap?dari_tanggal=2026-09-01&sampai_tanggal=2026-09-30');
        $res->assertStatus(200);

        $rows = collect($res->json('data'));
        $this->assertEquals(1, $rows->firstWhere('dosen_id', $this->dosenA->id)['total_bimbingan']);
        $this->assertEquals('2026-09-10', substr((string) $rows->firstWhere('dosen_id', $this->dosenA->id)['terakhir_bimbingan_at'], 0, 10));
        $this->assertEquals(1, $rows->firstWhere('dosen_id', $this->dosenB->id)['total_bimbingan']);
    }

    public function test_rekap_filter_dosen_id(): void
    {
        Passport::actingAs($this->admin);

        $res = $this->getJson("/api/v1/siakad/bimbingan/rekap?dosen_id={$this->dosenB->id}");
        $res->assertStatus(200);

        $rows = $res->json('data');
        $this->assertCount(1, $rows);
        $this->assertEquals($this->dosenB->id, $rows[0]['dosen_id']);
    }
}
