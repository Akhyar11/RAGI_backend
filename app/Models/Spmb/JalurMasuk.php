<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JalurMasuk extends Model
{
    use HasFactory;

    protected $table = 'spmb_jalur_masuk';

    protected $fillable = [
        'kode',
        'nama',
        'deskripsi',
        'is_active',
        'ada_wawancara',
        'pilih_perguruan_tinggi',
    ];

    protected $hidden = ['pivot'];

    protected $casts = [
        'ada_wawancara' => 'boolean',
        'pilih_perguruan_tinggi' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function gelombangPenerimaan()
    {
        return $this->belongsToMany(GelombangPenerimaan::class, 'spmb_gelombang_jalur_masuk', 'jalur_masuk_id', 'gelombang_id');
    }
}
