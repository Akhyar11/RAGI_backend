<?php

namespace Tests\Feature;

use App\Models\Sikeu\DispensasiTagihan;
use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Siakad\Mahasiswa;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SikeuDispensasiTest extends TestCase
{
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
            ['email' => 'admin.dispensasi@kampus.ac.id'],
            ['username' => 'admindispensasi', 'password' => Hash::make('password123'), 'is_active' => true, 'is_verified' => true]
        );
    }

    protected function headers(): array
    {
        $result = $this->admin->createToken('dispensasi-token');

        return ['Authorization' => 'Bearer ' . ($result->plainTextToken ?? $result->accessToken)];
    }

    protected function makeTagihan(string $nim, float $total = 4000000): TagihanMahasiswa
    {
        $prodi = MasterProgramStudi::firstOrCreate(
            ['kode_prodi' => 'DISP-TEST'],
            ['nama' => 'Prodi Dispensasi Test', 'jenjang' => 'S1', 'is_active' => true]
        );

        $mhs = Mahasiswa::firstOrCreate(
            ['nim' => $nim],
            ['nama_lengkap' => 'Mhs ' . $nim, 'program_studi_id' => $prodi->id, 'angkatan' => 2026, 'status' => 'aktif']
        );

        return TagihanMahasiswa::create([
            'mahasiswa_id' => $mhs->id,
            'tipe_referensi' => 'mahasiswa',
            'tahun_akademik_id' => 1,
            'semester' => 1,
            'nomor_tagihan' => 'INV-DISP-' . $nim,
            'total_tagihan' => $total,
            'status' => 'belum_bayar',
            'jatuh_tempo' => date('Y-m-d', strtotime('+30 days')),
        ]);
    }

    public function test_store_dispensasi_mencatat_audit_log(): void
    {
        $tagihan = $this->makeTagihan('DISP2026001');

        $res = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sikeu/dispensasi', [
                'tagihan_id' => $tagihan->id,
                'tipe_dispensasi' => 'cicilan',
                'jumlah_cicilan' => 4,
                'nominal_per_cicilan' => 1000000,
                'alasan' => 'Orang tua terkena PHK, butuh skema cicilan',
            ]);

        $res->assertStatus(201)->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('sikeu_dispensasi_tagihan', [
            'tagihan_id' => $tagihan->id,
            'tipe_dispensasi' => 'cicilan',
        ]);
        $this->assertDatabaseHas('core_audit_logs', [
            'module' => 'SIKEU',
            'action' => 'create',
            'table_name' => 'sikeu_dispensasi_tagihan',
        ]);
    }

    public function test_destroy_dispensasi_ditolak_bila_sudah_ada_pembayaran_cicilan(): void
    {
        $tagihan = $this->makeTagihan('DISP2026002');

        $disp = DispensasiTagihan::create([
            'tagihan_id' => $tagihan->id,
            'mahasiswa_id' => $tagihan->mahasiswa_id,
            'tipe_dispensasi' => 'cicilan',
            'jumlah_cicilan' => 4,
            'nominal_per_cicilan' => 1000000,
            'alasan' => 'Cicilan test',
            'status' => 'approved',
            'tanggal_persetujuan' => now()->subDay(),
            'created_at' => now()->subDays(2),
        ]);

        // Pembayaran cicilan SETELAH skema disetujui
        Pembayaran::create([
            'tagihan_id' => $tagihan->id,
            'kode_transaksi' => 'KASIR-DISP-001',
            'jumlah_bayar' => 1000000,
            'waktu_bayar' => now(),
            'channel_bayar' => 'LOKET_TUNAI',
            'status' => 'success',
        ]);
        $tagihan->update(['total_bayar' => 1000000, 'status' => 'sebagian']);

        $res = $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/sikeu/dispensasi/{$disp->id}");

        $res->assertStatus(422)
            ->assertJson(['status' => 'error'])
            ->assertJsonPath('data.payment_count', 1);

        $this->assertDatabaseHas('sikeu_dispensasi_tagihan', ['id' => $disp->id]);
    }

    public function test_destroy_dispensasi_tetap_bisa_bila_bayar_sebelum_skema_ada(): void
    {
        $tagihan = $this->makeTagihan('DISP2026005');

        // Pembayaran biasa 3jt SEBELUM dispensasi diajukan: bukan cicilan
        Pembayaran::create([
            'tagihan_id' => $tagihan->id,
            'kode_transaksi' => 'KASIR-DISP-003',
            'jumlah_bayar' => 3000000,
            'waktu_bayar' => now()->subDays(5),
            'channel_bayar' => 'LOKET_TUNAI',
            'status' => 'success',
        ]);
        $tagihan->update(['total_bayar' => 3000000, 'status' => 'sebagian']);

        $disp = DispensasiTagihan::create([
            'tagihan_id' => $tagihan->id,
            'mahasiswa_id' => $tagihan->mahasiswa_id,
            'tipe_dispensasi' => 'cicilan',
            'jumlah_cicilan' => 4,
            'nominal_per_cicilan' => 1000000,
            'alasan' => 'Cicilan test hapus walau sudah bayar biasa',
            'status' => 'approved',
            'tanggal_persetujuan' => now()->subDay(),
            'created_at' => now()->subDays(2),
        ]);
        $tagihan->update(['status' => 'dispensasi']);

        $res = $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/sikeu/dispensasi/{$disp->id}");

        $res->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertDatabaseMissing('sikeu_dispensasi_tagihan', ['id' => $disp->id]);
        $this->assertDatabaseHas('sikeu_tagihan_mahasiswa', [
            'id' => $tagihan->id,
            'status' => 'sebagian',
        ]);
    }

    public function test_destroy_dispensasi_berhasil_bila_belum_ada_pembayaran(): void
    {
        $tagihan = $this->makeTagihan('DISP2026003');

        $disp = DispensasiTagihan::create([
            'tagihan_id' => $tagihan->id,
            'mahasiswa_id' => $tagihan->mahasiswa_id,
            'tipe_dispensasi' => 'cicilan',
            'jumlah_cicilan' => 2,
            'nominal_per_cicilan' => 2000000,
            'alasan' => 'Cicilan test hapus',
            'status' => 'pending',
        ]);
        $tagihan->update(['status' => 'dispensasi']);

        $res = $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/sikeu/dispensasi/{$disp->id}");

        $res->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertDatabaseMissing('sikeu_dispensasi_tagihan', ['id' => $disp->id]);
        $this->assertDatabaseHas('sikeu_tagihan_mahasiswa', [
            'id' => $tagihan->id,
            'status' => 'belum_bayar',
        ]);
        $this->assertDatabaseHas('core_audit_logs', [
            'module' => 'SIKEU',
            'action' => 'delete',
            'table_name' => 'sikeu_dispensasi_tagihan',
        ]);
    }

    public function test_validasi_publik_dispensasi(): void
    {
        $tagihan = $this->makeTagihan('DISP2026004');

        $disp = DispensasiTagihan::create([
            'tagihan_id' => $tagihan->id,
            'mahasiswa_id' => $tagihan->mahasiswa_id,
            'tipe_dispensasi' => 'cicilan',
            'jumlah_cicilan' => 4,
            'nominal_per_cicilan' => 1000000,
            'alasan' => 'Validasi test',
            'status' => 'approved',
            'signature_hash' => DispensasiTagihan::makeSignatureHash(999999, $tagihan->mahasiswa_id, date('Y-m-d')),
        ]);
        $hash = 'SIG-DISP-TEST-' . $disp->id;
        $disp->update(['signature_hash' => $hash]);

        $res = $this->getJson("/api/v1/sikeu/dispensasi/validasi/{$hash}");

        $res->assertStatus(200)
            ->assertJson(['status' => 'success'])
            ->assertJsonPath('data.is_valid', true)
            ->assertJsonPath('data.security_hash', $hash);

        $notFound = $this->getJson('/api/v1/sikeu/dispensasi/validasi/SIG-DISP-TIDAKADA');
        $notFound->assertStatus(404);
    }

    public function test_approve_dispensasi_membuat_memo_jurnal_tanpa_efek_saldo(): void
    {
        $tagihan = $this->makeTagihan('DISP2026006');

        $disp = DispensasiTagihan::create([
            'tagihan_id' => $tagihan->id,
            'mahasiswa_id' => $tagihan->mahasiswa_id,
            'tipe_dispensasi' => 'cicilan',
            'jumlah_cicilan' => 4,
            'nominal_per_cicilan' => 1000000,
            'alasan' => 'Memo test',
            'status' => 'pending',
        ]);

        $res = $this->withHeaders($this->headers())
            ->postJson("/api/v1/sikeu/approvals/dispensasi/{$disp->id}/approve", [
                'catatan' => 'Setujui memo test',
            ]);

        $res->assertStatus(200)->assertJson(['status' => 'success']);

        $memo = \App\Models\Sikeu\JurnalUmum::where('nomor_jurnal', 'like', 'JRN-DSP-%')
            ->where('referensi_id', $disp->id)
            ->first();
        $this->assertNotNull($memo);
        $this->assertEquals(0, (float) $memo->total_debet);
        $this->assertEquals(0, (float) $memo->total_kredit);
        $this->assertNotNull($disp->fresh()->signature_hash);
    }
}
