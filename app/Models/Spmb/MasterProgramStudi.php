<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Siakad\Fakultas;
use App\Models\Siakad\Kurikulum;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\Dosen;

class MasterProgramStudi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'spmb_master_program_studi';

    protected $fillable = [
        'fakultas_id',
        'kode_prodi',
        'kode_prodi_dikti',
        'nama',
        'jenjang',
        'akreditasi',
        'akreditasi_berlaku_sampai',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'akreditasi_berlaku_sampai' => 'date',
    ];

    public function fakultas()
    {
        return $this->belongsTo(Fakultas::class, 'fakultas_id');
    }

    public function kurikulums()
    {
        return $this->hasMany(Kurikulum::class, 'program_studi_id');
    }

    public function mahasiswas()
    {
        return $this->hasMany(Mahasiswa::class, 'program_studi_id');
    }

    public function dosens()
    {
        return $this->hasMany(Dosen::class, 'program_studi_id');
    }

    public function pendaftaranCalonMhs()
    {
        return $this->hasMany(PendaftaranCalonMhs::class, 'program_studi_id');
    }

    public function tarifUktSpmb()
    {
        return $this->hasMany(TarifUktSpmb::class, 'program_studi_id');
    }

    public function cpls()
    {
        return $this->hasMany(\App\Models\Siakad\Cpl::class, 'program_studi_id');
    }
}
