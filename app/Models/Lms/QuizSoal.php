<?php

namespace App\Models\Lms;

use App\Models\Siakad\BankSoal;
use Illuminate\Database\Eloquent\Model;

class QuizSoal extends Model
{
    protected $table = 'lms_quiz_soal';

    protected $fillable = [
        'quiz_id',
        'bank_soal_id',
        'urutan',
        'poin',
    ];

    protected $casts = [
        'urutan' => 'integer',
        'poin' => 'decimal:2',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    public function bankSoal()
    {
        return $this->belongsTo(BankSoal::class, 'bank_soal_id');
    }

    public function jawabanAttempt()
    {
        return $this->hasMany(QuizAttemptJawaban::class, 'quiz_soal_id');
    }
}
