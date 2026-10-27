<?php

namespace Tests\Feature;

use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\DetailJurnalUmum;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\PeriodeAkuntansi;
use App\Models\Sikeu\SaldoAwal;
use App\Models\User;
use App\Services\Sikeu\SaldoAwalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SikeuSaldoAwalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);
        $this->seed(\Database\Seeders\Sikeu\SikeuAkuntansiSeeder::class);

        if (\Laravel\Passport\Client::where('personal_access_client', 1)->doesntExist()) {
            app(\Laravel\Passport\ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access Client');
        }

        $this->admin = User::firstOrCreate(
            ['email' => 'admin.saldoawal@kampus.ac.id'],
            ['username' => 'adminsaldoawal', 'password' => Hash::make('password123'), 'is_active' => true, 'is_verified' => true]
        );
    }

    protected function headers(): array
    {
        $result = $this->admin->createToken('saldo-awal-token');

        return ['Authorization' => 'Bearer ' . ($result->plainTextToken ?? $result->accessToken)];
    }

    private function akunId(string $kode): int
    {
        return (int) AkunKeuangan::where('kode_akun', $kode)->firstOrFail()->id;
    }

    private function payloadSaldoAwal(): array
    {
        return [
            'tanggal' => '2026-01-01',
            'keterangan' => 'Cut over migrasi sistem lama',
            'details' => [
                ['akun_id' => $this->akunId('102.01'), 'debet' => 100000000, 'kredit' => 0],
                ['akun_id' => $this->akunId('202.01'), 'debet' => 0, 'kredit' => 20000000],
            ],
        ];
    }

    public function test_halaman_saldo_awal_mengembalikan_coa_neraca_dan_akun_penyeimbang(): void
    {
        $res = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/akuntansi/saldo-awal');

        $res->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'saldo_awal' => null,
                    'akun_penyeimbang' => ['kode_akun' => '301.03'],
                ],
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'saldo_awal',
                    'akun_penyeimbang' => ['id', 'kode_akun', 'nama_akun'],
                    'coa',
                    'riwayat',
                ],
            ]);

        // Hanya akun neraca yang boleh muncul sebagai pilihan.
        foreach ($res->json('data.coa') as $akun) {
            $this->assertContains($akun['kelompok'], ['aset', 'liabilitas', 'ekuitas']);
            $this->assertNotSame('301.03', $akun['kode_akun']);
        }
    }

    public function test_simpan_draft_menyeimbangkan_selisih_ke_ekuitas_301_03(): void
    {
        $res = $this->withHeaders($this->headers())
            ->putJson('/api/v1/sikeu/akuntansi/saldo-awal', $this->payloadSaldoAwal());

        $res->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'status' => 'draft',
                    'nominal_penyeimbang' => 80000000,
                ],
            ]);

        $headerId = $res->json('data.id');
        $this->assertNotNull($headerId);
        $this->assertEquals(100000000, (float) $res->json('data.total_debet'));
        $this->assertEquals(100000000, (float) $res->json('data.total_kredit'));

        $this->assertDatabaseHas('sikeu_saldo_awal', [
            'id' => $headerId,
            'status' => 'draft',
            'akun_penyeimbang_id' => $this->akunId('301.03'),
        ]);
        $this->assertDatabaseCount('sikeu_saldo_awal_detail', 2);
    }

    public function test_perbarui_draft_mengembalikan_200_dan_mencatat_audit_update(): void
    {
        $awal = $this->withHeaders($this->headers())
            ->putJson('/api/v1/sikeu/akuntansi/saldo-awal', $this->payloadSaldoAwal());
        $awal->assertStatus(201);
        $headerId = (int) $awal->json('data.id');

        $payload = $this->payloadSaldoAwal();
        $payload['tanggal'] = '2026-01-02';

        $ubah = $this->withHeaders($this->headers())
            ->putJson('/api/v1/sikeu/akuntansi/saldo-awal', $payload);

        $ubah->assertStatus(200)->assertJsonPath('data.id', $headerId);
        $this->assertDatabaseHas('core_audit_logs', [
            'table_name' => 'sikeu_saldo_awal',
            'record_id' => $headerId,
            'action' => 'update',
        ]);
    }

    public function test_posting_membuat_jurnal_jrn_open_dan_laporan_neraca_seimbang(): void
    {
        $draft = $this->withHeaders($this->headers())
            ->putJson('/api/v1/sikeu/akuntansi/saldo-awal', $this->payloadSaldoAwal());
        $draft->assertStatus(201);
        $headerId = $draft->json('data.id');

        $res = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/akuntansi/saldo-awal');
        $res->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => ['status' => 'posted'],
            ]);

        $jurnalId = $res->json('data.jurnal_id');
        $this->assertNotNull($jurnalId);
        $this->assertDatabaseHas('sikeu_jurnal_umum', [
            'id' => $jurnalId,
            'jenis_sumber' => 'penyesuaian',
            'referensi_id' => $headerId,
            'status_posting' => 'posted',
        ]);
        $this->assertStringStartsWith('JRN-OPEN', (string) $res->json('data.jurnal.nomor_jurnal'));

        // Selisih 80jt otomatis masuk kredit ekuitas saldo awal.
        $this->assertEquals(80000000, (float) DetailJurnalUmum::where('jurnal_id', $jurnalId)
            ->where('akun_id', $this->akunId('301.03'))
            ->sum('kredit'));

        $lap = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/akuntansi/laporan');
        $lap->assertStatus(200);
        $this->assertEquals(100000000, $lap->json('data.neraca.aset.total_aset'));
        $this->assertEquals(20000000, $lap->json('data.neraca.liabilitas.total_liabilitas'));
        $this->assertEquals(80000000, $lap->json('data.neraca.ekuitas.ekuitas_awal'));
        $this->assertEquals(
            $lap->json('data.neraca.aset.total_aset'),
            $lap->json('data.neraca.total_pasiva')
        );

        // Setelah posted, draft tidak boleh diubah tanpa pembatalan.
        $ubah = $this->withHeaders($this->headers())
            ->putJson('/api/v1/sikeu/akuntansi/saldo-awal', $this->payloadSaldoAwal());
        $ubah->assertStatus(400);
    }

    public function test_batal_posting_ditolak_bila_ada_jurnal_setelahnya(): void
    {
        $this->withHeaders($this->headers())
            ->putJson('/api/v1/sikeu/akuntansi/saldo-awal', $this->payloadSaldoAwal())
            ->assertStatus(201);
        $post = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/akuntansi/saldo-awal');
        $post->assertStatus(201);
        $jurnalSaldoAwal = JurnalUmum::findOrFail($post->json('data.jurnal_id'));

        $jurnalBaru = JurnalUmum::create([
            'nomor_jurnal' => 'JRN-TEST-SETELAH',
            'tanggal_jurnal' => $jurnalSaldoAwal->tanggal_jurnal->copy()->addDay()->toDateString(),
            'jenis_sumber' => 'penyesuaian',
            'keterangan' => 'Jurnal setelah saldo awal',
            'status_posting' => 'posted',
            'total_debet' => 1000,
            'total_kredit' => 1000,
        ]);

        $res = $this->withHeaders($this->headers())
            ->deleteJson('/api/v1/sikeu/akuntansi/saldo-awal');
        $res->assertStatus(400);
        $this->assertDatabaseHas('sikeu_jurnal_umum', ['id' => $jurnalSaldoAwal->id]);

        $jurnalBaru->delete();

        $ok = $this->withHeaders($this->headers())
            ->deleteJson('/api/v1/sikeu/akuntansi/saldo-awal');
        $ok->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => null,
            ]);

        $this->assertDatabaseMissing('sikeu_jurnal_umum', ['id' => $jurnalSaldoAwal->id]);
        $this->assertDatabaseHas('sikeu_saldo_awal', ['status' => 'draft', 'jurnal_id' => null]);
    }

    public function test_saldo_awal_ditolak_pada_periode_yang_sudah_ditutup(): void
    {
        PeriodeAkuntansi::create([
            'nama_periode' => 'Januari 2026',
            'tahun' => 2026,
            'bulan' => 1,
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-01-31',
            'status' => 'ditutup',
        ]);

        $res = $this->withHeaders($this->headers())
            ->putJson('/api/v1/sikeu/akuntansi/saldo-awal', $this->payloadSaldoAwal());

        $res->assertStatus(400);
        $this->assertDatabaseCount('sikeu_saldo_awal', 0);
    }

    public function test_akun_pendapatan_ditolak_karena_bukan_akun_neraca(): void
    {
        $payload = $this->payloadSaldoAwal();
        $payload['details'][] = [
            'akun_id' => $this->akunId('401.01'),
            'debet' => 0,
            'kredit' => 1000,
        ];

        $res = $this->withHeaders($this->headers())
            ->putJson('/api/v1/sikeu/akuntansi/saldo-awal', $payload);

        $res->assertStatus(422)
            ->assertJson(['status' => 'error'])
            ->assertJsonValidationErrors(['details.2.akun_id']);
        $this->assertDatabaseCount('sikeu_saldo_awal', 0);
    }

    public function test_posting_ditolak_bila_ada_jurnal_pada_atau_sebelum_tanggal_saldo_awal(): void
    {
        JurnalUmum::create([
            'nomor_jurnal' => 'JRN-LEGACY-SEBELUM',
            'tanggal_jurnal' => '2025-12-31',
            'jenis_sumber' => 'penyesuaian',
            'keterangan' => 'Jurnal legacy sebelum cut-off',
            'status_posting' => 'posted',
            'total_debet' => 1000,
            'total_kredit' => 1000,
        ]);

        $draft = $this->withHeaders($this->headers())
            ->putJson('/api/v1/sikeu/akuntansi/saldo-awal', $this->payloadSaldoAwal());
        $draft->assertStatus(201)->assertJsonPath('data.status', 'draft');

        $validasi = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/akuntansi/saldo-awal');
        $validasi->assertStatus(200)
            ->assertJsonPath('data.validasi.siap_posting', false)
            ->assertJsonPath('data.validasi.jumlah_jurnal_sebelum', 1)
            ->assertJsonPath('data.validasi.tanggal_jurnal_terawal', '2025-12-31');

        $post = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/akuntansi/saldo-awal');

        $post->assertStatus(400)->assertJson(['status' => 'error']);
        $this->assertStringContainsString('terhitung dua kali', (string) $post->json('message'));
        $this->assertDatabaseMissing('sikeu_jurnal_umum', [
            'referensi_id' => $draft->json('data.id'),
            'jenis_sumber' => 'penyesuaian',
        ]);
        $this->assertDatabaseHas('sikeu_saldo_awal', [
            'id' => $draft->json('data.id'),
            'status' => 'draft',
            'jurnal_id' => null,
        ]);
    }

    public function test_posting_diizinkan_bila_semua_jurnal_lain_setelah_tanggal_saldo_awal(): void
    {
        JurnalUmum::create([
            'nomor_jurnal' => 'JRN-SETELAH-CUTOFF',
            'tanggal_jurnal' => '2026-01-02',
            'jenis_sumber' => 'penyesuaian',
            'keterangan' => 'Jurnal setelah cut-off',
            'status_posting' => 'posted',
            'total_debet' => 1000,
            'total_kredit' => 1000,
        ]);

        $this->withHeaders($this->headers())
            ->putJson('/api/v1/sikeu/akuntansi/saldo-awal', $this->payloadSaldoAwal())
            ->assertStatus(201);

        $validasi = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/akuntansi/saldo-awal');
        $validasi->assertStatus(200)
            ->assertJsonPath('data.validasi.siap_posting', true)
            ->assertJsonPath('data.validasi.jumlah_jurnal_sebelum', 0);

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/akuntansi/saldo-awal')
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'posted');
    }

    public function test_guard_migrasi_menolak_tanggal_pada_atau_sebelum_cut_off(): void
    {
        $this->withHeaders($this->headers())
            ->putJson('/api/v1/sikeu/akuntansi/saldo-awal', $this->payloadSaldoAwal())
            ->assertStatus(201);
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/akuntansi/saldo-awal')
            ->assertStatus(201);

        $service = app(SaldoAwalService::class);
        $this->assertSame('2026-01-01', $service->tanggalSaldoAwalPosted());

        try {
            $service->assertTanggalBolehDicatat('2026-01-01');
            $this->fail('Tanggal pada cut-off seharusnya ditolak.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('sudah tercakup dalam saldo awal', $e->getMessage());
        }

        $service->assertTanggalBolehDicatat('2026-01-02');
        $this->addToAssertionCount(1);
    }

    public function test_audit_bersih_setelah_saldo_awal_diposting(): void
    {
        $this->withHeaders($this->headers())
            ->putJson('/api/v1/sikeu/akuntansi/saldo-awal', $this->payloadSaldoAwal())
            ->assertStatus(201);
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/akuntansi/saldo-awal')
            ->assertStatus(201);

        $this->artisan('sikeu:saldo-awal:audit', ['--json' => true])
            ->assertExitCode(0);
    }

    public function test_audit_menandai_jurnal_legacy_sebelum_cut_off(): void
    {
        $this->withHeaders($this->headers())
            ->putJson('/api/v1/sikeu/akuntansi/saldo-awal', $this->payloadSaldoAwal())
            ->assertStatus(201);
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/akuntansi/saldo-awal')
            ->assertStatus(201);

        // Simulasi migrasi legacy yang berjalan setelah saldo awal diposting.
        JurnalUmum::create([
            'nomor_jurnal' => 'JRN-LEGACY-TELAT',
            'tanggal_jurnal' => '2025-12-31',
            'jenis_sumber' => 'penyesuaian',
            'keterangan' => 'Jurnal legacy masuk setelah cut-off',
            'status_posting' => 'posted',
            'total_debet' => 500,
            'total_kredit' => 500,
        ]);

        $this->artisan('sikeu:saldo-awal:audit', ['--json' => true])
            ->assertExitCode(1);
    }
}
