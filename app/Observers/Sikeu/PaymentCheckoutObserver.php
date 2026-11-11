<?php

namespace App\Observers\Sikeu;

use App\Models\Sikeu\PaymentCheckout;
use App\Services\AuditLogService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentCheckoutObserver
{
    private const FIELDS = ['tagihan_id', 'payment_gateway_config_id', 'gateway_name', 'environment', 'order_id', 'amount', 'status', 'expires_at'];

    /**
     * Snapshot nilai lama sebelum mutasi agar delta audit tetap akurat.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $oldValues = [];

    public function created(PaymentCheckout $checkout): void
    {
        $this->record($checkout, 'create', null);
    }

    public function updating(PaymentCheckout $checkout): void
    {
        $this->oldValues[spl_object_id($checkout)] = Arr::only($checkout->getOriginal(), self::FIELDS);
    }

    public function updated(PaymentCheckout $checkout): void
    {
        $key = spl_object_id($checkout);
        $oldValues = $this->oldValues[$key] ?? Arr::only($checkout->getOriginal(), self::FIELDS);
        unset($this->oldValues[$key]);
        $this->record($checkout, 'update', $oldValues);
    }

    public function deleting(PaymentCheckout $checkout): void
    {
        $this->oldValues[spl_object_id($checkout)] = Arr::only($checkout->getOriginal(), self::FIELDS);
    }

    public function deleted(PaymentCheckout $checkout): void
    {
        $key = spl_object_id($checkout);
        $oldValues = $this->oldValues[$key] ?? Arr::only($checkout->getOriginal(), self::FIELDS);
        unset($this->oldValues[$key]);
        $this->record($checkout, 'delete', $oldValues);
    }

    private function record(PaymentCheckout $checkout, string $action, ?array $oldValues): void
    {
        try {
            AuditLogService::record(module: 'SIKEU', action: $action, tableName: $checkout->getTable(), recordId: $checkout->id,
                oldValues: $oldValues,
                newValues: Arr::only($action === 'create' ? $checkout->getAttributes() : $checkout->getChanges(), self::FIELDS));
        } catch (Throwable $exception) {
            Log::warning('Gagal mencatat audit checkout pembayaran: '.$exception->getMessage());
        }
    }
}
