<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Tests\TestCase;

class SimpegPegawaiExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;
    protected UnitKerja $unitKerja1;
    protected UnitKerja $unitKerja2;
    protected Pegawai $pegawai1;
    protected Pegawai $pegawai2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $permRead = Permission::firstOrCreate(['slug' => 'simpeg.pegawai.read'], [
            'name' => 'Lihat Pegawai',
            'module' => 'simpeg',
            'action' => 'read',
        ]);
        $permManage = Permission::firstOrCreate(['slug' => 'simpeg.pegawai.manage'], [
            'name' => 'Kelola Pegawai',
            'module' => 'simpeg',
            'action' => 'update',
        ]);

        $roleAdmin = Role::firstOrCreate(['slug' => 'admin_simpeg', 'name' => 'Admin SIMPEG']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permRead, $permManage]);

        $this->admin = User::factory()->create([
            'username' => 'adminsimpeg',
            'email' => 'admin.simpeg@campus.ac.id',
        ]);
        $this->admin->roles()->attach($roleAdmin->id);

        $this->regularUser = User::factory()->create([
            'username' => 'userbiasa',
            'email' => 'userbiasa@campus.ac.id',
        ]);

        $this->unitKerja1 = UnitKerja::create([
            'nama' => 'Fakultas Teknik',
            'kode' => 'FT',
            'tipe' => 'fakultas',
            'is_active' => true,
        ]);

        $this->unitKerja2 = UnitKerja::create([
            'nama' => 'Fakultas Ekonomi',
            'kode' => 'FE',
            'tipe' => 'fakultas',
            'is_active' => true,
        ]);

        $userPegawai1 = User::factory()->create([
            'username' => 'dosen_budi',
            'email' => 'budi@campus.ac.id',
        ]);

        $this->pegawai1 = Pegawai::create([
            'user_id' => $userPegawai1->id,
            'unit_kerja_id' => $this->unitKerja1->id,
            'nip' => '198501012010121001',
            'nidn' => '0401018501',
            'nama_lengkap' => 'Budi Santoso',
            'jenis_kelamin' => 'L',
            'jenis_pegawai' => 'dosen',
            'status_kepegawaian' => 'tetap_yayasan',
            'status' => 'aktif',
        ]);

        $userPegawai2 = User::factory()->create([
            'username' => 'tendik_siti',
            'email' => 'siti@campus.ac.id',
        ]);

        $this->pegawai2 = Pegawai::create([
            'user_id' => $userPegawai2->id,
            'unit_kerja_id' => $this->unitKerja2->id,
            'nip' => '199002022015012002',
            'nama_lengkap' => 'Siti Aminah',
            'jenis_kelamin' => 'P',
            'jenis_pegawai' => 'tendik',
            'status_kepegawaian' => 'kontrak',
            'status' => 'non_aktif',
        ]);
    }

    public function test_unauthenticated_user_cannot_export_pegawai(): void
    {
        $response = $this->getJson('/api/simpeg/pegawai/export');
        $response->assertStatus(401);
    }

    public function test_user_without_permission_cannot_export_pegawai(): void
    {
        $response = $this->actingAs($this->regularUser, 'api')
            ->getJson('/api/simpeg/pegawai/export');

        $response->assertStatus(403);
    }

    public function test_can_export_pegawai_to_excel_with_all_columns(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->get('/api/simpeg/pegawai/export');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $temp = tempnam(sys_get_temp_dir(), 'exp_test_') . '.xlsx';
        file_put_contents($temp, $response->streamedContent());

        $reader = new Xlsx();
        $spreadsheet = $reader->load($temp);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertEquals('Data Pegawai', $sheet->getTitle());
        $this->assertEquals('No', $sheet->getCell('A6')->getValue());

        // Pastikan baris data memuat kedua pegawai
        $foundBudi = false;
        $foundSiti = false;

        $highestRow = $sheet->getHighestRow();
        for ($row = 7; $row <= $highestRow; $row++) {
            $rowValues = [];
            for ($col = 'A'; $col !== 'AC'; $col++) {
                $rowValues[] = (string) $sheet->getCell($col . $row)->getValue();
            }
            $rowString = implode(' ', $rowValues);
            if (str_contains($rowString, 'Budi Santoso')) $foundBudi = true;
            if (str_contains($rowString, 'Siti Aminah')) $foundSiti = true;
        }

        $this->assertTrue($foundBudi, 'Data Budi Santoso harus ada pada hasil ekspor');
        $this->assertTrue($foundSiti, 'Data Siti Aminah harus ada pada hasil ekspor');

        @unlink($temp);
    }

    public function test_can_export_pegawai_with_custom_columns_including_username(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->get('/api/simpeg/pegawai/export?columns[]=username&columns[]=nip&columns[]=nama_lengkap&columns[]=jenis_pegawai');

        $response->assertStatus(200);

        $temp = tempnam(sys_get_temp_dir(), 'exp_custom_') . '.xlsx';
        file_put_contents($temp, $response->streamedContent());

        $reader = new Xlsx();
        $spreadsheet = $reader->load($temp);
        $sheet = $spreadsheet->getActiveSheet();

        // Kolom A = No, B = Username Akun SSO, C = NIP, D = Nama Lengkap, E = Jenis Pegawai / Peran
        $this->assertEquals('Username Akun SSO', $sheet->getCell('B6')->getValue());
        $this->assertEquals('NIP', $sheet->getCell('C6')->getValue());
        $this->assertEquals('Nama Lengkap', $sheet->getCell('D6')->getValue());
        $this->assertEquals('Jenis Pegawai / Peran', $sheet->getCell('E6')->getValue());

        // Verifikasi username muncul pada data baris
        $this->assertEquals('dosen_budi', $sheet->getCell('B7')->getValue());
        $this->assertEquals('198501012010121001', $sheet->getCell('C7')->getValue());
        $this->assertEquals('Budi Santoso', $sheet->getCell('D7')->getValue());

        $this->assertEquals('tendik_siti', $sheet->getCell('B8')->getValue());

        @unlink($temp);
    }

    public function test_can_export_pegawai_with_active_filter(): void
    {
        // Filter hanya unit kerja 1 (Fakultas Teknik)
        $response = $this->actingAs($this->admin, 'api')
            ->get('/api/simpeg/pegawai/export?unit_kerja_id=' . $this->unitKerja1->id . '&columns[]=username&columns[]=nama_lengkap&columns[]=unit_kerja');

        $response->assertStatus(200);

        $temp = tempnam(sys_get_temp_dir(), 'exp_filter_') . '.xlsx';
        file_put_contents($temp, $response->streamedContent());

        $reader = new Xlsx();
        $spreadsheet = $reader->load($temp);
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestRow();
        $allText = '';
        for ($row = 7; $row <= $highestRow; $row++) {
            $allText .= $sheet->getCell('B' . $row)->getValue() . ' ' . $sheet->getCell('C' . $row)->getValue() . "\n";
        }

        $this->assertStringContainsString('Budi Santoso', $allText);
        $this->assertStringNotContainsString('Siti Aminah', $allText);

        @unlink($temp);
    }

    public function test_export_fails_with_invalid_unit_kerja(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/simpeg/pegawai/export?unit_kerja_id=999999');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['unit_kerja_id']);
    }

    public function test_export_fails_with_invalid_column(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/simpeg/pegawai/export?columns[]=kolom_palsu');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['columns.0']);
    }
}
