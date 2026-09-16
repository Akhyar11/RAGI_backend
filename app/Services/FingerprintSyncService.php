<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Simpeg\FingerprintDevice;
use App\Models\Simpeg\Pegawai;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FingerprintSyncService
{
    /**
     * Sinkronisasi data punch log dari mesin absensi sidik jari / terminal biometrik.
     *
     * @param array $payload
     * @return array
     */
    public function syncPunchLogs(array $payload): array
    {
        $deviceCode = $payload['device_code'] ?? $payload['device_id'] ?? null;
        $deviceIp = $payload['device_ip'] ?? null;
        $logs = $payload['logs'] ?? [];

        $device = null;
        if ($deviceCode) {
            $device = FingerprintDevice::where('device_code', $deviceCode)->first();
            if ($device) {
                $device->update([
                    'last_sync_at' => now(),
                    'last_status' => 'synced',
                    'ip_address' => $deviceIp ?? $device->ip_address,
                ]);
            }
        }

        $syncedCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($logs as $index => $log) {
            $pin = trim((string) ($log['pin'] ?? $log['nip'] ?? $log['pegawai_id'] ?? ''));
            $timestampStr = $log['timestamp'] ?? null;

            if (empty($pin) || empty($timestampStr)) {
                $skippedCount++;
                $errors[] = "Log index {$index}: PIN/NIP atau Timestamp kosong.";
                continue;
            }

            try {
                $punchTime = Carbon::parse($timestampStr);
            } catch (\Throwable $e) {
                $skippedCount++;
                $errors[] = "Log index {$index} (PIN: {$pin}): Format timestamp '{$timestampStr}' tidak valid.";
                continue;
            }

            // Cari pegawai berdasarkan NIP atau ID
            $pegawai = Pegawai::with(['shiftTemplate.days'])
                ->where(function ($q) use ($pin) {
                    $q->where('nip', $pin)
                      ->orWhere('id', is_numeric($pin) ? (int)$pin : 0);
                })
                ->first();

            if (!$pegawai) {
                $skippedCount++;
                $errors[] = "Log index {$index}: Pegawai dengan PIN/NIP '{$pin}' tidak ditemukan.";
                continue;
            }

            $dateStr = $punchTime->toDateString();
            $dayOfWeek = $punchTime->dayOfWeek;
            $schedule = $pegawai->shiftTemplate?->getScheduleForDay($dayOfWeek);

            // Ambil toleransi shift
            $lateToleranceMinutes = $schedule
                ? $schedule->getLateToleranceMinutes()
                : ($pegawai->shiftTemplate?->late_tolerance_minutes ?? 15);

            $earlyLeaveToleranceMinutes = $schedule
                ? $schedule->getEarlyLeaveToleranceMinutes()
                : ($pegawai->shiftTemplate?->early_leave_tolerance_minutes ?? 15);

            // Tentukan mode in/out
            $inOutMode = $log['in_out_mode'] ?? null; // 0=in, 1=out
            $verifyMode = $log['verify_mode'] ?? 1;

            // Cari atau buat record presensi pada tanggal tersebut
            $attendance = Attendance::where('pegawai_id', $pegawai->id)
                ->whereDate('tanggal', $dateStr)
                ->first();

            if (!$attendance) {
                $attendance = new Attendance([
                    'pegawai_id' => $pegawai->id,
                    'tanggal' => $dateStr,
                ]);
            }

            $isClockIn = false;
            $isClockOut = false;

            if ($inOutMode === 0 || $inOutMode === '0' || $inOutMode === 'in') {
                $isClockIn = true;
            } elseif ($inOutMode === 1 || $inOutMode === '1' || $inOutMode === 'out') {
                $isClockOut = true;
            } else {
                // Auto-detect mode berdasarkan status existing
                if (!$attendance->clock_in) {
                    $isClockIn = true;
                } else {
                    $isClockOut = true;
                }
            }

            if ($isClockIn) {
                // Clock-in
                $attendance->clock_in = $punchTime;
                $attendance->jam_masuk = $punchTime->format('H:i:s');

                // Hitung keterlambatan berdasarkan jadwal
                $lateMinutes = 0;
                $status = 'hadir';

                if ($schedule && !$schedule->is_day_off && $schedule->start_time) {
                    $scheduledStart = Carbon::parse("{$dateStr} {$schedule->start_time}");
                    $lateThreshold = $scheduledStart->copy()->addMinutes($lateToleranceMinutes);

                    if ($punchTime->greaterThan($lateThreshold)) {
                        $status = 'terlambat';
                        $lateMinutes = max(0, (int) $scheduledStart->diffInMinutes($punchTime, false));
                    }
                }

                $attendance->late_minutes = $lateMinutes;
                $attendance->status = $status;
                $attendance->status_kehadiran = $status;
            }

            if ($isClockOut) {
                // Clock-out
                $attendance->clock_out = $punchTime;
                $attendance->jam_keluar = $punchTime->format('H:i:s');

                // Hitung pulang lebih awal (early leave)
                $earlyLeaveMinutes = 0;
                if ($schedule && !$schedule->is_day_off && $schedule->end_time) {
                    $scheduledEnd = Carbon::parse("{$dateStr} {$schedule->end_time}");
                    $earlyThreshold = $scheduledEnd->copy()->subMinutes($earlyLeaveToleranceMinutes);

                    if ($punchTime->lessThan($earlyThreshold)) {
                        $earlyLeaveMinutes = max(0, (int) $punchTime->diffInMinutes($scheduledEnd, false));
                    }
                }

                $attendance->early_leave_minutes = $earlyLeaveMinutes;
            }

            $attendance->source = 'fingerprint';
            $attendance->device_id = $deviceCode;
            $attendance->device_ip = $deviceIp ?? $device?->ip_address;
            if (!$attendance->office_location_id && $device?->office_location_id) {
                $attendance->office_location_id = $device->office_location_id;
            }

            $attendance->save();
            $syncedCount++;
        }

        return [
            'total_logs' => count($logs),
            'synced_count' => $syncedCount,
            'skipped_count' => $skippedCount,
            'device_code' => $deviceCode,
            'errors' => $errors,
        ];
    }
}
