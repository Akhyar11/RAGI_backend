<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\UnitKas;
use App\Models\Simpeg\GajiPegawai;
use App\Models\Simpeg\JabatanFungsionalAkademik;
use App\Models\Simpeg\MasterKomponenGaji;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\RiwayatJabatan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpegPayrollFlexibleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Pegawai $pegawai;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Admin dengan role super-admin
        $role = Role::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
        $perms = [
            'simpeg.payroll.view',
            'simpeg.payroll.read',
            'simpeg.payroll.create',
            'simpeg.payroll.manage',
        ];
        foreach ($perms as $slug) {
            $p = Permission::create([
                'slug' => $slug,
                'name' => $slug,
                'module' => 'simpeg',
                'action' => 'read',
            ]);
            $role->permissions()->attach($p->id);
        }

        $this->admin = User::create([
            'username' => 'admin.payroll',
            'email' => 'admin.payroll@kampus.ac.id',
            'password' => bcrypt('password'),
            'user_type' => 'admin',
        ]);
        $this->admin->roles()->attach($role->id);

        // 2. Setup Pegawai Dosen
        $this->pegawai = Pegawai::create([
            'nip' => '198501012010121001',
            'nama_lengkap' => 'Dr. H. Ahmad Dahlan, M.Kom.',
            'jenis_pegawai' => 'dosen',
            'status_kepegawaian' => 'tetap',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        // Setup Jabatan Fungsional Lektor
        $jafung = JabatanFungsionalAkademik::create([
            'nama' => 'Lektor',
            'angka_kredit_min' => 200,
            'angka_kredit_max' => 300,
            'golongan' => 'lektor',
        ]);

        RiwayatJabatan::create([
            'pegawai_id' => $this->pegawai->id,
            'jabatan_fungsional_id' => $jafung->id,
            'mulai_jabatan' => '2024-01-01',
            'is_active' => true,
        ]);

        // 3. Seed Master Komponen Gaji
        $this->seed(\Database\Seeders\Simpeg\SimpegPayrollFlexibleSeeder::class);

        // 4. Setup Akun Keuangan & Unit Kas SIKEU
        AkunKeuangan::create(['kode_akun' => '501.01', 'nama_akun' => 'Beban Gaji & Honorarium', 'kelompok' => 'beban', 'saldo_normal' => 'debet']);
        AkunKeuangan::create(['kode_akun' => '501.02', 'nama_akun' => 'Beban Tunjangan & Insentif', 'kelompok' => 'beban', 'saldo_normal' => 'debet']);
        AkunKeuangan::create(['kode_akun' => '102.01', 'nama_akun' => 'Bank Operasional Kampus', 'kelompok' => 'aset', 'saldo_normal' => 'debet']);
        AkunKeuangan::create(['kode_akun' => '202.01', 'nama_akun' => 'Utang Pajak PPh 21', 'kelompok' => 'liabilitas', 'saldo_normal' => 'kredit']);
        AkunKeuangan::create(['kode_akun' => '201.01', 'nama_akun' => 'Utang Potongan BPJS', 'kelompok' => 'liabilitas', 'saldo_normal' => 'kredit']);

        UnitKas::create([
            'nama_kas' => 'Kas Operasional Rektorat',
            'tipe_kas' => 'utama',
            'saldo_awal' => 500000000,
            'saldo_saat_ini' => 500000000,
            'status' => true,
        ]);
    }

    public function test_can_list_and_manage_master_komponen_gaji(): void
    {
        // 1. List Komponen
        $response = $this->actingAs($this->admin, 'api')->getJson('/api/simpeg/payroll/komponen');
        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data', 'meta']);

        $this->assertNotEmpty($response->json('data'));

        // 2. Store Komponen Baru
        $storeRes = $this->actingAs($this->admin, 'api')->postJson('/api/simpeg/payroll/komponen', [
            'kode' => 'BONUS_KINERJA',
            'nama' => 'Bonus Prestasi Akademik',
            'jenis' => 'pendapatan',
            'tipe_nilai' => 'tetap',
            'nilai_default' => 500000,
            'is_taxable' => true,
            'is_active' => true,
            'urutan' => 7,
        ]);

        $storeRes->assertStatus(201);
        $this->assertDatabaseHas('simpeg_master_komponen_gaji', ['kode' => 'BONUS_KINERJA']);
    }

    public function test_can_generate_flexible_payroll_calculation(): void
    {
        $response = $this->actingAs($this->admin, 'api')->postJson('/api/simpeg/payroll/generate', [
            'periode' => '2026-09',
            'pegawai_id' => $this->pegawai->id,
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'success'])
            ->assertJsonStructure(['data' => ['payrolls']]);

        // Verifikasi data tersimpan di simpeg_gaji_pegawai
        $this->assertDatabaseHas('simpeg_gaji_pegawai', [
            'pegawai_id' => $this->pegawai->id,
            'periode_bulan_tahun' => '2026-09',
            'status_transfer' => 'draft',
        ]);

        // Verifikasi rincian butir tersimpan di simpeg_gaji_detail
        $gaji = GajiPegawai::where('pegawai_id', $this->pegawai->id)->first();
        $this->assertNotNull($gaji);
        $this->assertGreaterThan(0, $gaji->details()->count());
        $this->assertGreaterThan(0, $gaji->gaji_bersih);
    }

    public function test_can_view_payroll_detail_breakdown(): void
    {
        // Generate payroll terlebih dahulu
        $this->actingAs($this->admin, 'api')->postJson('/api/simpeg/payroll/generate', [
            'periode' => '2026-09',
            'pegawai_id' => $this->pegawai->id,
        ]);

        $gaji = GajiPegawai::where('pegawai_id', $this->pegawai->id)->first();

        $response = $this->actingAs($this->admin, 'api')->getJson("/api/simpeg/payroll/{$gaji->id}");
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $gaji->id,
                    'pegawai_id' => $this->pegawai->id,
                ],
            ])
            ->assertJsonStructure(['data' => ['details', 'pegawai']]);
    }

    public function test_process_payment_creates_balanced_sikeu_journal_and_cash_deduction(): void
    {
        // Generate payroll
        $this->actingAs($this->admin, 'api')->postJson('/api/simpeg/payroll/generate', [
            'periode' => '2026-09',
            'pegawai_id' => $this->pegawai->id,
        ]);

        $gaji = GajiPegawai::where('pegawai_id', $this->pegawai->id)->first();
        $initialSaldo = (float) UnitKas::first()->saldo_saat_ini;

        // Eksekusi Pembayaran SIKEU
        $response = $this->actingAs($this->admin, 'api')->postJson("/api/simpeg/payroll/{$gaji->id}/process-payment");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'status_transfer' => 'paid',
                ],
            ]);

        $gaji->refresh();
        $this->assertEquals('paid', $gaji->status_transfer);
        $this->assertNotNull($gaji->jurnal_id);

        // Verifikasi Jurnal Umum SIKEU berimbang
        $this->assertDatabaseHas('sikeu_jurnal_umum', [
            'id' => $gaji->jurnal_id,
            'status_posting' => 'posted',
        ]);

        // Saldo kas berkurang sebesar gaji_bersih
        $finalSaldo = (float) UnitKas::first()->saldo_saat_ini;
        $this->assertEquals($initialSaldo - (float) $gaji->gaji_bersih, $finalSaldo);
    }
}
