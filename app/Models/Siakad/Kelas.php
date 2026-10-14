<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Ruangan;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\Siakad\TahunAkademik;

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
        'is_gabungan',
        'parent_kelas_id',
        'tipe_sesi',
        'id_feeder',
    ];

    protected $casts = [
        'kapasitas' => 'integer',
        'kuota_krs' => 'integer',
        'is_gabungan' => 'boolean',
    ];

    public const TIPE_SESI = ['gabungan', 'teori', 'praktik'];

    public function mataKuliah()
    {
        return $this->belongsTo(MataKuliah::class, 'mata_kuliah_id');
    }

    public function tahunAkademik()
    {
        return $this->belongsTo(TahunAkademik::class, 'tahun_akademik_id');
    }

    public function programStudi()
    {
        return $this->belongsTo(MasterProgramStudi::class, 'program_studi_id');
    }

    /**
     * Prodi peserta kelas gabungan (selain homebase program_studi_id).
     */
    public function programStudis()
    {
        return $this->belongsToMany(
            MasterProgramStudi::class,
            'siakad_kelas_program_studi',
            'kelas_id',
            'program_studi_id'
        )->withTimestamps();
    }

    public function ruangan()
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }

    /**
     * Kelas induk teori gabungan (null untuk kelas biasa / induk).
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_kelas_id');
    }

    /**
     * Kelas anak praktik pecahan dari induk teori.
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_kelas_id');
    }

    /**
     * Rombel administratif peserta (siakad_master_kelas, cth. 24A/24B).
     */
    public function rombels()
    {
        return $this->belongsToMany(
            MasterKelas::class,
            'siakad_kelas_rombel',
            'kelas_id',
            'master_kelas_id'
        );
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

    public function lmsSetting()
    {
        return $this->hasOne(\App\Models\Lms\KelasLmsSetting::class, 'kelas_id');
    }
}
