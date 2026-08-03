<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Simpeg\Pegawai;
use App\Models\Sikeu\JenisBiaya;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\UnitKas;
use App\Models\Sippm\ProposalKegiatan;
use App\Models\Sippm\KontrakKegiatan;
use App\Models\Sippm\SkemaKegiatan;
use App\Models\Sippm\PeriodeHibah;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SikeuTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\IAM\RoleSeeder::class);
        $this->seed(\Database\Seeders\IAM\PermissionSeeder::class);
        $this->seed(\Database\Seeders\Sikeu\SikeuAkuntansiSeeder::class);
        $this->seed(\Database\Seeders\Sikeu\SikeuMasterSeeder::class);

        $this->user = User::factory()->create();
    }

    public function test_external_bill_generation_creates_bill_and_va()
    {
        $response = $this->actingAs($this->user, 'api')->postJson('/api/v1/sikeu/tagihan/external', [
            'mahasiswa_id' => 99,
            'tahun_akademik_id' => 1,
            'source_system' => 'SPMB',
            'requires_approval' => false,
            'keterangan' => 'Tagihan Seleksi SPMB',
            'details' => [
                [
                    'jenis_biaya_kode' => 'SPMB_ADM',
                    'nominal' => 350000,
                    'keterangan' => 'Biaya Formulir'
                ]
            ]
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('tagihan_mahasiswa', [
            'mahasiswa_id' => 99,
            'source_system' => 'SPMB',
            'status' => 'belum_bayar',
        ]);
    }

    public function test_external_bill_with_approval_enters_pending_approval_queue()
    {
        $response = $this->actingAs($this->user, 'api')->postJson('/api/v1/sikeu/tagihan/external', [
            'mahasiswa_id' => 100,
            'tahun_akademik_id' => 1,
            'source_system' => 'SIAKAD',
            'requires_approval' => true,
            'keterangan' => 'Tagihan Khusus Akselerasi',
            'details' => [
                [
                    'jenis_biaya_kode' => 'UKT_REG',
                    'nominal' => 4500000,
                    'keterangan' => 'UKT Semester 1'
                ]
            ]
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('tagihan_mahasiswa', [
            'mahasiswa_id' => 100,
            'status' => 'pending_approval',
            'status_approval' => 'pending',
        ]);
    }

    public function test_dispensation_request_creation()
    {
        $tagihan = TagihanMahasiswa::create([
            'mahasiswa_id' => 88,
            'tahun_akademik_id' => 1,
            'nomor_tagihan' => 'INV-TEST-001',
            'total_tagihan' => 3000000,
            'total_bayar' => 3000000,
            'status' => 'belum_bayar',
        ]);

        $response = $this->actingAs($this->user, 'api')->postJson('/api/v1/sikeu/dispensasi', [
            'tagihan_id' => $tagihan->id,
            'tipe_dispensasi' => 'penundaan_jatuh_tempo',
            'jatuh_tempo_baru' => '2026-10-15',
            'alasan' => 'Menunggu permohonan beasiswa daerah',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('dispensasi_tagihan', [
            'tagihan_id' => $tagihan->id,
            'status' => 'pending',
        ]);
    }

    public function test_external_income_recording_updates_cash_and_creates_journal()
    {
        $unitKas = UnitKas::first();
        $saldoAwal = $unitKas->saldo_saat_ini;

        $response = $this->actingAs($this->user, 'api')->postJson('/api/v1/sikeu/pemasukan/external', [
            'sumber_pemasukan' => 'hibah_sippm',
            'nominal' => 25000000,
            'tanggal_terima' => date('Y-m-d'),
            'nama_donor_instansi' => 'Kemdikbudristek',
            'nomor_kontrak_ref' => '045/SPK/LPPM/2026',
            'keterangan' => 'Pencairan Hibah Riset Unggulan 2026',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $unitKas->refresh();
        $this->assertEquals($saldoAwal + 25000000, $unitKas->saldo_saat_ini);

        $this->assertDatabaseHas('jurnal_umum', [
            'jenis_sumber' => 'pemasukan_hibah',
            'total_debet' => 25000000,
        ]);
    }

    public function test_payroll_posting_creates_journal_in_sikeu()
    {
        $pegawai = Pegawai::create([
            'nip' => '198501012010011001',
            'nama_lengkap' => 'Dr. Budi Santoso, M.Kom.',
            'jenis_kelamin' => 'L',
            'jenis_pegawai' => 'dosen',
            'status_kepegawaian' => 'pns',
        ]);

        $roleAdmin = Role::where('slug', 'admin')->first();
        if ($roleAdmin) {
            $this->user->roles()->syncWithoutDetaching([$roleAdmin->id]);
        }

        $response = $this->actingAs($this->user, 'api')->postJson('/api/simpeg/payroll', [
            'pegawai_id' => $pegawai->id,
            'periode_bulan_tahun' => '2026-08',
            'gaji_pokok' => 5000000,
            'total_tunjangan' => 2000000,
            'total_potongan' => 500000,
            'gaji_bersih' => 6500000,
            'status_transfer' => 'paid',
            'nomor_rekening' => '1234567890',
            'bank_nama' => 'Bank Mandiri',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('jurnal_umum', [
            'jenis_sumber' => 'pengeluaran_manual',
            'total_debet' => 7000000,
        ]);
    }

    public function test_sippm_disbursement_creates_pemasukan_and_journal()
    {
        $periode = PeriodeHibah::create([
            'tahun_anggaran' => 2026,
            'nama_gelombang' => 'Gelombang 1',
            'tgl_buka_proposal' => '2026-01-01',
            'tgl_tutup_proposal' => '2026-12-31',
            'is_active' => true,
        ]);

        $skema = SkemaKegiatan::create([
            'kode' => 'PEN-TERAPAN',
            'nama' => 'Skema Penelitian Terapan',
            'tipe' => 'penelitian',
            'sumber_dana' => 'internal',
            'maksimal_anggaran' => 50000000,
            'is_active' => true,
        ]);

        $pegawai = Pegawai::create([
            'nip' => '198501012010011002',
            'nama_lengkap' => 'Dr. Ani Triastuti, M.Si.',
            'jenis_kelamin' => 'P',
            'jenis_pegawai' => 'dosen',
            'status_kepegawaian' => 'pns',
        ]);

        $proposal = ProposalKegiatan::create([
            'kode_proposal' => 'PROP-2026-001',
            'periode_id' => $periode->id,
            'skema_id' => $skema->id,
            'ketua_pegawai_id' => $pegawai->id,
            'judul' => 'Penelitian RAG AI Kampus',
            'abstrak' => 'Abstrak penelitian RAG AI Kampus 2026',
            'rumpun_ilmu' => 'Informatika',
            'anggaran_diajukan' => 50000000,
            'file_proposal' => 'proposal.pdf',
            'status' => 'berjalan',
        ]);

        $kontrak = KontrakKegiatan::create([
            'proposal_id' => $proposal->id,
            'nomor_kontrak' => '055/SPK/SIPPM/2026',
            'dana_disetujui' => 50000000,
            'tgl_mulai' => '2026-01-01',
            'tgl_selesai' => '2026-12-31',
            'file_kontrak' => 'kontrak.pdf',
        ]);

        $response = $this->actingAs($this->user, 'api')->postJson("/api/sippm/kontrak/{$kontrak->id}/pencairan", [
            'termin_ke' => 1,
            'persen_pencairan' => 70,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('pemasukan_kampus', [
            'sumber_pemasukan' => 'hibah_sippm',
            'nominal' => 35000000,
        ]);

        $this->assertDatabaseHas('jurnal_umum', [
            'jenis_sumber' => 'pemasukan_hibah',
            'total_debet' => 35000000,
        ]);
    }

    public function test_spmb_callback_unlocks_registration()
    {
        $response = $this->actingAs($this->user, 'api')->postJson('/api/v1/sikeu/callback/spmb/777', [
            'order_id' => 'ORDER-SPMB-777',
            'nominal' => 350000,
            'status' => 'paid',
            'bank_kode' => 'BNI',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('spmb_unlock', true);

        $this->assertDatabaseHas('tagihan_mahasiswa', [
            'mahasiswa_id' => 777,
            'status' => 'lunas',
            'source_system' => 'SPMB',
        ]);
    }
}
