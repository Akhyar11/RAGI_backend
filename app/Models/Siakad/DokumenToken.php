<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class DokumenToken extends Model
{
    protected $table = 'siakad_dokumen_tokens';

    /** Jenis dokumen yang sah (closed-set domain, terpusat). */
    public const JENIS_KHS = 'KHS';
    public const JENIS_TRANSKRIP = 'TRANSKRIP';
    public const JENIS_DOKUMEN = [self::JENIS_KHS, self::JENIS_TRANSKRIP];

    protected $fillable = [
        'jenis',
        'mahasiswa_id',
        'tahun_akademik_id',
        'nomor_dokumen',
        'token',
        'snapshot',
        'tanggal_terbit',
        'is_active',
    ];

    protected $casts = [
        'mahasiswa_id' => 'integer',
        'tahun_akademik_id' => 'integer',
        'snapshot' => 'array',
        'tanggal_terbit' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }

    public function tahunAkademik()
    {
        return $this->belongsTo(TahunAkademik::class, 'tahun_akademik_id');
    }
}
