<?php

namespace App\Services\Siakad;

use App\Models\Siakad\KartuUjianToken;
use App\Models\Siakad\Mahasiswa;
use App\Models\Sikeu\DispensasiTagihan;
use App\Models\Sikeu\TagihanMahasiswa;
use Illuminate\Support\Str;

class KartuUjianService
{
    /**
     * Syarat kartu ujian = lunas SIKEU atau dispensasi approved dengan izin KRS
     * (aturan yang sama dengan gate KRS agar konsisten).
     */
    public function assertKeuanganLunas(Mahasiswa $mhs): void
    {
        $hasUnpaidBills = TagihanMahasiswa::where('mahasiswa_id', $mhs->id)
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->exists();
        if (!$hasUnpaidBills) {
            return;
        }
        $hasApprovedDispensasi = DispensasiTagihan::berlakuUntukKrs()
            ->where('mahasiswa_id', $mhs->id)
            ->whereHas('tagihan', fn($q) => $q->whereIn('status', ['belum_bayar', 'sebagian', 'dispensasi']))
            ->exists();
        if (!$hasApprovedDispensasi) {
            abort(response()->json([
                'status' => 'error',
                'message' => 'Kartu ujian tidak dapat dicetak karena Anda memiliki tagihan SIKEU yang belum diselesaikan. Silakan lunasi tagihan atau ajukan dispensasi keuangan.',
            ], 422));
        }
    }

    public function isKeuanganLunas(Mahasiswa $mhs): bool
    {
        try {
            $this->assertKeuanganLunas($mhs);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Terbitkan (atau segarkan) token kartu ujian. Mencetak ulang akan
     * memperbarui tanggal cetak + metadata perangkat.
     */
    public function terbitkan(Mahasiswa $mhs, int $tahunAkademikId, string $tipeUjian, ?string $ip = null, ?string $userAgent = null): KartuUjianToken
    {
        if ($mhs->status !== 'aktif') {
            abort(response()->json([
                'status' => 'error',
                'message' => "Kartu ujian ditolak. Status akademik '{$mhs->status}'.",
            ], 422));
        }
        $this->assertKeuanganLunas($mhs);

        return KartuUjianToken::updateOrCreate(
            ['mahasiswa_id' => $mhs->id, 'tahun_akademik_id' => $tahunAkademikId, 'tipe_ujian' => $tipeUjian],
            ['token' => Str::random(48), 'tanggal_cetak' => now(), 'ip' => $ip, 'user_agent' => $userAgent ? substr($userAgent, 0, 255) : null, 'is_active' => true]
        );
    }
}
