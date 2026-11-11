<?php

namespace App\Models\Sippm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Simpeg\Pegawai;

class PublikasiIlmiah extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sippm_publikasi_ilmiah';

    public const KATEGORI_MAP = [
        'scopus' => ['jenis' => 'jurnal_internasional_bereputasi', 'indexing' => 'scopus_q2'],
        'wos' => ['jenis' => 'jurnal_internasional_bereputasi', 'indexing' => 'wos'],
        'sinta_1_2' => ['jenis' => 'jurnal_nasional_terakreditasi', 'indexing' => 'sinta_2'],
        'sinta_3_6' => ['jenis' => 'jurnal_nasional_terakreditasi', 'indexing' => 'sinta_4'],
        'international' => ['jenis' => 'prosiding_internasional', 'indexing' => 'lainnya'],
        'national_indexed' => ['jenis' => 'jurnal_nasional_terakreditasi', 'indexing' => 'sinta_5'],
    ];

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
        'scopus_eid',
        'sinta_article_id',
        'citation_count',
        'publisher',
        'synced_at',
    ];

    protected $casts = [
        'is_verified_lppm' => 'boolean',
        'citation_count' => 'integer',
        'synced_at' => 'datetime:Y-m-d H:i:s',
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
