<?php

namespace Tests\Feature;

use App\Models\Gedung;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Sinapra\Aset;
use App\Models\Sinapra\KategoriAset;
use App\Models\Sinapra\MasterTipeRuangan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Laravel\Passport\Passport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SinapraImportTest extends TestCase
{
    protected User $adminUser;
    protected Gedung $gedung;
    protected MasterTipeRuangan $tipeRuangan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);

        $adminRole = Role::firstOrCreate(
            ['slug' => 'superadmin'],
            ['name' => 'Super Administrator', 'is_active' => true]
        );

        $permission = Permission::firstOrCreate(
            ['slug' => 'sinapra.master.manage'],
            ['name' => 'Kelola Master SINAPRA', 'module' => 'sinapra', 'action' => 'update']
        );
        $adminRole->permissions()->syncWithoutDetaching([$permission->id]);

        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->sync([$adminRole->id]);

        $this->gedung = Gedung::create([
            'kode' => 'GD-IMPORT-' . uniqid(),
            'nama' => 'Gedung Rektorat & Administrasi',
            'jumlah_lantai' => 3,
            'status' => 'aktif',
        ]);

        $this->tipeRuangan = MasterTipeRuangan::create([
            'kode' => 'R_KANTOR_' . uniqid(),
            'nama' => 'Ruang Kantor Administrasi',
            'is_active' => true,
            'urutan' => 1,
        ]);
    }

    protected function createExcelFile(array $headers, array $rows, string $sheetTitle = 'Worksheet'): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetTitle);

        foreach ($headers as $colIdx => $header) {
            $sheet->setCellValue([$colIdx + 1, 1], $header);
        }

        foreach ($rows as $rowIdx => $row) {
            foreach ($row as $colIdx => $cell) {
                $sheet->setCellValue([$colIdx + 1, $rowIdx + 2], $cell);
            }
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'test_import_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return new UploadedFile($tempPath, 'test_data.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_import_ruangan_without_tipe_resolves_legacy_column_and_avoids_truncation(): void
    {
        Passport::actingAs($this->adminUser);

        $headers = [
            'KODE GEDUNG',
            'KODE RUANGAN',
            'NAMA RUANGAN',
            'LANTAI',
            'KODE TIPE RUANGAN',
            'KAPASITAS (ORANG)',
            'ADA AC (1/0)',
            'JUMLAH AC',
            'ADA PROYEKTOR (1/0)',
            'JUMLAH PROYEKTOR',
            'ADA WIFI (1/0)',
            'JUMLAH WIFI',
            'STATUS',
        ];

        $kodeRuangan = 'R-ADM-' . uniqid();
        $rows = [
            [
                $this->gedung->kode,
                $kodeRuangan,
                'Kantor Biro Administrasi Akademik & Sarpras',
                1,
                $this->tipeRuangan->kode,
                25,
                1,
                2,
                0,
                0,
                1,
                2,
                'aktif',
            ],
        ];

        $file = $this->createExcelFile($headers, $rows, '3_ruangan');

        $response = $this->postJson('/api/sinapra/import/ruangan', [
            'file' => $file,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.created', 1)
                 ->assertJsonPath('data.failed', 0);

        $this->assertDatabaseHas('sinapra_ruangan', [
            'kode' => $kodeRuangan,
            'nama' => 'Kantor Biro Administrasi Akademik & Sarpras',
            'tipe' => 'kantor', // Tidak boleh 'umum', harus ter-resolve ke 'kantor'
            'kapasitas' => 25,
            'status' => 'aktif',
        ]);
    }

    public function test_import_ruangan_with_explicit_umum_resolves_safely(): void
    {
        Passport::actingAs($this->adminUser);

        $headers = [
            'KODE GEDUNG',
            'KODE RUANGAN',
            'NAMA RUANGAN',
            'LANTAI',
            'KODE TIPE RUANGAN',
            'TIPE',
            'KAPASITAS (ORANG)',
            'STATUS',
        ];

        $kodeRuangan = 'R-UMUM-' . uniqid();
        $rows = [
            [
                $this->gedung->kode,
                $kodeRuangan,
                'Ruang Serbaguna & Rapat',
                1,
                $this->tipeRuangan->kode,
                'umum', // User memasukkan string 'umum'
                50,
                'aktif',
            ],
        ];

        $file = $this->createExcelFile($headers, $rows, 'ruangan');

        $response = $this->postJson('/api/sinapra/import/ruangan', [
            'file' => $file,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.created', 1)
                 ->assertJsonPath('data.failed', 0);

        $this->assertDatabaseHas('sinapra_ruangan', [
            'kode' => $kodeRuangan,
            'nama' => 'Ruang Serbaguna & Rapat',
            'kapasitas' => 50,
        ]);
    }

    public function test_import_aset_with_status_aktif_resolves_to_tersedia(): void
    {
        Passport::actingAs($this->adminUser);

        $kategori = KategoriAset::create([
            'kode' => 'KAT-IT-' . uniqid(),
            'nama' => 'Peralatan IT & Komputer',
            'masa_manfaat_tahun' => 4,
            'tarif_penyusutan_persen' => 25.0,
        ]);

        $ruangan = Ruangan::create([
            'gedung_id' => $this->gedung->id,
            'tipe_ruangan_id' => $this->tipeRuangan->id,
            'kode' => 'R-LAB-' . uniqid(),
            'nama' => 'Lab Komputer 1',
            'lantai' => 1,
            'tipe' => 'lab',
            'kapasitas' => 30,
            'status' => 'aktif',
        ]);

        $headers = [
            'KODE ASET',
            'NAMA ASET',
            'KODE KATEGORI',
            'KODE RUANGAN',
            'KONDISI',
            'STATUS',
            'HARGA PEROLEHAN',
        ];

        $kodeAset = 'AST-PC-' . uniqid();
        $rows = [
            [
                $kodeAset,
                'PC All-in-One Core i7 Biro Akademik',
                $kategori->kode,
                $ruangan->kode,
                'baik',
                'aktif', // Dalam template sering ditulis 'aktif', di database aset kolom status harus 'tersedia'
                12500000,
            ],
        ];

        $file = $this->createExcelFile($headers, $rows, '11_inventaris_aset');

        $response = $this->postJson('/api/sinapra/import/aset', [
            'file' => $file,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.created', 1)
                 ->assertJsonPath('data.failed', 0);

        $this->assertDatabaseHas('sinapra_aset', [
            'kode_aset' => $kodeAset,
            'status' => 'tersedia', // Mapping berhasil dari 'aktif' ke 'tersedia'
            'kondisi' => 'baik',
        ]);
    }

    public function test_can_download_templates(): void
    {
        Passport::actingAs($this->adminUser);

        $entities = ['gedung', 'tipe-ruangan', 'ruangan', 'kategori-aset', 'kategori-bhp', 'satuan', 'vendor', 'aset'];

        foreach ($entities as $entity) {
            $response = $this->get("/api/sinapra/import/template/{$entity}");
            $response->assertStatus(200);
            $this->assertEquals(
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                $response->headers->get('Content-Type')
            );
        }
    }
}
