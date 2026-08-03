<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransaksiKasUnit extends Model
{
    use HasFactory;

    protected $table = 'transaksi_kas_unit';

    protected $fillable = [
        'unit_kas_id',
        'pengajuan_pencairan_id',
        'kode_transaksi',
        'jenis_transaksi',
        'nominal',
        'saldo_sebelum',
        'saldo_sesudah',
        'keterangan',
        'tanggal_transaksi',
        'created_by',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'saldo_sebelum' => 'decimal:2',
        'saldo_sesudah' => 'decimal:2',
        'tanggal_transaksi' => 'date',
    ];

    public function unitKas()
    {
        return $this->belongsTo(UnitKas::class, 'unit_kas_id');
    }

    public function pengajuanPencairan()
    {
        return $this->belongsTo(PengajuanPencairanKas::class, 'pengajuan_pencairan_id');
    }
}
