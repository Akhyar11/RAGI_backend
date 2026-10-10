<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SimpegPegawaiImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected UnitKerja $unitKerja;

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
            'nama' => 'Fakultas Ilmu Komputer',
            'kode' => 'FIK',
            'tipe' => 'fakultas',
            'is_active' => true,
        ]);

        Role::firstOrCreate(['slug' => 'dosen'], ['name' => 'Dosen']);
        Role::firstOrCreate(['slug' => 'tendik'], ['name' => 'Tenaga Kependidikan']);
    }

    public function test_can_download_pegawai_template_csv()
    {
        $response = $this->actingAs($this->admin, 'api')
            ->get('/api/simpeg/pegawai/template');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('nip,nik,nama_lengkap', $response->getContent());
    }

    public function test_can_import_pegawai_from_csv_and_auto_creates_sso_users_with_indonusa_password()
    {
        $csvContent = "\xEF\xBB\xBF" .
            "nip,nik,nama_lengkap,email,telepon,jenis_kelamin,tempat_lahir,tanggal_lahir,jenis_pegawai,status_kepegawaian,unit_kerja,jabatan,tanggal_masuk,alamat\n" .
            "198801012015011001,3271010101880001,Budi Santoso M.T.,budi.santoso@campus.ac.id,081234567891,L,Bandung,1988-01-01,dosen,tetap_yayasan,Fakultas Ilmu Komputer,Dosen,2015-01-01,Jl. Suci No. 1\n" .
            "199002022018022002,3271010202900002,Dewi Lestari S.E.,dewi.lestari@campus.ac.id,081234567892,P,Jakarta,1990-02-02,tendik,kontrak,Fakultas Ilmu Komputer,Staf,2018-02-01,Jl. Riau No. 2\n";

        $file = UploadedFile::fake()->createWithContent('data_pegawai.csv', $csvContent);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/pegawai/import', [
                'file' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'total' => 2,
                    'success' => 2,
                    'failed' => 0,
                ],
            ]);

        // Verifikasi User SSO Budi Santoso (Dosen)
        $userBudi = User::where('email', 'budi.santoso@campus.ac.id')->first();
        $this->assertNotNull($userBudi);
        $this->assertTrue(Hash::check('indonusa', $userBudi->password));
        $this->assertTrue($userBudi->is_verified);
        $this->assertTrue($userBudi->hasRole('dosen'));

        // Verifikasi Simpeg Pegawai Budi
        $pegawaiBudi = Pegawai::where('nip', '198801012015011001')->first();
        $this->assertNotNull($pegawaiBudi);
        $this->assertEquals($userBudi->id, $pegawaiBudi->user_id);
        $this->assertEquals('Budi Santoso M.T.', $pegawaiBudi->nama_lengkap);

        // Verifikasi User SSO Dewi Lestari (Tendik)
        $userDewi = User::where('email', 'dewi.lestari@campus.ac.id')->first();
        $this->assertNotNull($userDewi);
        $this->assertTrue(Hash::check('indonusa', $userDewi->password));
        $this->assertTrue($userDewi->is_verified);
        $this->assertTrue($userDewi->hasRole('tendik'));
    }

    public function test_manual_store_pegawai_auto_creates_sso_user_with_indonusa_password()
    {
        $shift = \App\Models\ShiftTemplate::create([
            'name' => 'Shift Reguler Pagi',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        $payload = [
            'nama_lengkap' => 'Rian Hidayat, M.Si.',
            'nip' => '199505052020051003',
            'nidn' => '0405059501',
            'nuptk' => '3271010505950001',
            'nik' => '3271010505950003',
            'jenis_kelamin' => 'L',
            'jenis_pegawai' => 'dosen',
            'status_kepegawaian' => 'tetap_yayasan',
            'unit_kerja_id' => $this->unitKerja->id,
            'email' => 'rian.hidayat@campus.ac.id',
            'tanggal_masuk' => '2020-05-01',
            'shift_template_id' => $shift->id,
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/pegawai', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'nama_lengkap' => 'Rian Hidayat, M.Si.',
                    'nip' => '199505052020051003',
                ],
            ]);

        $createdPegawai = Pegawai::where('nip', '199505052020051003')->first();
        $this->assertNotNull($createdPegawai);
        $this->assertNotNull($createdPegawai->user_id);

        $createdUser = User::find($createdPegawai->user_id);
        $this->assertNotNull($createdUser);
        $this->assertEquals('rian.hidayat@campus.ac.id', $createdUser->email);
        $this->assertTrue(Hash::check('indonusa', $createdUser->password));
        $this->assertTrue($createdUser->is_verified);
        $this->assertTrue($createdUser->hasRole('dosen'));
    }

    public function test_can_import_pegawai_with_empty_columns_and_does_not_set_default_values()
    {
        $csvContent = "\xEF\xBB\xBF" .
            "nip,nik,nama_lengkap,email,telepon,jenis_kelamin,tempat_lahir,tanggal_lahir,jenis_pegawai,status_kepegawaian,unit_kerja,jabatan,tanggal_masuk,alamat\n" .
            ",,Hendri Wijaya,,,,,,,,,,,\n";

        $file = UploadedFile::fake()->createWithContent('data_pegawai_minimal.csv', $csvContent);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/pegawai/import', [
                'file' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'total' => 1,
                    'success' => 1,
                    'failed' => 0,
                ],
            ]);

        $pegawai = Pegawai::where('nama_lengkap', 'Hendri Wijaya')->first();
        $this->assertNotNull($pegawai);
        $this->assertNull($pegawai->nip);
        $this->assertNull($pegawai->nik);
        $this->assertNull($pegawai->jenis_kelamin);
        $this->assertNull($pegawai->jenis_pegawai);
        $this->assertNull($pegawai->status_kepegawaian);
        $this->assertNull($pegawai->unit_kerja_id);
        $this->assertNull($pegawai->tanggal_masuk);
        $this->assertNull($pegawai->agama);
        $this->assertNull($pegawai->telepon);
        $this->assertNull($pegawai->alamat);

        // Pastikan akun SSO tetap dibuat dengan default password indonusa
        $user = User::find($pegawai->user_id);
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('indonusa', $user->password));
        $this->assertTrue($user->is_verified);
        // Karena jenis_pegawai kosong, tidak ada role dosen/tendik yang otomatis dipaksakan
        $this->assertFalse($user->hasRole('dosen'));
        $this->assertFalse($user->hasRole('tendik'));
    }

    public function test_import_pegawai_recognizes_pegawai_value_as_tendik_and_handles_spaced_headers()
    {
        // Simulasi berkas dengan header berspasi dan jenis pegawai bertuliskan 'pegawai'
        $csvContent = "\xEF\xBB\xBF" .
            "NIP,NIK,Nama Lengkap,Email,Jenis Kelamin,Jenis Pegawai,Status Kepegawaian,Unit Kerja\n" .
            "199303032019032003,3271010303930003,Bambang Supriyanto,bambang@campus.ac.id,Laki-laki,Pegawai,Tetap,Fakultas Ilmu Komputer\n";

        $file = UploadedFile::fake()->createWithContent('pegawai_tendik.csv', $csvContent);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/pegawai/import', [
                'file' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'total' => 1,
                    'success' => 1,
                    'failed' => 0,
                ],
            ]);

        $pegawai = Pegawai::where('nip', '199303032019032003')->first();
        $this->assertNotNull($pegawai);
        $this->assertEquals('Bambang Supriyanto', $pegawai->nama_lengkap);
        $this->assertEquals('tendik', $pegawai->jenis_pegawai); // Wajib 'tendik', bukan 'dosen'!
        $this->assertEquals('L', $pegawai->jenis_kelamin);
        $this->assertEquals('tetap_yayasan', $pegawai->status_kepegawaian);
        $this->assertEquals($this->unitKerja->id, $pegawai->unit_kerja_id);

        $user = User::find($pegawai->user_id);
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('tendik'));
        $this->assertFalse($user->hasRole('dosen'));
    }

    public function test_import_pegawai_rejects_duplicate_nidn_and_nuptk_in_file_and_against_db()
    {
        // 1. Buat pegawai awal dengan NIDN dan NUPTK tertentu di DB
        Pegawai::create([
            'nama_lengkap' => 'Prof. Haryanto',
            'nidn' => '0412345601',
            'nuptk' => '9876543210123456',
            'nip' => '198001012005011001',
            'jenis_pegawai' => 'dosen',
            'status' => 'aktif',
        ]);

        // 2. Berkas impor:
        // - Baris 1: Mencoba memakai NIDN yang sudah ada di database (harus ditolak)
        // - Baris 2: Data valid dengan NIDN baru
        // - Baris 3: Mencoba memakai NIDN yang sama dengan Baris 2 di dalam berkas ini (harus ditolak karena duplikat)
        $csvContent = "\xEF\xBB\xBF" .
            "nidn,nuptk,nip,nama_lengkap,jenis_pegawai\n" .
            "0412345601,1111222233334444,198101012006011001,Dosen A,dosen\n" .
            "0499999901,2222333344445555,198202022007021002,Dosen B,dosen\n" .
            "0499999901,3333444455556666,198303032008031003,Dosen C,dosen\n";

        $file = UploadedFile::fake()->createWithContent('test_duplicate_nidn.csv', $csvContent);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/pegawai/import', [
                'file' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'total' => 3,
                    'success' => 1,
                    'failed' => 2,
                ],
            ]);

        $responseData = $response->json('data');
        $this->assertCount(2, $responseData['errors']);
        $this->assertStringContainsString('NIDN \'0412345601\' sudah terdaftar', $responseData['errors'][0]);
        $this->assertStringContainsString('NIDN \'0499999901\' duplikat dengan baris #3', $responseData['errors'][1]);

        // Pastikan hanya Dosen B yang tersimpan
        $this->assertDatabaseHas('simpeg_pegawai', ['nama_lengkap' => 'Dosen B', 'nidn' => '0499999901']);
        $this->assertDatabaseMissing('simpeg_pegawai', ['nama_lengkap' => 'Dosen A']);
        $this->assertDatabaseMissing('simpeg_pegawai', ['nama_lengkap' => 'Dosen C']);
    }

    public function test_import_pegawai_sanitizes_dash_and_zero_placeholders_without_collision()
    {
        // Simulasi jika beberapa baris memiliki placeholder "-" atau "0" pada NIDN/NUPTK/NIP
        $csvContent = "\xEF\xBB\xBF" .
            "nidn,nuptk,nip,nama_lengkap,jenis_pegawai\n" .
            "-,0,-,Staf Pertama,tendik\n" .
            "none,-,0,Staf Kedua,tendik\n";

        $file = UploadedFile::fake()->createWithContent('test_placeholders.csv', $csvContent);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/pegawai/import', [
                'file' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'total' => 2,
                    'success' => 2,
                    'failed' => 0,
                ],
            ]);

        $staf1 = Pegawai::where('nama_lengkap', 'Staf Pertama')->first();
        $staf2 = Pegawai::where('nama_lengkap', 'Staf Kedua')->first();

        $this->assertNotNull($staf1);
        $this->assertNotNull($staf2);
        $this->assertNull($staf1->nidn);
        $this->assertNull($staf1->nuptk);
        $this->assertNull($staf1->nip);
        $this->assertNull($staf2->nidn);
        $this->assertNull($staf2->nuptk);
        $this->assertNull($staf2->nip);
    }
}


