<?php

namespace App\Models\Concerns;

/**
 * Status sinkronisasi integrasi LMS -> PresensiV2.
 *
 * Nilai-nilai ini merupakan *closed-set domain* (tidak memiliki tabel master),
 * sehingga dideklarasikan sebagai konstanta terpusat — bukan string literal
 * yang diulang di controller/service/frontend.
 *
 * Dipakai oleh model yang memiliki kolom `sync_status`:
 *  - App\Models\Siakad\Pertemuan
 *  - App\Models\Siakad\AbsensiMahasiswa
 */
trait HasPresensiSyncStatus
{
    public const SYNC_LOCAL = 'local';
    public const SYNC_PENDING = 'pending';
    public const SYNC_SYNCED = 'synced';
    public const SYNC_FAILED = 'failed';

    public const SYNC_STATUSES = [
        self::SYNC_LOCAL,
        self::SYNC_PENDING,
        self::SYNC_SYNCED,
        self::SYNC_FAILED,
    ];

    /**
     * Label Bahasa Indonesia untuk setiap status sinkron.
     *
     * @return array<string, string>
     */
    public static function syncStatusLabels(): array
    {
        return [
            self::SYNC_LOCAL => 'Hanya di LMS',
            self::SYNC_PENDING => 'Menunggu Sinkron',
            self::SYNC_SYNCED => 'Tersinkron',
            self::SYNC_FAILED => 'Gagal Sinkron',
        ];
    }
}
