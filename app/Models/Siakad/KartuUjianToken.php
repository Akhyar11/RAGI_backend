<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class KartuUjianToken extends Model
{
    protected $table = 'siakad_kartu_ujian_tokens';

    /** Tipe ujian yang sah (closed-set domain, terpusat). */
    public const TIPE_UTS = 'UTS';
    public const TIPE_UAS = 'UAS';
    public const TIPE_UJIAN = [self::TIPE_UTS, self::TIPE_UAS];

    protected $fillable = [
        'mahasiswa_id',
        'tahun_akademik_id',
        'tipe_ujian',
        'token',
        'tanggal_cetak',
        'ip',
        'user_agent',
        'is_active',
    ];

    protected $casts = [
        'mahasiswa_id' => 'integer',
        'tahun_akademik_id' => 'integer',
        'tanggal_cetak' => 'datetime',
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
