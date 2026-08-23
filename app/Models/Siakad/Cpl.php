<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Spmb\MasterProgramStudi;

class Cpl extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'siakad_cpl';

    protected $fillable = [
        'program_studi_id',
        'kode_cpl',
        'kategori',
        'deskripsi',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function programStudi()
    {
        return $this->belongsTo(MasterProgramStudi::class, 'program_studi_id');
    }

    public function cpmks()
    {
        return $this->hasMany(Cpmk::class, 'cpl_id');
    }

    public function mappedCpmks()
    {
        return $this->belongsToMany(Cpmk::class, 'siakad_pemetaan_cpl_cpmk', 'cpl_id', 'cpmk_id')
            ->withPivot('bobot_kontribusi')
            ->withTimestamps();
    }

    public function profilLulusans()
    {
        return $this->belongsToMany(ProfilLulusan::class, 'siakad_profil_lulusan_cpl', 'cpl_id', 'profil_lulusan_id');
    }
}
