<?php

namespace App\Models\Sikeu;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MahasiswaTipeTagihan extends Model
{
    use HasFactory;

    protected $table = 'sikeu_mahasiswa_tipe_tagihan';

    protected $fillable = [
        'mahasiswa_id',
        'nim',
        'nama_mahasiswa',
        'tahun_angkatan',
        'jalur_kelas',
        'kelompok_ukt',
        'status_pendaftaran',
        'catatan_perubahan',
        'updated_by',
    ];
}
