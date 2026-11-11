<?php

namespace App\Models\Sippm;

use App\Models\Simpeg\UnitKerja;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StandarIku5Prodi extends Model
{
    use HasFactory;

    protected $table = 'sippm_standar_iku5_prodi';

    protected $fillable = [
        'unit_kerja_id',
        'tahun_akademik',
        'target_publikasi_scopus',
        'target_publikasi_sinta',
        'target_hki_paten',
        'target_buku_isbn',
    ];

    protected $casts = [
        'unit_kerja_id' => 'integer',
        'target_publikasi_scopus' => 'integer',
        'target_publikasi_sinta' => 'integer',
        'target_hki_paten' => 'integer',
        'target_buku_isbn' => 'integer',
    ];

    protected $appends = [
        'prodi_id',
        'target_scopus',
        'target_sinta',
        'target_hki',
        'target_internal',
        'target_dikti',
        'min_capaian_iku',
        'nama_prodi',
    ];

    public function getProdiIdAttribute()
    {
        return (string) $this->unit_kerja_id;
    }

    public function getNamaProdiAttribute(): ?string
    {
        return $this->unitKerja?->nama;
    }

    public function getTargetScopusAttribute()
    {
        return $this->target_publikasi_scopus;
    }

    public function getTargetSintaAttribute()
    {
        return $this->target_publikasi_sinta;
    }

    public function getTargetHkiAttribute()
    {
        return $this->target_hki_paten;
    }

    public function getTargetInternalAttribute()
    {
        return $this->target_buku_isbn;
    }

    public function getTargetDiktiAttribute(): int
    {
        return (int) ($this->attributes['target_dikti'] ?? 0);
    }

    public function getMinCapaianIkuAttribute(): int
    {
        return (int) ($this->attributes['min_capaian_iku'] ?? 0);
    }

    /**
     * Get the associated Unit Kerja (Program Studi).
     */
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }
}
