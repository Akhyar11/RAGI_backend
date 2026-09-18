<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\OfficeLocation;
use App\Models\Simpeg\Pegawai;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(
        protected GeofenceService $geofenceService,
        protected PythonFaceService $faceService
    ) {}

    /**
     * Memproses Clock In dengan validasi menyeluruh di sisi server.
     */
    public function processClockIn(Pegawai $employee, array $data): Attendance
    {
        $now = Carbon::now();

        // Resolusi occurrence shift (tanggal dinas). Mendukung shift lintas hari
        // (misal satpam malam 22:00-06:00): punch setelah tengah malam (00:xx)
        // tetap diatribusikan ke tanggal dinas kemarin, bukan hari ini.
        [$schedule, $dutyDate, $resolvedStart] = $this->resolveClockInOccurrence($employee, $now);
        $today = $dutyDate;

        // 1. Cek apakah sudah pernah presensi masuk yang SAH/VALID pada tanggal dinas ini
        $existing = Attendance::where('pegawai_id', $employee->id)
            ->whereDate('tanggal', $today)
            ->first();

        if ($existing && in_array($existing->status, ['hadir', 'terlambat', 'menunggu_approval']) && $existing->clock_in) {
            throw ValidationException::withMessages([
                'attendance' => ['Karyawan sudah melakukan presensi masuk yang sah hari ini.'],
            ]);
        }

        // 2. Ambil pengaturan threshold sistem (fallback bila tanpa jadwal/shift)
        $minFaceScore = (float) SystemSetting::get('face_score_threshold', 0.80);
        $maxGpsAccuracy = (float) SystemSetting::get('gps_accuracy_threshold_meters', 50.0);
        $lateToleranceMinutes = (int) SystemSetting::get('late_tolerance_minutes', 15);
        $maxEarlyClockInMinutes = (int) SystemSetting::get('max_early_clock_in_minutes', 60);
        $maxLateClockInMinutes = (int) SystemSetting::get('max_late_clock_in_minutes', 240);

        // Ambil data payload
        $userLat = (float) ($data['latitude'] ?? 0);
        $userLon = (float) ($data['longitude'] ?? 0);
        $accuracy = (float) ($data['accuracy'] ?? 999.0);
        $faceScore = (float) ($data['face_score'] ?? 0.0);
        $isMock = (bool) ($data['is_mock_location'] ?? false);

        // 3. Resolusi lokasi kantor (multi-lokasi): lokasi utama + tambahan.
        // Diterima bila berada dalam radius LOKASI MANA PUN yang ditugaskan.
        [$office, $distance, $isWithinRadius, $nearestOffice, $nearestDistance, $checkedCount] =
            $this->resolveOfficeForCoordinates($employee, $userLat, $userLon);

        // 4. Evaluasi aturan validasi
        $rejectionReasons = [];

        // Aturan A: Deteksi Mock Location
        if ($isMock) {
            $rejectionReasons[] = 'Terdeteksi penggunaan Fake/Mock Location (GPS Palsu).';
        }

        // Aturan B: GPS Accuracy
        if ($accuracy > $maxGpsAccuracy) {
            $rejectionReasons[] = "Akurasi GPS tidak memadai ({$accuracy}m, batas maksimal {$maxGpsAccuracy}m).";
        }

        // Aturan C: Geofence radius (multi-lokasi: lolos bila di lokasi mana pun)
        if ($office && !$isWithinRadius) {
            $rejectionReasons[] = "Di luar area kantor (terdekat: {$nearestOffice->name}, jarak {$nearestDistance}m, radius {$nearestOffice->radius_meters}m; {$checkedCount} lokasi absen diperiksa).";
        }

        // Aturan D: Face match score & Server-Side Biometric Verification ke Python port 8001
        $faceImage = $data['face_image'] ?? $data['foto'] ?? $data['foto_presensi'] ?? null;
        if (!empty($faceImage)) {
            if (empty($employee->face_embedding)) {
                $rejectionReasons[] = 'Data biometrik wajah karyawan belum terdaftar di sistem. Silakan lakukan pendaftaran wajah terlebih dahulu.';
            } else {
                $enrolledVec = json_decode($employee->face_embedding, true) ?? [];
                // Kandidat multi-pose (tegak/nunduk/dongak) mengikuti fungsi
                // project Presensi Indonusa; fallback ke centroid untuk data lama.
                $enrolledPoseVecs = [];
                if (!empty($employee->face_embeddings)) {
                    $decodedPoses = json_decode($employee->face_embeddings, true);
                    if (is_array($decodedPoses)) {
                        $enrolledPoseVecs = array_values(array_filter($decodedPoses, 'is_array'));
                    }
                }
                $verifyResult = $this->faceService->verifyFace($faceImage, $enrolledVec, $minFaceScore, $enrolledPoseVecs);

                if ($verifyResult['success']) {
                    $faceScore = (float) $verifyResult['similarity'];
                    if (!$verifyResult['is_match']) {
                        $rejectionReasons[] = "Wajah tidak cocok dengan profil biometrik terdaftar di server (Skor kemiripan: {$faceScore}, batas minimal {$minFaceScore}).";
                    }
                    if (!empty($verifyResult['live_embedding'])) {
                        $data['face_embedding'] = $verifyResult['live_embedding'];
                    }
                } else {
                    // Fallback jika python microservice offline tetapi client mengirim face_score numerik valid
                    if (isset($data['face_score']) && (float) $data['face_score'] >= $minFaceScore) {
                        $faceScore = (float) $data['face_score'];
                    } else {
                        $rejectionReasons[] = $verifyResult['message'] ?? 'Verifikasi biometrik wajah di server gagal.';
                    }
                }
            }
        } elseif (isset($data['face_score'])) {
            if ($faceScore < $minFaceScore) {
                $rejectionReasons[] = "Skor pengenalan wajah rendah ({$faceScore}, batas minimal {$minFaceScore}).";
            }
        } else {
            $rejectionReasons[] = 'Foto wajah presensi wajib disertakan untuk verifikasi biometrik.';
        }

        // 5. Cek Hari Libur Nasional & Jadwal Kerja / Shift pada tanggal dinas
        // (untuk shift lintas hari, libur dinilai dari tanggal mulai dinas)
        $nationalHoliday = \App\Models\NationalHoliday::isHoliday($today);

        $appliesNationalHoliday = $schedule ? $schedule->appliesNationalHolidays() : ($employee->shiftTemplate?->applies_national_holidays ?? true);
        $isHolidayForEmployee = $nationalHoliday && $appliesNationalHoliday;

        // Ambil toleransi keterlambatan dari shift/jadwal harian
        $lateToleranceMinutes = $schedule
            ? $schedule->getLateToleranceMinutes()
            : (int) SystemSetting::get('late_tolerance_minutes', 15);

        // Resolusi jendela clock-in via hierarki: hari -> template -> SystemSetting.
        // max_late = 0 berarti tanpa batas (keterlambatan selalu diterima sebagai 'terlambat').
        $maxEarlyClockInMinutes = $schedule
            ? $schedule->getMaxEarlyClockInMinutes()
            : (($employee->shiftTemplate?->max_early_clock_in_minutes !== null)
                ? (int) $employee->shiftTemplate->max_early_clock_in_minutes
                : (int) SystemSetting::get('max_early_clock_in_minutes', 60));

        $maxLateClockInMinutes = $schedule
            ? $schedule->getMaxLateClockInMinutes()
            : (($employee->shiftTemplate?->max_late_clock_in_minutes !== null)
                ? (int) $employee->shiftTemplate->max_late_clock_in_minutes
                : (int) SystemSetting::get('max_late_clock_in_minutes', 240));

        // Aturan E: Batas Pembukaan Presensi Masuk (Tidak boleh scan terlalu pagi sebelum shift dibuka)
        // Aturan E2: Batas Penutupan Presensi Masuk (Tidak boleh scan setelah melewati batas telat maksimal)
        if ($schedule && !$schedule->is_day_off && $schedule->start_time && !$isHolidayForEmployee) {
            $scheduledStart = Carbon::parse("{$today} {$schedule->start_time}");
            $earliestClockIn = $scheduledStart->copy()->subMinutes($maxEarlyClockInMinutes);

            if ($now->lessThan($earliestClockIn)) {
                $rejectionReasons[] = "Presensi masuk belum dibuka. Presensi untuk shift ini ({$schedule->start_time}) baru dapat dilakukan mulai pukul {$earliestClockIn->format('H:i')} (maksimal {$maxEarlyClockInMinutes} menit sebelum jam kerja).";
            }

            if ($maxLateClockInMinutes > 0) {
                $latestClockIn = $scheduledStart->copy()->addMinutes($maxLateClockInMinutes);
                if ($now->greaterThan($latestClockIn)) {
                    $rejectionReasons[] = "Presensi masuk ditutup. Batas maksimal keterlambatan untuk shift ini ({$schedule->start_time}) adalah {$maxLateClockInMinutes} menit setelah jam kerja (terakhir pukul {$latestClockIn->format('H:i')}). Hubungi HR untuk pencatatan manual.";
                }
            }
        }

        // Tentukan status presensi & durasi terlambat
        $status = 'hadir';
        $requiresApproval = false;
        $lateMinutes = 0;

        if (!empty($rejectionReasons)) {
            $status = 'ditolak';
        } elseif ($isHolidayForEmployee) {
            $status = 'hadir';
            $requiresApproval = false;
        } elseif ($schedule && $schedule->is_day_off) {
            $status = 'menunggu_approval';
            $requiresApproval = true;
        } elseif ($schedule && $schedule->start_time) {
            $scheduledStart = Carbon::parse("{$today} {$schedule->start_time}");
            $lateThreshold = $scheduledStart->copy()->addMinutes($lateToleranceMinutes);

            if ($now->greaterThan($lateThreshold)) {
                $status = 'terlambat';
                $lateMinutes = (int) $scheduledStart->diffInMinutes($now, false);
                if ($lateMinutes < 0) {
                    $lateMinutes = 0;
                }
            }
        }

        // 6. Simpan / update log presensi lengkap
        // Jika record berasal dari cut-off Alfa, reset flag approval sistem agar
        // kehadiran susulan tercatat sebagai data scan valid, bukan Alfa.
        $attendance = $existing ?? new Attendance();
        if ($existing && $existing->status === 'alfa' && $status !== 'ditolak') {
            $attendance->is_approved_by_admin = null;
            $attendance->approved_by = null;
            $attendance->approved_at = null;
        }

        $photoPath = $this->saveAttendanceFile($faceImage, 'presensi');

        $attendanceData = [
            'pegawai_id' => $employee->id,
            'office_location_id' => $office?->id,
            'tanggal' => $today,
            'clock_in_latitude' => $userLat,
            'clock_in_longitude' => $userLon,
            'clock_in_distance_meters' => $distance,
            'clock_in_accuracy' => $accuracy,
            'clock_in_face_score' => $faceScore,
            'clock_in_is_mock_location' => $isMock,
            'status' => $status,
            'late_minutes' => $lateMinutes,
            'rejection_reason' => !empty($rejectionReasons) ? implode(' | ', $rejectionReasons) : null,
            'source' => $data['source'] ?? 'mobile_gps',
        ];

        if ($photoPath) {
            $attendanceData['foto_presensi'] = $photoPath;
        }

        if (!empty($data['device_id']) || !empty($data['device_info'])) {
            $attendanceData['device_id'] = $data['device_id'] ?? $data['device_info'];
        }

        if ($status !== 'ditolak') {
            $attendanceData['clock_in'] = $now;
            $attendanceData['jam_masuk'] = $now->format('H:i:s');
            $attendanceData['lat_long'] = "{$userLat},{$userLon}";

            if ($isHolidayForEmployee) {
                $attendanceData['notes'] = "Presensi masuk pada Hari Libur Nasional: {$nationalHoliday->name} (Lembur/Piket).";
            } elseif ($nationalHoliday && !$appliesNationalHoliday) {
                $attendanceData['notes'] = "Tugas Shift Hari Libur Nasional: {$nationalHoliday->name} (Shift Operasional/Satpam).";
            } elseif ($status === 'terlambat') {
                // Log eksplisit agar keterlambatan melebihi toleransi tetap tercatat & teraudit.
                $attendanceData['notes'] = "Terlambat {$lateMinutes} menit (Jadwal: {$schedule->start_time}, Toleransi: {$lateToleranceMinutes} mnt, Masuk: {$now->format('H:i:s')})."
                    . ($existing && $existing->status === 'ditolak' ? ' Presensi berhasil setelah percobaan sebelumnya sempat ditolak.' : '')
                    . ($existing && $existing->status === 'alfa' ? ' Mencatat ulang hasil cut-off Alfa menjadi kehadiran terlambat.' : '');
            } else {
                $attendanceData['notes'] = $requiresApproval
                    ? 'Presensi masuk pada hari libur terjadwal (butuh persetujuan HR).'
                    : ($existing && $existing->status === 'ditolak' ? 'Presensi berhasil setelah percobaan sebelumnya sempat ditolak.' : null);
            }

            // Penanda shift lintas hari agar jejak audit jelas (pulang keesokan harinya).
            if ($schedule && $schedule->isOvernight() && $schedule->end_time) {
                $overnightNote = "Shift lintas hari (dinas {$today}, pulang " . $schedule->getScheduledEndForDate($today)->format('d/m H:i') . ").";
                $attendanceData['notes'] = !empty($attendanceData['notes'])
                    ? $attendanceData['notes'] . ' ' . $overnightNote
                    : $overnightNote;
            }

            // Penanda lokasi absen (multi-lokasi): catat bila absen dari lokasi tambahan.
            if ($office && (int) $employee->office_location_id !== (int) $office->id) {
                $locationNote = "Lokasi absen: {$office->name} (jarak {$distance}m).";
                $attendanceData['notes'] = !empty($attendanceData['notes'])
                    ? $attendanceData['notes'] . ' ' . $locationNote
                    : $locationNote;
            }

            if (!empty($data['notes']) || !empty($data['catatan'])) {
                $customNote = $data['notes'] ?? $data['catatan'];
                $attendanceData['notes'] = !empty($attendanceData['notes'])
                    ? $attendanceData['notes'] . ' | ' . $customNote
                    : $customNote;
            }
        } else {
            $attendanceData['clock_in'] = null;
            $attendanceData['notes'] = "Percobaan presensi masuk ditolak pada {$now->toTimeString()}.";
        }

        $attendance->fill($attendanceData);
        $attendance->save();

        // 7. Adaptive Biometric Template Update (EMA)
        if ($status !== 'ditolak' && $faceScore >= 0.80 && !empty($data['face_embedding'])) {
            $this->updateAdaptiveFaceEmbedding($employee, $data['face_embedding']);
        }

        return $attendance;
    }

    /**
     * Resolusi lokasi absen untuk koordinat pengguna (multi-lokasi).
     *
     * Dianggap sah bila berada dalam radius LOKASI MANA PUN yang ditugaskan
     * (lokasi utama + tambahan). Mengembalikan:
     * [0] office yang dicatat (yang cocok, atau terdekat bila tidak ada yang cocok),
     * [1] jarak ke office tercatat, [2] apakah di dalam radius,
     * [3] office terdekat, [4] jarak terdekat, [5] jumlah lokasi yang diperiksa.
     *
     * @return array{0: ?OfficeLocation, 1: ?float, 2: bool, 3: ?OfficeLocation, 4: ?float, 5: int}
     */
    protected function resolveOfficeForCoordinates(Pegawai $employee, float $userLat, float $userLon): array
    {
        $candidates = $employee->getAllowedOfficeLocations();

        if ($candidates->isEmpty()) {
            $fallback = OfficeLocation::where('is_active', true)->first();
            if ($fallback) {
                $candidates = collect([$fallback]);
            }
        }

        $matched = null;
        $matchedDistance = null;
        $nearest = null;
        $nearestDistance = null;

        foreach ($candidates as $candidate) {
            $d = $this->geofenceService->calculateDistance(
                $userLat,
                $userLon,
                (float) $candidate->latitude,
                (float) $candidate->longitude
            );

            if ($nearestDistance === null || $d < $nearestDistance) {
                $nearest = $candidate;
                $nearestDistance = $d;
            }

            if ($d <= $candidate->radius_meters
                && ($matchedDistance === null || $d < $matchedDistance)) {
                $matched = $candidate;
                $matchedDistance = $d;
            }
        }

        if ($matched) {
            return [$matched, $matchedDistance, true, $nearest, $nearestDistance, $candidates->count()];
        }

        return [$nearest, $nearestDistance, false, $nearest, $nearestDistance, $candidates->count()];
    }

    /**
     * Resolusi occurrence shift untuk clock-in: [schedule, dutyDate Y-m-d, scheduledStart].
     *
     * Shift lintas hari (end <= start, misal 22:00-06:00): punch setelah tengah malam
     * yang masih dalam jendela clock-in shift kemarin ATAU masih dalam jam kerja
     * shift kemarin diatribusikan ke tanggal dinas kemarin.
     */
    protected function resolveClockInOccurrence(Pegawai $employee, Carbon $now): array
    {
        $template = $employee->shiftTemplate;
        if (!$template) {
            return [null, $now->toDateString(), null];
        }

        $yesterday = $now->copy()->subDay();
        $yDateStr = $yesterday->toDateString();
        $ySchedule = $template->getScheduleForDay($yesterday->dayOfWeek);

        if ($ySchedule && !$ySchedule->is_day_off && $ySchedule->start_time && $ySchedule->isOvernight()) {
            $yStart = $ySchedule->getScheduledStartForDate($yDateStr);
            $yEnd = $ySchedule->getScheduledEndForDate($yDateStr);
            $yEarliest = $yStart->copy()->subMinutes($ySchedule->getMaxEarlyClockInMinutes());
            $maxLate = $ySchedule->getMaxLateClockInMinutes();

            $inClockInWindow = $now->greaterThanOrEqualTo($yEarliest)
                && ($maxLate <= 0 || $now->lessThanOrEqualTo($yStart->copy()->addMinutes($maxLate)));
            $stillOnShift = $now->greaterThanOrEqualTo($yEarliest) && $now->lessThanOrEqualTo($yEnd);

            if ($inClockInWindow || $stillOnShift) {
                return [$ySchedule, $yDateStr, $yStart];
            }
        }

        $todayStr = $now->toDateString();
        $schedule = $template->getScheduleForDay($now->dayOfWeek);
        $scheduledStart = ($schedule && !$schedule->is_day_off && $schedule->start_time)
            ? $schedule->getScheduledStartForDate($todayStr)
            : null;

        return [$schedule, $todayStr, $scheduledStart];
    }

    /**
     * Cari record terbuka untuk clock-out: prioritas record hari ini,
     * fallback ke record shift lintas hari kemarin yang belum pulang.
     */
    protected function resolveClockOutRecord(Pegawai $employee, Carbon $now): ?Attendance
    {
        $today = $now->toDateString();

        $attendance = Attendance::where('pegawai_id', $employee->id)
            ->whereDate('tanggal', $today)
            ->whereIn('status', ['hadir', 'terlambat', 'menunggu_approval'])
            ->whereNotNull('clock_in')
            ->first();

        if ($attendance) {
            return $attendance;
        }

        $yesterdayStr = $now->copy()->subDay()->toDateString();
        $candidate = Attendance::where('pegawai_id', $employee->id)
            ->whereDate('tanggal', $yesterdayStr)
            ->whereIn('status', ['hadir', 'terlambat', 'menunggu_approval'])
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->first();

        if ($candidate && $employee->shiftTemplate) {
            $ySchedule = $employee->shiftTemplate->getScheduleForDay(
                Carbon::parse($yesterdayStr)->dayOfWeek
            );
            if ($ySchedule && !$ySchedule->is_day_off && $ySchedule->isOvernight()) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Memperbarui profil vektor biometrik wajah karyawan secara adaptif (Exponential Moving Average)
     */
    protected function updateAdaptiveFaceEmbedding(Pegawai $employee, mixed $currentEmbeddingInput): void
    {
        try {
            $currentVec = is_string($currentEmbeddingInput)
                ? json_decode($currentEmbeddingInput, true)
                : $currentEmbeddingInput;

            if (!is_array($currentVec) || count($currentVec) < 128) {
                return;
            }

            $dim = count($currentVec);
            $currentVec = array_map('floatval', $currentVec);

            $stored = $employee->face_embedding;
            if (empty($stored)) {
                return;
            }

            $storedVec = json_decode($stored, true);
            if (!is_array($storedVec) || count($storedVec) !== $dim) {
                return;
            }

            $storedVec = array_map('floatval', $storedVec);

            $alpha = 0.90;
            $updatedVec = array_fill(0, $dim, 0.0);

            for ($i = 0; $i < $dim; $i++) {
                $val = ($alpha * $storedVec[$i]) + ((1.0 - $alpha) * $currentVec[$i]);
                $updatedVec[$i] = round($val, 5);
            }

            $employee->update([
                'face_embedding' => json_encode($updatedVec),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal memperbarui adaptive face embedding: " . $e->getMessage());
        }
    }

    /**
     * Memproses Clock Out
     */
    public function processClockOut(Pegawai $employee, array $data): Attendance
    {
        $now = Carbon::now();

        // Cari record terbuka: hari ini dulu, fallback ke shift lintas hari kemarin.
        $attendance = $this->resolveClockOutRecord($employee, $now);

        if (!$attendance) {
            throw ValidationException::withMessages([
                'attendance' => ['Belum melakukan presensi masuk yang valid hari ini. Tidak dapat melakukan presensi pulang.'],
            ]);
        }

        if ($attendance->clock_out) {
            throw ValidationException::withMessages([
                'attendance' => ['Karyawan sudah melakukan presensi pulang hari ini.'],
            ]);
        }

        $minFaceScore = (float) SystemSetting::get('face_score_threshold', 0.80);
        $maxGpsAccuracy = (float) SystemSetting::get('gps_accuracy_threshold_meters', 50.0);

        $userLat = (float) ($data['latitude'] ?? 0);
        $userLon = (float) ($data['longitude'] ?? 0);
        $accuracy = (float) ($data['accuracy'] ?? 999.0);
        $faceScore = (float) ($data['face_score'] ?? 0.0);
        $isMock = (bool) ($data['is_mock_location'] ?? false);

        // Lokasi boleh berbeda dengan lokasi clock-in (misal dosen pindah kampus
        // di siang hari): sah selama masih salah satu lokasi yang ditugaskan.
        [$office, $distance, $isWithinRadius, $nearestOffice, $nearestDistance, $checkedCount] =
            $this->resolveOfficeForCoordinates($employee, $userLat, $userLon);

        $rejectionReasons = [];
        if ($isMock) {
            $rejectionReasons[] = 'Terdeteksi penggunaan Fake/Mock Location (GPS Palsu).';
        }
        if ($accuracy > $maxGpsAccuracy) {
            $rejectionReasons[] = "Akurasi GPS tidak memadai ({$accuracy}m, batas maksimal {$maxGpsAccuracy}m).";
        }
        if ($office && !$isWithinRadius) {
            $rejectionReasons[] = "Di luar area kantor (terdekat: {$nearestOffice->name}, jarak {$nearestDistance}m, radius {$nearestOffice->radius_meters}m; {$checkedCount} lokasi absen diperiksa).";
        }
        if ($faceScore < $minFaceScore) {
            $rejectionReasons[] = "Skor pengenalan wajah rendah ({$faceScore}, batas minimal {$minFaceScore}).";
        }

        // Jadwal dihitung dari tanggal dinas record (duty date), bukan tanggal sekarang,
        // agar shift lintas hari (22:00-06:00) memakai jam pulang keesokan harinya.
        $dutyDateStr = Carbon::parse($attendance->getAttribute('tanggal'))->toDateString();
        $today = $dutyDateStr;

        $schedule = null;
        if ($employee->shiftTemplate) {
            $schedule = $employee->shiftTemplate->getScheduleForDay(
                Carbon::parse($dutyDateStr)->dayOfWeek
            );
        }
        if (!$schedule) {
            $schedule = $employee->shiftTemplate?->getScheduleForDay($now->dayOfWeek);
        }

        $earlyLeaveToleranceMinutes = $schedule
            ? $schedule->getEarlyLeaveToleranceMinutes()
            : (int) SystemSetting::get('early_leave_tolerance_minutes', 15);

        $appliesNationalHoliday = $schedule
            ? $schedule->appliesNationalHolidays()
            : ($employee->shiftTemplate ? $employee->shiftTemplate->applies_national_holidays : true);

        $nationalHoliday = \App\Models\NationalHoliday::isHoliday($today);
        $isHolidayForEmployee = $nationalHoliday && $appliesNationalHoliday;

        $earlyLeaveMinutes = 0;
        if ($schedule && !$schedule->is_day_off && $schedule->end_time && !$isHolidayForEmployee) {
            // getScheduledEndForDate otomatis +1 hari untuk shift lintas hari.
            $scheduledEnd = $schedule->getScheduledEndForDate($dutyDateStr);
            $earliestClockOut = $scheduledEnd->copy()->subMinutes($earlyLeaveToleranceMinutes);

            if ($now->lessThan($earliestClockOut)) {
                $rejectionReasons[] = "Presensi pulang belum dibuka. Presensi pulang untuk shift ini ({$schedule->start_time} - {$schedule->end_time}) baru dapat dilakukan mulai pukul {$earliestClockOut->format('H:i')} (toleransi pulang cepat: {$earlyLeaveToleranceMinutes} menit).";
            } elseif ($now->lessThan($scheduledEnd)) {
                $earlyLeaveMinutes = (int) $now->diffInMinutes($scheduledEnd, false);
                if ($earlyLeaveMinutes < 0) {
                    $earlyLeaveMinutes = 0;
                }
            }
        }

        if (!empty($rejectionReasons)) {
            throw ValidationException::withMessages([
                'attendance' => ['Presensi pulang ditolak: ' . implode(' | ', $rejectionReasons)],
            ]);
        }

        $clockOutData = [
            'clock_out' => $now,
            'jam_keluar' => $now->format('H:i:s'),
            'clock_out_latitude' => $userLat,
            'clock_out_longitude' => $userLon,
            'clock_out_distance_meters' => $distance,
            'clock_out_accuracy' => $accuracy,
            'clock_out_face_score' => $faceScore,
            'clock_out_is_mock_location' => $isMock,
            'early_leave_minutes' => $earlyLeaveMinutes,
        ];

        // Penanda bila pulang dari lokasi berbeda dengan lokasi masuk (multi-lokasi).
        if ($office && (int) $attendance->office_location_id !== (int) $office->id) {
            $locationNote = "Pulang dari lokasi: {$office->name} (jarak {$distance}m).";
            $clockOutData['notes'] = !empty($attendance->notes)
                ? $attendance->notes . ' ' . $locationNote
                : $locationNote;
        }

        if (!empty($data['notes']) || !empty($data['catatan'])) {
            $customNote = $data['notes'] ?? $data['catatan'];
            $clockOutData['notes'] = !empty($attendance->notes)
                ? $attendance->notes . ' | ' . $customNote
                : $customNote;
        }

        $attendance->update($clockOutData);

        return $attendance;
    }

    /**
     * Simpan file gambar wajah atau berkas lampiran ke storage publik.
     */
    public function saveAttendanceFile(mixed $fileInput, string $subFolder = 'presensi'): ?string
    {
        if (empty($fileInput)) {
            return null;
        }

        try {
            // 1. Jika instance UploadedFile dari multipart form request
            if ($fileInput instanceof \Illuminate\Http\UploadedFile) {
                $ext = $fileInput->getClientOriginalExtension() ?: 'jpg';
                $fileName = \Illuminate\Support\Str::uuid() . '.' . strtolower($ext);
                $datePath = date('Y/m');
                return $fileInput->storeAs("simpeg/{$subFolder}/{$datePath}", $fileName, 'public');
            }

            // 2. Jika berupa base64 data URI atau raw base64 string
            if (is_string($fileInput) && (str_contains($fileInput, ';base64,') || (strlen($fileInput) > 100 && base64_decode(substr($fileInput, 0, 100), true) !== false))) {
                $content = $fileInput;
                $ext = 'jpg';
                if (str_contains($content, ';base64,')) {
                    [$header, $body] = explode(';base64,', $content, 2);
                    if (str_contains($header, 'png')) {
                        $ext = 'png';
                    } elseif (str_contains($header, 'pdf')) {
                        $ext = 'pdf';
                    } elseif (str_contains($header, 'webp')) {
                        $ext = 'webp';
                    }
                    $content = $body;
                }
                $decoded = base64_decode($content);
                if ($decoded !== false && strlen($decoded) > 0) {
                    $fileName = \Illuminate\Support\Str::uuid() . '.' . $ext;
                    $relPath = "simpeg/{$subFolder}/" . date('Y/m') . "/{$fileName}";
                    \Illuminate\Support\Facades\Storage::disk('public')->put($relPath, $decoded);
                    return $relPath;
                }
            }

            // 3. Jika sudah berupa path file relatif
            if (is_string($fileInput) && (str_starts_with($fileInput, 'simpeg/') || str_starts_with($fileInput, 'http'))) {
                return $fileInput;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal menyimpan file presensi: " . $e->getMessage());
        }

        return null;
    }
}
