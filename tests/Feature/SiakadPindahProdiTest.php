<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\PindahProdi;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SiakadPindahProdiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $prodiA;
    protected $prodiB;
    protected $ta;
    protected $mhs;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\RoleSeeder']);
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => '\Database\Seeders\IAM\PermissionSeeder']);

        $role = Role::where('slug', 'superadmin')->first();
        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->roles()->attach($role->id);

        $this->prodiA = ProgramStudi::create(['kode' => 'TI01', 'kode_prodi' => 'TI01', 'nama' => 'Teknik Informatika', 'jenjang' => 'S1', 'is_active' => true]);
        $this->prodiB = ProgramStudi::create(['kode' => 'SI01', 'kode_prodi' => 'SI01', 'nama' => 'Sistem Informasi', 'jenjang' => 'S1', 'is_active' => true]);
        $this->ta = TahunAkademik::create(['kode' => '20261', 'nama' => '2026/2027 Ganjil', 'semester' => 'ganjil', 'tahun_mulai' => 2026, 'tahun_selesai' => 2027, 'is_aktif' => true]);
        $this->mhs = Mahasiswa::create([
            'program_studi_id' => $this->prodiA->id,
            'nim' => '2401001',
            'nama_lengkap' => 'Mahasiswa Pindah',
            'angkatan' => 2024,
            'status' => 'aktif',
        ]);

        Passport::actingAs($this->user);
    }

    public function test_alur_penuh_create_cek_approve_apply(): void
    {
        $create = $this->postJson('/api/v1/siakad/pindah-prodi', [
            'mahasiswa_id' => $this->mhs->id,
            'new_program_studi_id' => $this->prodiB->id,
            'alasan' => 'Alasan pindah prodi yang cukup panjang',
        ]);
        $create->assertStatus(201);
        $id = $create->json('data.id');
        $this->assertDatabaseHas('siakad_mahasiswa_pindah_prodi', ['id' => $id, 'status' => 'pending']);

        // Approve tanpa sign-off wajib ditolak service
        $noCek = $this->postJson("/api/v1/siakad/pindah-prodi/{$id}/approve");
        $noCek->assertStatus(422);

        $cek = $this->postJson("/api/v1/siakad/pindah-prodi/{$id}/cek-keuangan", ['hasil' => 'layak']);
        $cek->assertStatus(200);

        $approve = $this->postJson("/api/v1/siakad/pindah-prodi/{$id}/approve");
        $approve->assertStatus(200);
        $this->assertDatabaseHas('siakad_mahasiswa_pindah_prodi', ['id' => $id, 'status' => 'approved']);

        $apply = $this->postJson("/api/v1/siakad/pindah-prodi/{$id}/apply");
        $apply->assertStatus(200);
        $this->assertDatabaseHas('siakad_mahasiswa_pindah_prodi', ['id' => $id, 'status' => 'applied']);
        $this->assertDatabaseHas('siakad_mahasiswa', ['id' => $this->mhs->id, 'program_studi_id' => $this->prodiB->id]);

        // Proyeksi SIKEU tercatat via event listener
        $this->assertDatabaseHas('sikeu_mahasiswa_perubahan_prodi', ['referensi_surat' => 'SIAKAD-PINDAH-' . $id]);

        // Filter status memakai enum DB
        $list = $this->getJson('/api/v1/siakad/pindah-prodi?status=applied');
        $list->assertStatus(200);
        $this->assertEquals(1, $list->json('meta.total'));
    }

    public function test_apply_menyelaraskan_prodi_spmb_asal(): void
    {
        $taSpmb = \App\Models\Spmb\MasterTahunAkademik::create([
            'kode' => '20241', 'nama' => 'TA SPMB Test', 'semester' => 'ganjil',
            'tahun_mulai' => 2024, 'tahun_selesai' => 2025, 'is_active' => true,
        ]);
        $jalur = \App\Models\Spmb\JalurMasuk::firstOrCreate(
            ['kode' => 'REG-PINDAH'],
            ['nama' => 'Jalur Reguler Pindah', 'tipe' => 'reguler', 'is_active' => true]
        );
        $gelombang = \App\Models\Spmb\GelombangPenerimaan::create([
            'nama' => 'Gelombang Pindah',
            'jalur_masuk_id' => $jalur->id,
            'tahun_akademik_id' => $taSpmb->id,
            'tanggal_buka' => now()->subMonth(),
            'tanggal_tutup' => now()->addMonth(),
            'status' => 'aktif',
        ]);
        $daftar = \App\Models\Spmb\PendaftaranCalonMhs::create([
            'gelombang_id' => $gelombang->id,
            'user_id' => $this->user->id,
            'program_studi_id' => $this->prodiA->id,
            'nama_lengkap' => 'Mahasiswa Pindah',
            'no_pendaftaran' => 'REG-PINDAH-001',
            'nik' => '3201999999999999',
            'status' => 'verified',
        ]);
        \App\Models\Spmb\KonversiMahasiswa::create([
            'pendaftaran_id' => $daftar->id,
            'mahasiswa_id' => $this->mhs->id,
        ]);

        $create = $this->postJson('/api/v1/siakad/pindah-prodi', [
            'mahasiswa_id' => $this->mhs->id,
            'new_program_studi_id' => $this->prodiB->id,
            'alasan' => 'Alasan pindah prodi yang cukup panjang',
        ]);
        $id = $create->json('data.id');
        $this->postJson("/api/v1/siakad/pindah-prodi/{$id}/cek-keuangan", ['hasil' => 'layak']);
        $this->postJson("/api/v1/siakad/pindah-prodi/{$id}/approve");
        $apply = $this->postJson("/api/v1/siakad/pindah-prodi/{$id}/apply");
        $apply->assertStatus(200);

        // SIAKAD termutasi + SPMB asal selaras, riwayat seleksi utuh
        $this->assertDatabaseHas('siakad_mahasiswa', ['id' => $this->mhs->id, 'program_studi_id' => $this->prodiB->id]);
        $this->assertDatabaseHas('spmb_pendaftaran_calon_mhs', ['id' => $daftar->id, 'program_studi_id' => $this->prodiB->id]);
    }
}
