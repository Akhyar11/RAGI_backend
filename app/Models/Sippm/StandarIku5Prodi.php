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

    /**
     * Get the associated Unit Kerja (Program Studi).
     */
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }
}
