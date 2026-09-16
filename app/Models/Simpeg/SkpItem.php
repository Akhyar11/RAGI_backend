<?php

namespace App\Models\Simpeg;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SkpItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'simpeg_skp_item';

    protected $fillable = [
        'penilaian_kinerja_id',
        'kategori_skp_id',
        'uraian_tugas',
        'target_output',
        'target_mutu',
        'target_waktu',
        'target_biaya',
        'realisasi_output',
        'realisasi_mutu',
        'realisasi_waktu',
        'realisasi_biaya',
        'nilai_capaian',
        'berkas_bukti',
        'keterangan',
    ];

    protected $casts = [
        'target_mutu' => 'decimal:2',
        'target_biaya' => 'decimal:2',
        'realisasi_mutu' => 'decimal:2',
        'realisasi_biaya' => 'decimal:2',
        'nilai_capaian' => 'decimal:2',
    ];

    public function penilaianKinerja(): BelongsTo
    {
        return $this->belongsTo(PenilaianKinerja::class, 'penilaian_kinerja_id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(MasterKategoriSkp::class, 'kategori_skp_id');
    }
}
