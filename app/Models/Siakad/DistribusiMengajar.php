<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DistribusiMengajar extends Model
{
    use HasFactory;

    protected $table = 'siakad_distribusi_mengajar';

    protected $fillable = [
        'program_studi_id',
        'tahun_akademik_id',
        'kurikulum_id',
        'mata_kuliah_id',
        'semester',
        'dosen_koordinator_id',
        'dosen_anggota_ids',
        'kelas_ids',
        'is_active',
    ];

    protected $casts = [
        'program_studi_id' => 'integer',
        'tahun_akademik_id' => 'integer',
        'kurikulum_id' => 'integer',
        'mata_kuliah_id' => 'integer',
        'semester' => 'integer',
        'dosen_koordinator_id' => 'integer',
        'dosen_anggota_ids' => 'array',
        'kelas_ids' => 'array',
        'is_active' => 'boolean',
    ];

    public function programStudi()
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }

    public function tahunAkademik()
    {
        return $this->belongsTo(TahunAkademik::class, 'tahun_akademik_id');
    }

    public function kurikulum()
    {
        return $this->belongsTo(Kurikulum::class, 'kurikulum_id');
    }

    public function mataKuliah()
    {
        return $this->belongsTo(MataKuliah::class, 'mata_kuliah_id');
    }

    public function dosenKoordinator()
    {
        return $this->belongsTo(Dosen::class, 'dosen_koordinator_id');
    }
}
