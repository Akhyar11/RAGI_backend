<?php

namespace App\Services\Sikeu;

class VaNumberService
{
    public const DEFAULT_PREFIX = '88012';

    /**
     * Generate standard bank VA number for student / prospective student.
     * Prefix: 88012 (or custom bank prefix)
     * Body: sanitized numeric digits of NIM, No Pendaftaran, or ID padded to 10 digits.
     */
    public static function generate(string|int|null $identifier, string $prefix = self::DEFAULT_PREFIX): string
    {
        $cleanDigits = preg_replace('/[^0-9]/', '', (string)$identifier);

        if (empty($cleanDigits)) {
            $cleanDigits = '0000000001';
        }

        $padded = str_pad($cleanDigits, 10, '0', STR_PAD_LEFT);

        return $prefix . $padded;
    }
}
