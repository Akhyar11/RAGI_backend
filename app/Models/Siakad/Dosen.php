<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Spmb\MasterProgramStudi;

class Dosen extends Model
{
    use SoftDeletes;

    protected $table = 'siakad_dosen';

    protected $fillable = [
        'user_id',
        'pegawai_id',
        'nidn',
        'nip',
        'nama_lengkap',
        'gelar_depan',
        'gelar_belakang',
        'program_studi_id',
        'jabatan_akademik',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function programStudi()
    {
        return $this->belongsTo(MasterProgramStudi::class, 'program_studi_id');
    }

    public function kelasPengampu()
    {
        return $this->hasMany(DosenPengampu::class, 'dosen_id');
    }

    public function mahasiswaBimbingan()
    {
        return $this->hasMany(Mahasiswa::class, 'dosen_wali_id');
    }

    public function getNamaGelarAttribute(): string
    {
        $depan = $this->gelar_depan ? "{$this->gelar_depan} " : '';
        $belakang = $this->gelar_belakang ? ", {$this->gelar_belakang}" : '';
        return "{$depan}{$this->nama_lengkap}{$belakang}";
    }
}
