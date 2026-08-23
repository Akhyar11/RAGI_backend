<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Spmb\PertanyaanKuesionerSpmb;

class KuesionerSpmb extends Model
{
    use HasFactory;

    protected $table = 'spmb_kuesioner';

    protected $fillable = [
        'gelombang_id',
        'judul',
        'deskripsi',
        'is_required',
        'is_active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function gelombangPenerimaan()
    {
        return $this->belongsTo(GelombangPenerimaan::class, 'gelombang_id');
    }

    public function pertanyaanKuesionerSpmb()
    {
        return $this->hasMany(PertanyaanKuesionerSpmb::class, 'kuesioner_id');
    }
}
