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
    ];

    protected $casts = [
        'tahun_mulai' => 'integer',
        'tahun_selesai' => 'integer',
        'is_active' => 'boolean',
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
