<?php

namespace App\Services\Spmb;

use App\Models\Spmb\GelombangPenerimaan;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Menentukan gelombang pendaftaran berdasarkan jalur masuk.
 *
 * Aturan: pilih jalur lebih dulu. Bila ada gelombang aktif yang dipetakan ke jalur itu,
 * gelombang tersebut dipakai. Bila tidak ada, pilihan berupa gelombang aktif yang tidak
 * dipetakan ke jalur mana pun (gelombang umum).
 */
class SpmbGelombangResolver
{
    public const KONTEKS_MANUAL = 'manual';

    public const KONTEKS_ONLINE = 'online';

    public const MODE_OTOMATIS = 'otomatis';

    public const MODE_PILIH = 'pilih';

    public const MODE_TIDAK_TERSEDIA = 'tidak_tersedia';

    /**
     * @param  bool  $requireOpen  true untuk pendaftaran online (harus dalam periode buka).
     * @return array{mode: string, gelombang: ?GelombangPenerimaan, options: Collection<int, GelombangPenerimaan>, sumber: string}
     */
    public function forJalur(int $jalurMasukId, bool $requireOpen = false): array
    {
        $active = fn () => GelombangPenerimaan::query()
            ->with('jalurMasuk')
            ->where('status', GelombangPenerimaan::STATUS_AKTIF)
            ->when($requireOpen, fn ($q) => $q->pendaftaranDibuka())
            ->orderBy('id');

        $mapped = $active()->untukJalur($jalurMasukId)->get();

        if ($mapped->count() === 1) {
            return ['mode' => self::MODE_OTOMATIS, 'gelombang' => $mapped->first(), 'options' => collect(), 'sumber' => 'terpetakan'];
        }

        if ($mapped->count() > 1) {
            return ['mode' => self::MODE_PILIH, 'gelombang' => null, 'options' => $mapped, 'sumber' => 'terpetakan'];
        }

        $umum = $active()->umum()->get();

        return [
            'mode' => $umum->isEmpty() ? self::MODE_TIDAK_TERSEDIA : self::MODE_PILIH,
            'gelombang' => null,
            'options' => $umum,
            'sumber' => 'umum',
        ];
    }

    /**
     * Gelombang boleh dipakai bila umum (tanpa jalur) atau dipetakan ke jalur yang sama.
     *
     * @throws ValidationException
     */
    public function assertCompatible(?GelombangPenerimaan $gelombang, ?int $jalurMasukId, string $field = 'gelombang_id'): void
    {
        if (! $gelombang || ! $jalurMasukId || $gelombang->jalur_masuk_id === null) {
            return;
        }

        if ((int) $gelombang->jalur_masuk_id !== (int) $jalurMasukId) {
            throw ValidationException::withMessages([
                $field => 'Gelombang yang dipilih dipetakan ke jalur lain. Pilih gelombang umum atau gelombang untuk jalur ini.',
            ]);
        }
    }
}
