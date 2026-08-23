<?php

namespace App\Models\Sippm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenilaianProposal extends Model
{
    use HasFactory;

    protected $table = 'sippm_penilaian_proposal';

    protected $fillable = [
        'reviewer_kegiatan_id',
        'skor_rekam_jejak',
        'skor_substansi',
        'skor_rencana_anggaran',
        'skor_total',
        'rekomendasi',
        'catatan_revisi',
        'file_penilaian',
        'submitted_at',
    ];

    protected $casts = [
        'skor_rekam_jejak' => 'decimal:2',
        'skor_substansi' => 'decimal:2',
        'skor_rencana_anggaran' => 'decimal:2',
        'skor_total' => 'decimal:2',
        'submitted_at' => 'datetime',
    ];

    public function reviewerKegiatan()
    {
        return $this->belongsTo(ReviewerKegiatan::class, 'reviewer_kegiatan_id');
    }
}
