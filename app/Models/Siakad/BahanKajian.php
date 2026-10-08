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
        'kurikulum_id',
        'kode_bk',
        'nama_bk',
        'koordinator_id',
        'deskripsi',
    ];

    protected $casts = [
        'program_studi_id' => 'integer',
        'kurikulum_id' => 'integer',
        'koordinator_id' => 'integer',
    ];

    public function programStudi()
    {
        return $this->belongsTo(MasterProgramStudi::class, 'program_studi_id');
    }

    public function kurikulum()
    {
        return $this->belongsTo(Kurikulum::class, 'kurikulum_id');
    }

    public function koordinator()
    {
        return $this->belongsTo(Dosen::class, 'koordinator_id');
    }

    public function mataKuliahs()
    {
        return $this->belongsToMany(MataKuliah::class, 'siakad_mata_kuliah_bahan_kajian', 'bahan_kajian_id', 'mata_kuliah_id');
    }

    public function cpls()
    {
        return $this->belongsToMany(Cpl::class, 'siakad_cpl_bahan_kajian', 'bahan_kajian_id', 'cpl_id');
    }
}
