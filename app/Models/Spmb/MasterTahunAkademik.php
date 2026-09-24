<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterTahunAkademik extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'spmb_master_tahun_akademik';

    protected $fillable = [
        'kode',
        'nama',
        'tahun_mulai',
        'tahun_selesai',
        'is_active',
        'mode_penilaian',
        'krs_mulai',
        'krs_selesai',
        'kprs_mulai',
        'kprs_selesai',
        'perkuliahan_mulai',
        'perkuliahan_selesai',
        'input_nilai_mulai',
        'input_nilai_selesai',
    ];

    protected $casts = [
        'tahun_mulai' => 'integer',
        'tahun_selesai' => 'integer',
        'is_active' => 'boolean',
        'krs_mulai' => 'date',
        'krs_selesai' => 'date',
        'kprs_mulai' => 'date',
        'kprs_selesai' => 'date',
        'perkuliahan_mulai' => 'date',
        'perkuliahan_selesai' => 'date',
        'input_nilai_mulai' => 'date',
        'input_nilai_selesai' => 'date',
    ];

    public function gelombangPenerimaan()
    {
        return $this->hasMany(GelombangPenerimaan::class, 'tahun_akademik_id');
    }

    public function tarifUktSpmb()
    {
        return $this->hasMany(TarifUktSpmb::class, 'tahun_akademik_id');
    }
}
