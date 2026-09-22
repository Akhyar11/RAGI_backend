<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Khs extends Model
{
    use SoftDeletes;

    protected $table = 'siakad_khs';

    protected $fillable = [
        'mahasiswa_id',
        'tahun_akademik_id',
        'ips',
        'total_sks_semester',
        'sks_kumulatif',
        'ipk',
        'is_locked',
    ];

    protected $casts = [
        'ips' => 'decimal:2',
        'ipk' => 'decimal:2',
        'total_sks_semester' => 'integer',
        'sks_kumulatif' => 'integer',
        'is_locked' => 'boolean',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }

    public function tahunAkademik()
    {
        return $this->belongsTo(TahunAkademik::class, 'tahun_akademik_id');
    }
}
