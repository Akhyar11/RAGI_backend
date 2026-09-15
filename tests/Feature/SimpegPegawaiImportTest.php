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

    public function test_can_import_pegawai_from_csv_and_auto_creates_sso_users_with_secure_credentials()
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

        // Verifikasi User SSO Budi Santoso (Dosen): Password aman (bukan indonusa) & butuh verifikasi
        $userBudi = User::where('email', 'budi.santoso@campus.ac.id')->first();
        $this->assertNotNull($userBudi);
        $this->assertFalse(Hash::check('indonusa', $userBudi->password));
        $this->assertFalse($userBudi->is_verified);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $userBudi->email]);
        $this->assertTrue($userBudi->hasRole('dosen'));

        // Verifikasi Simpeg Pegawai Budi
        $pegawaiBudi = Pegawai::where('nip', '198801012015011001')->first();
        $this->assertNotNull($pegawaiBudi);
        $this->assertEquals($userBudi->id, $pegawaiBudi->user_id);
        $this->assertEquals('Budi Santoso M.T.', $pegawaiBudi->nama_lengkap);

        // Verifikasi User SSO Dewi Lestari (Tendik): Password aman (bukan indonusa) & butuh verifikasi
        $userDewi = User::where('email', 'dewi.lestari@campus.ac.id')->first();
        $this->assertNotNull($userDewi);
        $this->assertFalse(Hash::check('indonusa', $userDewi->password));
        $this->assertFalse($userDewi->is_verified);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $userDewi->email]);
        $this->assertTrue($userDewi->hasRole('tendik'));
    }

    public function test_manual_store_pegawai_auto_creates_sso_user_with_secure_credentials()
    {
        $payload = [
            'nama_lengkap' => 'Rian Hidayat, M.Si.',
            'nip' => '199505052020051003',
            'nik' => '3271010505950003',
            'jenis_kelamin' => 'L',
            'jenis_pegawai' => 'dosen',
            'status_kepegawaian' => 'tetap_yayasan',
            'unit_kerja_id' => $this->unitKerja->id,
            'email' => 'rian.hidayat@campus.ac.id',
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
        $this->assertFalse(Hash::check('indonusa', $createdUser->password));
        $this->assertFalse($createdUser->is_verified);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $createdUser->email]);
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

        // Pastikan akun SSO tetap dibuat dengan kredensial aman & butuh verifikasi
        $user = User::find($pegawai->user_id);
        $this->assertNotNull($user);
        $this->assertFalse(Hash::check('indonusa', $user->password));
        $this->assertFalse($user->is_verified);
        // Karena jenis_pegawai kosong, tidak ada role dosen/tendik yang otomatis dipaksakan
        $this->assertFalse($user->hasRole('dosen'));
        $this->assertFalse($user->hasRole('tendik'));
    }
}
