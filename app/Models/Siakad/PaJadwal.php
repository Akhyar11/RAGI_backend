<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class PaJadwal extends Model
{
    protected $table = 'siakad_pa_jadwal';

    protected $fillable = [
        'dosen_id',
        'tahun_akademik_id',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'mode',
        'lokasi',
        'kuota',
        'catatan',
        'status',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'kuota' => 'integer',
    ];

    public function dosen()
    {
        return $this->belongsTo(Dosen::class, 'dosen_id');
    }

    public function tahunAkademik()
    {
        return $this->belongsTo(TahunAkademik::class, 'tahun_akademik_id');
    }

    public function bookings()
    {
        return $this->hasMany(PaBooking::class, 'jadwal_id');
    }
}
