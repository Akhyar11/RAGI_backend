<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Spmb\MasterProgramStudi;

class ProfilLulusan extends Model
{
    use SoftDeletes;

    protected $table = 'siakad_profil_lulusan';

    protected $fillable = [
        'program_studi_id',
        'kode_pl',
        'nama',
        'deskripsi',
        'urutan',
    ];

    public function programStudi()
    {
        return $this->belongsTo(MasterProgramStudi::class, 'program_studi_id');
    }

    public function cpls()
    {
        return $this->belongsToMany(Cpl::class, 'siakad_profil_lulusan_cpl', 'profil_lulusan_id', 'cpl_id');
    }
}
