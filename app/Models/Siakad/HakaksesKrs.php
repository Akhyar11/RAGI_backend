<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class HakaksesKrs extends Model
{
    protected $table = 'siakad_hakakses_krs';

    protected $fillable = [
        'mahasiswa_id',
        'tahun_akademik_id',
        'is_dibuka',
        'dibuka_at',
    ];

    protected $casts = [
        'mahasiswa_id' => 'integer',
        'tahun_akademik_id' => 'integer',
        'is_dibuka' => 'boolean',
        'dibuka_at' => 'datetime',
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
