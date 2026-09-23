<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AlatKalibrasi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sinapra_alat_kalibrasi';

    protected $fillable = [
        'aset_id',
        'institusi_kalibrasi',
        'nomor_sertifikat',
        'tanggal_kalibrasi',
        'tanggal_kadaluarsa',
        'status_kelayakan',
        'catatan',
    ];

    protected $casts = [
        'tanggal_kalibrasi' => 'date',
        'tanggal_kadaluarsa' => 'date',
    ];

    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class, 'aset_id');
    }
}
