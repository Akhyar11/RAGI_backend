<?php

namespace App\Models\Lms;

use App\Models\Siakad\Dosen;
use Illuminate\Database\Eloquent\Model;

class QuizKolaborator extends Model
{
    protected $table = 'lms_quiz_kolaborator';

    /**
     * Closed-set peran kolaborator quiz (domain tetap, tanpa tabel master).
     */
    public const PERAN_PENGAWAS = 'pengawas';
    public const PERAN_PEMANTAU = 'pemantau';
    public const PERAN_PENGINPUT_SOAL = 'penginput_soal';

    public const PERAN = [
        self::PERAN_PENGAWAS,
        self::PERAN_PEMANTAU,
        self::PERAN_PENGINPUT_SOAL,
    ];

    protected $fillable = [
        'quiz_id',
        'dosen_id',
        'peran',
        'ditambah_oleh',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    public function dosen()
    {
        return $this->belongsTo(Dosen::class, 'dosen_id');
    }
}
