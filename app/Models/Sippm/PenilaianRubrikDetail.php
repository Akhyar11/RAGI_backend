<?php

namespace App\Models\Sippm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Simpeg\Pegawai;

class PenilaianRubrikDetail extends Model
{
    use HasFactory;

    protected $table = 'sippm_penilaian_rubrik_detail';

    protected $fillable = [
        'proposal_id',
        'rubrik_id',
        'tipe_reviewer',
        'reviewer_pegawai_id',
        'skor',
        'catatan',
    ];

    protected $casts = [
        'skor' => 'decimal:2',
    ];

    public function proposal()
    {
        return $this->belongsTo(ProposalKegiatan::class, 'proposal_id');
    }

    public function rubrik()
    {
        return $this->belongsTo(RubrikIndikator::class, 'rubrik_id');
    }

    public function reviewerPegawai()
    {
        return $this->belongsTo(Pegawai::class, 'reviewer_pegawai_id');
    }
}
