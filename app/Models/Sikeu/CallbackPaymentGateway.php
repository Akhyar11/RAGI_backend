<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallbackPaymentGateway extends Model
{
    use HasFactory;

    protected $table = 'callback_payment_gateway';

    protected $fillable = [
        'order_id',
        'payment_type',
        'raw_payload',
        'status',
        'pembayaran_id',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function pembayaran()
    {
        return $this->belongsTo(Pembayaran::class, 'pembayaran_id');
    }
}
