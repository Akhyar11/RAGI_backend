<?php

namespace App\Services\Spmb;

use App\Models\Spmb\MasterKomponenBiaya;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\PotonganCalon;
use Illuminate\Support\Facades\DB;

class SpmbPotonganCalonService
{
    /**
     * Simpan potongan kustom baru untuk seorang calon mahasiswa.
     * Setiap potongan menunjuk tepat satu komponen biaya.
     */
    public function create(PendaftaranCalonMhs $pendaftaran, array $data): PotonganCalon
    {
        return DB::transaction(function () use ($pendaftaran, $data) {
            $komponen = MasterKomponenBiaya::findOrFail($data['komponen_biaya_id']);

            $potongan = PotonganCalon::create([
                'pendaftaran_id' => $pendaftaran->id,
                'komponen_biaya_id' => $komponen->id,
                'nama_komponen' => $komponen->nama,
                'nama_potongan' => $data['nama_potongan'],
                'tipe_potongan' => $data['tipe_potongan'],
                'nilai_potongan' => $data['nilai_potongan'],
                'tahap' => $data['tahap'],
                'nomor_sk' => $data['nomor_sk'] ?? null,
                'keterangan' => $data['keterangan'] ?? null,
                'berlaku_mulai' => $data['berlaku_mulai'] ?? null,
                'berlaku_sampai' => $data['berlaku_sampai'] ?? null,
                'status' => $data['status'] ?? 'aktif',
                'dibuat_oleh' => auth()->id(),
            ]);

            return $potongan->load(['komponenBiaya', 'pembuat:id,name,username']);
        });
    }

    public function update(PotonganCalon $potongan, array $data): PotonganCalon
    {
        return DB::transaction(function () use ($potongan, $data) {
            if (! empty($data['komponen_biaya_id']) && (int) $data['komponen_biaya_id'] !== (int) $potongan->komponen_biaya_id) {
                $komponen = MasterKomponenBiaya::findOrFail($data['komponen_biaya_id']);
                $data['nama_komponen'] = $komponen->nama;
            }

            $potongan->update($data);

            return $potongan->fresh()->load(['komponenBiaya', 'pembuat:id,name,username']);
        });
    }

    public function delete(PotonganCalon $potongan): bool
    {
        return (bool) $potongan->delete();
    }

    /**
     * Susun payload potongan untuk dikirim ke SIKEU (ExternalTagihanService)
     * berdasarkan potongan aktif calon pada tahap tertentu.
     *
     * @param  array<int, array{komponen_biaya_id?: int|null, nominal?: float, ...}>  $details
     * @return array<int, array{tipe: string, nominal_potongan: float, keterangan: string}>
     */
    public function buildPotonganPayload(PendaftaranCalonMhs $pendaftaran, string $tahap, array $details): array
    {
        $potonganList = PotonganCalon::query()
            ->where('pendaftaran_id', $pendaftaran->id)
            ->aktif()
            ->berlaku()
            ->untukTahap($tahap)
            ->get();

        if ($potonganList->isEmpty()) {
            return [];
        }

        // Map detail komponen biaya -> nominal
        $detailMap = collect($details)
            ->filter(fn ($d) => ! empty($d['komponen_biaya_id']))
            ->keyBy(fn ($d) => (int) $d['komponen_biaya_id']);

        $payload = [];

        foreach ($potonganList as $potongan) {
            $detail = $detailMap->get((int) $potongan->komponen_biaya_id);
            if (! $detail) {
                continue;
            }

            $base = (float) ($detail['nominal'] ?? 0);
            if ($base <= 0) {
                continue;
            }

            $nominal = $potongan->tipe_potongan === 'persen'
                ? round($base * ((float) $potongan->nilai_potongan / 100), 2)
                : (float) $potongan->nilai_potongan;

            $nominal = min($nominal, $base);

            if ($nominal <= 0) {
                continue;
            }

            $payload[] = [
                'tipe' => 'diskon',
                'nominal_potongan' => $nominal,
                'keterangan' => $potongan->nama_potongan . ($potongan->nomor_sk ? ' (SK: ' . $potongan->nomor_sk . ')' : ''),
            ];
        }

        return $payload;
    }

    /**
     * Susun payload potongan DAFTAR ULANG yang menggabungkan:
     * 1. Diskon default gelombang (`potongan_biaya_daftar_ulang`, %) untuk komponen `berlaku_diskon`.
     * 2. Potongan kustom per calon (stacking, menunjuk 1 komponen).
     * Total per komponen dibatasi maksimal sebesar nominal komponen.
     *
     * @param  array<int, array{komponen_biaya_id?: int|null, nominal?: float, berlaku_diskon?: bool, keterangan?: string}>  $details
     * @return array<int, array{tipe: string, nominal_potongan: float, keterangan: string}>
     */
    public function buildDaftarUlangPotonganPayload(PendaftaranCalonMhs $pendaftaran, array $details): array
    {
        $gelombangPct = (float) ($pendaftaran->gelombangPenerimaan?->potongan_biaya_daftar_ulang ?? 0);

        $customList = PotonganCalon::query()
            ->where('pendaftaran_id', $pendaftaran->id)
            ->aktif()
            ->berlaku()
            ->untukTahap('daftar_ulang')
            ->get()
            ->groupBy('komponen_biaya_id');

        $payload = [];

        foreach ($details as $detail) {
            $komponenId = $detail['komponen_biaya_id'] ?? null;
            if (empty($komponenId)) {
                continue;
            }

            $base = (float) ($detail['nominal'] ?? 0);
            if ($base <= 0) {
                continue;
            }

            $parts = [];
            $diskon = 0.0;

            // 1. Diskon default gelombang (hanya komponen yang berlaku_diskon).
            if ($gelombangPct > 0 && ($detail['berlaku_diskon'] ?? false)) {
                $diskon += round($base * ($gelombangPct / 100), 2);
                $parts[] = 'Diskon Gelombang ' . rtrim(rtrim(number_format($gelombangPct, 2, '.', ''), '0'), '.') . '%';
            }

            // 2. Potongan kustom calon (stacking).
            foreach ($customList->get((int) $komponenId, collect()) as $potongan) {
                $nominal = $potongan->tipe_potongan === 'persen'
                    ? round($base * ((float) $potongan->nilai_potongan / 100), 2)
                    : (float) $potongan->nilai_potongan;

                if ($nominal > 0) {
                    $diskon += $nominal;
                    $parts[] = $potongan->nama_potongan;
                }
            }

            $diskon = min($diskon, $base);

            if ($diskon <= 0) {
                continue;
            }

            $payload[] = [
                'tipe' => 'diskon',
                'nominal_potongan' => round($diskon, 2),
                'keterangan' => ($detail['keterangan'] ?? 'Komponen biaya') . ' — ' . implode(' + ', $parts),
            ];
        }

        return $payload;
    }
}
