<?php

namespace App\Models\Lms;

use App\Models\Siakad\BankSoalOpsi;
use Illuminate\Database\Eloquent\Model;

class QuizAttemptJawaban extends Model
{
    protected $table = 'lms_quiz_attempt_jawaban';

    protected $fillable = [
        'attempt_id',
        'quiz_soal_id',
        'bank_opsi_id',
        'jawaban_teks',
        'is_benar',
        'poin_didapat',
        'feedback_dosen',
    ];

    protected $casts = [
        'is_benar' => 'boolean',
        'poin_didapat' => 'decimal:2',
    ];

    public function attempt()
    {
        return $this->belongsTo(QuizAttempt::class, 'attempt_id');
    }

    public function quizSoal()
    {
        return $this->belongsTo(QuizSoal::class, 'quiz_soal_id');
    }

    public function opsiDipilih()
    {
        return $this->belongsTo(BankSoalOpsi::class, 'bank_opsi_id');
    }
}
