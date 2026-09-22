<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterKomponenBiaya extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'spmb_master_komponen_biaya';

    protected $fillable = [
        'kode',
        'nama',
        'kategori',
        'tipe_potongan',
        'urutan',
        'is_active',
        'keterangan',
    ];

    protected $casts = [
        'tipe_potongan' => 'boolean',
        'is_active' => 'boolean',
        'urutan' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(MasterBiayaItem::class, 'komponen_biaya_id');
    }
}
