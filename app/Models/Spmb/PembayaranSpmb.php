<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Spmb\PendaftaranCalonMhs;

class PembayaranSpmb extends Model
{
    use HasFactory;

    protected $table = 'spmb_pembayaran';

    protected $fillable = [
        'pendaftaran_id',
        'kode_bayar',
        'jumlah_tagihan',
        'jumlah_bayar',
        'status',
        'metode_bayar',
        'va_number',
        'gateway_response',
        'paid_at',
        'expired_at',
    ];

    protected $casts = [
        'jumlah_tagihan' => 'decimal:2',
        'jumlah_bayar' => 'decimal:2',
        'gateway_response' => 'array',
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function pendaftaranCalonMhs()
    {
        return $this->belongsTo(PendaftaranCalonMhs::class, 'pendaftaran_id');
    }
}
