<?php

namespace Tests\Feature;

use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\PemasukanKampus;
use App\Models\Sikeu\PengeluaranKampus;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\UnitKas;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SikeuDashboardTest extends TestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);

        if (\Laravel\Passport\Client::where('personal_access_client', 1)->doesntExist()) {
            app(\Laravel\Passport\ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access Client');
        }

        $this->admin = User::firstOrCreate(
            ['email' => 'admin.sikeudash@kampus.ac.id'],
            ['username' => 'sikeudashadmin', 'password' => Hash::make('password123'), 'is_active' => true, 'is_verified' => true]
        );
    }

    protected function adminToken(): string
    {
        $result = $this->admin->createToken('sikeu-dash-token');

        return $result->plainTextToken ?? $result->accessToken;
    }

    protected function headers(): array
    {
        return ['Authorization' => 'Bearer ' . $this->adminToken()];
    }

    public function test_get_dashboard_summary_without_date_filter(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/dashboard-summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'filter' => ['start_date', 'end_date', 'has_filter'],
                    'metrics' => [
                        'total_penerimaan',
                        'penerimaan_mahasiswa',
                        'penerimaan_eksternal',
                        'total_pengeluaran',
                        'saldo_kas_utama',
                        'saldo_total_kas',
                        'total_piutang_mahasiswa',
                        'pajak_terutang',
                    ],
                    'payment_gateway',
                    'unit_kas',
                    'recent_jurnals',
                ],
            ]);

        $this->assertFalse($response->json('data.filter.has_filter'));
    }

    public function test_get_dashboard_summary_with_date_filter(): void
    {
        $today = now()->format('Y-m-d');

        // Pastikan ada akun keuangan & unit kas
        $unitKas = UnitKas::firstOrCreate(
            ['nama_kas' => 'Kas Operasional Kampus Test'],
            ['kode_kas' => 'KAS-TEST-01', 'saldo_saat_ini' => 10000000, 'status' => true]
        );

        $akunKas = AkunKeuangan::firstOrCreate(
            ['kode_akun' => '101.99'],
            ['nama_akun' => 'Kas Test', 'kelompok' => 'aset', 'saldo_normal' => 'debet']
        );

        $akunPendapatan = AkunKeuangan::firstOrCreate(
            ['kode_akun' => '401.99'],
            ['nama_akun' => 'Pendapatan Hibah Test', 'kelompok' => 'pendapatan', 'saldo_normal' => 'kredit']
        );

        // Buat Pemasukan pada hari ini
        PemasukanKampus::create([
            'nomor_transaksi' => 'TRX-IN-TEST-' . uniqid(),
            'sumber_pemasukan' => 'hibah_sippm',
            'unit_kas_id' => $unitKas->id,
            'akun_pendapatan_id' => $akunPendapatan->id,
            'nominal' => 2500000,
            'tanggal_terima' => $today,
            'nama_donor_instansi' => 'PT Mitra Riset',
        ]);

        // Buat Jurnal pada hari ini
        JurnalUmum::create([
            'nomor_jurnal' => 'JRN-TEST-' . uniqid(),
            'tanggal_jurnal' => $today,
            'jenis_sumber' => 'pemasukan_hibah',
            'keterangan' => 'Test Jurnal Hari Ini',
            'status_posting' => 'posted',
            'total_debet' => 2500000,
            'total_kredit' => 2500000,
        ]);

        $response = $this->withHeaders($this->headers())
            ->getJson("/api/v1/sikeu/dashboard-summary?start_date={$today}&end_date={$today}");

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.filter.has_filter'));
        $this->assertEquals($today, $response->json('data.filter.start_date'));
        $this->assertEquals($today, $response->json('data.filter.end_date'));
        $this->assertGreaterThanOrEqual(2500000, (float)$response->json('data.metrics.penerimaan_eksternal'));
        $this->assertNotEmpty($response->json('data.recent_jurnals'));
    }
}
