<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Spmb\GelombangPenerimaan;

class JalurMasuk extends Model
{
    use HasFactory;

    protected $table = 'spmb_jalur_masuk';

    protected $fillable = [
        'kode',
        'nama',
        'deskripsi',
        'master_tipe_jalur_id',
        'ada_wawancara',
        'is_active',
    ];

    protected $casts = [
        'ada_wawancara' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function gelombangPenerimaan()
    {
        return $this->hasMany(GelombangPenerimaan::class, 'jalur_masuk_id');
    }

    public function masterTipeJalur()
    {
        return $this->belongsTo(\App\Models\MasterTipeJalur::class, 'master_tipe_jalur_id');
    }
}
