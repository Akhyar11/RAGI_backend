<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class AbsensiMahasiswa extends Model
{
    protected $table = 'siakad_absensi_mahasiswa';

    protected $fillable = [
        'pertemuan_id',
        'mahasiswa_id',
        'status',
        'catatan',
    ];

    public function pertemuan()
    {
        return $this->belongsTo(Pertemuan::class, 'pertemuan_id');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }
}
