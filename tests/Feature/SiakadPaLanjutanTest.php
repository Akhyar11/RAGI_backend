<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\PaJadwal;
use App\Models\Siakad\PaPkl;
use App\Models\Siakad\ProgramStudi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Fase 2 Bimbingan PA (Terakhir): jadwal/konsultasi, PKL/PL/Magang,
 * insiden (kekerasan/pelecehan)+konseling+eskalasi, dan laporan monitoring bulanan.
 *
 * Menguji alur fungsional sekaligus otorisasi/ownership antar dosen PA.
 */
class SiakadPaLanjutanTest extends TestCase
{
    use RefreshDatabase;

    protected User $dosenAUser;
    protected User $dosenBUser;
    protected Dosen $dosenA;
    protected Dosen $dosenB;
    protected Mahasiswa $mhsA;
    protected User $mhsAUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();

        $prodi = ProgramStudi::create([
            'kode_prodi' => 'IF-PALANJUT',
            'nama'       => 'Informatika Lanjutan PA',
            'jenjang'    => 'S1',
        ]);

        $permPaRead  = Permission::firstOrCreate(
            ['slug' => 'siakad.pa.read'],
            ['name' => 'Lihat Data Bimbingan PA', 'module' => 'siakad', 'action' => 'read']
        );
        $permPaCreate = Permission::firstOrCreate(
            ['slug' => 'siakad.pa.create'],
            ['name' => 'Tambah Data Bimbingan PA', 'module' => 'siakad', 'action' => 'create']
        );
        $permPaUpdate = Permission::firstOrCreate(
            ['slug' => 'siakad.pa.update'],
            ['name' => 'Ubah Data Bimbingan PA', 'module' => 'siakad', 'action' => 'update']
        );
        $permPaDelete = Permission::firstOrCreate(
            ['slug' => 'siakad.pa.delete'],
            ['name' => 'Hapus Data Bimbingan PA', 'module' => 'siakad', 'action' => 'delete']
        );
        $permKrsRead = Permission::firstOrCreate(
            ['slug' => 'siakad.krs.read'],
            ['name' => 'Lihat KRS', 'module' => 'siakad', 'action' => 'read']
        );

        $roleDosen = Role::firstOrCreate(['slug' => 'dosen'], ['name' => 'Dosen']);
        $roleDosen->permissions()->syncWithoutDetaching([
            $permPaRead->id, $permPaCreate->id, $permPaUpdate->id, $permPaDelete->id, $permKrsRead->id,
        ]);

        $roleMhs = Role::firstOrCreate(['slug' => 'mahasiswa'], ['name' => 'Mahasiswa']);
        $roleMhs->permissions()->syncWithoutDetaching([
            $permPaRead->id, $permPaCreate->id, $permKrsRead->id,
        ]);

        $this->dosenAUser = User::factory()->create(['username' => 'dosen.lanjut.a']);
        $this->dosenAUser->roles()->attach($roleDosen->id);
        $this->dosenBUser = User::factory()->create(['username' => 'dosen.lanjut.b']);
        $this->dosenBUser->roles()->attach($roleDosen->id);

        $this->dosenA = Dosen::create([
            'user_id' => $this->dosenAUser->id, 'program_studi_id' => $prodi->id,
            'nidn' => '0077007700', 'nama_lengkap' => 'Dosen Lanjut A', 'status_aktif' => 'aktif',
        ]);
        $this->dosenB = Dosen::create([
            'user_id' => $this->dosenBUser->id, 'program_studi_id' => $prodi->id,
            'nidn' => '0088008800', 'nama_lengkap' => 'Dosen Lanjut B', 'status_aktif' => 'aktif',
        ]);

        $this->mhsAUser = User::factory()->create(['username' => 'mhs.lanjut.a']);
        $this->mhsAUser->roles()->attach($roleMhs->id);
        $this->mhsA = Mahasiswa::create([
            'user_id' => $this->mhsAUser->id, 'program_studi_id' => $prodi->id,
            'nim' => '2026009101', 'nama_lengkap' => 'Mhs Lanjut A', 'angkatan' => 2026,
            'status' => 'aktif', 'dosen_wali_id' => $this->dosenA->id,
        ]);
    }

    public function test_dosen_dapat_membuat_dan_melihat_jadwal(): void
    {
        Passport::actingAs($this->dosenAUser);

        $res = $this->postJson('/api/v1/siakad/bimbingan/jadwal', [
            'tanggal' => '2026-10-25', 'jam_mulai' => '09:00', 'jam_selesai' => '10:00',
            'mode' => 'tatap_muka', 'lokasi' => 'R. 201', 'kuota' => 5,
        ]);

        $res->assertStatus(201)->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('siakad_pa_jadwal', ['dosen_id' => $this->dosenA->id, 'status' => 'dibuka']);

        $list = $this->getJson('/api/v1/siakad/bimbingan/jadwal');
        $list->assertStatus(200)->assertJsonPath('meta.total', 1);
    }

    public function test_dosen_tidak_dapat_mengubah_jadwal_pa_lain(): void
    {
        $jadwal = PaJadwal::create([
            'dosen_id' => $this->dosenA->id, 'tanggal' => '2026-10-25',
            'jam_mulai' => '09:00', 'jam_selesai' => '10:00', 'kuota' => 3,
        ]);

        Passport::actingAs($this->dosenBUser);
        $this->putJson("/api/v1/siakad/bimbingan/jadwal/{$jadwal->id}", ['kuota' => 10])->assertStatus(403);
        $this->deleteJson("/api/v1/siakad/bimbingan/jadwal/{$jadwal->id}")->assertStatus(403);
    }

    public function test_mahasiswa_mengajukan_konsultasi_dan_dosen_memproses(): void
    {
        $jadwal = PaJadwal::create([
            'dosen_id' => $this->dosenA->id, 'tanggal' => '2026-10-26',
            'jam_mulai' => '09:00', 'jam_selesai' => '10:00', 'kuota' => 1, 'status' => 'dibuka',
        ]);

        // Mahasiswa mengajukan.
        Passport::actingAs($this->mhsAUser);
        $res = $this->postJson("/api/v1/siakad/bimbingan/jadwal/{$jadwal->id}/ajukan", [
            'topik' => 'Konsultasi KRS',
        ]);
        $res->assertStatus(201)->assertJsonPath('data.status', 'diajukan');
        $bookingId = $res->json('data.id');

        // Dosen memproses.
        Passport::actingAs($this->dosenAUser);
        $this->patchJson("/api/v1/siakad/bimbingan/booking/{$bookingId}/proses", [
            'status' => 'disetujui', 'catatan_dosen' => 'Silakan hadir.',
        ])->assertStatus(200)->assertJsonPath('data.status', 'disetujui');
    }

    public function test_booking_ditolak_saat_kuota_penuh(): void
    {
        $jadwal = PaJadwal::create([
            'dosen_id' => $this->dosenA->id, 'tanggal' => '2026-10-27',
            'jam_mulai' => '09:00', 'jam_selesai' => '10:00', 'kuota' => 1, 'status' => 'dibuka',
        ]);

        Passport::actingAs($this->mhsAUser);
        $this->postJson("/api/v1/siakad/bimbingan/jadwal/{$jadwal->id}/ajukan", [])->assertStatus(201);
        // Kuota 1 sudah terpakai → pengajuan kedua (mahasiswa sama) ditolak 422.
        $this->postJson("/api/v1/siakad/bimbingan/jadwal/{$jadwal->id}/ajukan", [])->assertStatus(422);
    }

    public function test_dosen_mencatat_status_pkl_bimbingan(): void
    {
        Passport::actingAs($this->dosenAUser);

        $res = $this->postJson('/api/v1/siakad/bimbingan/pkl', [
            'mahasiswa_id' => $this->mhsA->id, 'jenis' => 'magang',
            'instansi' => 'PT Teknologi Nusantara', 'status' => 'berjalan',
        ]);

        $res->assertStatus(201)->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('siakad_pa_pkl', ['mahasiswa_id' => $this->mhsA->id, 'jenis' => 'magang']);

        $this->getJson('/api/v1/siakad/bimbingan/pkl')->assertStatus(200)->assertJsonPath('meta.total', 1);
    }

    public function test_dosen_tidak_boleh_mencatat_pkl_mahasiswa_bukan_bimbingannya(): void
    {
        Passport::actingAs($this->dosenBUser);

        $this->postJson('/api/v1/siakad/bimbingan/pkl', [
            'mahasiswa_id' => $this->mhsA->id, 'jenis' => 'magang', 'instansi' => 'PT X',
        ])->assertStatus(403);
    }

    public function test_laporan_insiden_anonim_dan_eskalasi_konseling(): void
    {
        Passport::actingAs($this->dosenAUser);

        // Lapor anonim.
        $res = $this->postJson('/api/v1/siakad/bimbingan/insiden', [
            'jenis' => 'pelecehan_seksual', 'deskripsi' => 'Kronologi kejadian…',
            'tanggal_kejadian' => '2026-10-05', 'anonim' => true,
        ]);
        $res->assertStatus(201)->assertJsonPath('status', 'success');
        $insidenId = $res->json('data.id');

        // Baris anonim: identitas pelapor disamarkan.
        $this->assertDatabaseHas('siakad_pa_insiden', ['id' => $insidenId, 'anonim' => true, 'dosen_id' => null]);

        // Eskalasi ke Satgas PPKS.
        $this->patchJson("/api/v1/siakad/bimbingan/insiden/{$insidenId}/eskalasi", [
            'eskalasi_tujuan' => 'satgas_ppks',
        ])->assertStatus(200)->assertJsonPath('data.status', 'diverifikasi');

        // Rujukan konseling.
        $this->patchJson("/api/v1/siakad/bimbingan/insiden/{$insidenId}/konseling", [
            'konseling_ke' => 'Unit Layanan Konseling',
        ])->assertStatus(200)->assertJsonPath('data.rujukan_konseling', true);
    }

    public function test_jenis_insiden_tidak_terdaftar_ditolak(): void
    {
        Passport::actingAs($this->dosenAUser);

        $this->postJson('/api/v1/siakad/bimbingan/insiden', [
            'jenis' => 'tidak_ada_di_master', 'deskripsi' => 'X',
        ])->assertStatus(422);
    }

    public function test_laporan_monitoring_bulanan(): void
    {
        Passport::actingAs($this->dosenAUser);

        $res = $this->getJson('/api/v1/siakad/bimbingan/laporan-bulanan?bulan=2026-10');

        $res->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.periode.bulan', '2026-10')
            ->assertJsonPath('data.kehadiran.total_advisees', 1);
    }

    public function test_referensi_pa_tersedia(): void
    {
        foreach (['pa_jadwal_mode', 'pa_jadwal_status', 'pa_booking_status', 'pa_pkl_jenis', 'pa_pkl_status', 'pa_insiden_jenis', 'pa_insiden_status', 'pa_eskalasi_tujuan', 'pa_catatan_kategori', 'pa_tindak_lanjut_status'] as $tipe) {
            $this->assertGreaterThan(0, DB::table('spmb_master_referensi')
                ->where('modul', 'siakad')->where('tipe', $tipe)->count(), "Referensi {$tipe} kosong.");
        }
    }
}
