<?php

namespace App\Services\Spmb;

use App\Models\Sikeu\PaymentGatewayConfig;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Services\Sikeu\ExternalTagihanService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SpmbBillingService
{
    public function __construct(private readonly ExternalTagihanService $issuer, private readonly SpmbBillAllocator $allocator) {}

    public function stageQuery(int $applicationId, bool $reenrollment = false): Builder
    {
        return TagihanMahasiswa::spmbStage($applicationId, $reenrollment);
    }

    public function issue(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $application = PendaftaranCalonMhs::query()->lockForUpdate()->findOrFail($payload['calon_mahasiswa_id']);
            $reenrollment = $payload['tipe_referensi'] === TagihanMahasiswa::TIPE_SPMB_DAFTAR_ULANG;
            $existing = $this->stageQuery($application->id, $reenrollment)->with(['virtualAccount', 'detailTagihan'])->orderBy('id')->get();
            if ($existing->isNotEmpty()) {
                return $this->envelope($existing->all());
            }
            $details = $payload['details'];
            $default = PaymentGatewayConfig::activeSpmb();
            foreach ($details as &$detail) {
                $id = $detail['payment_gateway_config_id'] ?? null;
                $gateway = $id ? PaymentGatewayConfig::find($id) : $default;
                if (! $id && (! $gateway || ! $gateway->is_active || ! in_array($gateway->gateway_name, PaymentGatewayConfig::SPMB_CHANNELS, true))) {
                    throw ValidationException::withMessages(['payment_gateway_config_id' => 'Kanal default pembayaran SPMB belum dikonfigurasi. Pilih kanal aktif pada komponen biaya atau atur default SIKEU.']);
                }
                if ($id && (! $gateway || ! $gateway->is_active || ! in_array($gateway->gateway_name, PaymentGatewayConfig::SPMB_CHANNELS, true))) {
                    throw ValidationException::withMessages(['payment_gateway_config_id' => 'Kanal pembayaran komponen biaya tidak aktif atau tidak didukung. Hubungi panitia SPMB.']);
                }
                $detail['payment_gateway_config_id'] = $gateway && in_array($gateway->gateway_name, PaymentGatewayConfig::SPMB_CHANNELS, true) ? $gateway->id : null;
            }
            unset($detail);
            $bills = [];
            $zeroBills = [];
            foreach ($this->allocator->split($details, $payload['potongan'] ?? []) as $group) {
                $issued = $this->issuer->issueExternalBill(array_merge($payload, $group));
                $bill = $issued['tagihan'];
                $net = (float) $bill->total_tagihan - (float) $bill->total_potongan;
                if ($net <= 0) {
                    $zeroBills[] = $bill;
                }
                $bills[] = $bill;
            }
            foreach ($zeroBills as $bill) {
                $bill->update(['status' => 'lunas']);
            }
            app(SpmbPaymentStatusService::class)->sync($application->id, $reenrollment);

            return $this->envelope($bills);
        });
    }

    public function envelope(array $bills): array
    {
        $items = array_map(function (TagihanMahasiswa $bill): array {
            $bill->loadMissing(['virtualAccount', 'detailTagihan']);

            return ['tagihan' => $bill, 'virtual_account' => $bill->virtualAccount];
        }, $bills);

        return array_merge($items[0] ?? ['tagihan' => null, 'virtual_account' => null], ['tagihan_list' => $items]);
    }
}
