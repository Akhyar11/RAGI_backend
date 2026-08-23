<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Ruangan;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\Spmb\MasterTahunAkademik;

class Kelas extends Model
{
    use SoftDeletes;

    protected $table = 'siakad_kelas';

    protected $fillable = [
        'mata_kuliah_id',
        'tahun_akademik_id',
        'program_studi_id',
        'ruangan_id',
        'kode_kelas',
        'nama_kelas',
        'kapasitas',
        'kuota_krs',
        'hari',
        'jam_mulai',
        'jam_selesai',
        'status',
    ];

    protected $casts = [
        'kapasitas' => 'integer',
        'kuota_krs' => 'integer',
    ];

    public function mataKuliah()
    {
        return $this->belongsTo(MataKuliah::class, 'mata_kuliah_id');
    }

    public function tahunAkademik()
    {
        return $this->belongsTo(MasterTahunAkademik::class, 'tahun_akademik_id');
    }

    public function programStudi()
    {
        return $this->belongsTo(MasterProgramStudi::class, 'program_studi_id');
    }

    public function ruangan()
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }

    public function dosenPengampu()
    {
        return $this->hasMany(DosenPengampu::class, 'kelas_id');
    }

    public function krsDetails()
    {
        return $this->hasMany(KrsDetail::class, 'kelas_id');
    }

    public function pertemuans()
    {
        return $this->hasMany(Pertemuan::class, 'kelas_id');
    }
}
