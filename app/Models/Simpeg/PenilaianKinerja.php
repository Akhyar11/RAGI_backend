<?php

namespace App\Models\Simpeg;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenilaianKinerja extends Model
{
    use HasFactory;

    protected $table = 'simpeg_penilaian_kinerja';

    protected $fillable = [
        'pegawai_id',
        'tahun',
        'semester',
        'status',
        'pejabat_penilai_id',
        'tanggal_pengajuan',
        'tanggal_persetujuan',
        'nilai_skp',
        'nilai_bkd',
        'predikat',
        'catatan_evaluator',
        'evaluator_id',
        'evaluated_at',
    ];

    protected $casts = [
        'tahun' => 'integer',
        'nilai_skp' => 'decimal:2',
        'nilai_bkd' => 'decimal:2',
        'tanggal_pengajuan' => 'datetime',
        'tanggal_persetujuan' => 'datetime',
        'evaluated_at' => 'datetime',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function pejabatPenilai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pejabat_penilai_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SkpItem::class, 'penilaian_kinerja_id');
    }
}
