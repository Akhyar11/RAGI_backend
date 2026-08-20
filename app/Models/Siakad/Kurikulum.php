<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Spmb\MasterProgramStudi;

class Kurikulum extends Model
{
    use SoftDeletes;

    protected $table = 'siakad_kurikulum';

    protected $fillable = [
        'program_studi_id',
        'kode',
        'nama',
        'tahun_berlaku',
        'total_sks_lulus',
        'deskripsi',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'tahun_berlaku' => 'integer',
        'total_sks_lulus' => 'integer',
    ];

    public function programStudi()
    {
        return $this->belongsTo(MasterProgramStudi::class, 'program_studi_id');
    }

    public function mataKuliahs()
    {
        return $this->hasMany(MataKuliah::class, 'kurikulum_id');
    }
}
