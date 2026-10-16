<?php

namespace Tests\Feature;

use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\TutupKasUnit;
use App\Models\Sikeu\UangMukaKerja;
use App\Models\Sikeu\UnitKas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SikeuUmkTutupKasTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected UnitKas $unitKas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\Sikeu\SikeuAkuntansiSeeder::class);

        if (\Laravel\Passport\Client::where('personal_access_client', 1)->doesntExist()) {
            app(\Laravel\Passport\ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access Client');
        }

        $this->admin = User::firstOrCreate(
            ['email' => 'admin.umk@kampus.ac.id'],
            ['username' => 'umkadmin', 'password' => Hash::make('password123'), 'is_active' => true, 'is_verified' => true]
        );

        $this->unitKas = UnitKas::create([
            'nama_kas' => 'Kas Utama Test',
            'tipe_kas' => 'tunai',
            'saldo_awal' => 10000000,
            'saldo_saat_ini' => 10000000,
            'status' => true,
        ]);
    }

    protected function headers(): array
    {
        $result = $this->admin->createToken('umk-token');
        $token = $result->plainTextToken ?? $result->accessToken;

        return ['Authorization' => 'Bearer ' . $token];
    }

    public function test_umk_crud_dan_status()
    {
        $res = $this->withHeaders($this->headers())->postJson('/api/v1/sikeu/umk', [
            'unit_kas_id' => $this->unitKas->id,
            'nama_penerima' => 'Bendahara Fakultas',
            'tanggal' => now()->toDateString(),
            'keterangan' => 'Panjar seminar',
            'total' => 1500000,
        ]);
        $res->assertStatus(201)->assertJsonPath('status', 'success');
        $id = $res->json('data.id');
        $this->assertDatabaseHas('sikeu_umk', ['id' => $id, 'status' => 'aktif']);

        $res = $this->withHeaders($this->headers())->getJson('/api/v1/sikeu/umk?search=Bendahara');
        $res->assertStatus(200)->assertJsonStructure(['status', 'data', 'meta']);

        $res = $this->withHeaders($this->headers())->postJson("/api/v1/sikeu/umk/{$id}/status", [
            'status' => 'selesai',
            'tanggal_selesai' => now()->toDateString(),
        ]);
        $res->assertStatus(200)->assertJsonPath('data.status', 'selesai');

        // UMK selesai tidak boleh diubah/dihapus
        $res = $this->withHeaders($this->headers())->putJson("/api/v1/sikeu/umk/{$id}", ['nama_penerima' => 'Diubah']);
        $res->assertStatus(422);
        $res = $this->withHeaders($this->headers())->deleteJson("/api/v1/sikeu/umk/{$id}");
        $res->assertStatus(422);
    }

    public function test_tutup_kas_mengunci_kasir_dan_umk()
    {
        $tagihan = TagihanMahasiswa::create([
            'tahun_akademik_id' => 1,
            'nomor_tagihan' => 'INV-TUTUP-' . uniqid(),
            'total_tagihan' => 1000000,
            'total_potongan' => 0,
            'total_denda' => 0,
            'total_bayar' => 0,
            'status' => 'belum_bayar',
            'jatuh_tempo' => now()->addDays(30),
        ]);

        $res = $this->withHeaders($this->headers())->postJson('/api/v1/sikeu/tutup-kas', [
            'unit_kas_id' => $this->unitKas->id,
            'tanggal' => now()->toDateString(),
            'saldo_awal' => 10000000,
            'saldo_akhir' => 10000000,
        ]);
        $res->assertStatus(201)->assertJsonPath('status', 'success');
        $tutupId = $res->json('data.id');
        $this->assertDatabaseHas('sikeu_tutup_kas', ['id' => $tutupId, 'is_locked' => true]);

        // Kasir ke unit terkunci ditolak
        $res = $this->withHeaders($this->headers())->postJson('/api/v1/sikeu/pembayaran/kasir', [
            'tagihan_id' => $tagihan->id,
            'jumlah_bayar' => 500000,
            'channel_bayar' => 'LOKET_TUNAI',
            'unit_kas_id' => $this->unitKas->id,
        ]);
        $res->assertStatus(403);

        // UMK mundur ke unit terkunci ditolak
        $res = $this->withHeaders($this->headers())->postJson('/api/v1/sikeu/umk', [
            'unit_kas_id' => $this->unitKas->id,
            'nama_penerima' => 'Penerima Terkunci',
            'tanggal' => now()->toDateString(),
            'total' => 100000,
        ]);
        $res->assertStatus(422);

        // Buka kunci: kasir jalan lagi
        $res = $this->withHeaders($this->headers())->deleteJson("/api/v1/sikeu/tutup-kas/{$tutupId}");
        $res->assertStatus(200);
        $this->assertDatabaseMissing('sikeu_tutup_kas', ['id' => $tutupId]);

        $res = $this->withHeaders($this->headers())->postJson('/api/v1/sikeu/pembayaran/kasir', [
            'tagihan_id' => $tagihan->id,
            'jumlah_bayar' => 500000,
            'channel_bayar' => 'LOKET_TUNAI',
            'unit_kas_id' => $this->unitKas->id,
        ]);
        $res->assertStatus(201);
    }

    public function test_umk_list_mendukung_filter_status()
    {
        UangMukaKerja::create([
            'unit_kas_id' => $this->unitKas->id,
            'nama_penerima' => 'Aktif Satu',
            'tanggal' => now()->toDateString(),
            'total' => 100000,
            'status' => 'aktif',
        ]);
        UangMukaKerja::create([
            'unit_kas_id' => $this->unitKas->id,
            'nama_penerima' => 'Selesai Satu',
            'tanggal' => now()->toDateString(),
            'tanggal_selesai' => now()->toDateString(),
            'total' => 200000,
            'status' => 'selesai',
        ]);

        $res = $this->withHeaders($this->headers())->getJson('/api/v1/sikeu/umk?status=selesai&sort_by=tanggal&sort_order=desc');
        $res->assertStatus(200);
        $this->assertEquals(1, $res->json('meta.total'));
        $this->assertDatabaseHas('sikeu_umk', ['nama_penerima' => 'Aktif Satu']);

        $tutup = TutupKasUnit::where('unit_kas_id', $this->unitKas->id)->count();
        $this->assertEquals(0, $tutup);
    }
}
