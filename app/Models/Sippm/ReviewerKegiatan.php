<?php

namespace App\Models\Sippm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Simpeg\Pegawai;

class ReviewerKegiatan extends Model
{
    use HasFactory;

    protected $table = 'sippm_reviewer_kegiatan';

    protected $fillable = [
        'proposal_id',
        'reviewer_pegawai_id',
        'tgl_penugasan',
        'status_review',
    ];

    protected $casts = [
        'tgl_penugasan' => 'date:Y-m-d',
    ];

    public function proposal()
    {
        return $this->belongsTo(ProposalKegiatan::class, 'proposal_id');
    }

    public function reviewerPegawai()
    {
        return $this->belongsTo(Pegawai::class, 'reviewer_pegawai_id');
    }

    public function penilaian()
    {
        return $this->hasOne(PenilaianProposal::class, 'reviewer_kegiatan_id');
    }
}
