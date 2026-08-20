<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Spmb\MasterTahunAkademik;

class Krs extends Model
{
    use SoftDeletes;

    protected $table = 'siakad_krs';

    protected $fillable = [
        'mahasiswa_id',
        'tahun_akademik_id',
        'total_sks_diambil',
        'status',
        'disetujui_oleh',
        'disetujui_at',
        'locked_by_keuangan',
    ];

    protected $casts = [
        'total_sks_diambil' => 'integer',
        'locked_by_keuangan' => 'boolean',
        'disetujui_at' => 'datetime',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }

    public function tahunAkademik()
    {
        return $this->belongsTo(MasterTahunAkademik::class, 'tahun_akademik_id');
    }

    public function dosenPembimbing()
    {
        return $this->belongsTo(Dosen::class, 'disetujui_oleh');
    }

    public function krsDetails()
    {
        return $this->hasMany(KrsDetail::class, 'krs_id');
    }
}
