<?php

namespace App\Models\Lms;

use App\Models\Siakad\Mahasiswa;
use Illuminate\Database\Eloquent\Model;

class QuizAttempt extends Model
{
    protected $table = 'lms_quiz_attempt';

    protected $fillable = [
        'quiz_id',
        'mahasiswa_id',
        'attempt_ke',
        'status',
        'dimulai_at',
        'disubmit_at',
        'nilai_akhir',
        'butuh_penilaian_manual',
        'dinilai_oleh',
        'dinilai_at',
    ];

    protected $casts = [
        'attempt_ke' => 'integer',
        'nilai_akhir' => 'decimal:2',
        'butuh_penilaian_manual' => 'boolean',
        'dimulai_at' => 'datetime',
        'disubmit_at' => 'datetime',
        'dinilai_at' => 'datetime',
    ];

    /**
     * Status kelulusan dihitung dari `passing_score` quiz induk.
     * `null` = ambang belum ditetapkan atau attempt belum memiliki nilai.
     *
     * @var array<int, string>
     */
    protected $appends = ['is_lulus'];

    public function getIsLulusAttribute(): ?bool
    {
        if ($this->nilai_akhir === null) {
            return null;
        }

        return $this->quiz?->isLulus((float) $this->nilai_akhir);
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }

    public function jawaban()
    {
        return $this->hasMany(QuizAttemptJawaban::class, 'attempt_id');
    }

    public function isBerlangsung(): bool
    {
        return $this->status === 'berlangsung';
    }
}
