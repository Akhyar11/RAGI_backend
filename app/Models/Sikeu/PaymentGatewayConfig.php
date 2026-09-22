<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGatewayConfig extends Model
{
    use HasFactory;

    protected $table = 'sikeu_payment_gateway_configs';

    protected $fillable = [
        'gateway_name',
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
        'api_key_encrypted' => 'encrypted',
        'public_key_encrypted' => 'encrypted',
        'webhook_token_encrypted' => 'encrypted',
        'db_password_encrypted' => 'encrypted',
        'db_port' => 'integer',
        'is_active' => 'boolean',
        'auto_disbursement_enabled' => 'boolean',
        'account_validation_enabled' => 'boolean',
        'max_disbursement_limit' => 'decimal:2',
    ];
}
