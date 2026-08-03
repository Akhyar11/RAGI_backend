<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\JadwalUjianSpmb;

class PesertaUjianSpmb extends Model
{
    use HasFactory;

    protected $table = 'peserta_ujian_spmb';

    protected $fillable = [
        'pendaftaran_id',
        'jadwal_ujian_id',
        'no_peserta',
        'nomor_kursi',
        'hadir',
    ];

    protected $casts = [
        'hadir' => 'boolean',
    ];

    public function pendaftaranCalonMhs()
    {
        return $this->belongsTo(PendaftaranCalonMhs::class, 'pendaftaran_id');
    }

    public function jadwalUjianSpmb()
    {
        return $this->belongsTo(JadwalUjianSpmb::class, 'jadwal_ujian_id');
    }
}
