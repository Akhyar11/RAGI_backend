<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KasKecilTransaksi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sikeu_kas_kecil_transaksi';

    protected $fillable = [
        'unit_kas_id',
        'transaksi_kas_unit_id',
        'nomor_transaksi',
        'referensi_kategori_id',
        'uraian',
        'penerima',
        'nominal',
        'tanggal_transaksi',
        'file_bukti_path',
        'keterangan',
        'created_by',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'tanggal_transaksi' => 'date',
    ];

    public function unitKas()
    {
        return $this->belongsTo(UnitKas::class, 'unit_kas_id');
    }

    public function transaksiKasUnit()
    {
        return $this->belongsTo(TransaksiKasUnit::class, 'transaksi_kas_unit_id');
    }

    public function kategori()
    {
        return $this->belongsTo(\App\Models\System\MasterReferensi::class, 'referensi_kategori_id');
    }

    public function dibuatOleh()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}