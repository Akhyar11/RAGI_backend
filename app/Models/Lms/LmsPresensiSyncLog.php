<?php

namespace App\Models\Lms;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Jejak sinkronisasi LMS -> PresensiV2.
 *
 * Setiap percobaan push/pull ke PresensiV2 (berhasil maupun gagal) dicatat di
 * sini sebagai bookkeeping, sehingga admin dapat melihat sesi mana yang masih
 * gagal dan memicu percobaan ulang.
 */
class LmsPresensiSyncLog extends Model
{
    protected $table = 'lms_presensi_sync_log';

    protected $fillable = [
        'entitas',
        'entitas_id',
        'kelas_id',
        'pertemuan_ke',
        'aksi',
        'status',
        'via',
        'message',
        'payload',
        'user_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'pertemuan_ke' => 'integer',
    ];

    /** Entitas lokal yang disinkronkan. */
    public const ENTITAS_PERTEMUAN = 'pertemuan';
    public const ENTITAS_ABSENSI = 'absensi';

    /** Status percobaan sinkron. */
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_SKIPPED = 'skipped';

    /** Transport yang dipakai. */
    public const VIA_API = 'api';
    public const VIA_DB = 'db';
    public const VIA_LOCAL = 'local';
    public const VIA_NONE = 'none';

    /** Aksi sinkron. */
    public const AKSI_BUKA_JURNAL = 'buka_jurnal';
    public const AKSI_HADIR = 'hadir';
    public const AKSI_HAPUS_HADIR = 'hapus_hadir';
    public const AKSI_PUSH_KEHADIRAN = 'push_kehadiran';
    public const AKSI_RESET = 'reset';
    public const AKSI_TARIK = 'tarik';

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
