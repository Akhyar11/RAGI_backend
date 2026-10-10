<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\PengajuanPencairanKas;
use App\Models\Sikeu\UnitKas;
use App\Models\Simpeg\GajiPegawai;
use App\Models\Simpeg\Pegawai;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trial antrean terpadu: batch penggajian SIMPEG menjadi satu dokumen
 * PengajuanPencairanKas (sumber_type = gaji_simpeg) yang mengalir lewat
 * rel approve → pencairan SIKEU yang sudah ada, tanpa input ulang.
 */
class SikeuPengajuanGajiBatchTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected UnitKas $unitKas;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        foreach (['simpeg.payroll.create', 'simpeg.payroll.manage', 'simpeg.payroll.read'] as $slug) {
            $p = Permission::create(['slug' => $slug, 'name' => $slug, 'module' => 'simpeg', 'action' => 'read']);
            $role->permissions()->attach($p->id);
        }
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($role->id);

        AkunKeuangan::create(['kode_akun' => '501.01', 'nama_akun' => 'Beban Gaji & Honorarium', 'kelompok' => 'beban', 'saldo_normal' => 'debet']);
        AkunKeuangan::create(['kode_akun' => '501.02', 'nama_akun' => 'Beban Tunjangan & Insentif', 'kelompok' => 'beban', 'saldo_normal' => 'debet']);
        AkunKeuangan::create(['kode_akun' => '102.01', 'nama_akun' => 'Bank Operasional Kampus', 'kelompok' => 'aset', 'saldo_normal' => 'debet']);
        AkunKeuangan::create(['kode_akun' => '202.01', 'nama_akun' => 'Utang Pajak PPh 21', 'kelompok' => 'liabilitas', 'saldo_normal' => 'kredit']);
        AkunKeuangan::create(['kode_akun' => '201.01', 'nama_akun' => 'Utang Potongan BPJS', 'kelompok' => 'liabilitas', 'saldo_normal' => 'kredit']);

        $this->unitKas = UnitKas::create([
            'nama_kas' => 'Kas Operasional Rektorat',
            'tipe_kas' => 'utama',
            'saldo_awal' => 500000000,
            'saldo_saat_ini' => 500000000,
            'status' => true,
        ]);

        foreach ([
            ['nip' => '198501012010121001', 'nama' => 'Dr. H. Ahmad Dahlan, M.Kom.', 'pokok' => 8000000, 'tunjangan' => 2000000, 'potongan' => 500000, 'pph21' => 300000],
            ['nip' => '199002102015032001', 'nama' => 'Siti Rahmawati, A.Md.', 'pokok' => 5000000, 'tunjangan' => 1000000, 'potongan' => 200000, 'pph21' => 100000],
        ] as $row) {
            $pegawai = Pegawai::create([
                'nip' => $row['nip'],
                'nama_lengkap' => $row['nama'],
                'jenis_pegawai' => 'tendik',
                'status_kepegawaian' => 'tetap',
                'status' => 'aktif',
            ]);
            GajiPegawai::create([
                'pegawai_id' => $pegawai->id,
                'periode_bulan_tahun' => '2026-10',
                'gaji_pokok' => $row['pokok'],
                'total_tunjangan' => $row['tunjangan'],
                'total_potongan' => $row['potongan'],
                'total_pph21' => $row['pph21'],
                'gaji_bersih' => $row['pokok'] + $row['tunjangan'] - $row['potongan'],
                'status_transfer' => 'draft',
            ]);
        }
    }

    public function test_submit_membuat_batch_idempoten(): void
    {
        $url = '/api/simpeg/payroll/submit-to-sikeu';

        $first = $this->actingAs($this->admin, 'api')->postJson($url, ['periode' => '2026-10']);
        $first->assertStatus(200)->assertJsonPath('status', 'success');
        $batchId = $first->json('data.id');
        $this->assertNotEmpty($batchId);

        $batch = PengajuanPencairanKas::find($batchId);
        $this->assertEquals(PengajuanPencairanKas::SUMBER_GAJI_SIMPEG, $batch->sumber_type);
        $this->assertEquals('2026-10', $batch->sumber_id);
        $this->assertEquals('non_barang', $batch->kategori_pengajuan);
        $this->assertEquals('pending_keuangan', $batch->status);
        $this->assertEquals(2, $batch->items()->count());
        // 9.5jt + 5.8jt = 15.3jt
        $this->assertEquals(15300000, (float) $batch->nominal_diajukan);

        $this->assertEquals(0, GajiPegawai::where('periode_bulan_tahun', '2026-10')->where('status_transfer', 'draft')->count());

        // Pengajuan ulang periode sama = batch yang sama (tanpa slip draft tersisa → 422).
        $again = $this->actingAs($this->admin, 'api')->postJson($url, ['periode' => '2026-10']);
        $again->assertStatus(422);
        $this->assertEquals(1, PengajuanPencairanKas::where('sumber_type', PengajuanPencairanKas::SUMBER_GAJI_SIMPEG)->where('sumber_id', '2026-10')->count());
    }

    public function test_approve_lalu_cairkan_menerbitkan_jurnal_gaji(): void
    {
        $batchId = $this->actingAs($this->admin, 'api')->postJson('/api/simpeg/payroll/submit-to-sikeu', ['periode' => '2026-10'])->json('data.id');

        // pending_keuangan -> pending_direktur -> disetujui
        $this->actingAs($this->admin, 'api')->postJson("/api/v1/sikeu/pengajuan-operasional/{$batchId}/approve", ['aksi' => 'approve'])->assertStatus(200);
        $this->actingAs($this->admin, 'api')->postJson("/api/v1/sikeu/pengajuan-operasional/{$batchId}/approve", ['aksi' => 'approve'])->assertStatus(200)
            ->assertJsonPath('data.status', 'disetujui');

        // Tanpa unit kas → 422
        $this->actingAs($this->admin, 'api')->postJson("/api/v1/sikeu/pengajuan-operasional/{$batchId}/pencairan", [])->assertStatus(422);

        $cair = $this->actingAs($this->admin, 'api')->postJson("/api/v1/sikeu/pengajuan-operasional/{$batchId}/pencairan", [
            'unit_kas_id' => $this->unitKas->id,
            'tanggal_pencairan' => '2026-10-11',
        ]);
        $cair->assertStatus(200)->assertJsonPath('data.status', 'dicairkan');

        // Slip paid + jurnal JRN-GAJI per pegawai
        $this->assertEquals(0, GajiPegawai::where('periode_bulan_tahun', '2026-10')->where('status_transfer', '!=', 'paid')->count());
        $this->assertEquals(2, JurnalUmum::where('nomor_jurnal', 'like', 'JRN-GAJI%')->count());
        $jurnal = JurnalUmum::where('nomor_jurnal', 'like', 'JRN-GAJI%')->first();
        $this->assertStringStartsWith('JRN-GAJI', $jurnal->nomor_jurnal);
        $this->assertEquals((float) $jurnal->total_debet, (float) $jurnal->total_kredit);

        // Kas berkurang sebesar total bersih
        $this->assertEquals(500000000 - 15300000, (float) $this->unitKas->fresh()->saldo_saat_ini);

        // Detail batch memuat rincian pegawai + jurnal
        $detail = $this->actingAs($this->admin, 'api')->getJson("/api/v1/sikeu/pengajuan-operasional/{$batchId}");
        $detail->assertStatus(200);
        $this->assertCount(2, $detail->json('data.items'));
        $this->assertNotEmpty($detail->json('data.items.0.gaji_pegawai'));
    }

    public function test_reject_mengembalikan_slip_ke_draft(): void
    {
        $batchId = $this->actingAs($this->admin, 'api')->postJson('/api/simpeg/payroll/submit-to-sikeu', ['periode' => '2026-10'])->json('data.id');

        $this->actingAs($this->admin, 'api')->postJson("/api/v1/sikeu/pengajuan-operasional/{$batchId}/approve", [
            'aksi' => 'reject',
            'catatan' => 'Nominal tunjangan belum sesuai SK.',
        ])->assertStatus(200)->assertJsonPath('data.status', 'ditolak');

        $this->assertEquals(2, GajiPegawai::where('periode_bulan_tahun', '2026-10')->where('status_transfer', 'draft')->count());

        // Bisa diajukan ulang → batch yang sama dibuka kembali
        $resubmit = $this->actingAs($this->admin, 'api')->postJson('/api/simpeg/payroll/submit-to-sikeu', ['periode' => '2026-10']);
        $resubmit->assertStatus(200)->assertJsonPath('data.id', $batchId)
            ->assertJsonPath('data.status', 'pending_keuangan');
    }

    public function test_index_bisa_disaring_sumber(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/simpeg/payroll/submit-to-sikeu', ['periode' => '2026-10'])->assertStatus(200);

        $res = $this->actingAs($this->admin, 'api')->getJson('/api/v1/sikeu/pengajuan-operasional?sumber=gaji_simpeg');
        $res->assertStatus(200);
        $this->assertNotEmpty($res->json('data'));
        foreach ($res->json('data') as $row) {
            $this->assertEquals('gaji_simpeg', $row['sumber_type']);
        }
    }

    public function test_tab_operasional_tidak_menampilkan_batch_gaji(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/simpeg/payroll/submit-to-sikeu', ['periode' => '2026-10'])->assertStatus(200);

        // Antrean tanpa filter tetap memuat batch (unified queue).
        $all = $this->actingAs($this->admin, 'api')->getJson('/api/v1/sikeu/pengajuan-operasional');
        $all->assertStatus(200);
        $this->assertNotEmpty(collect($all->json('data'))->where('sumber_type', 'gaji_simpeg')->all());

        // Tab operasional murni menyembunyikan batch bersumber.
        $ops = $this->actingAs($this->admin, 'api')->getJson('/api/v1/sikeu/pengajuan-operasional?tab=operasional');
        $ops->assertStatus(200);
        $this->assertEmpty(collect($ops->json('data'))->where('sumber_type', 'gaji_simpeg')->all());
    }
}
