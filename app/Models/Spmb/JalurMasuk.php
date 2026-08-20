<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Spmb\GelombangPenerimaan;

class JalurMasuk extends Model
{
    use HasFactory;

    protected $table = 'jalur_masuk';

    protected $fillable = [
        'kode',
        'nama',
        'deskripsi',
        'master_tipe_jalur_id',
        'tipe',
        'ada_ujian_tulis',
        'ada_ujian_praktik',
        'ada_wawancara',
        'is_active',
    ];

    protected $casts = [
        'ada_ujian_tulis' => 'boolean',
        'ada_ujian_praktik' => 'boolean',
        'ada_wawancara' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function gelombangPenerimaan()
    {
        return $this->hasMany(GelombangPenerimaan::class, 'jalur_masuk_id');
    }
}
