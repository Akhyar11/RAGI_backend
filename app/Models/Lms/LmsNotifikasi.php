<?php

namespace App\Models\Lms;

use App\Models\Siakad\Kelas;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Notifikasi in-app modul LMS.
 *
 * Menyimpan pengingat (deadline tugas/quiz, tugas baru, kuis baru, aktivitas
 * forum) sebagai record agar status baca/unread konsisten antar perangkat.
 */
class LmsNotifikasi extends Model
{
    protected $table = 'lms_notifikasi';

    protected $fillable = [
        'user_id',
        'jenis',
        'judul',
        'pesan',
        'rute',
        'kelas_id',
        'entitas_type',
        'entitas_id',
        'prioritas',
        'is_read',
        'read_at',
        'dedup_key',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * `dedup_key` adalah internal (mencegah notifikasi ganda) — tidak
     * diekspos ke client.
     *
     * @var array<int, string>
     */
    protected $hidden = ['dedup_key'];

    /**
     * Jenis notifikasi (closed-set domain, bukan entitas master).
     */
    public const JENIS_DEADLINE_TUGAS = 'deadline_tugas';

    public const JENIS_DEADLINE_QUIZ = 'deadline_quiz';

    public const JENIS_TUGAS_BARU = 'tugas_baru';

    public const JENIS_QUIZ_BARU = 'quiz_baru';

    public const JENIS_FORUM = 'forum';

    public const JENIS_INFO = 'info';

    /**
     * @var array<int, string>
     */
    public const JENIS = [
        self::JENIS_DEADLINE_TUGAS,
        self::JENIS_DEADLINE_QUIZ,
        self::JENIS_TUGAS_BARU,
        self::JENIS_QUIZ_BARU,
        self::JENIS_FORUM,
        self::JENIS_INFO,
    ];

    /**
     * Prioritas notifikasi (closed-set domain).
     */
    public const PRIORITAS_RENDAH = 'rendah';

    public const PRIORITAS_NORMAL = 'normal';

    public const PRIORITAS_TINGGI = 'tinggi';

    /**
     * @var array<int, string>
     */
    public const PRIORITAS = [
        self::PRIORITAS_RENDAH,
        self::PRIORITAS_NORMAL,
        self::PRIORITAS_TINGGI,
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }
}
