<?php

namespace App\Models\Sippm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Simpeg\Pegawai;

class PublikasiIlmiah extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'publikasi_ilmiah';

    protected $fillable = [
        'proposal_id',
        'pegawai_id',
        'judul_artikel',
        'jenis_publikasi',
        'nama_jurnal_prosiding',
        'indexing',
        'volume_issue_tahun',
        'doi',
        'url_artikel',
        'file_artikel',
        'is_verified_lppm',
    ];

    protected $casts = [
        'is_verified_lppm' => 'boolean',
    ];

    public function proposal()
    {
        return $this->belongsTo(ProposalKegiatan::class, 'proposal_id');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
