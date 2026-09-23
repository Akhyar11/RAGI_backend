<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabBhpTransaksi extends Model
{
    use HasFactory;

    protected $table = 'sinapra_lab_bhp_transaksi';

    protected $fillable = [
        'bhp_id',
        'user_id',
        'jenis_transaksi',
        'jumlah',
        'tanggal',
        'keterangan',
    ];

    protected $casts = [
        'jumlah' => 'float',
        'tanggal' => 'date',
    ];

    public function bhp(): BelongsTo
    {
        return $this->belongsTo(LabBhp::class, 'bhp_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
