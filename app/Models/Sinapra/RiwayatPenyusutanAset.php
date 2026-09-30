<?php

namespace App\Models\Sinapra;

use App\Models\Aset;
use App\Models\Sikeu\JurnalUmum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatPenyusutanAset extends Model
{
    use HasFactory;

    protected $table = 'sinapra_riwayat_penyusutan_aset';

    protected $fillable = [
        'aset_id',
        'jurnal_umum_id',
        'periode_tahun',
        'nilai_perolehan',
        'persentase_penyusutan',
        'beban_penyusutan',
        'nilai_buku_setelah',
        'tanggal_posting',
        'catatan',
        'diposting_oleh',
    ];

    protected $casts = [
        'periode_tahun' => 'integer',
        'nilai_perolehan' => 'decimal:2',
        'persentase_penyusutan' => 'decimal:2',
        'beban_penyusutan' => 'decimal:2',
        'nilai_buku_setelah' => 'decimal:2',
        'tanggal_posting' => 'date',
    ];

    protected $appends = [
        'tahun',
        'nominal_penyusutan',
        'nilai_buku_sesudah',
    ];

    public function getTahunAttribute(): int
    {
        return (int) $this->periode_tahun;
    }

    public function getNominalPenyusutanAttribute(): float
    {
        return (float) $this->beban_penyusutan;
    }

    public function getNilaiBukuSesudahAttribute(): float
    {
        return (float) $this->nilai_buku_setelah;
    }

    /**
     * Relasi ke Aset
     */
    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class, 'aset_id');
    }

    /**
     * Relasi ke Jurnal Umum SIKEU
     */
    public function jurnalUmum(): BelongsTo
    {
        return $this->belongsTo(JurnalUmum::class, 'jurnal_umum_id');
    }

    /**
     * Alias relasi jurnal untuk konsistensi API
     */
    public function jurnal(): BelongsTo
    {
        return $this->belongsTo(JurnalUmum::class, 'jurnal_umum_id');
    }

    /**
     * Relasi ke User yang memposting
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diposting_oleh');
    }
}
