<?php

namespace App\Models\Siakad;

use App\Models\Siakad\Dosen;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TugasAkhirBimbingan extends Model
{
    use HasFactory;

    protected $table = 'siakad_ta_bimbingan';

    public const STATUS_MENUNGGU = 'menunggu';
    public const STATUS_ACC = 'acc';
    public const STATUS_REVISI = 'revisi';

    protected $fillable = [
        'tugas_akhir_id',
        'dosen_id',
        'tanggal',
        'bab_pembahasan',
        'uraian_konsultasi',
        'arahan_revisi',
        'berkas_draft',
        'status_validasi',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function tugasAkhir(): BelongsTo
    {
        return $this->belongsTo(TugasAkhir::class, 'tugas_akhir_id');
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'dosen_id');
    }
}
