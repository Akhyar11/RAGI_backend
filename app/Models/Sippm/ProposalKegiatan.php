<?php

namespace App\Models\Sippm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Simpeg\Pegawai;

class ProposalKegiatan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'proposal_kegiatan';

    protected $fillable = [
        'periode_id',
        'skema_id',
        'ketua_pegawai_id',
        'mitra_kerjasama_id',
        'kode_proposal',
        'judul',
        'abstrak',
        'rumpun_ilmu',
        'target_tkt',
        'anggaran_diajukan',
        'anggaran_disetujui',
        'file_proposal',
        'status',
    ];

    protected $casts = [
        'target_tkt' => 'integer',
        'anggaran_diajukan' => 'decimal:2',
        'anggaran_disetujui' => 'decimal:2',
    ];

    public function periode()
    {
        return $this->belongsTo(PeriodeHibah::class, 'periode_id');
    }

    public function skema()
    {
        return $this->belongsTo(SkemaKegiatan::class, 'skema_id');
    }

    public function ketuaPegawai()
    {
        return $this->belongsTo(Pegawai::class, 'ketua_pegawai_id');
    }

    public function anggota()
    {
        return $this->hasMany(AnggotaKegiatan::class, 'proposal_id');
    }

    public function reviewer()
    {
        return $this->hasMany(ReviewerKegiatan::class, 'proposal_id');
    }

    public function kontrak()
    {
        return $this->hasOne(KontrakKegiatan::class, 'proposal_id');
    }

    public function publikasi()
    {
        return $this->hasMany(PublikasiIlmiah::class, 'proposal_id');
    }

    public function hkiDanBuku()
    {
        return $this->hasMany(HkiDanBuku::class, 'proposal_id');
    }
}
