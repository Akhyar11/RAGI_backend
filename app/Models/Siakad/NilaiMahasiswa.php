<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class NilaiMahasiswa extends Model
{
    use SoftDeletes;

    protected $table = 'siakad_nilai_mahasiswa';

    protected $fillable = [
        'krs_detail_id',
        'nilai_harian',
        'nilai_uts',
        'nilai_uas',
        'nilai_praktik',
        'nilai_akhir',
        'nilai_huruf',
        'bobot_mutu',
        'is_final',
        'diinput_oleh',
    ];

    protected $casts = [
        'nilai_harian' => 'decimal:2',
        'nilai_uts' => 'decimal:2',
        'nilai_uas' => 'decimal:2',
        'nilai_praktik' => 'decimal:2',
        'nilai_akhir' => 'decimal:2',
        'bobot_mutu' => 'decimal:2',
        'is_final' => 'boolean',
    ];

    public function krsDetail()
    {
        return $this->belongsTo(KrsDetail::class, 'krs_detail_id');
    }

    public function diinputOleh()
    {
        return $this->belongsTo(User::class, 'diinput_oleh');
    }
}
