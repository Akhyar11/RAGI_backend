<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\PresensiPegawai;
use App\Models\Simpeg\PresensiPeriode;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PresensiBundleService
{
    /**
     * Nama-nama bulan dalam Bahasa Indonesia
     */
    protected const BULAN_INDO = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    /**
     * Hitung rentang periode siklus kampus (26 bulan lalu s/d 25 bulan ini) untuk tanggal acuan.
     *
     * Contoh:
     * - 2026-01-26 s/d 2026-02-25 => Bundle Februari 2026 (bulan_tahun: 2026-02)
     * - 2026-02-26 s/d 2026-03-25 => Bundle Maret 2026 (bulan_tahun: 2026-03)
     */
    public function getPeriodForDate(Carbon|string $date): array
    {
        $carbon = Carbon::parse($date);
        $day = (int) $carbon->day;

        if ($day <= 25) {
            // Tanggal 1 s/d 25: akhir periode adalah tanggal 25 bulan ini
            $end = $carbon->copy()->day(25);
            $start = $carbon->copy()->subMonthNoOverflow()->day(26);
            $bundleMonth = $end;
        } else {
            // Tanggal 26 s/d akhir bulan: awal periode adalah tanggal 26 bulan ini
            $start = $carbon->copy()->day(26);
            $end = $carbon->copy()->addMonthNoOverflow()->day(25);
            $bundleMonth = $end;
        }

        $monthNum = (int) $bundleMonth->month;
        $yearNum = (int) $bundleMonth->year;
        $namaBulan = self::BULAN_INDO[$monthNum] ?? $bundleMonth->format('F');

        $today = Carbon::today();
        $isCompleted = $today->greaterThanOrEqualTo($end);

        return [
            'tanggal_awal' => $start->toDateString(),
            'tanggal_akhir' => $end->toDateString(),
            'bulan_tahun' => $bundleMonth->format('Y-m'),
            'nama_periode' => "Presensi {$namaBulan} {$yearNum}",
            'is_completed' => $isCompleted,
        ];
    }

    /**
     * Sinkronisasikan seluruh log kehadiran dari tabel simpeg_presensi_pegawai
     * ke dalam PresensiPeriode (Bundle) yang sesuai siklus 26 s/d 25.
     */
    public function autoSyncBundlesFromRealtime(?int $creatorId = null): array
    {
        $creatorUserId = $creatorId
            ?? auth()->id()
            ?? User::whereHas('roles.permissions', fn($q) => $q->whereIn('slug', ['simpeg.presensi.manage', 'simpeg.presensi.create']))->value('id')
            ?? User::first()?->id;

        // Kumpulkan seluruh tanggal unik yang memiliki catatan presensi
        $dates = PresensiPegawai::distinct()->pluck('tanggal')->filter()->map(function ($d) {
            return Carbon::parse($d)->toDateString();
        })->unique();

        // Selalu sertakan tanggal hari ini agar bundle periode berjalan terbentuk jika tanggalnya telah memenuhi
        $today = Carbon::today()->toDateString();
        $allDates = $dates->push($today)->unique();

        $periodsMap = [];
        foreach ($allDates as $dateStr) {
            $period = $this->getPeriodForDate($dateStr);
            $periodsMap[$period['bulan_tahun']] = $period;
        }

        $syncedBundles = [];

        DB::transaction(function () use ($periodsMap, $creatorUserId, &$syncedBundles) {
            foreach ($periodsMap as $bulanTahun => $period) {
                // Buat atau temukan bundle periode
                $bundle = PresensiPeriode::firstOrCreate(
                    ['bulan_tahun' => $bulanTahun],
                    [
                        'nama_periode' => $period['nama_periode'],
                        'tanggal_awal' => $period['tanggal_awal'],
                        'tanggal_akhir' => $period['tanggal_akhir'],
                        'catatan' => 'Bundle otomatis dari log presensi realtime (Siklus 26 - 25)',
                        'total_record' => 0,
                        'created_by' => $creatorUserId,
                    ]
                );

                // Perbarui rentang tanggal jika sebelumnya belum diset
                if (empty($bundle->tanggal_awal) || empty($bundle->tanggal_akhir)) {
                    $bundle->update([
                        'tanggal_awal' => $period['tanggal_awal'],
                        'tanggal_akhir' => $period['tanggal_akhir'],
                    ]);
                }

                // Tautkan seluruh log presensi di rentang tanggal tersebut ke bundle ini
                $affected = PresensiPegawai::whereBetween('tanggal', [$period['tanggal_awal'], $period['tanggal_akhir']])
                    ->where(function ($q) use ($bundle) {
                        $q->whereNull('presensi_periode_id')
                          ->orWhere('presensi_periode_id', '!=', $bundle->id);
                    })
                    ->update(['presensi_periode_id' => $bundle->id]);

                // Hitung total record aktual yang terhubung ke bundle ini
                $totalRecord = PresensiPegawai::where('presensi_periode_id', $bundle->id)->count();
                $totalPegawai = PresensiPegawai::where('presensi_periode_id', $bundle->id)
                    ->distinct('pegawai_id')
                    ->count('pegawai_id');

                $bundle->update(['total_record' => $totalRecord]);

                $syncedBundles[] = [
                    'id' => $bundle->id,
                    'nama_periode' => $bundle->nama_periode,
                    'bulan_tahun' => $bundle->bulan_tahun,
                    'tanggal_awal' => $bundle->tanggal_awal,
                    'tanggal_akhir' => $bundle->tanggal_akhir,
                    'total_record' => $totalRecord,
                    'total_pegawai' => $totalPegawai,
                    'is_completed' => $period['is_completed'],
                    'affected_rows' => $affected,
                ];
            }
        });

        return [
            'total_bundles' => count($syncedBundles),
            'bundles' => $syncedBundles,
        ];
    }
}
