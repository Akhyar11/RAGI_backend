<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Siakad\Mahasiswa;
use App\Models\User;

class PengumpulanTugas extends Model
{
    use HasFactory;

    protected $table = 'lms_pengumpulan_tugas';

    protected $fillable = [
        'tugas_id',
        'mahasiswa_id',
        'catatan_mahasiswa',
        'file_path',
        'disk',
        'nama_file_asli',
        'ukuran_bytes',
        'mime_type',
        'is_late',
        'nilai',
        'feedback_dosen',
        'dinilai_at',
        'dinilai_oleh',
    ];

    protected $casts = [
        'is_late' => 'boolean',
        'nilai' => 'decimal:2',
        'ukuran_bytes' => 'integer',
        'dinilai_at' => 'datetime',
    ];

    public function tugas()
    {
        return $this->belongsTo(Tugas::class, 'tugas_id');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }

    public function penilai()
    {
        return $this->belongsTo(User::class, 'dinilai_oleh');
    }
}
