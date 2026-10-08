<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ObeRubrikKriteria extends Model
{
    use HasFactory;

    protected $table = 'siakad_obe_rubrik_kriteria';

    protected $fillable = [
        'rubrik_id',
        'nama_kriteria',
        'skor_min',
        'skor_max',
        'deskripsi',
        'bobot_persen',
        'deskripsi_sangat_baik',
        'deskripsi_baik',
        'deskripsi_cukup',
        'deskripsi_kurang',
        'urutan',
    ];

    protected $casts = [
        'rubrik_id' => 'integer',
        'skor_min' => 'float',
        'skor_max' => 'float',
        'bobot_persen' => 'float',
        'urutan' => 'integer',
    ];

    public function rubrik()
    {
        return $this->belongsTo(ObeRubrik::class, 'rubrik_id');
    }
}
