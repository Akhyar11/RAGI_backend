<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentCheckout extends Model
{
    protected $table = 'sikeu_payment_checkouts';

    protected $guarded = ['id'];

    protected $hidden = ['token'];

    protected $casts = ['amount' => 'decimal:2', 'expires_at' => 'datetime'];

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(TagihanMahasiswa::class, 'tagihan_id');
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGatewayConfig::class, 'payment_gateway_config_id');
    }
}
