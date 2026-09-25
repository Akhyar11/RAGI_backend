<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Siakad\Pertemuan;
use App\Models\Siakad\Mahasiswa;
use App\Models\User;

class IzinAbsensi extends Model
{
    use HasFactory;

    protected $table = 'lms_izin_absensi';

    protected $fillable = [
        'pertemuan_id',
        'mahasiswa_id',
        'tipe_izin_id',
        'tipe_izin',
        'alasan',
        'surat_path',
        'disk',
        'status',
        'catatan_dosen',
        'diproses_oleh',
        'diproses_at',
    ];

    protected $casts = [
        'tipe_izin_id' => 'integer',
        'diproses_at' => 'datetime',
    ];

    public function tipeIzin()
    {
        return $this->belongsTo(\App\Models\System\MasterReferensi::class, 'tipe_izin_id');
    }

    public function pertemuan()
    {
        return $this->belongsTo(Pertemuan::class, 'pertemuan_id');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }

    public function diprosesOleh()
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }
}
