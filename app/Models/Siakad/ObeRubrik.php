<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ObeRubrik extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'siakad_obe_rubrik';

    protected $fillable = [
        'program_studi_id',
        'cpmk_id',
        'kode_rubrik',
        'nama_rubrik',
        'tipe_rubrik',
        'deskripsi',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'program_studi_id' => 'integer',
        'cpmk_id' => 'integer',
    ];

    public function programStudi()
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }

    public function cpmk()
    {
        return $this->belongsTo(Cpmk::class, 'cpmk_id');
    }

    public function kriterias()
    {
        return $this->hasMany(ObeRubrikKriteria::class, 'rubrik_id')->orderBy('urutan');
    }
}
