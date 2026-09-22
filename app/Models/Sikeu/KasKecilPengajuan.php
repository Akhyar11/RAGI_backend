<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KasKecilPengajuan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sikeu_kas_kecil_pengajuan';

    protected $fillable = [
        'unit_kas_id',
        'transaksi_kas_unit_id',
        'nomor_pengajuan',
        'judul_pengajuan',
        'keperluan',
        'nominal_diajukan',
        'nominal_disetujui',
        'status',
        'catatan_penolakan',
        'pemohon_id',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected $casts = [
        'nominal_diajukan' => 'decimal:2',
        'nominal_disetujui' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function unitKas()
    {
        return $this->belongsTo(UnitKas::class, 'unit_kas_id');
    }

    public function transaksiKasUnit()
    {
        return $this->belongsTo(TransaksiKasUnit::class, 'transaksi_kas_unit_id');
    }

    public function pemohon()
    {
        return $this->belongsTo(\App\Models\User::class, 'pemohon_id');
    }

    public function approver()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }
}