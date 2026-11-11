<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGatewayConfig extends Model
{
    use HasFactory;

    public const MIDTRANS = 'midtrans';
    public const XENDIT = 'xendit';
    public const H2H = 'bsn_h2h';
    public const SPMB_CHANNELS = [self::MIDTRANS, self::XENDIT, self::H2H];

    protected $table = 'sikeu_payment_gateway_configs';

    protected $fillable = [
        'gateway_name',
        'va_fee',
        'vat_percent',
        'charge_fee_to_payer',
        'environment',
        'base_url',
        'server_location',
        'api_key_encrypted',
        'public_key_encrypted',
        'webhook_token_encrypted',
        'db_host',
        'db_port',
        'db_name',
        'db_username',
        'db_password_encrypted',
        'is_active',
        'is_default',
        'auto_disbursement_enabled',
        'account_validation_enabled',
        'max_disbursement_limit',
        'updated_by',
    ];

    /**
     * The attributes that should be cast automatically.
     * Using Laravel's native 'encrypted' cast ensures AES-256-CBC encryption in Database.
     */
    protected $casts = [
        'va_fee' => 'decimal:2',
        'vat_percent' => 'decimal:2',
        'charge_fee_to_payer' => 'boolean',
        'api_key_encrypted' => 'encrypted',
        'public_key_encrypted' => 'encrypted',
        'webhook_token_encrypted' => 'encrypted',
        'db_password_encrypted' => 'encrypted',
        'db_port' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'auto_disbursement_enabled' => 'boolean',
        'account_validation_enabled' => 'boolean',
        'max_disbursement_limit' => 'decimal:2',
    ];

    /**
     * Biaya gateway (fee + PPN) untuk satu transaksi VA.
     */
    public function computeVaFee(): float
    {
        $fee = (float) $this->va_fee;
        $vat = $fee * ((float) $this->vat_percent / 100);

        return round($fee + $vat, 2);
    }

    /**
     * Konfigurasi gateway aktif.
     */
    public static function active(): ?self
    {
        return static::where('is_active', true)->orderByDesc('is_default')->orderBy('id')->first();
    }

    /**
     * Konfigurasi gateway aktif yang didukung alur SPMB.
     * Default global modul lain tidak boleh terpilih sebagai kanal SPMB.
     */
    public static function activeSpmb(): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->whereIn('gateway_name', self::SPMB_CHANNELS)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }
}
