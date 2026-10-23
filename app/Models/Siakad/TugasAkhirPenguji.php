<?php

namespace App\Models\Siakad;

use App\Models\Siakad\Dosen;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TugasAkhirPenguji extends Model
{
    use HasFactory;

    protected $table = 'siakad_ta_penguji';

    public const PERAN_KETUA = 'ketua';
    public const PERAN_SEKRETARIS = 'sekretaris';
    public const PERAN_PENGUJI_1 = 'penguji_1';
    public const PERAN_PENGUJI_2 = 'penguji_2';
    public const PERAN_PENGUJI_LUAR = 'penguji_luar';

    public const HADIR = 'hadir';
    public const IZIN = 'izin';
    public const TIDAK_HADIR = 'tidak_hadir';

    protected $fillable = [
        'sidang_id',
        'dosen_id',
        'peran_penguji',
        'kehadiran',
    ];

    public function sidang(): BelongsTo
    {
        return $this->belongsTo(TugasAkhirSidang::class, 'sidang_id');
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'dosen_id');
    }

    public function nilai(): HasOne
    {
        return $this->hasOne(TugasAkhirNilai::class, 'penguji_id');
    }
}
