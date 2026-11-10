<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Sikeu\DetailTagihan;
use App\Models\Sikeu\MasterBiaya;
use App\Models\Sikeu\PaymentGatewayConfig;
use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\UnitKas;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MidtransIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected string $serverKey = 'test-midtrans-server-key';

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);

        config([
            'services.sikeu.midtrans.server_key' => $this->serverKey,
        ]);
    }

    public function test_midtrans_callback_fails_with_invalid_signature(): void
    {
        $payload = [
            'order_id'           => 'TAG-TEST-999',
            'status_code'        => '200',
            'gross_amount'       => '150000.00',
            'signature_key'      => 'invalidsignature123',
            'transaction_status' => 'settlement',
        ];

        $response = $this->postJson('/api/v1/sikeu/callback/midtrans', $payload);

        $response->assertStatus(400)
            ->assertJson([
                'status'  => 'error',
                'message' => 'Signature key tidak valid.',
            ]);
    }

    public function test_midtrans_callback_fails_when_tagihan_not_found(): void
    {
        $orderId = 'TAG-NONEXISTENT-' . uniqid();
        $statusCode = '200';
        $grossAmount = '500000.00';
        $signature = hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);

        $payload = [
            'order_id'           => $orderId,
            'status_code'        => $statusCode,
            'gross_amount'       => $grossAmount,
            'signature_key'      => $signature,
            'transaction_status' => 'settlement',
            'payment_type'       => 'gopay',
        ];

        $response = $this->postJson('/api/v1/sikeu/callback/midtrans', $payload);

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
            ]);
    }

    public function test_midtrans_settlement_workflow_updates_tagihan_and_kas(): void
    {
        // 1. Siapkan Unit Kas Midtrans
        $unitKas = UnitKas::firstOrCreate(
            ['kanal' => 'midtrans'],
            [
                'nama_kas'       => 'Midtrans Disbursement Kampus',
                'tipe_kas'       => 'operasional',
                'bank_name'      => 'Midtrans',
                'saldo_awal'     => 0,
                'saldo_saat_ini' => 0,
                'status'         => true,
            ]
        );
        $initialSaldo = (float) $unitKas->saldo_saat_ini;

        // 2. Siapkan Tagihan Mahasiswa
        $nominal = 750000;
        $orderId = 'TAG-MID-' . rand(10000, 99999);

        $tagihan = TagihanMahasiswa::create([
            'nomor_tagihan'  => $orderId,
            'total_tagihan'  => $nominal,
            'total_bayar'    => 0,
            'total_potongan' => 0,
            'total_denda'    => 0,
            'status'         => 'belum_bayar',
            'source_system'  => 'SIAKAD',
        ]);

        $masterBiaya = MasterBiaya::firstOrCreate(
            ['kode' => 'SPP'],
            [
                'nama'             => 'SPP Kuliah',
                'tipe'             => 'spp',
                'nominal_standar'  => $nominal,
                'is_active'        => true,
            ]
        );

        DetailTagihan::create([
            'tagihan_id'      => $tagihan->id,
            'master_biaya_id' => $masterBiaya->id,
            'nominal'         => $nominal,
            'terbayar'        => 0,
            'keterangan'      => 'SPP Semester Gasal',
        ]);

        // 3. Hitung Signature SHA512 Midtrans
        $statusCode = '200';
        $grossAmountFormatted = number_format($nominal, 2, '.', '');
        $signature = hash('sha512', $orderId . $statusCode . $grossAmountFormatted . $this->serverKey);
        $trxId = 'MID-TRX-' . uniqid();

        $payload = [
            'order_id'           => $orderId,
            'status_code'        => $statusCode,
            'gross_amount'       => $grossAmountFormatted,
            'signature_key'      => $signature,
            'transaction_status' => 'settlement',
            'payment_type'       => 'gopay',
            'fraud_status'       => 'accept',
            'transaction_id'     => $trxId,
        ];

        // 4. Kirim Webhook Callback
        $response = $this->postJson('/api/v1/sikeu/callback/midtrans', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Pembayaran Midtrans berhasil diproses.',
            ]);

        // 5. Verifikasi Tagihan Menjadi Lunas
        $tagihanFresh = $tagihan->fresh();
        $this->assertEquals('lunas', $tagihanFresh->status);
        $this->assertEquals($nominal, (float) $tagihanFresh->total_bayar);

        // 6. Verifikasi Record Pembayaran
        $this->assertDatabaseHas('sikeu_pembayaran', [
            'tagihan_id'     => $tagihan->id,
            'kode_transaksi' => $trxId,
            'channel_bayar'  => 'MIDTRANS_GOPAY',
            'status'         => 'success',
        ]);

        // 7. Verifikasi Saldo Kas Bertambah
        $unitKasFresh = $unitKas->fresh();
        $this->assertEquals($initialSaldo + $nominal, (float) $unitKasFresh->saldo_saat_ini);

        // 8. Idempotensi Check: Callback ulang tidak menduplikasi pembayaran
        $responseIdempotent = $this->postJson('/api/v1/sikeu/callback/midtrans', $payload);
        $responseIdempotent->assertStatus(200);
        $this->assertEquals(1, Pembayaran::where('kode_transaksi', $trxId)->count());
    }

    public function test_midtrans_super_admin_trial_payment_endpoint(): void
    {
        // 1. Buat User Admin dengan permission 'sikeu.paymentgateway.manage'
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(
            ['slug' => 'sikeu.paymentgateway.manage'],
            [
                'name'        => 'Kelola Payment Gateway',
                'module'      => 'sikeu',
                'action'      => 'update',
                'description' => 'Mengatur koneksi payment gateway',
            ]
        );
        $role = Role::firstOrCreate(
            ['slug' => 'admin_keuangan_test'],
            ['name' => 'Admin Keuangan Test', 'module' => 'sikeu']
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        // 2. Siapkan Tagihan Mahasiswa untuk Trial
        $nominal = 500000;
        $tagihan = TagihanMahasiswa::create([
            'nomor_tagihan'  => 'TAG-TRIAL-' . uniqid(),
            'total_tagihan'  => $nominal,
            'total_bayar'    => 0,
            'total_potongan' => 0,
            'total_denda'    => 0,
            'status'         => 'belum_bayar',
            'source_system'  => 'SIAKAD',
        ]);

        // 3. Eksekusi Trial Pembayaran sebagai Super Admin
        Passport::actingAs($user);
        $response = $this->postJson('/api/v1/sikeu/payment-gateway/midtrans/trial', [
                'tagihan_id'   => $tagihan->id,
                'nominal'      => $nominal,
                'payment_type' => 'qris',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Trial / Simulasi Pembayaran Midtrans Berhasil Dieksekusi.',
                'data'    => [
                    'status_tagihan_akhir' => 'lunas',
                    'channel_bayar'        => 'MIDTRANS_QRIS',
                ],
            ]);

        // 4. Verifikasi Tagihan Berubah Lunas
        $this->assertEquals('lunas', $tagihan->fresh()->status);
    }

    public function test_midtrans_create_snap_token_success(): void
    {
        // 1. Fake Midtrans Snap API HTTP Response
        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token'        => 'mock-snap-token-xyz123',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/mock-snap-token-xyz123',
            ], 201),
        ]);

        // 2. User berwenang
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(
            ['slug' => 'sikeu.tagihan.read'],
            [
                'name'        => 'Lihat Tagihan',
                'module'      => 'sikeu',
                'action'      => 'read',
                'description' => 'Melihat tagihan mahasiswa',
            ]
        );
        $role = Role::firstOrCreate(
            ['slug' => 'staff_keuangan_snap_test'],
            ['name' => 'Staff Keuangan Snap Test', 'module' => 'sikeu']
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        // 3. Tagihan Mahasiswa Belum Lunas
        $nominal = 1250000;
        $tagihan = TagihanMahasiswa::create([
            'nomor_tagihan'  => 'TAG-SNAP-' . uniqid(),
            'total_tagihan'  => $nominal,
            'total_bayar'    => 0,
            'total_potongan' => 0,
            'total_denda'    => 0,
            'status'         => 'belum_bayar',
            'source_system'  => 'SIAKAD',
        ]);

        // 4. Request Snap Token
        Passport::actingAs($user);
        $response = $this->postJson('/api/v1/sikeu/midtrans/snap-token', [
                'tagihan_id' => $tagihan->id,
                'first_name' => 'Ahmad Fulan',
                'email'      => 'fulan@kampus.ac.id',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Token Midtrans Snap berhasil diterbitkan.',
                'data'    => [
                    'token'        => 'mock-snap-token-xyz123',
                    'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/mock-snap-token-xyz123',
                    'gross_amount' => $nominal,
                    'environment'  => 'sandbox',
                ],
            ]);
    }

    public function test_midtrans_create_snap_token_fails_when_tagihan_already_paid(): void
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(
            ['slug' => 'sikeu.tagihan.read'],
            ['name' => 'Lihat Tagihan', 'module' => 'sikeu', 'action' => 'read', 'description' => 'Melihat tagihan']
        );
        $role = Role::firstOrCreate(
            ['slug' => 'staff_keuangan_snap_test2'],
            ['name' => 'Staff Keuangan Snap Test 2', 'module' => 'sikeu']
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        $tagihan = TagihanMahasiswa::create([
            'nomor_tagihan'  => 'TAG-PAID-' . uniqid(),
            'total_tagihan'  => 500000,
            'total_bayar'    => 500000,
            'total_potongan' => 0,
            'total_denda'    => 0,
            'status'         => 'lunas',
            'source_system'  => 'SIAKAD',
        ]);

        Passport::actingAs($user);
        $response = $this->postJson('/api/v1/sikeu/midtrans/snap-token', [
                'tagihan_id' => $tagihan->id,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'status'  => 'error',
                'message' => 'Tagihan ini sudah lunas.',
            ]);
    }

    public function test_midtrans_create_snap_token_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(
            ['slug' => 'user_biasa_test'],
            ['name' => 'User Biasa Test', 'module' => 'umum']
        );
        $user->roles()->syncWithoutDetaching([$role->id]);

        $tagihan = TagihanMahasiswa::create([
            'nomor_tagihan'  => 'TAG-FORBIDDEN-' . uniqid(),
            'total_tagihan'  => 500000,
            'total_bayar'    => 0,
            'total_potongan' => 0,
            'total_denda'    => 0,
            'status'         => 'belum_bayar',
            'source_system'  => 'SIAKAD',
        ]);

        Passport::actingAs($user);
        $response = $this->postJson('/api/v1/sikeu/midtrans/snap-token', [
                'tagihan_id' => $tagihan->id,
            ]);

        $response->assertStatus(403);
    }
}
