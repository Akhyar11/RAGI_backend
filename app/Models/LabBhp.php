<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabBhp extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sinapra_lab_bhp';

    protected $fillable = [
        'ruangan_id',
        'kategori_bhp_id',
        'satuan_id',
        'kode_bhp',
        'nama_bhp',
        'kategori',
        'stok_saat_ini',
        'stok_minimum',
        'satuan',
        'spesifikasi',
        'lokasi_penyimpanan',
    ];

    protected $casts = [
        'stok_saat_ini' => 'float',
        'stok_minimum' => 'float',
    ];

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }

    public function kategoriBhp(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Sinapra\MasterKategoriBhp::class, 'kategori_bhp_id');
    }

    public function satuanData(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Sinapra\MasterSatuan::class, 'satuan_id');
    }

    public function transaksi(): HasMany
    {
        return $this->hasMany(LabBhpTransaksi::class, 'bhp_id');
    }
}
