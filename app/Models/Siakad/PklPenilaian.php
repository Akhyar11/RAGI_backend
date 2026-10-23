<?php

namespace App\Models\Siakad;

use App\Models\User;
use App\Models\Siakad\Dosen;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PklPenilaian extends Model
{
    use HasFactory;

    protected $table = 'siakad_pkl_penilaian';

    protected $fillable = [
        'pkl_id',
        'penilai_dosen_id',
        'nilai_lapangan_kedisiplinan',
        'nilai_lapangan_kinerja',
        'nilai_lapangan_kemampuan_teknis',
        'nilai_lapangan_inisiatif',
        'nilai_lapangan_total',
        'berkas_nilai_lapangan',
        'catatan_lapangan',
        'nilai_dosen_bimbingan',
        'nilai_dosen_laporan',
        'nilai_dosen_presentasi',
        'nilai_dosen_total',
        'catatan_dosen',
        'bobot_lapangan_persen',
        'bobot_dosen_persen',
        'nilai_angka_akhir',
        'nilai_huruf_akhir',
        'bobot_mutu_akhir',
        'is_finalized',
        'finalized_at',
        'finalized_by',
    ];

    protected $casts = [
        'nilai_lapangan_kedisiplinan' => 'decimal:2',
        'nilai_lapangan_kinerja' => 'decimal:2',
        'nilai_lapangan_kemampuan_teknis' => 'decimal:2',
        'nilai_lapangan_inisiatif' => 'decimal:2',
        'nilai_lapangan_total' => 'decimal:2',
        'nilai_dosen_bimbingan' => 'decimal:2',
        'nilai_dosen_laporan' => 'decimal:2',
        'nilai_dosen_presentasi' => 'decimal:2',
        'nilai_dosen_total' => 'decimal:2',
        'bobot_lapangan_persen' => 'decimal:2',
        'bobot_dosen_persen' => 'decimal:2',
        'nilai_angka_akhir' => 'decimal:2',
        'bobot_mutu_akhir' => 'decimal:2',
        'is_finalized' => 'boolean',
        'finalized_at' => 'datetime',
    ];

    public function pkl(): BelongsTo
    {
        return $this->belongsTo(Pkl::class, 'pkl_id');
    }

    public function penilaiDosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'penilai_dosen_id');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
