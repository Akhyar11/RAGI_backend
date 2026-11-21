<?php

namespace App\Services\Mobile;

use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\TahunAkademik;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MobileStaffService
{
    /**
     * Resolve Dosen by user ID, NIDN, NIP, or username.
     */
    public function resolveDosen(?int $userId = null, ?string $nidn = null, ?string $nip = null, ?string $username = null): ?Dosen
    {
        $query = Dosen::query();

        if ($userId) {
            $dosen = (clone $query)->where('user_id', $userId)->first();
            if ($dosen) return $dosen;
        }

        $identifier = $nidn ?: $username;
        if ($identifier) {
            $clean = trim($identifier, "'\" ");
            $noZero = ltrim($clean, '0');
            $padded = str_pad($noZero, 10, '0', STR_PAD_LEFT);

            $dosen = (clone $query)->where(function ($q) use ($clean, $noZero, $padded) {
                $q->where('nidn', $clean)
                  ->orWhere('nidn', $noZero)
                  ->orWhere('nidn', $padded)
                  ->orWhere('nip', $clean);
            })->first();

            if ($dosen) return $dosen;
        }

        if ($nip) {
            $cleanNip = trim($nip, "'\" ");
            $dosen = (clone $query)->where('nip', $cleanNip)->first();
            if ($dosen) return $dosen;
        }

        return null;
    }

    /**
     * Dapatkan jadwal mengajar dosen (hari ini & seluruh semester).
     */
    public function getJadwalMengajar(
        ?int $userId = null,
        ?string $nidn = null,
        ?string $nip = null,
        ?string $username = null,
        ?string $hari = null,
        ?int $tahunAkademikId = null,
        ?string $sortBy = 'jam_mulai',
        ?string $sortOrder = 'asc'
    ): array {
        $dosen = $this->resolveDosen($userId, $nidn, $nip, $username);
        $todayDay = $this->getIndonesianDayName(now());
        $nowTime = now()->format('H:i:s');
        $sortOrder = strtolower($sortOrder ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSort = ['jam_mulai', 'kode_kelas', 'created_at'];
        $sortBy = in_array($sortBy, $allowedSort, true) ? $sortBy : 'jam_mulai';

        // Jika dosen belum ditemukan di siakad_dosen
        if (!$dosen) {
            return [
                'dosen' => [
                    'id' => null,
                    'nama_lengkap' => $username ?? 'Dosen',
                    'nama_gelar' => $username ?? 'Dosen',
                    'nidn' => $nidn ?? '-',
                    'nip' => $nip ?? '-',
                    'is_fallback' => false,
                ],
                'today_day' => $todayDay,
                'filter_day' => $hari,
                'today_classes' => [],
                'all_classes' => [],
                'total_sks' => 0,
                'total_kelas' => 0,
            ];
        }

        $tahunAkademik = $tahunAkademikId
            ? TahunAkademik::find($tahunAkademikId)
            : TahunAkademik::where('is_active', true)->first();

        // Ambil kelas yang diampu oleh dosen ini
        $query = Kelas::with([
            'mataKuliah',
            'ruangan.gedung',
            'programStudi',
            'dosenPengampu',
        ])
        ->withCount(['krsDetails as total_mahasiswa' => fn($q) => $q->where('status', 'aktif')])
        ->whereHas('dosenPengampu', fn($dp) => $dp->where('dosen_id', $dosen->id))
        ->when($tahunAkademik, fn($q) => $q->where('tahun_akademik_id', $tahunAkademik->id));

        $classes = $query->orderByRaw("
            CASE LOWER(hari)
                WHEN 'senin' THEN 1
                WHEN 'selasa' THEN 2
                WHEN 'rabu' THEN 3
                WHEN 'kamis' THEN 4
                WHEN 'jumat' THEN 5
                WHEN 'sabtu' THEN 6
                WHEN 'minggu' THEN 7
                ELSE 8
            END ASC
        ")->orderBy($sortBy, $sortOrder)->get();

        $formattedClasses = $classes->map(
            fn($k) => $this->formatKelasItem($k, $todayDay, $nowTime, $dosen)
        );

        $todayClasses = $formattedClasses->where('is_today', true)->values()->all();

        $finalClasses = $hari
            ? $formattedClasses->filter(fn($c) => strtolower($c['hari']) === strtolower($hari))->values()->all()
            : $formattedClasses->values()->all();

        return [
            'dosen' => [
                'id' => $dosen->id,
                'nama_lengkap' => $dosen->nama_lengkap,
                'nama_gelar' => $dosen->nama_gelar ?? $dosen->nama_lengkap,
                'nidn' => $dosen->nidn,
                'nip' => $dosen->nip,
                'is_fallback' => false,
            ],
            'today_day' => $todayDay,
            'filter_day' => $hari,
            'today_classes' => $todayClasses,
            'all_classes' => $finalClasses,
            'total_sks' => (int) $formattedClasses->sum('sks'),
            'total_kelas' => $formattedClasses->count(),
        ];
    }

    /**
     * Format item kelas untuk respon API jadwal mengajar.
     */
    protected function formatKelasItem(Kelas $k, string $todayDay, string $nowTime, Dosen $dosen): array
    {
        $isToday = strtolower($k->hari ?? '') === strtolower($todayDay);
        $start = $k->jam_mulai ? substr($k->jam_mulai, 0, 5) : '08:00';
        $end = $k->jam_selesai ? substr($k->jam_selesai, 0, 5) : '10:30';

        $isOngoing = false;
        $status = 'Terjadwal';
        if ($isToday) {
            if ($nowTime >= $k->jam_mulai && $nowTime <= $k->jam_selesai) {
                $isOngoing = true;
                $status = 'Sedang Berlangsung';
            } elseif ($nowTime < $k->jam_mulai) {
                $diffMin = Carbon::parse($k->jam_mulai)->diffInMinutes(now());
                $status = $diffMin <= 60 ? "Mulai dlm {$diffMin} Menit" : 'Akan Datang';
            } else {
                $status = 'Selesai';
            }
        }

        $dp = $k->dosenPengampu->firstWhere('dosen_id', $dosen->id);
        $peran = $dp?->peran ?? DosenPengampu::DEFAULT_PERAN;

        return [
            'id' => $k->id,
            'course_code' => $k->mataKuliah?->kode_mk ?? $k->kode_kelas,
            'course_name' => $k->mataKuliah?->nama ?? $k->nama_kelas,
            'sks' => (int) ($k->mataKuliah?->total_sks ?? 3),
            'class_name' => $k->nama_kelas,
            'room' => $k->ruangan?->nama ?? $k->ruangan?->nama_ruang ?? 'Ruang Teori',
            'room_code' => $k->ruangan?->kode ?? '-',
            'start_time' => $start,
            'end_time' => $end,
            'student_count' => (int) ($k->total_mahasiswa > 0 ? $k->total_mahasiswa : ($k->kapasitas ?: 30)),
            'session' => 10,
            'status' => $status,
            'hari' => $k->hari ?? '-',
            'is_today' => $isToday,
            'is_ongoing' => $isOngoing,
            'reminder_active' => true,
            'peran' => $peran,
        ];
    }

    /**
     * Nama hari dalam bahasa Indonesia.
     */
    public function getIndonesianDayName(Carbon $date): string
    {
        $days = [
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            6 => 'sabtu',
            7 => 'minggu',
        ];
        return $days[$date->dayOfWeekIso] ?? 'senin';
    }
}
