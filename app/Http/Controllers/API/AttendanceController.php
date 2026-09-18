<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\NationalHoliday;
use App\Models\Simpeg\Pegawai;
use App\Services\AttendanceRecapService;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService,
        protected AttendanceRecapService $recapService
    ) {}

    /**
     * Ambil profil pegawai aktif atau inisialisasi default
     */
    protected function getOrCreateEmployee($user): Pegawai
    {
        $employee = Pegawai::with(['officeLocation', 'additionalOffices', 'shiftTemplate.days', 'user'])
            ->where('user_id', $user->id)
            ->first();

        if (!$employee) {
            $defaultOffice = \App\Models\OfficeLocation::where('is_active', true)->first();
            $defaultShift = \App\Models\ShiftTemplate::where('is_active', true)->first();
            $unitKerja = \App\Models\Simpeg\UnitKerja::first();

            $employee = Pegawai::firstOrCreate([
                'user_id' => $user->id,
            ], [
                'unit_kerja_id' => $unitKerja?->id,
                'office_location_id' => $defaultOffice?->id,
                'shift_template_id' => $defaultShift?->id,
                'nip' => '19' . date('ymd') . rand(100000, 999999),
                'nama_lengkap' => $user->name ?: ucfirst($user->username),
                'jenis_kelamin' => 'L',
                'jenis_pegawai' => 'dosen',
                'status_kepegawaian' => 'tetap_yayasan',
                'status' => 'aktif',
                'is_active' => true,
            ]);
            $employee->load(['officeLocation', 'additionalOffices', 'shiftTemplate.days', 'user']);
        }

        return $employee;
    }

    /**
     * Ambil status presensi dan jadwal kerja hari ini
     */
    public function todayStatus(Request $request): JsonResponse
    {
        $employee = $this->getOrCreateEmployee($request->user());

        $now = Carbon::now();
        $today = $now->toDateString();
        $dayOfWeek = $now->dayOfWeek; // 0=Minggu, 1=Senin, ..., 6=Sabtu

        $shiftTemplate = $employee->shiftTemplate ?? \App\Models\ShiftTemplate::where('is_active', true)->first();
        $schedule = null;
        if ($shiftTemplate) {
            $schedule = $shiftTemplate->getScheduleForDay($dayOfWeek);
        }

        $office = $employee->officeLocation ?? \App\Models\OfficeLocation::where('is_active', true)->first();

        $nationalHoliday = NationalHoliday::isHoliday(Carbon::parse($today));

        $attendance = Attendance::where('pegawai_id', $employee->id)
            ->whereDate('tanggal', $today)
            ->first();

        // Shift lintas hari: bila tidak ada record hari ini, tampilkan record
        // shift malam kemarin yang masih terbuka (belum clock-out).
        $dutyDate = $today;
        if (!$attendance && $shiftTemplate) {
            $yesterday = $now->copy()->subDay();
            $ySchedule = $shiftTemplate->getScheduleForDay($yesterday->dayOfWeek);
            if ($ySchedule && !$ySchedule->is_day_off && $ySchedule->isOvernight()) {
                $candidate = Attendance::where('pegawai_id', $employee->id)
                    ->whereDate('tanggal', $yesterday->toDateString())
                    ->whereIn('status', ['hadir', 'terlambat', 'menunggu_approval'])
                    ->whereNotNull('clock_in')
                    ->whereNull('clock_out')
                    ->first();
                if ($candidate) {
                    $attendance = $candidate;
                    $schedule = $ySchedule;
                    $dutyDate = $yesterday->toDateString();
                }
            }
        }

        $appliesNationalHoliday = $schedule
            ? $schedule->appliesNationalHolidays()
            : ($shiftTemplate ? $shiftTemplate->applies_national_holidays : true);

        $isDutyOnHoliday = ($nationalHoliday !== null && !$appliesNationalHoliday);

        // Helper boolean flags untuk Mobile UI
        $isClockedIn = ($attendance && $attendance->clock_in !== null);
        $isClockedOut = ($attendance && $attendance->clock_out !== null);
        $hasValidClockIn = ($attendance && in_array($attendance->status, ['hadir', 'terlambat', 'menunggu_approval']) && $attendance->clock_in !== null);

        // Tentukan apakah waktu saat ini sudah jam pulang shift
        $isPastShiftEnd = false;
        $isInClockOutWindow = false;

        if ($schedule && !$schedule->is_day_off && $schedule->end_time) {
            $dutyDateStr = $attendance ? Carbon::parse($attendance->getAttribute('tanggal'))->toDateString() : $dutyDate;
            $scheduledEnd = $schedule->getScheduledEndForDate($dutyDateStr);
            $earlyLeaveTolerance = $schedule->getEarlyLeaveToleranceMinutes();
            $earliestClockOut = $scheduledEnd->copy()->subMinutes($earlyLeaveTolerance);

            $isPastShiftEnd = $now->greaterThanOrEqualTo($scheduledEnd);
            $isInClockOutWindow = $now->greaterThanOrEqualTo($earliestClockOut);
        } elseif (!$schedule) {
            $scheduledEnd = Carbon::parse("{$dutyDate} 17:00:00");
            $earlyLeaveTolerance = $shiftTemplate ? $shiftTemplate->early_leave_tolerance_minutes : 15;
            $earliestClockOut = $scheduledEnd->copy()->subMinutes($earlyLeaveTolerance);

            $isPastShiftEnd = $now->greaterThanOrEqualTo($scheduledEnd);
            $isInClockOutWindow = $now->greaterThanOrEqualTo($earliestClockOut);
        }

        if ($isClockedOut) {
            $canClockIn = false;
            $canClockOut = false;
        } elseif ($hasValidClockIn) {
            $canClockIn = false;
            // Presensi pulang HANYA dibuka jika waktu saat ini sudah memasuki jendela jam pulang shift
            $canClockOut = $isInClockOutWindow;
        } else {
            // Belum pernah scan masuk hari ini
            if ($isPastShiftEnd || $isInClockOutWindow) {
                // Jam kerja shift sudah berakhir / sudah waktu pulang (misal jam 17:00 pada shift 08:00 - 16:00).
                // Jendela presensi masuk ditutup, buka presensi pulang!
                $canClockIn = false;
                $canClockOut = true;
            } else {
                $canClockIn = true;
                $canClockOut = false;
            }
        }

        // Fallback schedule object yang aman bagi parser client Flutter
        $schedulePayload = $schedule ? [
            'id' => $schedule->id,
            'day_of_week' => $schedule->day_of_week,
            'day_name' => $schedule->day_name,
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'break_start' => $schedule->break_start,
            'break_end' => $schedule->break_end,
            'is_day_off' => $schedule->is_day_off,
            'is_overnight' => $schedule->isOvernight(),
            'duty_date' => $dutyDate,
            'late_tolerance_minutes' => $schedule->getLateToleranceMinutes(),
            'early_leave_tolerance_minutes' => $schedule->getEarlyLeaveToleranceMinutes(),
            'max_early_clock_in_minutes' => $schedule->getMaxEarlyClockInMinutes(),
            'max_late_clock_in_minutes' => $schedule->getMaxLateClockInMinutes(),
        ] : [
            'id' => null,
            'day_of_week' => $dayOfWeek,
            'day_name' => match ($dayOfWeek) {
                0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', default => 'Hari Ini'
            },
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
            'is_day_off' => ($dayOfWeek === 0 || $dayOfWeek === 6),
            'is_overnight' => false,
            'duty_date' => $dutyDate,
            'late_tolerance_minutes' => $shiftTemplate ? $shiftTemplate->late_tolerance_minutes : 15,
            'early_leave_tolerance_minutes' => $shiftTemplate ? $shiftTemplate->early_leave_tolerance_minutes : 15,
            'max_early_clock_in_minutes' => $shiftTemplate ? $shiftTemplate->max_early_clock_in_minutes : 60,
            'max_late_clock_in_minutes' => $shiftTemplate ? ($shiftTemplate->max_late_clock_in_minutes ?? 240) : 240,
        ];

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => [
                'server_time' => $now->toIso8601String(),
                'server_timestamp' => $now->timestamp,
                'server_date' => $today,
                'server_time_formatted' => $now->format('H:i:s'),
                'can_clock_in' => $canClockIn,
                'can_clock_out' => $canClockOut,
                'is_clocked_in' => $isClockedIn,
                'is_clocked_out' => $isClockedOut,
                'clock_in_time' => $attendance?->clock_in ? $attendance->clock_in->format('H:i:s') : null,
                'clock_out_time' => $attendance?->clock_out ? $attendance->clock_out->format('H:i:s') : null,
                'status' => $attendance?->status ?? 'belum_absen',
                'user' => [
                    'id' => $employee->user ? $employee->user->id : $request->user()->id,
                    'name' => $employee->nama_lengkap ?: $request->user()->username,
                    'username' => $request->user()->username,
                    'email' => $request->user()->email,
                    'avatar' => $employee->avatar,
                ],
                'employee' => [
                    'id' => $employee->id,
                    'employee_code' => $employee->nip,
                    'nip' => $employee->nip,
                    'nama' => $employee->nama_lengkap,
                    'nama_lengkap' => $employee->nama_lengkap,
                    'position' => $employee->position,
                    'department' => $employee->department,
                    'avatar' => $employee->avatar,
                    'foto_url' => $employee->foto_url,
                    'is_face_enrolled' => !empty($employee->face_embedding),
                    'consent_pdp_at' => $employee->consent_pdp_at,
                    'office' => $office,
                    'shift' => $shiftTemplate,
                ],
                'date' => $today,
                'day_name' => $schedulePayload['day_name'],
                'schedule' => $schedulePayload,
                'office' => $office,
                'allowed_offices' => $employee->getAllowedOfficeLocations()->values(),
                'attendance' => $attendance,
                'is_national_holiday' => $nationalHoliday !== null,
                'applies_national_holidays' => $appliesNationalHoliday,
                'is_duty_on_holiday' => $isDutyOnHoliday,
                'national_holiday' => $nationalHoliday ? [
                    'name' => $nationalHoliday->name,
                    'is_mass_leave' => $nationalHoliday->is_mass_leave,
                    'description' => $nationalHoliday->description,
                ] : null,
            ],
        ]);
    }

    /**
     * Presensi Masuk (Clock In)
     */
    public function clockIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy' => 'required|numeric',
            'face_score' => 'nullable|numeric',
            'face_image' => 'nullable|string',
            'foto' => 'nullable',
            'foto_presensi' => 'nullable',
            'is_mock_location' => 'nullable|boolean',
            'face_embedding' => 'nullable',
            'timestamp' => 'nullable',
            'device_id' => 'nullable|string',
            'device_info' => 'nullable|string',
            'address' => 'nullable|string',
            'notes' => 'nullable|string|max:500',
            'catatan' => 'nullable|string|max:500',
        ]);

        $validated['is_mock_location'] = (bool) ($validated['is_mock_location'] ?? false);

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto');
        } elseif ($request->hasFile('foto_presensi')) {
            $validated['foto_presensi'] = $request->file('foto_presensi');
        } elseif ($request->hasFile('face_image')) {
            $validated['face_image'] = $request->file('face_image');
        }

        $employee = $this->getOrCreateEmployee($request->user());

        $attendance = $this->attendanceService->processClockIn($employee, $validated);

        $isSuccess = in_array($attendance->status, ['hadir', 'terlambat', 'menunggu_approval']);

        return response()->json([
            'status' => $isSuccess ? 'success' : 'error',
            'success' => $isSuccess,
            'message' => match ($attendance->status) {
                'hadir' => ($attendance->clock_out && !$attendance->clock_in)
                    ? 'Presensi pulang berhasil dicatat (jam shift telah berakhir).'
                    : 'Presensi masuk berhasil (Tepat Waktu).',
                'terlambat' => 'Presensi masuk berhasil dicatat (Terlambat).',
                'menunggu_approval' => 'Presensi masuk pada hari libur tersimpan, menunggu persetujuan HR.',
                'ditolak' => 'Presensi masuk ditolak: ' . $attendance->rejection_reason,
                default => 'Status presensi: ' . $attendance->status,
            },
            'data' => $attendance,
        ], $attendance->status === 'ditolak' ? 422 : 200);
    }

    /**
     * Presensi Pulang (Clock Out)
     */
    public function clockOut(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy' => 'required|numeric',
            'face_score' => 'nullable|numeric',
            'face_image' => 'nullable|string',
            'foto' => 'nullable',
            'foto_presensi' => 'nullable',
            'is_mock_location' => 'nullable|boolean',
            'timestamp' => 'nullable',
            'device_id' => 'nullable|string',
            'device_info' => 'nullable|string',
            'notes' => 'nullable|string|max:500',
            'catatan' => 'nullable|string|max:500',
        ]);

        $validated['is_mock_location'] = (bool) ($validated['is_mock_location'] ?? false);
        $validated['face_score'] = (float) ($validated['face_score'] ?? 0.85);

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto');
        } elseif ($request->hasFile('foto_presensi')) {
            $validated['foto_presensi'] = $request->file('foto_presensi');
        } elseif ($request->hasFile('face_image')) {
            $validated['face_image'] = $request->file('face_image');
        }

        $employee = $this->getOrCreateEmployee($request->user());

        $attendance = $this->attendanceService->processClockOut($employee, $validated);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Presensi pulang berhasil dicatat.',
            'data' => $attendance,
        ]);
    }

    /**
     * Pengajuan izin / sakit / dinas mandiri dari Mobile
     */
    public function keterangan(Request $request): JsonResponse
    {
        $status = $request->input('status_kehadiran') ?? $request->input('status');
        $catatan = $request->input('catatan') ?? $request->input('notes') ?? ucfirst($status ?? 'izin');

        $request->merge([
            'status_kehadiran' => $status,
            'catatan' => $catatan,
        ]);

        $validated = $request->validate([
            'tanggal' => 'required|date',
            'status_kehadiran' => 'required|string|in:izin,sakit,dinas,cuti,alfa',
            'catatan' => 'nullable|string|max:1000',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'lampiran' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'bukti' => 'nullable',
            'foto' => 'nullable',
        ]);

        $employee = $this->getOrCreateEmployee($request->user());

        $existing = Attendance::where('pegawai_id', $employee->id)
            ->whereDate('tanggal', $validated['tanggal'])
            ->first();

        if ($existing && ($existing->clock_in || $existing->clock_out)) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Tanggal tersebut sudah memiliki data hasil scan presensi dan tidak dapat ditimpa.',
            ], 422);
        }

        // Simpan lampiran/bukti jika ada
        $attachmentFile = $request->file('file')
            ?? $request->file('lampiran')
            ?? $request->file('foto')
            ?? $request->input('bukti')
            ?? $request->input('foto');

        $attachmentPath = null;
        if ($attachmentFile) {
            $attachmentPath = $this->attendanceService->saveAttendanceFile($attachmentFile, 'presensi/lampiran');
        }

        $payload = [
            'status' => $validated['status_kehadiran'],
            'status_kehadiran' => $validated['status_kehadiran'],
            'notes' => $catatan,
            'catatan' => $catatan,
            'is_approved_by_admin' => false,
        ];

        if ($attachmentPath) {
            $payload['foto_presensi'] = $attachmentPath;
        }

        if ($existing) {
            $existing->update($payload);
            $attendance = $existing->fresh();
        } else {
            $attendance = Attendance::create(array_merge($payload, [
                'pegawai_id' => $employee->id,
                'tanggal' => $validated['tanggal'],
                'source' => 'mobile_gps',
            ]));
        }

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => "Pengajuan keterangan {$attendance->status} berhasil disimpan.",
            'data' => $attendance,
        ], 201);
    }

    /**
     * Riwayat presensi milik karyawan yang sedang login
     */
    public function history(Request $request): JsonResponse
    {
        $employee = $this->getOrCreateEmployee($request->user());

        $query = Attendance::where('pegawai_id', $employee->id)->orderByDesc('tanggal');

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('tanggal', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('tanggal', $request->month)
                  ->whereYear('tanggal', $request->year);
        } elseif ($request->filled('date')) {
            $query->whereDate('tanggal', $request->date);
        }

        if ($request->filled('status')) {
            $status = $request->status;
            $query->where(function ($q) use ($status) {
                $q->where('status', $status)
                  ->orWhere('status_kehadiran', $status);
            });
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', $request->input('limit', 31))));
        $paginator = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Riwayat presensi berhasil diambil',
            'data' => [
                'attendances' => $paginator->items(),
                'total' => $paginator->total(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * Rekap bulanan untuk karyawan yang login
     */
    public function recap(Request $request): JsonResponse
    {
        $employee = $this->getOrCreateEmployee($request->user());

        $month = (int) $request->input('month', Carbon::now()->month);
        $year = (int) $request->input('year', Carbon::now()->year);

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $recap = $this->recapService->generateRecap($employee, $startDate, $endDate);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Rekap presensi berhasil diambil',
            'data' => $recap,
        ]);
    }
}
