<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\DokumenPegawai;
use Illuminate\Support\Facades\URL;

class FileSecurityService
{
    /**
     * Generate a secure, time-limited signed URL for document access.
     */
    public static function generateSignedUrl(DokumenPegawai $dokumen, int $expirationMinutes = 15): string
    {
        return URL::temporarySignedRoute(
            'simpeg.dokumen.download',
            now()->addMinutes($expirationMinutes),
            ['id' => $dokumen->id]
        );
    }

    /**
     * Get watermark metadata text to overlay on the document.
     */
    public static function getWatermarkText(DokumenPegawai $dokumen): string
    {
        $pegawaiNama = $dokumen->pegawai ? $dokumen->pegawai->nama_lengkap : 'Pegawai Kampus';
        $timestamp = now()->format('Y-m-d H:i:s');
        return "RAGI CAMPUS OFFICIAL • CONFIDENTIAL • {$pegawaiNama} • {$timestamp}";
    }
}
