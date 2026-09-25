<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Siakad\Pertemuan;
use App\Models\Siakad\KomponenPenilaian;

class Tugas extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lms_tugas';

    protected $fillable = [
        'pertemuan_id',
        'komponen_penilaian_id',
        'judul',
        'deskripsi',
        'deadline_at',
        'maks_nilai',
        'can_submit_late',
        'is_published',
    ];

    protected $casts = [
        'deadline_at' => 'datetime',
        'maks_nilai' => 'integer',
        'can_submit_late' => 'boolean',
        'is_published' => 'boolean',
    ];

    public function pertemuan()
    {
        return $this->belongsTo(Pertemuan::class, 'pertemuan_id');
    }

    public function komponenPenilaian()
    {
        return $this->belongsTo(KomponenPenilaian::class, 'komponen_penilaian_id');
    }

    public function pengumpulan()
    {
        return $this->hasMany(PengumpulanTugas::class, 'tugas_id');
    }
}
