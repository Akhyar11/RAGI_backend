<?php

namespace App\Models\Siakad;

final class MahasiswaStatus
{
    public const AKTIF = 'A';
    public const CUTI = 'C';
    public const DOUBLE_DEGREE = 'G';
    public const KAMPUS_MERDEKA = 'M';
    public const NON_AKTIF = 'N';
    public const LULUS = 'L';
    public const MENUNGGU_UJIAN = 'U';

    public const LABELS = [
        self::AKTIF => 'Aktif',
        self::CUTI => 'Cuti',
        self::DOUBLE_DEGREE => 'Sedang Double Degree',
        self::KAMPUS_MERDEKA => 'Kampus Merdeka',
        self::NON_AKTIF => 'Non-Aktif',
        self::LULUS => 'Lulus',
        self::MENUNGGU_UJIAN => 'Menunggu Ujian',
    ];

    public static function codes(): array
    {
        return array_keys(self::LABELS);
    }

    public static function label(mixed $code): ?string
    {
        return self::LABELS[strtoupper(trim((string) $code))] ?? null;
    }

    public static function isActive(mixed $code): bool
    {
        return strtoupper(trim((string) $code)) === self::AKTIF;
    }
}