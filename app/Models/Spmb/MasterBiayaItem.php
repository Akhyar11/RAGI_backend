<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterBiayaItem extends Model
{
    use HasFactory;

    protected $table = 'spmb_master_biaya_item';

    protected $fillable = [
        'master_biaya_id',
        'komponen_biaya_id',
        'nominal',
        'dibebankan_saat_pendaftaran',
        'keterangan',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'dibebankan_saat_pendaftaran' => 'boolean',
    ];

    public function masterBiaya()
    {
        return $this->belongsTo(MasterBiaya::class, 'master_biaya_id');
    }

    public function komponenBiaya()
    {
        return $this->belongsTo(MasterKomponenBiaya::class, 'komponen_biaya_id');
    }
}
