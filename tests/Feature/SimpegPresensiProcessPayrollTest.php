<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Simpeg\GajiDetail;
use App\Models\Simpeg\GajiPegawai;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PresensiPegawai;
use App\Models\Simpeg\PresensiPeriode;
use App\Models\User;
use Database\Seeders\Simpeg\SimpegPayrollFlexibleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpegPresensiProcessPayrollTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Pegawai $pegawai;
    protected PresensiPeriode $periode;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Role dan Permission
        $role = Role::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
        $perms = [
            'simpeg.presensi.read',
            'simpeg.presensi.manage',
            'simpeg.payroll.read',
            'simpeg.payroll.create',
            'simpeg.payroll.manage',
        ];
        foreach ($perms as $slug) {
            $p = Permission::create([
                'slug' => $slug,
                'name' => $slug,
                'module' => 'simpeg',
                'action' => 'update',
            ]);
            $role->permissions()->attach($p->id);
        }

        $this->admin = User::create([
            'username' => 'admin.simpeg',
            'email' => 'admin.simpeg@kampus.ac.id',
            'password' => bcrypt('password'),
            'user_type' => 'admin',
        ]);
        $this->admin->roles()->attach($role->id);

        // 2. Setup Pegawai
        $this->pegawai = Pegawai::create([
            'nip' => '198801012015011001',
            'nama_lengkap' => 'Budi Santoso, M.Kom.',
            'jenis_pegawai' => 'dosen',
            'status_kepegawaian' => 'tetap',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        // 3. Seed Master Komponen Gaji
        $this->seed(SimpegPayrollFlexibleSeeder::class);

        // 4. Setup Periode Presensi
        $this->periode = PresensiPeriode::create([
            'nama_periode' => 'Periode Oktober 2026',
            'bulan_tahun' => '2026-10',
            'tanggal_awal' => '2026-09-26',
            'tanggal_akhir' => '2026-10-25',
            'is_locked' => false,
        ]);

        // 5. Presensi Hadir Lengkap (Valid)
        PresensiPegawai::create([
            'presensi_periode_id' => $this->periode->id,
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => '2026-10-01',
            'jam_masuk' => '07:55:00',
            'jam_keluar' => '16:05:00',
            'status_kehadiran' => 'hadir',
        ]);

        // Presensi Scan Parsial (Hanya Masuk - Tidak Dihitung)
        PresensiPegawai::create([
            'presensi_periode_id' => $this->periode->id,
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => '2026-10-02',
            'jam_masuk' => '08:00:00',
            'jam_keluar' => null,
            'status_kehadiran' => 'hadir',
        ]);

        // Presensi Dinas Disetujui (Valid)
        PresensiPegawai::create([
            'presensi_periode_id' => $this->periode->id,
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => '2026-10-05',
            'jam_masuk' => null,
            'jam_keluar' => null,
            'status_kehadiran' => 'dinas',
            'is_approved_by_admin' => true,
        ]);
    }

    public function test_can_process_payroll_from_presensi_bundle_and_lock_bundle(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/simpeg/presensi/{$this->periode->id}/payroll");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        // Periode terkunci
        $this->assertDatabaseHas('simpeg_presensi_periode', [
            'id' => $this->periode->id,
            'is_locked' => 1,
            'locked_by' => $this->admin->id,
        ]);

        // Data gaji tersimpan sebagai draft
        $this->assertDatabaseHas('simpeg_gaji_pegawai', [
            'pegawai_id' => $this->pegawai->id,
            'periode_bulan_tahun' => '2026-10',
            'status_transfer' => 'draft',
            'jumlah_hari_hadir_tepat_waktu' => 2, // 1 Hadir Lengkap + 1 Dinas Disetujui
        ]);

        $gaji = GajiPegawai::where('pegawai_id', $this->pegawai->id)
            ->where('periode_bulan_tahun', '2026-10')
            ->first();

        $this->assertNotNull($gaji);

        // Details tersimpan
        $details = GajiDetail::where('gaji_pegawai_id', $gaji->id)->get();
        $this->assertNotEmpty($details);

        // Mencoba koreksi pada bundle terkunci harus ditolak
        $koreksiRes = $this->actingAs($this->admin, 'api')
            ->postJson('/api/simpeg/presensi/koreksi', [
                'pegawai_id' => $this->pegawai->id,
                'tanggal' => '2026-10-01',
                'jam_masuk' => '08:00:00',
                'presensi_periode_id' => $this->periode->id,
            ]);

        $koreksiRes->assertStatus(422)
            ->assertJson([
                'status' => 'error',
            ]);
    }
}
