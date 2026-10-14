<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PresensiPegawai;
use App\Models\Simpeg\PresensiPeriode;
use App\Models\Simpeg\GajiPegawai;
use App\Models\Simpeg\SuratPeringatan;
use App\Models\SystemSetting;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SuratPeringatanService
{
    /**
     * Periksa dan terbitkan SP untuk satu pegawai setelah penggajian berstatus paid.
     */
    public function checkAndGenerateSpForGaji(GajiPegawai $gaji): ?SuratPeringatan
    {
        if ($gaji->status_transfer !== 'paid') {
            return null;
        }

        $pegawai = $gaji->pegawai;
        if (!$pegawai) {
            return null;
        }

        $periode = $gaji->periode_bulan_tahun; // Format: 'YYYY-MM', misal '2026-10'
        $maxLateAllowed = (int) SystemSetting::get('max_late_per_month', 5);

        // Cari bundle presensi terkait
        $bundle = PresensiPeriode::where('bulan_tahun', $periode)
            ->orWhere('nama_periode', 'like', "%{$periode}%")
            ->first();

        // Hitung total keterlambatan pegawai pada periode/bulan tersebut
        $totalTerlambat = $this->calculateLateCountForPegawai($pegawai->id, $periode, $bundle);

        if ($totalTerlambat > $maxLateAllowed) {
            return $this->createOrUpdateSp($pegawai, $gaji, $bundle, $periode, $totalTerlambat, $maxLateAllowed);
        }

        return null;
    }

    /**
     * Periksa dan terbitkan SP untuk seluruh pegawai dalam satu periode payroll.
     */
    public function checkAndGenerateSpForPeriode(string $periode): int
    {
        $gajiList = GajiPegawai::where('periode_bulan_tahun', $periode)
            ->where('status_transfer', 'paid')
            ->get();

        $count = 0;
        foreach ($gajiList as $gaji) {
            $sp = $this->checkAndGenerateSpForGaji($gaji);
            if ($sp) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Hitung total keterlambatan (status='terlambat') pegawai dalam rentang periode.
     */
    public function calculateLateCountForPegawai(int $pegawaiId, string $periode, ?PresensiPeriode $bundle = null): int
    {
        $query = PresensiPegawai::where('pegawai_id', $pegawaiId)
            ->where(function ($q) {
                $q->where('status_kehadiran', 'terlambat')
                  ->orWhere('status', 'terlambat');
            });

        if ($bundle && $bundle->tanggal_awal && $bundle->tanggal_akhir) {
            $start = Carbon::parse($bundle->tanggal_awal)->format('Y-m-d');
            $end = Carbon::parse($bundle->tanggal_akhir)->format('Y-m-d');
            $query->whereBetween('tanggal', [$start, $end]);
        } else {
            $startOfMonth = Carbon::parse($periode . '-01')->startOfMonth()->format('Y-m-d');
            $endOfMonth = Carbon::parse($periode . '-01')->endOfMonth()->format('Y-m-d');
            $query->whereBetween('tanggal', [$startOfMonth, $endOfMonth]);
        }

        return $query->count();
    }

    /**
     * Terbitkan atau perbarui record SP (idempoten).
     */
    protected function createOrUpdateSp(
        Pegawai $pegawai,
        GajiPegawai $gaji,
        ?PresensiPeriode $bundle,
        string $periode,
        int $totalTerlambat,
        int $maxLateAllowed
    ): SuratPeringatan {
        $existingSp = SuratPeringatan::where('pegawai_id', $pegawai->id)
            ->where('periode_bulan_tahun', $periode)
            ->first();

        if ($existingSp) {
            $oldValues = $existingSp->getOriginal();
            $existingSp->update([
                'gaji_pegawai_id' => $gaji->id,
                'presensi_periode_id' => $bundle?->id,
                'total_terlambat' => $totalTerlambat,
                'batas_maksimal' => $maxLateAllowed,
            ]);
            $newValues = $existingSp->getChanges();

            try {
                AuditLogService::record(
                    module: 'SIMPEG',
                    action: 'update',
                    tableName: 'simpeg_surat_peringatan',
                    recordId: $existingSp->id,
                    oldValues: $oldValues,
                    newValues: $newValues
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log pembaruan SP: ' . $e->getMessage());
            }

            return $existingSp;
        }

        // Generate nomor SP resmi
        $year = Carbon::now()->format('Y');
        $month = Carbon::now()->format('m');
        $countThisYear = SuratPeringatan::whereYear('created_at', $year)->count() + 1;
        $nomorUrut = str_pad((string) $countThisYear, 4, '0', STR_PAD_LEFT);
        $nomorSp = "SP/SIMPEG/{$year}/{$month}/{$nomorUrut}";

        $namaBulan = Carbon::parse($periode . '-01')->locale('id')->isoFormat('MMMM Y');

        $sp = SuratPeringatan::create([
            'pegawai_id' => $pegawai->id,
            'gaji_pegawai_id' => $gaji->id,
            'presensi_periode_id' => $bundle?->id,
            'nomor_sp' => $nomorSp,
            'jenis_sp' => 'SP 1',
            'judul' => "Surat Peringatan Keterlambatan Presensi Periode {$namaBulan}",
            'periode_bulan_tahun' => $periode,
            'total_terlambat' => $totalTerlambat,
            'batas_maksimal' => $maxLateAllowed,
            'keterangan' => "Surat peringatan diterbitkan secara otomatis oleh sistem kepegawaian karena akumulasi keterlambatan presensi Anda pada periode {$namaBulan} tercatat sebanyak {$totalTerlambat} kali, melebihi batas toleransi maksimal yang ditetapkan kampus ({$maxLateAllowed} kali). Harap meningkatkan kedisiplinan dan kepatuhan jam kerja.",
            'tanggal_terbit' => Carbon::now()->toDateString(),
            'status' => 'aktif',
        ]);

        try {
            AuditLogService::record(
                module: 'SIMPEG',
                action: 'create',
                tableName: 'simpeg_surat_peringatan',
                recordId: $sp->id,
                oldValues: null,
                newValues: $sp->toArray()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log penerbitan SP: ' . $e->getMessage());
        }

        return $sp;
    }
}
