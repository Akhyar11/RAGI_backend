<?php

namespace App\Models\Spmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Spmb\JalurMasuk;

class MasterBiaya extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'spmb_master_biaya';

    protected $fillable = [
        'jalur_masuk_id',
        'program_studi_id',
        'nomor_sk',
        'dokumen_pendukung_path',
        'total_biaya',
        'is_active',
        'keterangan',
    ];

    public function arsipNomorSurat()
    {
        return $this->belongsTo(\App\Models\Arsip\NomorSurat::class, 'nomor_sk', 'nomor_surat');
    }

    protected $appends = ['dokumen_pendukung_url'];

    public function getDokumenPendukungUrlAttribute(): ?string
    {
        return $this->dokumen_pendukung_path
            ? app(\App\Services\Storage\FileStorageService::class)->signedUrl($this->dokumen_pendukung_path)
            : null;
    }

    protected $casts = [
        'total_biaya' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(MasterBiayaItem::class, 'master_biaya_id');
    }

    public function jalurMasuk()
    {
        return $this->belongsTo(JalurMasuk::class, 'jalur_masuk_id');
    }

    public function programStudi()
    {
        return $this->belongsTo(MasterProgramStudi::class, 'program_studi_id');
    }

    public function recalculateTotal(): void
    {
        $total = $this->items()->sum('nominal');
        $this->update(['total_biaya' => $total]);
    }
}
