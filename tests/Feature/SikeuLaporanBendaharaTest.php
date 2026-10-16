<?php

namespace Tests\Feature;

use App\Models\Siakad\Mahasiswa;
use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\UnitKas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SikeuLaporanBendaharaTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected UnitKas $unitKas;
    protected Mahasiswa $mahasiswa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\Sikeu\SikeuAkuntansiSeeder::class);

        if (\Laravel\Passport\Client::where('personal_access_client', 1)->doesntExist()) {
            app(\Laravel\Passport\ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access Client');
        }

        $this->admin = User::firstOrCreate(
            ['email' => 'admin.lapbend@kampus.ac.id'],
            ['username' => 'lapbendadmin', 'password' => Hash::make('password123'), 'is_active' => true, 'is_verified' => true]
        );

        $this->unitKas = UnitKas::create([
            'nama_kas' => 'Kas Bendahara Test',
            'tipe_kas' => 'tunai',
            'saldo_awal' => 5000000,
            'saldo_saat_ini' => 5000000,
            'status' => true,
        ]);

        $prodi = \App\Models\Siakad\ProgramStudi::create([
            'kode_prodi' => 'LAP' . rand(100, 999),
            'nama' => 'Prodi Laporan Test',
            'jenjang' => 'D3',
            'is_active' => true,
        ]);

        $this->mahasiswa = Mahasiswa::create([
            'program_studi_id' => $prodi->id,
            'nim' => 'LAP' . rand(10000, 99999),
            'nama_lengkap' => 'Mhs Laporan',
            'email' => 'mhslaporan@kampus.ac.id',
            'angkatan' => 2024,
            'status' => 'aktif',
        ]);
    }

    protected function headers(): array
    {
        $result = $this->admin->createToken('lapbend-token');
        $token = $result->plainTextToken ?? $result->accessToken;

        return ['Authorization' => 'Bearer ' . $token];
    }

    protected function buatTagihanLunas(float $total = 2000000): TagihanMahasiswa
    {
        $tagihan = TagihanMahasiswa::create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => 1,
            'nomor_tagihan' => 'INV-LAP-' . uniqid(),
            'total_tagihan' => $total,
            'total_potongan' => 0,
            'total_denda' => 0,
            'total_bayar' => 0,
            'status' => 'belum_bayar',
            'jatuh_tempo' => now()->addDays(30),
        ]);

        Pembayaran::create([
            'tagihan_id' => $tagihan->id,
            'unit_kas_id' => $this->unitKas->id,
            'kode_transaksi' => 'TRX-LAP-' . uniqid(),
            'jumlah_bayar' => $total,
            'waktu_bayar' => now(),
            'channel_bayar' => 'LOKET_TUNAI',
            'status' => 'success',
        ]);
        $tagihan->update(['total_bayar' => $total, 'status' => 'lunas']);

        return $tagihan->fresh();
    }

    protected function tutupBulanLalu(): void
    {
        $akhirBulanLalu = now()->firstOfMonth()->subDay()->toDateString();
        $this->withHeaders($this->headers())->postJson('/api/v1/sikeu/tutup-kas', [
            'unit_kas_id' => $this->unitKas->id,
            'tanggal' => $akhirBulanLalu,
            'saldo_awal' => 1000000,
            'saldo_akhir' => 1000000,
        ])->assertStatus(201);
    }

    public function test_saldo_harian_dan_bulanan()
    {
        $this->buatTagihanLunas(2000000);

        $res = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/laporan-bendahara/saldo-harian?tanggal=' . now()->toDateString());
        $res->assertStatus(200)->assertJsonPath('status', 'success');
        $res->assertJsonStructure(['status', 'data' => ['tanggal', 'saldo_masuk', 'saldo_keluar', 'saldo_bersih', 'rincian']]);
        $this->assertEquals(2000000, $res->json('data.saldo_masuk'));

        $res = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/laporan-bendahara/saldo-bulanan?tanggal=' . now()->format('Y-m'));
        $res->assertStatus(200)->assertJsonPath('status', 'success');
    }

    public function test_harian_wajib_tutup_bulan_lalu_untuk_tunai()
    {
        // Tanpa tutup bulan lalu -> 422 untuk kas tunai per unit
        $res = $this->withHeaders($this->headers())->getJson(
            '/api/v1/sikeu/laporan-bendahara/harian?tanggal=' . now()->toDateString() . '&unit_kas_id=' . $this->unitKas->id
        );
        $res->assertStatus(422)->assertJsonPath('status', 'error');

        $this->tutupBulanLalu();
        $this->buatTagihanLunas(2000000);

        $res = $this->withHeaders($this->headers())->getJson(
            '/api/v1/sikeu/laporan-bendahara/harian?tanggal=' . now()->toDateString() . '&unit_kas_id=' . $this->unitKas->id
        );
        $res->assertStatus(200);
        $res->assertJsonStructure(['status', 'data' => ['tanggal', 'saldo_awal', 'saldo_akhir', 'umk_aktif', 'transaksi'], 'meta']);
        $this->assertEquals(1000000, $res->json('data.saldo_awal'));
    }

    public function test_periodik_perakun_dan_rekap()
    {
        $this->buatTagihanLunas(2000000);
        $awal = now()->toDateString();

        $res = $this->withHeaders($this->headers())
            ->getJson("/api/v1/sikeu/laporan-bendahara/periodik?awal={$awal}&akhir={$awal}");
        $res->assertStatus(200)->assertJsonStructure(['status', 'data', 'meta']);
        $this->assertGreaterThanOrEqual(1, $res->json('meta.total'));

        $res = $this->withHeaders($this->headers())
            ->getJson("/api/v1/sikeu/laporan-bendahara/perakun?awal={$awal}&akhir={$awal}");
        $res->assertStatus(200)->assertJsonPath('status', 'success');

        $res = $this->withHeaders($this->headers())
            ->getJson('/api/v1/sikeu/laporan-bendahara/rekap-mahasiswa?mahasiswa_id=' . $this->mahasiswa->id);
        $res->assertStatus(200);
        $this->assertEquals(0, $res->json('data.ringkasan.total_kekurangan'));

        $res = $this->withHeaders($this->headers())->postJson('/api/v1/sikeu/laporan-bendahara/rekap-perprodikelas', [
            'data' => [['mahasiswa_id' => $this->mahasiswa->id]],
        ]);
        $res->assertStatus(200);
        $res->assertJsonStructure(['status', 'data', 'header', 'ringkasan']);
        $this->assertEquals(1, $res->json('ringkasan.mahasiswa'));
    }

    public function test_export_excel_mencatat_audit()
    {
        $awal = now()->toDateString();
        $res = $this->withHeaders($this->headers())
            ->getJson("/api/v1/sikeu/laporan-bendahara/export-excel?jenis=harian&tanggal={$awal}");
        $res->assertStatus(200);
        $this->assertDatabaseHas('core_audit_logs', ['module' => 'SIKEU', 'action' => 'export']);
    }

    public function test_periodik_mendukung_sort_jumlah_desc()
    {
        $this->buatTagihanLunas(2000000);
        $this->buatTagihanLunas(500000);
        $awal = now()->toDateString();

        $res = $this->withHeaders($this->headers())
            ->getJson("/api/v1/sikeu/laporan-bendahara/periodik?awal={$awal}&akhir={$awal}&sort_by=jumlah&sort_order=desc");
        $res->assertStatus(200);
        $jumlah = collect($res->json('data'))->pluck('jumlah')->all();
        $this->assertGreaterThanOrEqual(2, count($jumlah));
        $this->assertEquals(2000000, (float) $jumlah[0]);

        $res = $this->withHeaders($this->headers())
            ->getJson("/api/v1/sikeu/laporan-bendahara/periodik?awal={$awal}&akhir={$awal}&sort_by=salah");
        $res->assertStatus(422);
    }
}
