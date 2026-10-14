<?php

namespace App\Services\Siakad;

use App\Models\Siakad\DokumenToken;
use App\Models\Siakad\Khs;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\NilaiMahasiswa;
use App\Models\Siakad\SkalaNilai;
use Illuminate\Support\Str;

class DokumenService
{
    /**
     * Terbitkan (atau segarkan) dokumen resmi + snapshot anti-tamper.
     * Nomor stabil per (jenis, mhs, periode); token + snapshot diperbarui.
     */
    public function terbitkan(string $jenis, Mahasiswa $mhs, ?int $tahunAkademikId): DokumenToken
    {
        $snapshot = $jenis === DokumenToken::JENIS_KHS
            ? $this->snapshotKhs((int) $mhs->id, (int) $tahunAkademikId)
            : $this->snapshotTranskrip((int) $mhs->id);

        $nomor = $jenis === DokumenToken::JENIS_KHS
            ? "KHS/{$mhs->nim}/{$tahunAkademikId}/" . now()->format('Y')
            : "TRX/{$mhs->nim}/KUM/" . now()->format('Y');

        return DokumenToken::updateOrCreate(
            ['jenis' => $jenis, 'mahasiswa_id' => $mhs->id, 'tahun_akademik_id' => $tahunAkademikId],
            [
                'nomor_dokumen' => $nomor,
                'token' => Str::random(48),
                'snapshot' => $snapshot,
                'tanggal_terbit' => now(),
                'is_active' => true,
            ]
        );
    }

    protected function snapshotKhs(int $mhsId, int $taId): array
    {
        $khs = Khs::where('mahasiswa_id', $mhsId)->where('tahun_akademik_id', $taId)->first();
        if (!$khs) {
            abort(response()->json([
                'status' => 'error',
                'message' => 'Belum ada KHS tersimpan untuk periode ini (nilai belum final).',
            ], 422));
        }
        return [
            'ips' => (float) $khs->ips,
            'sks_semester' => (int) $khs->total_sks_semester,
            'sks_kumulatif' => (int) $khs->sks_kumulatif,
            'ipk' => (float) $khs->ipk,
            'is_locked' => (bool) $khs->is_locked,
        ];
    }

    /**
     * Ringkasan transkrip best-grade (aturan sama dengan endpoint transkrip:
     * transfer D/E dilewati, reguler E tak lulus, IPK atas total SKS diambil).
     */
    protected function snapshotTranskrip(int $mhsId): array
    {
        $mhs = Mahasiswa::with(['konversiTransfer.details.mataKuliahDiakui'])->findOrFail($mhsId);
        $totalDiambil = 0;
        $totalLulus = 0;
        $totalMutu = 0;
        $jumlahMk = 0;

        if ($mhs->konversiTransfer && $mhs->konversiTransfer->status === 'disetujui' && $mhs->konversiTransfer->details) {
            foreach ($mhs->konversiTransfer->details as $konv) {
                $huruf = strtoupper(trim((string) $konv->nilai_huruf_asal));
                if (in_array($huruf, ['D', 'E', ''], true)) {
                    continue;
                }
                $mk = $konv->mataKuliahDiakui;
                $sks = $mk ? $mk->total_sks : $konv->sks_asal;
                $mutu = $this->mutuHuruf($huruf);
                $totalDiambil += $sks;
                $totalLulus += $sks;
                $totalMutu += $mutu * $sks;
                $jumlahMk++;
            }
        }

        $nilaiList = NilaiMahasiswa::with('krsDetail.kelas.mataKuliah')
            ->whereHas('krsDetail.krs', fn($q) => $q->where('mahasiswa_id', $mhsId))
            ->where('is_final', true)
            ->get();
        $best = [];
        foreach ($nilaiList as $n) {
            $mkId = $n->krsDetail?->kelas?->mataKuliah?->id ?? ('krs-' . $n->krs_detail_id);
            if (!isset($best[$mkId]) || (float) $n->bobot_mutu > (float) $best[$mkId]->bobot_mutu) {
                $best[$mkId] = $n;
            }
        }
        foreach ($best as $n) {
            $sks = $n->krsDetail?->kelas?->mataKuliah?->total_sks ?? 3;
            $totalDiambil += $sks;
            if (($n->nilai_huruf ?? 'E') !== 'E') {
                $totalLulus += $sks;
            }
            $totalMutu += (float) $n->bobot_mutu * $sks;
            $jumlahMk++;
        }

        $ipk = $totalDiambil > 0 ? round($totalMutu / $totalDiambil, 2) : 0.00;
        return [
            'total_sks' => $totalDiambil,
            'total_sks_lulus' => $totalLulus,
            'ipk' => $ipk,
            'jumlah_mk' => $jumlahMk,
            'predikat' => $ipk >= 3.51 ? 'Dengan Pujian (Cum Laude)' : ($ipk >= 3.01 ? 'Sangat Memuaskan' : 'Memuaskan'),
        ];
    }

    protected function mutuHuruf(string $huruf): float
    {
        $skala = SkalaNilai::where('is_active', true)->where('nilai_huruf', $huruf)
            ->orderByRaw('program_studi_id IS NULL')->first();
        if ($skala) {
            return (float) $skala->bobot_indeks;
        }
        return match ($huruf) {
            'A' => 4.00, 'A-' => 3.75, 'B+' => 3.25, 'B' => 3.00,
            'B-' => 2.75, 'C+' => 2.25, 'C' => 2.00, 'D' => 1.00,
            default => 0.00,
        };
    }
}
