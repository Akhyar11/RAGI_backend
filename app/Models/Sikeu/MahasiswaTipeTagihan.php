<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MahasiswaTipeTagihan extends Model
{
    use HasFactory;

    protected $table = 'mahasiswa_tipe_tagihan';

    protected $fillable = [
        'mahasiswa_id',
        'nim',
        'nama_mahasiswa',
        'tahun_angkatan',
        'jalur_kelas',
        'kelompok_ukt',
        'beasiswa_id',
        'status_pendaftaran',
        'catatan_perubahan',
        'updated_by',
    ];

    public function beasiswa()
    {
        return $this->belongsTo(Beasiswa::class, 'beasiswa_id');
    }
}
