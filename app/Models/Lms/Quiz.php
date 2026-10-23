<?php

namespace App\Models\Lms;

use App\Models\Siakad\KomponenPenilaian;
use App\Models\Siakad\Pertemuan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quiz extends Model
{
    use SoftDeletes;

    protected $table = 'lms_quiz';

    protected $fillable = [
        'pertemuan_id',
        'kelas_id',
        'tipe',
        'komponen_penilaian_id',
        'judul',
        'deskripsi',
        'durasi_menit',
        'max_attempt',
        'passing_score',
        'acak_soal',
        'acak_jawaban',
        'batch_size',
        'dibuka_at',
        'ditutup_at',
        'is_published',
        'kode_akses',
        'is_archived',
        'dibuat_oleh',
    ];

    protected $casts = [
        'max_attempt' => 'integer',
        'passing_score' => 'decimal:2',
        'durasi_menit' => 'integer',
        'batch_size' => 'integer',
        'acak_soal' => 'boolean',
        'acak_jawaban' => 'boolean',
        'is_published' => 'boolean',
        'is_archived' => 'boolean',
        'dibuka_at' => 'datetime',
        'ditutup_at' => 'datetime',
    ];

    /**
     * Jenis quiz (closed-set domain, bukan entitas master).
     *
     * Dideklarasikan sebagai konstanta terpusat agar tidak ada string literal
     * yang disalin di service/controller.
     */
    public const TIPE_KUIS = 'kuis';

    public const TIPE_TRYOUT = 'tryout';

    /**
     * @var array<int, string>
     */
    public const TIPE = [
        self::TIPE_KUIS,
        self::TIPE_TRYOUT,
    ];

    public function pertemuan()
    {
        return $this->belongsTo(Pertemuan::class, 'pertemuan_id');
    }

    public function kelas()
    {
        return $this->belongsTo(\App\Models\Siakad\Kelas::class, 'kelas_id');
    }

    public function tryoutPeserta()
    {
        return $this->hasMany(TryoutPeserta::class, 'quiz_id');
    }

    public function komponenPenilaian()
    {
        return $this->belongsTo(KomponenPenilaian::class, 'komponen_penilaian_id');
    }

    public function soal()
    {
        return $this->hasMany(QuizSoal::class, 'quiz_id')->orderBy('urutan');
    }

    public function attempts()
    {
        return $this->hasMany(QuizAttempt::class, 'quiz_id');
    }

    public function kolaborators()
    {
        return $this->hasMany(QuizKolaborator::class, 'quiz_id');
    }

    /**
     * Batch size efektif: override per quiz, fallback ke setting global.
     */
    public function effectiveBatchSize(): int
    {
        if ($this->batch_size && $this->batch_size > 0) {
            return $this->batch_size;
        }

        $global = (int) \App\Models\SystemSetting::get('lms_quiz_batch_size', 10);

        return $global > 0 ? $global : 10;
    }

    /**
     * Status kelulusan untuk sebuah nilai akhir.
     *
     * Mengembalikan `null` bila quiz tidak menetapkan ambang kelulusan,
     * sehingga pemanggil dapat membedakan "tidak lulus" dari "tidak dinilai".
     */
    public function isLulus(?float $nilai): ?bool
    {
        if ($nilai === null || $this->passing_score === null) {
            return null;
        }

        return $nilai >= (float) $this->passing_score;
    }

    /**
     * True bila quiz sedang dalam jendela pengerjaan (atau tanpa jendela).
     */
    public function isDalamJendela(): bool
    {
        $now = now();

        if ($this->dibuka_at && $now->lt($this->dibuka_at)) {
            return false;
        }
        if ($this->ditutup_at && $now->gt($this->ditutup_at)) {
            return false;
        }

        return true;
    }
}
