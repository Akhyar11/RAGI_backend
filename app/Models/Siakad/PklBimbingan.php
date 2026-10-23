<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PklBimbingan extends Model
{
    use HasFactory;

    protected $table = 'siakad_pkl_bimbingan';

    public const STATUS_PENDING = 'pending';
    public const STATUS_DISETUJUI = 'disetujui';
    public const STATUS_REVISI = 'revisi';

    protected $fillable = [
        'pkl_id',
        'tanggal',
        'aktivitas',
        'target_capaian',
        'kendala',
        'file_lampiran',
        'status_verifikasi',
        'catatan_dosen',
        'diverifikasi_oleh_dosen_at',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'diverifikasi_oleh_dosen_at' => 'datetime',
    ];

    public function pkl(): BelongsTo
    {
        return $this->belongsTo(Pkl::class, 'pkl_id');
    }
}
