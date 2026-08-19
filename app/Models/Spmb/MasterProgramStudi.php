<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterProgramStudi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'master_program_studi';

    protected $fillable = [
        'kode_prodi',
        'nama',
        'jenjang',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function pendaftaranCalonMhs()
    {
        return $this->hasMany(PendaftaranCalonMhs::class, 'program_studi_id');
    }

    public function tarifUktSpmb()
    {
        return $this->hasMany(TarifUktSpmb::class, 'program_studi_id');
    }
}
