<?php

namespace Tests\Feature\SPMB;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Siakad\ProgramStudi;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SpmbPendaftaranImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = [
        'No', 'No.Daftar', 'Nama', 'Tempat Lahir', 'Tgl.Lahir', 'Jenis Kelamin', 'Alamat', 'Telp/HP', 'Email',
        'Nama Org Tua', 'Alamat Org Tua', 'Telp. Org Tua', 'Pekerjaan OrgTua', 'Sekolah', 'Alamat Sekolah',
        'Jurusan Sekolah', 'Tahun Lulus', 'Program Studi', 'Kelas', 'Info Daftar', 'Ket. Info Daftar',
        'On/Off-Line', 'Tgl. Daftar', 'Bayar Pendaftaran', 'Keterangan Seleksi', 'Validasi Pembayaran', 'Beasiswa',
    ];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');
        $this->setUpPassport();

        $adminRole = Role::firstOrCreate(['slug' => 'admin_spmb'], ['name' => 'Admin SPMB', 'is_active' => true]);
        $permission = Permission::firstOrCreate(
            ['slug' => 'spmb.manage'],
            ['name' => 'Kelola Admin SPMB', 'module' => 'spmb', 'action' => 'update'],
        );
        $adminRole->permissions()->syncWithoutDetaching([$permission->id]);
        Role::firstOrCreate(['slug' => 'calon_mhs'], ['name' => 'Calon Mahasiswa', 'is_active' => true]);

        $this->admin = User::create([
            'username' => 'admin_import', 'email' => 'admin_import@test.com',
            'password' => bcrypt('password'), 'is_active' => true, 'is_verified' => true,
        ]);
        $this->admin->roles()->syncWithoutDetaching([$adminRole->id]);

        ProgramStudi::create(['kode_prodi' => 'D3-FAR', 'nama' => 'Farmasi', 'jenjang' => 'D3', 'is_active' => true]);
        ProgramStudi::create(['kode_prodi' => 'D4-PM', 'nama' => 'Produksi Media', 'jenjang' => 'D4', 'is_active' => true]);
        ProgramStudi::create(['kode_prodi' => 'D3-MI', 'nama' => 'Manajemen Informatika', 'jenjang' => 'D3', 'is_active' => false]);
    }

    private function row(array $override = []): array
    {
        $base = [
            1, 202720001, 'RISKA RACHMAWATI', 'SURAKARTA', 38590, 'P', 'BIBIS LUHUR', '085712784226',
            'RRACHMAWATI26@GMAIL.COM', 'JAKA WALUYO', 'BIBIS LUHUR', '087756846920', 'SWASTA',
            'SMK FARMASI', 'JL. YOS', 'FARMASI', 2023, 'D3-Farmasi', 'Khusus', 'REKOMENDASI', 'YUDHA',
            'ONLINE', 46309.38, 'Belum', 'Belum Seleksi', 'Sudah Membayar', 'REGULAR',
        ];
        foreach ($override as $i => $v) {
            $base[$i] = $v;
        }

        return $base;
    }

    private function upload(array $rows, bool $withTitle = true): UploadedFile
    {
        $sheet = ($book = new Spreadsheet)->getActiveSheet();
        $r = 1;
        if ($withTitle) {
            $sheet->setCellValue('A1', 'DATA PENDAFTARAN ANGKATAN 2027');
            $sheet->setCellValue('A2', 'PER TANGGAL : 25-11-2026');
            $r = 3;
        }
        $sheet->fromArray(self::HEADERS, null, 'A'.$r);
        $sheet->fromArray($rows, null, 'A'.($r + 1));
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, 'pendaftar.xlsx', null, null, true);
    }

    public function test_non_admin_cannot_preview_or_import(): void
    {
        $user = User::create([
            'username' => 'biasa', 'email' => 'biasa@test.com',
            'password' => bcrypt('x'), 'is_active' => true, 'is_verified' => true,
        ]);

        $this->actingAs($user, 'api')
            ->postJson('/api/spmb/pendaftaran/import/preview', ['file' => $this->upload([$this->row()])])
            ->assertStatus(403);
        $this->actingAs($user, 'api')
            ->postJson('/api/spmb/pendaftaran/import', ['file' => $this->upload([$this->row()])])
            ->assertStatus(403);
    }

    public function test_preview_reports_valid_and_invalid_rows_without_saving(): void
    {
        $file = $this->upload([
            $this->row(),
            $this->row([1 => 202720002, 8 => 'a@test.com', 17 => 'D4/S1 Terapan-Tidak Ada']),
            $this->row([1 => 202720003, 8 => 'b@test.com', 17 => 'D3-Manajemen Informatika']),
            $this->row([1 => 202720001, 8 => 'rrachmawati26@gmail.com']),
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/spmb/pendaftaran/import/preview', ['file' => $file]);

        $response->assertOk()
            ->assertJsonPath('data.summary', ['total' => 4, 'valid' => 1, 'error' => 3])
            ->assertJsonPath('data.rows.0.status', 'valid')
            ->assertJsonPath('data.rows.1.status', 'error')
            ->assertJsonStructure(['data' => ['summary', 'warnings', 'rows' => [['row', 'no_daftar', 'nama', 'email', 'program_studi', 'status', 'errors']]]]);
        $this->assertCount(2, $response->json('data.warnings'));
        $this->assertStringContainsString('duplikat', implode(' ', $response->json('data.rows.3.errors')));
        $this->assertDatabaseCount('spmb_pendaftaran_calon_mhs', 0);
        $this->assertDatabaseMissing('core_users', ['email' => 'rrachmawati26@gmail.com']);
    }

    public function test_import_saves_valid_rows_with_account_and_default_password(): void
    {
        $file = $this->upload([
            $this->row(),
            $this->row([1 => 202720002, 8 => 'lindalva@test.com', 2 => 'LINDALVA', 17 => 'D4/S1 Terapan-Produksi Media', 25 => 'Belum Membayar', 16 => 0]),
            $this->row([1 => 202720003, 8 => 'bad@test.com', 17 => 'D3-Tidak Ada']),
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/spmb/pendaftaran/import', ['file' => $file]);

        $response->assertStatus(201)
            ->assertJsonPath('data.summary', ['total' => 3, 'imported' => 2, 'failed' => 1])
            ->assertJsonPath('data.failed.0.no_daftar', '202720003');

        $first = PendaftaranCalonMhs::where('no_pendaftaran', '202720001')->firstOrFail();
        $this->assertNull($first->gelombang_id);
        $this->assertNull($first->nik);
        $this->assertSame('import', $first->sumber_pendaftaran);
        $this->assertSame('submitted', $first->status);
        $this->assertSame('lunas', $first->status_pembayaran);
        $this->assertSame('sekolah', $first->asal_lulusan);
        $this->assertSame('SWASTA', $first->pekerjaan_ortu);
        $this->assertSame('2023', $first->tahun_lulus);
        $this->assertSame('2005-08-26', $first->tanggal_lahir->toDateString());
        $this->assertSame('Khusus', $first->data_import['kelas']);
        $this->assertSame('REGULAR', $first->data_import['beasiswa']);
        $this->assertSame($this->admin->id, (int) $first->diinput_oleh);

        $user = User::where('email', 'rrachmawati26@gmail.com')->firstOrFail();
        $this->assertTrue(Hash::check('indonusa', $user->password));
        $this->assertTrue($user->roles->contains('slug', 'calon_mhs'));
        $this->assertSame($user->id, (int) $first->user_id);

        $second = PendaftaranCalonMhs::where('no_pendaftaran', '202720002')->firstOrFail();
        $this->assertSame('belum_bayar', $second->status_pembayaran);
        $this->assertNull($second->tahun_lulus);
        $this->assertDatabaseMissing('core_users', ['email' => 'bad@test.com']);
    }

    public function test_reimporting_same_file_rejects_existing_rows(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/spmb/pendaftaran/import', ['file' => $this->upload([$this->row()])])
            ->assertStatus(201);

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/spmb/pendaftaran/import', ['file' => $this->upload([$this->row()])])
            ->assertStatus(201)
            ->assertJsonPath('data.summary', ['total' => 1, 'imported' => 0, 'failed' => 1]);

        $this->assertDatabaseCount('spmb_pendaftaran_calon_mhs', 1);
    }

    public function test_rejects_file_without_known_header_and_wrong_type(): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([['foo', 'bar'], [1, 2]]);
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($book))->save($path);

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/spmb/pendaftaran/import/preview', ['file' => new UploadedFile($path, 'x.xlsx', null, null, true)])
            ->assertStatus(422)->assertJsonValidationErrors('file');

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/spmb/pendaftaran/import/preview', ['file' => UploadedFile::fake()->create('x.txt', 1, 'text/plain')])
            ->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_template_download_is_admin_only_and_importable_headers(): void
    {
        $user = User::create([
            'username' => 'biasa2', 'email' => 'biasa2@test.com',
            'password' => bcrypt('x'), 'is_active' => true, 'is_verified' => true,
        ]);
        $this->actingAs($user, 'api')->get('/api/spmb/pendaftaran/import/template')->assertStatus(403);

        $response = $this->actingAs($this->admin, 'api')->get('/api/spmb/pendaftaran/import/template');
        $response->assertOk();
        $this->assertDatabaseHas('core_audit_logs', ['action' => 'pendaftaran_import_download_template']);
        $this->assertStringContainsString('template-import-pendaftar.xlsx', $response->headers->get('content-disposition'));

        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($response->baseResponse->getFile()->getPathname())->getSheet(0);
        $this->assertSame(self::HEADERS, array_slice($sheet->toArray()[2], 0, count(self::HEADERS)));
    }

    public function test_admin_can_edit_imported_applicant_and_assign_gelombang(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/spmb/pendaftaran/import', ['file' => $this->upload([$this->row()])])->assertStatus(201);
        $p = PendaftaranCalonMhs::firstOrFail();

        $jalur = \App\Models\Spmb\JalurMasuk::create(['kode' => 'REG', 'nama' => 'Reguler', 'is_active' => true]);
        $gel = \App\Models\Spmb\GelombangPenerimaan::create([
            'jalur_masuk_id' => $jalur->id, 'nama' => 'Gel 1', 'tanggal_buka' => '2026-01-01',
            'tanggal_tutup' => '2026-12-31', 'kuota_total' => 10, 'kuota_terisi' => 0,
            'biaya_pendaftaran' => 0, 'status' => 'aktif',
        ]);

        $this->actingAs($this->admin, 'api')->putJson("/api/spmb/pendaftaran/{$p->id}", [
            'nama_lengkap' => 'RISKA R EDIT', 'nik' => '3372010101010001', 'gelombang_id' => $gel->id,
        ])->assertOk()
            ->assertJsonPath('data.gelombang_penerimaan.jalur_masuk.nama', 'Reguler');

        $this->assertDatabaseHas('spmb_pendaftaran_calon_mhs', [
            'id' => $p->id, 'nama_lengkap' => 'RISKA R EDIT', 'nik' => '3372010101010001', 'gelombang_id' => $gel->id,
        ]);
        $this->assertDatabaseHas('core_audit_logs', ['action' => 'pendaftaran_diedit', 'record_id' => $p->id]);
        $log = \App\Models\AuditLog::where('action', 'pendaftaran_diedit')->where('record_id', $p->id)->firstOrFail();
        $this->assertSame('RISKA RACHMAWATI', $log->old_values['nama_lengkap']);
        $this->assertSame('RISKA R EDIT', $log->new_values['nama_lengkap']);
    }

    public function test_edit_validates_and_blocks_non_admin_and_converted(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/spmb/pendaftaran/import', ['file' => $this->upload([$this->row()])])->assertStatus(201);
        $p = PendaftaranCalonMhs::firstOrFail();

        $this->actingAs($this->admin, 'api')->putJson("/api/spmb/pendaftaran/{$p->id}", [
            'nik' => '123', 'program_studi_id' => 99999, 'nama_lengkap' => '',
        ])->assertStatus(422)->assertJsonValidationErrors(['nik', 'program_studi_id', 'nama_lengkap']);

        $user = User::create([
            'username' => 'biasa3', 'email' => 'biasa3@test.com',
            'password' => bcrypt('x'), 'is_active' => true, 'is_verified' => true,
        ]);
        $this->actingAs($user, 'api')->putJson("/api/spmb/pendaftaran/{$p->id}", ['nama_lengkap' => 'X'])->assertStatus(403);

        $p->update(['status' => 'mahasiswa_baru']);
        $this->actingAs($this->admin, 'api')->putJson("/api/spmb/pendaftaran/{$p->id}", ['nama_lengkap' => 'X'])
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_edit_updates_account_email_family_and_referral(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/spmb/pendaftaran/import', ['file' => $this->upload([$this->row()])])->assertStatus(201);
        $p = PendaftaranCalonMhs::firstOrFail();
        $referrer = User::create([
            'username' => 'ref1', 'email' => 'ref1@test.com', 'password' => bcrypt('x'),
            'is_active' => true, 'is_verified' => true, 'referral_code' => 'REFABC123',
        ]);

        $this->actingAs($this->admin, 'api')->putJson("/api/spmb/pendaftaran/{$p->id}", [
            'email' => 'Baru@Test.com', 'nama_ayah' => 'AYAH', 'pekerjaan_ayah' => 'wiraswasta',
            'penghasilan_ayah' => '2_sampai_5_juta', 'asal_lulusan' => 'pt', 'ipk_pt' => 3.5,
            'status_sipil' => 'menikah', 'referrer_user_id' => $referrer->id,
        ])->assertOk();

        $this->assertDatabaseHas('core_users', ['id' => $p->user_id, 'email' => 'baru@test.com']);
        $this->assertDatabaseHas('spmb_pendaftaran_calon_mhs', [
            'id' => $p->id, 'nama_ayah' => 'AYAH', 'asal_lulusan' => 'pt', 'status_sipil' => 'menikah',
            'referrer_user_id' => $referrer->id, 'used_referral_code' => 'REFABC123',
        ]);
        $this->assertDatabaseHas('spmb_referral_usages', ['pendaftaran_id' => $p->id, 'referrer_user_id' => $referrer->id]);

        $this->actingAs($this->admin, 'api')->putJson("/api/spmb/pendaftaran/{$p->id}", ['referrer_user_id' => null])->assertOk();
        $this->assertDatabaseHas('spmb_pendaftaran_calon_mhs', ['id' => $p->id, 'referrer_user_id' => null]);
    }

    public function test_edit_rejects_duplicate_email_and_requires_passport_for_wna(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/spmb/pendaftaran/import', ['file' => $this->upload([$this->row()])])->assertStatus(201);
        $p = PendaftaranCalonMhs::firstOrFail();

        $this->actingAs($this->admin, 'api')->putJson("/api/spmb/pendaftaran/{$p->id}", ['email' => 'admin_import@test.com'])
            ->assertStatus(422)->assertJsonValidationErrors('email');

        $wna = \App\Models\System\MasterReferensi::create([
            'tipe' => 'kewarganegaraan', 'kode' => 'WNA', 'nama' => 'Warga Negara Asing', 'is_active' => true,
        ]);
        $this->actingAs($this->admin, 'api')->putJson("/api/spmb/pendaftaran/{$p->id}", ['kewarganegaraan_id' => $wna->id])
            ->assertStatus(422)->assertJsonValidationErrors(['nomor_paspor', 'negara_penerbit_paspor']);
    }

    public function test_jalur_status_filter_accepts_aktif_and_boolean_values(): void
    {
        \App\Models\Spmb\JalurMasuk::create(['kode' => 'AKT', 'nama' => 'Jalur Aktif', 'is_active' => true]);
        \App\Models\Spmb\JalurMasuk::create(['kode' => 'NON', 'nama' => 'Jalur Nonaktif', 'is_active' => false]);

        $names = fn (string $query) => collect(
            $this->actingAs($this->admin, 'api')->getJson('/api/spmb/jalur?'.$query)->assertOk()->json('data')
        )->pluck('nama')->all();

        $this->assertSame(['Jalur Aktif'], $names('status=aktif'));
        $this->assertSame(['Jalur Aktif'], $names('status=true'));
        $this->assertSame(['Jalur Nonaktif'], $names('status=nonaktif'));
        $this->assertSame(['Jalur Nonaktif'], $names('status=false'));
        $this->assertCount(2, $names('status=ngawur'));
        $this->assertCount(2, $names(''));
    }
}
