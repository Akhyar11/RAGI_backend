<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\PaCatatan;
use App\Models\Siakad\ProgramStudi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Otorisasi Bimbingan PA (Fase 0 Kritis).
 *
 * Menguji bahwa:
 * - Dosen PA (permission granular `siakad.pa.create/update/delete`) kini BISA
 *   menulis catatan bimbingannya (bug lama: Gate `siakad.krs.approve` menolak role dosen).
 * - Ownership tetap dijaga: dosen tidak bisa mengubah catatan PA lain (403).
 * - Endpoint read dilindungi `siakad.pa.read` (pihak tanpa permission diblokir).
 * - Advisees mengembalikan kontak (telepon & email) mahasiswa.
 */
class SiakadPaAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $dosenAUser;
    protected User $dosenBUser;
    protected User $outsiderUser;
    protected Dosen $dosenA;
    protected Dosen $dosenB;
    protected Mahasiswa $mhsA;
    protected PaCatatan $catatanDosenA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $prodi = ProgramStudi::create([
            'kode_prodi' => 'IF-PAAUTH',
            'nama'       => 'Informatika Otorisasi PA',
            'jenjang'    => 'S1',
        ]);

        // Permission yang dibutuhkan (idempoten; pa.* sudah dibuat migration).
        $permPaRead  = Permission::firstOrCreate(
            ['slug' => 'siakad.pa.read'],
            ['name' => 'Lihat Data Bimbingan PA', 'module' => 'siakad', 'action' => 'read', 'description' => 'Melihat catatan, laporan, aktivitas & komposisi bimbingan PA']
        );
        $permPaCreate = Permission::firstOrCreate(
            ['slug' => 'siakad.pa.create'],
            ['name' => 'Tambah Data Bimbingan PA', 'module' => 'siakad', 'action' => 'create', 'description' => 'Menambah catatan, laporan, aktivitas, pengajuan konsultasi & data PKL/insiden PA']
        );
        $permPaUpdate = Permission::firstOrCreate(
            ['slug' => 'siakad.pa.update'],
            ['name' => 'Ubah Data Bimbingan PA', 'module' => 'siakad', 'action' => 'update', 'description' => 'Mengubah catatan, laporan, jadwal, proses konsultasi & tindak lanjut insiden PA']
        );
        $permPaDelete = Permission::firstOrCreate(
            ['slug' => 'siakad.pa.delete'],
            ['name' => 'Hapus Data Bimbingan PA', 'module' => 'siakad', 'action' => 'delete', 'description' => 'Menghapus catatan, jadwal & data PKL bimbingan PA']
        );
        $permMhsRead = Permission::firstOrCreate(
            ['slug' => 'siakad.mahasiswa.read'],
            ['name' => 'Lihat Mahasiswa Bimbingan', 'module' => 'siakad', 'action' => 'read', 'description' => 'Melihat direktori mahasiswa wali']
        );

        // Role dosen mendapat pa.read, pa.create, pa.update, pa.delete & mahasiswa.read.
        $roleDosen = Role::firstOrCreate(['slug' => 'dosen'], ['name' => 'Dosen']);
        $roleDosen->permissions()->syncWithoutDetaching([
            $permPaRead->id,
            $permPaCreate->id,
            $permPaUpdate->id,
            $permPaDelete->id,
            $permMhsRead->id,
        ]);

        // Role tanpa satu pun permission PA (untuk uji blokir read).
        Role::firstOrCreate(['slug' => 'calon_mhs'], ['name' => 'Calon Mahasiswa']);

        $this->dosenAUser = User::factory()->create(['username' => 'dosen.auth.a']);
        $this->dosenAUser->roles()->attach($roleDosen->id);

        $this->dosenBUser = User::factory()->create(['username' => 'dosen.auth.b']);
        $this->dosenBUser->roles()->attach($roleDosen->id);

        $roleCalon = Role::where('slug', 'calon_mhs')->first();
        $this->outsiderUser = User::factory()->create(['username' => 'outsider.pa']);
        $this->outsiderUser->roles()->attach($roleCalon->id);

        $this->dosenA = Dosen::create([
            'user_id'          => $this->dosenAUser->id,
            'program_studi_id' => $prodi->id,
            'nidn'             => '0055005500',
            'nama_lengkap'     => 'Dosen Auth A',
            'status_aktif'     => 'aktif',
        ]);

        $this->dosenB = Dosen::create([
            'user_id'          => $this->dosenBUser->id,
            'program_studi_id' => $prodi->id,
            'nidn'             => '0066006600',
            'nama_lengkap'     => 'Dosen Auth B',
            'status_aktif'     => 'aktif',
        ]);

        $this->mhsA = Mahasiswa::create([
            'user_id'          => User::factory()->create(['username' => 'mhs.auth.a'])->id,
            'program_studi_id' => $prodi->id,
            'nim'              => '2026009001',
            'nama_lengkap'     => 'Mhs Bimbingan Auth A',
            'telepon'          => '081299998888',
            'email'            => 'mhs.auth@kampus.ac.id',
            'angkatan'         => 2026,
            'status'           => 'aktif',
            'dosen_wali_id'    => $this->dosenA->id,
        ]);

        // Catatan milik Dosen A (untuk uji update sendiri vs milik PA lain).
        $this->catatanDosenA = PaCatatan::create([
            'dosen_id'          => $this->dosenA->id,
            'mahasiswa_id'      => $this->mhsA->id,
            'tanggal_bimbingan' => '2026-10-01',
            'kategori'          => 'akademik',
            'isi'               => 'Bimbingan awal semester.',
        ]);
    }

    public function test_dosen_pa_dapat_membuat_catatan_bimbingan_sendiri(): void
    {
        Passport::actingAs($this->dosenAUser);

        $res = $this->postJson('/api/v1/siakad/bimbingan/catatan', [
            'mahasiswa_id'      => $this->mhsA->id,
            'kategori'          => 'krs',
            'isi'               => 'Konsultasi pengambilan SKS.',
            'tanggal_bimbingan' => '2026-10-05',
        ]);

        $res->assertStatus(201)->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('siakad_pa_catatan', [
            'dosen_id'     => $this->dosenA->id,
            'mahasiswa_id' => $this->mhsA->id,
            'kategori'     => 'krs',
        ]);
    }

    public function test_dosen_pa_dapat_mengubah_catatan_sendiri(): void
    {
        Passport::actingAs($this->dosenAUser);

        $res = $this->putJson("/api/v1/siakad/bimbingan/catatan/{$this->catatanDosenA->id}", [
            'isi'       => 'Revisi catatan bimbingan oleh PA sendiri.',
            'kesimpulan' => 'Disetujui 20 SKS.',
        ]);

        $res->assertStatus(200)->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('siakad_pa_catatan', [
            'id'    => $this->catatanDosenA->id,
            'isi'   => 'Revisi catatan bimbingan oleh PA sendiri.',
        ]);
    }

    public function test_dosen_tidak_dapat_mengubah_catatan_pa_lain(): void
    {
        // Catatan ini milik Dosen A, dicoba diubah Dosen B → 403.
        Passport::actingAs($this->dosenBUser);

        $res = $this->putJson("/api/v1/siakad/bimbingan/catatan/{$this->catatanDosenA->id}", [
            'isi' => 'Tidak boleh diubah dosen lain.',
        ]);

        $res->assertStatus(403);
        $this->assertDatabaseHas('siakad_pa_catatan', [
            'id'  => $this->catatanDosenA->id,
            'isi' => 'Bimbingan awal semester.',
        ]);
    }

    public function test_dosen_dapat_membaca_daftar_catatan(): void
    {
        Passport::actingAs($this->dosenAUser);

        $res = $this->getJson('/api/v1/siakad/bimbingan/catatan');

        $res->assertStatus(200)->assertJsonPath('status', 'success');
    }

    public function test_user_tanpa_permission_pa_diblokir_membaca_catatan(): void
    {
        Passport::actingAs($this->outsiderUser);

        $res = $this->getJson('/api/v1/siakad/bimbingan/catatan');

        $res->assertStatus(403);
    }

    public function test_advisees_mengembalikan_kontak_mahasiswa(): void
    {
        Passport::actingAs($this->dosenAUser);

        $res = $this->getJson('/api/v1/siakad/bimbingan/advisees');

        $res->assertStatus(200);
        $row = collect($res->json('data'))->firstWhere('id', $this->mhsA->id);
        $this->assertNotNull($row);
        $this->assertEquals('081299998888', $row['telepon']);
        $this->assertEquals('mhs.auth@kampus.ac.id', $row['email']);
    }
}
