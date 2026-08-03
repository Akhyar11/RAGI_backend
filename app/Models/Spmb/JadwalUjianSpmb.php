<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Spmb\PesertaUjianSpmb;

class JadwalUjianSpmb extends Model
{
    use HasFactory;

    protected $table = 'jadwal_ujian_spmb';

    protected $fillable = [
        'gelombang_id',
        'ruangan_id',
        'nama_sesi',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'kapasitas',
        'tipe_ujian',
    ];

    protected $casts = [
        'tanggal' => 'date',
        // 'jam_mulai' => 'datetime:H:i',
        // 'jam_selesai' => 'datetime:H:i',
    ];

    public function gelombangPenerimaan()
    {
        return $this->belongsTo(GelombangPenerimaan::class, 'gelombang_id');
    }

    public function pesertaUjianSpmb()
    {
        return $this->hasMany(PesertaUjianSpmb::class, 'jadwal_ujian_id');
    }
}
