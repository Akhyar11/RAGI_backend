<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\ProgramStudi;
use App\Services\Spmb\SpmbKonversiService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SiakadMahasiswaImportNimTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected ProgramStudi $prodi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $adminRole = Role::firstOrCreate(
            ['slug' => 'superadmin'],
            ['name' => 'Super Administrator', 'is_active' => true]
        );

        $permission = Permission::firstOrCreate(
            ['slug' => 'siakad.mahasiswa.manage'],
            ['name' => 'Kelola Data Mahasiswa & NIM', 'module' => 'siakad', 'action' => 'update']
        );
        $adminRole->permissions()->syncWithoutDetaching([$permission->id]);

        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->sync([$adminRole->id]);

        $this->prodi = ProgramStudi::create([
            'kode_prodi' => 'TI',
            'nama' => 'Teknik Informatika',
            'jenjang' => 'S1',
            'status' => 'aktif',
        ]);
    }

    public function test_can_import_nim_with_comma_delimiter(): void
    {
        Passport::actingAs($this->adminUser);

        $mhsUser = User::factory()->create();
        $mhs = Mahasiswa::create([
            'user_id' => $mhsUser->id,
            'program_studi_id' => $this->prodi->id,
            'nim' => 'TEMP-001',
            'nama_lengkap' => 'Budi Pratama',
            'tanggal_lahir' => '2004-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'angkatan' => 2026,
        ]);

        $csvContent = "ID,Nama,Prodi,NIM Baru\n{$mhs->id},Budi Pratama,Teknik Informatika,2601001\n";
        $file = UploadedFile::fake()->createWithContent('mapping_nim.csv', $csvContent);

        $response = $this->postJson('/api/v1/siakad/mahasiswa/import-nim', [
            'file' => $file,
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.updated_count', 1);

        $this->assertDatabaseHas('siakad_mahasiswa', [
            'id' => $mhs->id,
            'nim' => '2601001',
        ]);
    }

    public function test_can_import_nim_with_semicolon_delimiter_and_bom(): void
    {
        Passport::actingAs($this->adminUser);

        $mhsUser = User::factory()->create();
        $mhs = Mahasiswa::create([
            'user_id' => $mhsUser->id,
            'program_studi_id' => $this->prodi->id,
            'nim' => 'TEMP-002',
            'nama_lengkap' => 'Siti Rahma',
            'tanggal_lahir' => '2004-05-10',
            'jenis_kelamin' => 'P',
            'status' => 'aktif',
            'angkatan' => 2026,
        ]);

        $bom = "\xEF\xBB\xBF";
        $csvContent = $bom . "ID;Nama;Prodi;NIM Baru\n{$mhs->id};Siti Rahma;Teknik Informatika;2601002\n";
        $file = UploadedFile::fake()->createWithContent('mapping_nim_excel.csv', $csvContent);

        $response = $this->postJson('/api/v1/siakad/mahasiswa/import-nim', [
            'file' => $file,
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.updated_count', 1);

        $this->assertDatabaseHas('siakad_mahasiswa', [
            'id' => $mhs->id,
            'nim' => '2601002',
        ]);
    }

    public function test_generate_nim_uses_program_study_prefix_and_incrementing_sequence(): void
    {
        $this->prodi->update(['prefix_nim' => 'D']);

        Mahasiswa::create([
            'program_studi_id' => $this->prodi->id,
            'nim' => 'D25010',
            'nama_lengkap' => 'Mahasiswa Sebelumnya',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'angkatan' => 2025,
        ]);

        $nim = app(SpmbKonversiService::class)->generateNIM(2025, $this->prodi->id);

        $this->assertSame('D25011', $nim);
    }

    public function test_generate_nim_uses_standard_fallback_when_prefix_is_empty(): void
    {
        $nim = app(SpmbKonversiService::class)->generateNIM(2025, $this->prodi->id);
        $expectedPrefix = '25' . str_pad((string) $this->prodi->id, 2, '0', STR_PAD_LEFT);

        $this->assertSame($expectedPrefix . '0001', $nim);
    }
}
