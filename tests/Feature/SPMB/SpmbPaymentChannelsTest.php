<?php

namespace Tests\Feature\SPMB;

use App\Events\Spmb\MahasiswaDiterima;
use App\Models\CoreNotification;
use App\Models\MasterTipeJalur;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Siakad\ProgramStudi;
use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\PaymentGatewayConfig;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\UnitKas;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Spmb\HasilSeleksi;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\MasterBiaya;
use App\Models\Spmb\MasterKomponenBiaya;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\User;
use App\Services\Sikeu\BsnH2hService;
use App\Services\Spmb\SpmbBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SpmbPaymentChannelsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private PendaftaranCalonMhs $application;

    private PaymentGatewayConfig $midtrans;

    private PaymentGatewayConfig $xendit;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Event::fake([MahasiswaDiterima::class]);
        Http::preventStrayRequests();
        Permission::firstOrCreate(['slug' => 'spmb.manage'], ['name' => 'Kelola SPMB', 'module' => 'spmb', 'action' => 'update']);
        User::factory()->create();
        $this->owner = User::factory()->create();
        $jalur = JalurMasuk::create(['kode' => fake()->unique()->lexify('J????'), 'nama' => fake()->sentence(2)]);
        $wave = GelombangPenerimaan::create(['jalur_masuk_id' => $jalur->id, 'nama' => fake()->sentence(2), 'tanggal_buka' => '01-01', 'tanggal_tutup' => '12-31', 'status' => 'aktif']);
        $prodi = ProgramStudi::create(['kode_prodi' => fake()->unique()->lexify('P????'), 'nama' => fake()->sentence(2), 'jenjang' => 'S1']);
        $this->application = PendaftaranCalonMhs::create(['user_id' => $this->owner->id, 'gelombang_id' => $wave->id, 'program_studi_id' => $prodi->id,
            'no_pendaftaran' => fake()->unique()->numerify('PG-#####'), 'nama_lengkap' => fake()->name(), 'email' => $this->owner->email,
            'status' => PendaftaranCalonMhs::STATUS_DRAFT, 'status_pembayaran' => 'belum_bayar']);
        $this->midtrans = $this->gateway(PaymentGatewayConfig::MIDTRANS);
        $this->xendit = $this->gateway(PaymentGatewayConfig::XENDIT);
        $cash = AkunKeuangan::firstOrCreate(['kode_akun' => '102.01'], ['nama_akun' => fake()->sentence(2), 'kelompok' => 'aset', 'saldo_normal' => 'debet', 'is_active' => true]);
        foreach (['midtrans', 'xendit', 'bank_h2h'] as $channel) {
            UnitKas::updateOrCreate(['kanal' => $channel], ['nama_kas' => fake()->sentence(2), 'tipe_kas' => 'bank', 'akun_keuangan_id' => $cash->id, 'saldo_saat_ini' => 0, 'status' => true]);
        }
        Passport::actingAs($this->owner);
    }

    public function test_options_require_management_permission_and_never_expose_credentials(): void
    {
        $this->getJson('/api/spmb/master/payment-gateway-options')->assertForbidden();
        $this->grant('spmb.manage');
        $response = $this->getJson('/api/spmb/master/payment-gateway-options?per_page=1&search=midtrans&sort_by=gateway_name&sort_order=asc')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $this->midtrans->id)
            ->assertJsonStructure(['status', 'message', 'data', 'meta'])
            ->assertJsonMissingPath('filters');
        $this->assertArrayNotHasKey('api_key_encrypted', $response->json('data.0'));
        $this->assertArrayNotHasKey('webhook_token_encrypted', $response->json('data.0'));
        $this->midtrans->update(['is_active' => false]);
        $this->getJson('/api/spmb/master/payment-gateway-options?search=midtrans')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_component_mapping_can_be_created_read_updated_and_cleared(): void
    {
        $this->grant('spmb.manage');
        $payload = ['kode' => fake()->unique()->lexify('B????'), 'nama' => fake()->sentence(2), 'payment_gateway_config_id' => $this->midtrans->id];
        $id = $this->postJson('/api/spmb/master/komponen-biaya', $payload)->assertCreated()->json('data.id');
        $this->assertDatabaseHas('spmb_master_komponen_biaya', ['id' => $id, 'payment_gateway_config_id' => $this->midtrans->id]);
        $this->getJson('/api/spmb/master/komponen-biaya/'.$id)->assertOk()->assertJsonPath('data.payment_gateway.gateway_name', PaymentGatewayConfig::MIDTRANS)
            ->assertJsonMissingPath('data.payment_gateway.api_key_encrypted');
        $payload['payment_gateway_config_id'] = null;
        $this->putJson('/api/spmb/master/komponen-biaya/'.$id, $payload)->assertOk();
        $this->assertDatabaseHas('spmb_master_komponen_biaya', ['id' => $id, 'payment_gateway_config_id' => null]);
        $this->xendit->update(['is_active' => false]);
        $payload['payment_gateway_config_id'] = $this->xendit->id;
        $this->putJson('/api/spmb/master/komponen-biaya/'.$id, $payload)->assertUnprocessable();
        $this->deleteJson('/api/spmb/master/komponen-biaya/'.$id)->assertOk();
        $this->assertSoftDeleted('spmb_master_komponen_biaya', ['id' => $id]);
    }

    public function test_bills_group_channels_allocate_discounts_once_and_keep_snapshot_on_retry(): void
    {
        $details = [$this->detail($this->midtrans, 100000, 1), $this->detail($this->xendit, 200000, 2)];
        $issued = $this->issue($details, [['komponen_biaya_id' => 2, 'nominal_potongan' => 50000], ['nominal_potongan' => 25000]]);
        $this->assertCount(2, $issued['tagihan_list']);
        $this->assertDatabaseHas('sikeu_tagihan_mahasiswa', ['payment_gateway_config_id' => $this->midtrans->id, 'total_potongan' => 10000]);
        $this->assertDatabaseHas('sikeu_tagihan_mahasiswa', ['payment_gateway_config_id' => $this->xendit->id, 'total_potongan' => 65000]);
        $this->assertDatabaseCount('sikeu_virtual_account', 0);
        $this->midtrans->update(['is_active' => false]);
        $retry = $this->issue([$this->detail($this->xendit, 999999, 1)]);
        $this->assertCount(2, $retry['tagihan_list']);
        $this->assertSame($issued['tagihan']->id, $retry['tagihan']->id);
        $this->assertSame('midtrans', $retry['tagihan']->payment_gateway_name);
        Http::assertNothingSent();
    }

    public function test_same_gateway_combines_components_and_inactive_mapping_rolls_back_all_bills(): void
    {
        $this->xendit->update(['is_active' => false]);
        try {
            $this->issue([$this->detail($this->midtrans, 100000), $this->detail($this->xendit, 200000)]);
            $this->fail('Inactive mapping must fail.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('sikeu_tagihan_mahasiswa', 0);
        }
        $issued = $this->issue([$this->detail($this->midtrans, 100000), $this->detail($this->midtrans, 200000)]);
        $this->assertCount(1, $issued['tagihan_list']);
        $this->assertEquals(300000, $issued['tagihan']->total_tagihan);
    }

    public function test_private_payment_list_and_checkout_cannot_access_another_applicant(): void
    {
        $issued = $this->issue([$this->detail($this->midtrans, 100000)]);
        $this->getJson('/api/spmb/pembayaran?tahap=pendaftaran&per_page=1')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.gateway_name', 'midtrans')->assertJsonMissingPath('data.0.api_key_encrypted');
        Passport::actingAs(User::factory()->create());
        $this->getJson('/api/spmb/pembayaran')->assertForbidden();
        $this->postJson('/api/spmb/pembayaran/'.$issued['tagihan']->id.'/checkout')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_midtrans_checkout_reuses_order_and_verified_callback_is_idempotent(): void
    {
        $bill = $this->issue([$this->detail($this->midtrans, 100000)])['tagihan'];
        $kas = UnitKas::where('kanal', 'midtrans')->firstOrFail();
        $this->fakeMidtrans();
        $checkout = $this->postJson('/api/spmb/pembayaran/'.$bill->id.'/checkout')->assertOk()->assertJsonPath('data.method', 'redirect')->json('data');
        $this->postJson('/api/spmb/pembayaran/'.$bill->id.'/checkout')->assertOk()->assertJsonPath('data.order_id', $checkout['order_id']);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('sikeu_payment_checkouts', 1);
        Http::assertSent(fn ($request) => $request['transaction_details']['gross_amount'] === 100000
            && str_ends_with($request['callbacks']['finish'], '/spmb/pembayaran?tahap=pendaftaran'));
        $payload = $this->midtransPayload($checkout['order_id'], 100000);
        $this->postJson('/api/v1/sikeu/callback/midtrans', $payload)->assertOk()->assertJsonPath('data.processed', true);
        $this->postJson('/api/v1/sikeu/callback/midtrans', $payload)->assertOk()->assertJsonPath('data.processed', false);
        $this->assertDatabaseCount('sikeu_pembayaran', 1);
        $this->assertEquals(100000, $kas->fresh()->saldo_saat_ini);
        $this->assertDatabaseCount('sikeu_jurnal_umum', 1);
        $this->assertDatabaseCount('sikeu_detail_jurnal_umum', 2);
        $this->assertSame('lunas', $this->application->fresh()->status_pembayaran);
        $this->assertSame(PendaftaranCalonMhs::STATUS_SUBMITTED, $this->application->fresh()->status);
        $this->assertSame(1, CoreNotification::where('type', 'payment_paid')->count());
        $this->postJson('/api/spmb/pembayaran/'.$bill->id.'/checkout')->assertUnprocessable();
    }

    public function test_midtrans_rejects_bad_signature_amount_and_browser_supplied_amount(): void
    {
        $bill = $this->issue([$this->detail($this->midtrans, 100000)])['tagihan'];
        $this->postJson('/api/spmb/pembayaran/'.$bill->id.'/checkout', ['amount' => 1])->assertUnprocessable();
        $this->fakeMidtrans();
        $checkout = $this->postJson('/api/spmb/pembayaran/'.$bill->id.'/checkout')->assertOk()->json('data');
        $payload = $this->midtransPayload($checkout['order_id'], 100000);
        $payload['signature_key'] = 'invalid';
        $this->postJson('/api/v1/sikeu/callback/midtrans', $payload)->assertStatus(400);
        $this->postJson('/api/v1/sikeu/callback/midtrans', $this->midtransPayload($checkout['order_id'], 1))->assertUnprocessable();
        $pending = $this->midtransPayload($checkout['order_id'], 100000);
        $pending['transaction_status'] = 'pending';
        $this->postJson('/api/v1/sikeu/callback/midtrans', $pending)->assertOk();
        $this->assertDatabaseCount('sikeu_pembayaran', 0);
        $this->assertSame('belum_bayar', $bill->fresh()->status);
    }

    public function test_xendit_invoice_callback_checks_token_status_identity_amount_and_retries(): void
    {
        $bill = $this->issue([$this->detail($this->xendit, 100000)])['tagihan'];
        Http::fake(['api.xendit.co/v2/invoices' => Http::response(['id' => 'invoice-test', 'invoice_url' => 'https://checkout.xendit.co/web/test'], 200)]);
        $checkout = $this->postJson('/api/spmb/pembayaran/'.$bill->id.'/checkout')->assertOk()->json('data');
        $payload = ['id' => 'invoice-test', 'external_id' => $checkout['order_id'], 'amount' => 100000, 'currency' => 'IDR', 'status' => 'PAID'];
        $endpoint = '/api/v1/sikeu/callback/xendit-checkout';
        $this->postJson($endpoint, $payload)->assertUnauthorized();
        $headers = ['x-callback-token' => $this->xendit->webhook_token_encrypted];
        $this->postJson($endpoint, array_merge($payload, ['status' => 'PENDING']), $headers)->assertOk();
        $this->assertSame('belum_bayar', $bill->fresh()->status);
        $this->postJson($endpoint, array_merge($payload, ['id' => 'wrong']), $headers)->assertStatus(400);
        $this->postJson($endpoint, array_merge($payload, ['amount' => 1]), $headers)->assertUnprocessable();
        $this->postJson($endpoint, $payload, $headers)->assertOk()->assertJsonPath('data.processed', true);
        $this->postJson($endpoint, $payload, $headers)->assertOk()->assertJsonPath('data.processed', false);
        $this->assertDatabaseCount('sikeu_pembayaran', 1);
    }

    public function test_partial_stage_settlement_does_not_unlock_registration_or_reenrollment(): void
    {
        $bills = $this->issue([$this->detail($this->midtrans, 100000), $this->detail($this->xendit, 200000)])['tagihan_list'];
        $bills[0]['tagihan']->update(['total_bayar' => 100000, 'status' => 'lunas']);
        $this->assertSame('sebagian', $this->application->fresh()->status_pembayaran);
        $this->assertSame(PendaftaranCalonMhs::STATUS_DRAFT, $this->application->fresh()->status);
        $bills[1]['tagihan']->update(['total_bayar' => 200000, 'status' => 'lunas']);
        $this->assertSame('lunas', $this->application->fresh()->status_pembayaran);
        $result = HasilSeleksi::create(['pendaftaran_id' => $this->application->id, 'status' => HasilSeleksi::STATUS_LULUS, 'status_daftar_ulang' => 'menunggu_pembayaran']);
        $duBills = $this->issue([$this->detail($this->midtrans, 100000), $this->detail($this->xendit, 200000)], [], true)['tagihan_list'];
        $duBills[0]['tagihan']->update(['total_bayar' => 100000, 'status' => 'lunas']);
        $this->assertSame('menunggu_pembayaran', $result->fresh()->status_daftar_ulang);
        $duBills[1]['tagihan']->update(['total_bayar' => 200000, 'status' => 'lunas']);
        $this->assertSame('lunas', $result->fresh()->status_daftar_ulang);
        $this->assertSame(1, CoreNotification::where('type', 'reenrollment_lunas')->count());
    }

    public function test_h2h_checkout_issues_real_billing_and_reuses_it(): void
    {
        $h2h = $this->gateway(PaymentGatewayConfig::H2H);
        $h2h->update(['base_url' => 'https://bridge.example.test']);
        $bill = $this->issue([$this->detail($h2h, 100000)])['tagihan'];
        Http::fake(['bridge.example.test/sikeu/billing' => Http::response(['rc' => 'OK', 'data' => ['id' => 991, 'id_tagihan' => 'bridge-991']], 200)]);
        $this->postJson('/api/spmb/pembayaran/'.$bill->id.'/checkout')->assertOk()->assertJsonPath('data.method', 'h2h')
            ->assertJsonPath('data.custid', $this->application->no_pendaftaran)->assertJsonPath('data.total_bridge', 101500);
        $this->postJson('/api/spmb/pembayaran/'.$bill->id.'/checkout')->assertOk()->assertJsonPath('data.reused', true);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('sikeu_tagihan_mahasiswa', ['id' => $bill->id, 'h2h_billing_id' => 991]);
        $row = (object) ['id' => 991, 'total_nominal' => 101500, 'tanggal_transaksi' => now()->toDateTimeString(), 'id_transaksi' => 'H2H-PAY-991'];
        (new \ReflectionMethod(BsnH2hService::class, 'terapkanPembayaran'))->invoke(null, $bill->fresh(), $row);
        $this->assertSame('lunas', $this->application->fresh()->status_pembayaran);
        $this->assertEquals(100000, $bill->fresh()->total_bayar);
    }

    public function test_provider_errors_never_generate_fake_virtual_accounts_or_mark_paid(): void
    {
        $bill = $this->issue([$this->detail($this->midtrans, 100000)])['tagihan'];
        Http::fake(['*' => Http::response(['error' => 'unavailable'], 503)]);
        $this->postJson('/api/spmb/pembayaran/'.$bill->id.'/checkout')->assertStatus(502);
        $this->assertDatabaseCount('sikeu_virtual_account', 0);
        $this->assertDatabaseCount('sikeu_pembayaran', 0);
        $this->assertSame('belum_bayar', $bill->fresh()->status);
        $this->assertDatabaseHas('sikeu_payment_checkouts', ['tagihan_id' => $bill->id, 'status' => 'failed']);
    }

    public function test_multiple_gateways_remain_active_and_only_legacy_default_changes(): void
    {
        $this->grant('sikeu.paymentgateway.manage');
        $config = ['environment' => 'sandbox', 'is_active' => true, 'is_default' => true,
            'api_key' => $this->xendit->api_key_encrypted, 'public_key' => $this->xendit->public_key_encrypted,
            'webhook_token' => $this->xendit->webhook_token_encrypted, 'auto_disbursement_enabled' => false,
            'account_validation_enabled' => false, 'max_disbursement_limit' => 0];
        $this->putJson('/api/v1/sikeu/payment-gateway/xendit', $config)->assertOk();
        $this->assertTrue($this->midtrans->fresh()->is_active);
        $this->assertSame($this->xendit->id, PaymentGatewayConfig::active()->id);
        $this->getJson('/api/v1/sikeu/payment-gateway/active')->assertOk()->assertJsonMissingPath('data.api_key_encrypted');
        $detail = $this->detail($this->midtrans, 100000);
        unset($detail['payment_gateway_config_id']);
        $bill = $this->issue([$detail])['tagihan'];
        $this->assertSame($this->xendit->id, $bill->payment_gateway_config_id);
        $this->putJson('/api/v1/sikeu/payment-gateway/xendit', array_merge($config, ['environment' => 'production']))->assertUnprocessable();
        $this->assertSame('sandbox', $bill->fresh()->payment_gateway_environment);
    }

    public function test_journal_failure_rolls_back_payment_balance_and_notifications(): void
    {
        $bill = $this->issue([$this->detail($this->midtrans, 100000)])['tagihan'];
        $cash = UnitKas::where('kanal', 'midtrans')->firstOrFail();
        $cash->update(['akun_keuangan_id' => null]);
        $this->fakeMidtrans();
        $order = $this->postJson('/api/spmb/pembayaran/'.$bill->id.'/checkout')->assertOk()->json('data.order_id');
        $this->postJson('/api/v1/sikeu/callback/midtrans', $this->midtransPayload($order, 100000))->assertStatus(500);
        $this->assertDatabaseCount('sikeu_pembayaran', 0);
        $this->assertEquals(0, $cash->fresh()->saldo_saat_ini);
        $this->assertSame('belum_bayar', $bill->fresh()->status);
        $this->assertSame(0, CoreNotification::where('type', 'payment_paid')->count());
    }

    public function test_real_registration_and_reenrollment_issuance_include_every_channel(): void
    {
        $this->grant('spmb.pendaftaran.create');
        $type = MasterTipeJalur::create(['kode' => fake()->unique()->lexify('T????'), 'nama' => fake()->sentence(2)]);
        $this->application->update(['master_tipe_jalur_id' => $type->id]);
        $master = MasterBiaya::create(['master_tipe_jalur_id' => $type->id, 'program_studi_id' => $this->application->program_studi_id, 'is_active' => true]);
        foreach ([$this->midtrans, $this->xendit] as $gateway) {
            $component = MasterKomponenBiaya::create(['kode' => fake()->unique()->lexify('F?????'), 'nama' => fake()->sentence(2), 'payment_gateway_config_id' => $gateway->id]);
            $master->items()->create(['komponen_biaya_id' => $component->id, 'nominal' => 100000, 'dibebankan_saat_pendaftaran' => true]);
            $otherComponent = MasterKomponenBiaya::create(['kode' => fake()->unique()->lexify('F?????'), 'nama' => fake()->sentence(2), 'payment_gateway_config_id' => $gateway->id]);
            $master->items()->create(['komponen_biaya_id' => $otherComponent->id, 'nominal' => 200000, 'dibebankan_saat_pendaftaran' => false]);
        }
        $this->postJson('/api/spmb/pendaftaran/biodata', ['nama_lengkap' => $this->application->nama_lengkap])->assertOk()
            ->assertJsonCount(2, 'data.tagihan.tagihan_list');
        $this->getJson('/api/spmb/pendaftaran/me')->assertOk()->assertJsonCount(2, 'data.tagihan_list');
        $this->postJson('/api/spmb/pendaftaran/biodata', ['nama_lengkap' => $this->application->nama_lengkap])->assertOk();
        $this->assertDatabaseCount('sikeu_tagihan_mahasiswa', 2);
        HasilSeleksi::create(['pendaftaran_id' => $this->application->id, 'status' => HasilSeleksi::STATUS_LULUS]);
        $this->postJson('/api/spmb/daftar-ulang/'.$this->application->id.'/generate-tagihan')->assertOk()
            ->assertJsonCount(2, 'data.tagihan_list');
        $this->getJson('/api/spmb/pembayaran?tahap=daftar_ulang')->assertOk()->assertJsonPath('meta.total', 2);
        $this->assertDatabaseCount('sikeu_tagihan_mahasiswa', 4);
        $this->assertSame(4, CoreNotification::where('type', 'billing_issued')->count());
        $this->assertStringContainsString('tahap=daftar_ulang', CoreNotification::where('type', 'billing_issued')->latest('id')->firstOrFail()->url);
        Http::assertNothingSent();
    }

    public function test_zero_cost_component_does_not_unlock_an_unpaid_group_during_issue(): void
    {
        $this->issue([$this->detail($this->midtrans, 100000, 1), $this->detail($this->xendit, 200000, 2)],
            [['komponen_biaya_id' => 1, 'nominal_potongan' => 100000]]);
        $this->assertSame(PendaftaranCalonMhs::STATUS_DRAFT, $this->application->fresh()->status);
        $this->assertSame('belum_bayar', $this->application->fresh()->status_pembayaran);
    }

    private function gateway(string $name): PaymentGatewayConfig
    {
        return PaymentGatewayConfig::create(['gateway_name' => $name, 'environment' => 'sandbox', 'is_active' => true,
            'api_key_encrypted' => fake()->sha256(), 'public_key_encrypted' => fake()->sha256(), 'webhook_token_encrypted' => fake()->sha256()]);
    }

    public function test_payment_channel_schema_can_be_rolled_back_and_reapplied(): void
    {
        $migration = require database_path('migrations/2026_11_11_140000_add_spmb_payment_channels.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('sikeu_payment_checkouts'));
        $this->assertFalse(Schema::hasColumn('spmb_master_komponen_biaya', 'payment_gateway_config_id'));
        $migration->up();
        $this->assertTrue(Schema::hasTable('sikeu_payment_checkouts'));
        $this->assertTrue(Schema::hasColumn('sikeu_tagihan_mahasiswa', 'payment_gateway_config_id'));
    }

    public function test_default_channel_selection_ignores_non_spmb_gateway(): void
    {
        PaymentGatewayConfig::query()->update(['is_default' => false]);
        PaymentGatewayConfig::create(['gateway_name' => 'doku', 'environment' => 'sandbox', 'is_active' => true,
            'is_default' => true, 'api_key_encrypted' => fake()->sha256()]);
        $detail = $this->detail($this->midtrans, 100000);
        unset($detail['payment_gateway_config_id']);
        $bill = $this->issue([$detail])['tagihan'];
        $this->assertSame($this->midtrans->id, $bill->payment_gateway_config_id);
        $this->assertSame(PaymentGatewayConfig::MIDTRANS, $bill->payment_gateway_name);
    }

    public function test_unconfigured_default_does_not_create_placeholder_virtual_accounts(): void
    {
        PaymentGatewayConfig::query()->update(['is_active' => false]);
        $detail = $this->detail($this->midtrans, 100000);
        unset($detail['payment_gateway_config_id']);
        try {
            $this->issue([$detail]);
            $this->fail('Missing default must fail.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('sikeu_tagihan_mahasiswa', 0);
            $this->assertDatabaseCount('sikeu_virtual_account', 0);
        }
    }

    private function grant(string $ability): void
    {
        $permission = Permission::firstOrCreate(['slug' => $ability], ['name' => $ability, 'module' => explode('.', $ability)[0], 'action' => 'update']);
        $role = Role::create(['slug' => fake()->unique()->slug(), 'name' => fake()->sentence(2), 'is_active' => true]);
        $role->permissions()->attach($permission->id);
        $role->menus()->syncWithoutDetaching(Menu::query()->pluck('id')->all());
        $this->owner->roles()->attach($role->id);
        $this->owner->unsetRelation('roles');
        Passport::actingAs($this->owner->fresh());
    }

    private function detail(PaymentGatewayConfig $gateway, int $amount, ?int $componentId = null): array
    {
        return ['master_biaya_kode' => fake()->unique()->lexify('PG-??????'), 'komponen_biaya_id' => $componentId,
            'payment_gateway_config_id' => $gateway->id, 'nominal' => $amount, 'keterangan' => fake()->sentence(2)];
    }

    private function issue(array $details, array $discounts = [], bool $reenrollment = false): array
    {
        return app(SpmbBillingService::class)->issue(['calon_mahasiswa_id' => $this->application->id, 'source_system' => 'SPMB',
            'tipe_referensi' => $reenrollment ? TagihanMahasiswa::TIPE_SPMB_DAFTAR_ULANG : TagihanMahasiswa::TIPE_SPMB_PENDAFTARAN,
            'details' => $details, 'potongan' => $discounts]);
    }

    private function fakeMidtrans(): void
    {
        Http::fake(['app.sandbox.midtrans.com/snap/v1/transactions' => Http::response(['token' => 'snap-test',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/test'], 201)]);
    }

    private function midtransPayload(string $order, int $amount): array
    {
        $gross = number_format($amount, 2, '.', '');

        return ['order_id' => $order, 'gross_amount' => $gross, 'status_code' => '200', 'transaction_status' => 'settlement',
            'signature_key' => hash('sha512', $order.'200'.$gross.$this->midtrans->api_key_encrypted)];
    }
}
