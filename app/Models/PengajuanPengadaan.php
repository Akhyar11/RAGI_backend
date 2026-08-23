<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PengajuanPengadaan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sinapra_pengajuan_pengadaan';

    protected $fillable = [
        'unit_kerja_id',
        'diajukan_oleh',
        'judul',
        'alasan_kebutuhan',
        'tanggal_pengajuan',
        'estimasi_anggaran',
        'status',
        'disetujui_oleh',
    ];

    protected $casts = [
        'tanggal_pengajuan' => 'date',
        'estimasi_anggaran' => 'decimal:2',
    ];

    /**
     * Relasi ke Unit Kerja
     */
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Simpeg\UnitKerja::class, 'unit_kerja_id');
    }

    /**
     * Relasi ke User Pengaju
     */
    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    /**
     * Relasi ke User Approver
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    /**
     * Relasi ke Rincian Detail Pengadaan
     */
    public function details(): HasMany
    {
        return $this->hasMany(DetailPengadaan::class, 'pengajuan_id');
    }
}
