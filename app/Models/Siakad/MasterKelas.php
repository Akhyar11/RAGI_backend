<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterKelas extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'siakad_master_kelas';

    protected $fillable = [
        'program_studi_id',
        'nama_kelas',
        'tahun_angkatan',
        'dosen_pa_id',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'program_studi_id' => 'integer',
        'tahun_angkatan' => 'integer',
        'dosen_pa_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function programStudi()
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }

    public function dosenPa()
    {
        return $this->belongsTo(Dosen::class, 'dosen_pa_id');
    }

    public function mahasiswas()
    {
        return $this->hasMany(Mahasiswa::class, 'kelas', 'nama_kelas')
            ->where('program_studi_id', $this->program_studi_id);
    }
}
