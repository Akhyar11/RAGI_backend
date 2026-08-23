<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiwayatJabatan extends Model
{
    use HasFactory;

    protected $table = 'simpeg_riwayat_jabatan';

    protected $fillable = [
        'pegawai_id',
        'jabatan_id',
        'jabatan_fungsional_id',
        'mulai_jabatan',
        'selesai_jabatan',
        'sk_nomor',
        'sk_tanggal',
        'file_sk',
        'is_active',
    ];

    protected $casts = [
        'mulai_jabatan' => 'date:Y-m-d',
        'selesai_jabatan' => 'date:Y-m-d',
        'sk_tanggal' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function jabatan()
    {
        return $this->belongsTo(Jabatan::class, 'jabatan_id');
    }

    public function jabatanFungsional()
    {
        return $this->belongsTo(JabatanFungsionalAkademik::class, 'jabatan_fungsional_id');
    }
}
