<?php

namespace App\Models\Siakad;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PaInsiden extends Model
{
    protected $table = 'siakad_pa_insiden';

    protected $fillable = [
        'mahasiswa_id',
        'dosen_id',
        'pelapor_user_id',
        'anonim',
        'jenis',
        'tingkat',
        'deskripsi',
        'tanggal_kejadian',
        'lokasi',
        'status',
        'eskalasi_tujuan',
        'dieskalasi_pada',
        'rujukan_konseling',
        'konseling_ke',
        'tindak_lanjut',
    ];

    protected $casts = [
        'anonim' => 'boolean',
        'rujukan_konseling' => 'boolean',
        'tanggal_kejadian' => 'date',
        'dieskalasi_pada' => 'datetime',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }

    public function dosen()
    {
        return $this->belongsTo(Dosen::class, 'dosen_id');
    }

    public function pelapor()
    {
        return $this->belongsTo(User::class, 'pelapor_user_id');
    }
}
