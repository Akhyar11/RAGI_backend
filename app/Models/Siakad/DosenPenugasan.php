<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\Spmb\MasterTahunAkademik;

class DosenPenugasan extends Model
{
    use SoftDeletes;

    protected $table = 'siakad_dosen_penugasan';

    protected $fillable = [
        'dosen_id',
        'program_studi_id',
        'tahun_akademik_id',
        'nomor_surat_tugas',
        'tanggal_surat_tugas',
        'tmt_surat_tugas',
        'is_homebase',
        'id_feeder',
        'sync_status',
        'last_synced_at',
    ];

    protected $casts = [
        'is_homebase' => 'boolean',
        'tanggal_surat_tugas' => 'date',
        'tmt_surat_tugas' => 'date',
        'last_synced_at' => 'datetime',
    ];

    public function dosen()
    {
        return $this->belongsTo(Dosen::class, 'dosen_id');
    }

    public function programStudi()
    {
        return $this->belongsTo(MasterProgramStudi::class, 'program_studi_id');
    }

    public function tahunAkademik()
    {
        return $this->belongsTo(MasterTahunAkademik::class, 'tahun_akademik_id');
    }

    public function pengampuKelas()
    {
        return $this->hasMany(DosenPengampu::class, 'penugasan_id');
    }
}
