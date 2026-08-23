<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Spmb\MasterProgramStudi;

class BahanKajian extends Model
{
    use SoftDeletes;

    protected $table = 'siakad_bahan_kajian';

    protected $fillable = [
        'program_studi_id',
        'kode_bk',
        'nama_bk',
        'deskripsi',
    ];

    public function programStudi()
    {
        return $this->belongsTo(MasterProgramStudi::class, 'program_studi_id');
    }

    public function mataKuliahs()
    {
        return $this->belongsToMany(MataKuliah::class, 'siakad_mata_kuliah_bahan_kajian', 'bahan_kajian_id', 'mata_kuliah_id');
    }
}
