<?php

namespace App\Models\Simpeg;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenilaianKinerja extends Model
{
    use HasFactory;

    protected $table = 'penilaian_kinerja';

    protected $fillable = [
        'pegawai_id',
        'tahun',
        'semester',
        'nilai_skp',
        'nilai_bkd',
        'predikat',
        'catatan_evaluator',
        'evaluator_id',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}
