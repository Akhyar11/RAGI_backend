<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TugasAkhirNilai extends Model
{
    use HasFactory;

    protected $table = 'siakad_ta_nilai';

    protected $fillable = [
        'penguji_id',
        'skor_metodologi',
        'skor_materi_skripsi',
        'skor_presentasi',
        'skor_penguasaan_materi',
        'skor_total',
        'catatan_revisi',
        'is_submitted',
        'submitted_at',
    ];

    protected $casts = [
        'skor_metodologi' => 'decimal:2',
        'skor_materi_skripsi' => 'decimal:2',
        'skor_presentasi' => 'decimal:2',
        'skor_penguasaan_materi' => 'decimal:2',
        'skor_total' => 'decimal:2',
        'is_submitted' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    public function penguji(): BelongsTo
    {
        return $this->belongsTo(TugasAkhirPenguji::class, 'penguji_id');
    }
}
