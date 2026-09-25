<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Siakad\Kelas;

class KelasLmsSetting extends Model
{
    use HasFactory;

    protected $table = 'lms_kelas_setting';

    protected $fillable = [
        'kelas_id',
        'total_pertemuan',
        'metode_absensi',
        'batas_min_hadir_persen',
        'can_submit_late',
        'show_nilai_to_mahasiswa',
        'storage_disk',
    ];

    protected $casts = [
        'total_pertemuan' => 'integer',
        'batas_min_hadir_persen' => 'integer',
        'can_submit_late' => 'boolean',
        'show_nilai_to_mahasiswa' => 'boolean',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }
}
