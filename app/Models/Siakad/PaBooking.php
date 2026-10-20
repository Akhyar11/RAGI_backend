<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class PaBooking extends Model
{
    protected $table = 'siakad_pa_booking';

    protected $fillable = [
        'jadwal_id',
        'mahasiswa_id',
        'topik',
        'catatan_mahasiswa',
        'status',
        'catatan_dosen',
        'diproses_pada',
    ];

    protected $casts = [
        'diproses_pada' => 'datetime',
    ];

    public function jadwal()
    {
        return $this->belongsTo(PaJadwal::class, 'jadwal_id');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }
}
