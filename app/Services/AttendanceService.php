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
        $today = $now->toDateString();
        $dayOfWeek = $now->dayOfWeek; // 0 = Minggu, 1 = Senin, ..., 6 = Sabtu

        // 1. Cek apakah sudah pernah presensi masuk yang SAH/VALID hari ini
        $existing = Attendance::where('pegawai_id', $employee->id)
            ->whereDate('tanggal', $today)
            ->first();

        if ($existing && in_array($existing->status, ['hadir', 'terlambat', 'menunggu_approval']) && $existing->clock_in) {
            throw ValidationException::withMessages([
                'attendance' => ['Karyawan sudah melakukan presensi masuk yang sah hari ini.'],
            ]);
        }

        // 2. Ambil pengaturan threshold sistem
        $minFaceScore = (float) SystemSetting::get('face_score_threshold', 0.80);
        $maxGpsAccuracy = (float) SystemSetting::get('gps_accuracy_threshold_meters', 50.0);
        $lateToleranceMinutes = (int) SystemSetting::get('late_tolerance_minutes', 15);
        $maxEarlyClockInMinutes = (int) SystemSetting::get('max_early_clock_in_minutes', 60);

        // Ambil data payload
        $userLat = (float) ($data['latitude'] ?? 0);
        $userLon = (float) ($data['longitude'] ?? 0);
        $accuracy = (float) ($data['accuracy'] ?? 999.0);
        $faceScore = (float) ($data['face_score'] ?? 0.0);
        $isMock = (bool) ($data['is_mock_location'] ?? false);

        // 3. Ambil lokasi kantor yang ditugaskan ke karyawan
        $office = $employee->officeLocation;
        if (!$office) {
            $office = OfficeLocation::where('is_active', true)->first();
        }

        $distance = null;
        $isWithinRadius = false;
        if ($office) {
            $distance = $this->geofenceService->calculateDistance(
                $userLat,
                $userLon,
                $office->latitude,
                $office->longitude
            );
            $isWithinRadius = $distance <= $office->radius_meters;
        }

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

        // Aturan C: Geofence radius
        if ($office && !$isWithinRadius) {
            $rejectionReasons[] = "Di luar area kantor (Jarak: {$distance}m, radius diizinkan: {$office->radius_meters}m).";
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

        // 5. Cek Hari Libur Nasional & Jadwal Kerja / Shift Hari Ini
        $nationalHoliday = \App\Models\NationalHoliday::isHoliday($today);

        $schedule = null;
        if ($employee->shiftTemplate) {
            $schedule = $employee->shiftTemplate->getScheduleForDay($dayOfWeek);
        }

        $appliesNationalHoliday = $schedule ? $schedule->appliesNationalHolidays() : ($employee->shiftTemplate?->applies_national_holidays ?? true);
        $isHolidayForEmployee = $nationalHoliday && $appliesNationalHoliday;

        // Ambil toleransi keterlambatan dari shift/jadwal harian
        $lateToleranceMinutes = $schedule
            ? $schedule->getLateToleranceMinutes()
            : (int) SystemSetting::get('late_tolerance_minutes', 15);

        // Aturan E: Batas Pembukaan Presensi Masuk (Tidak boleh scan terlalu pagi sebelum shift dibuka)
        if ($schedule && !$schedule->is_day_off && $schedule->start_time && !$isHolidayForEmployee) {
            $scheduledStart = Carbon::parse("{$today} {$schedule->start_time}");
            $earliestClockIn = $scheduledStart->copy()->subMinutes($maxEarlyClockInMinutes);

            if ($now->lessThan($earliestClockIn)) {
                $rejectionReasons[] = "Presensi masuk belum dibuka. Presensi untuk shift ini ({$schedule->start_time}) baru dapat dilakukan mulai pukul {$earliestClockIn->format('H:i')} (maksimal {$maxEarlyClockInMinutes} menit sebelum jam kerja).";
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
        $attendance = $existing ?? new Attendance();

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
            } else {
                $attendanceData['notes'] = $requiresApproval
                    ? 'Presensi masuk pada hari libur terjadwal (butuh persetujuan HR).'
                    : ($existing && $existing->status === 'ditolak' ? 'Presensi berhasil setelah percobaan sebelumnya sempat ditolak.' : null);
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
        $today = $now->toDateString();

        $attendance = Attendance::where('pegawai_id', $employee->id)
            ->whereDate('tanggal', $today)
            ->whereIn('status', ['hadir', 'terlambat', 'menunggu_approval'])
            ->whereNotNull('clock_in')
            ->first();

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

        $office = $attendance->officeLocation ?? $employee->officeLocation;
        if (!$office) {
            $office = OfficeLocation::where('is_active', true)->first();
        }

        $distance = null;
        $isWithinRadius = false;
        if ($office) {
            $distance = $this->geofenceService->calculateDistance(
                $userLat,
                $userLon,
                $office->latitude,
                $office->longitude
            );
            $isWithinRadius = $distance <= $office->radius_meters;
        }

        $rejectionReasons = [];
        if ($isMock) {
            $rejectionReasons[] = 'Terdeteksi penggunaan Fake/Mock Location (GPS Palsu).';
        }
        if ($accuracy > $maxGpsAccuracy) {
            $rejectionReasons[] = "Akurasi GPS tidak memadai ({$accuracy}m, batas maksimal {$maxGpsAccuracy}m).";
        }
        if ($office && !$isWithinRadius) {
            $rejectionReasons[] = "Di luar area kantor (Jarak: {$distance}m, radius diizinkan: {$office->radius_meters}m).";
        }
        if ($faceScore < $minFaceScore) {
            $rejectionReasons[] = "Skor pengenalan wajah rendah ({$faceScore}, batas minimal {$minFaceScore}).";
        }

        $dayOfWeek = $now->dayOfWeek;
        $schedule = null;
        if ($employee->shiftTemplate) {
            $schedule = $employee->shiftTemplate->getScheduleForDay($dayOfWeek);
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
            $scheduledEnd = Carbon::parse("{$today} {$schedule->end_time}");
            $earliestClockOut = $scheduledEnd->copy()->subMinutes($earlyLeaveToleranceMinutes);

            if ($now->lessThan($earliestClockOut)) {
                $rejectionReasons[] = "Presensi pulang belum dibuka. Presensi pulang untuk shift ini ({$schedule->end_time}) baru dapat dilakukan mulai pukul {$earliestClockOut->format('H:i')} (toleransi pulang cepat: {$earlyLeaveToleranceMinutes} menit).";
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
