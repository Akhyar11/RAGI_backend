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
     * Ambil status presensi dan jadwal kerja hari ini
     */
    public function todayStatus(Request $request): JsonResponse
    {
        $employee = Pegawai::with(['officeLocation', 'shiftTemplate.days', 'user'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $today = Carbon::today();
        $dayOfWeek = $today->dayOfWeek; // 0=Minggu, 1=Senin, ..., 6=Sabtu

        $schedule = null;
        if ($employee->shiftTemplate) {
            $schedule = $employee->shiftTemplate->getScheduleForDay($dayOfWeek);
        }

        $nationalHoliday = NationalHoliday::isHoliday($today);

        $attendance = Attendance::where('pegawai_id', $employee->id)
            ->where('tanggal', $today->toDateString())
            ->first();

        $appliesNationalHoliday = $schedule
            ? $schedule->appliesNationalHolidays()
            : ($employee->shiftTemplate ? $employee->shiftTemplate->applies_national_holidays : true);

        $isDutyOnHoliday = ($nationalHoliday !== null && !$appliesNationalHoliday);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $employee->user->id,
                    'name' => $employee->nama_lengkap ?: $employee->user->username,
                    'email' => $employee->user->email,
                ],
                'employee' => [
                    'id' => $employee->id,
                    'employee_code' => $employee->nip,
                    'position' => $employee->position,
                    'department' => $employee->department,
                    'is_face_enrolled' => !empty($employee->face_embedding),
                    'consent_pdp_at' => $employee->consent_pdp_at,
                    'office' => $employee->officeLocation,
                ],
                'date' => $today->toDateString(),
                'day_name' => $schedule ? $schedule->day_name : 'Hari Ini',
                'schedule' => $schedule,
                'office' => $employee->officeLocation,
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
            'is_mock_location' => 'required|boolean',
            'face_embedding' => 'nullable',
            'timestamp' => 'nullable',
        ]);

        $employee = Pegawai::with(['officeLocation', 'shiftTemplate.days'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $attendance = $this->attendanceService->processClockIn($employee, $validated);

        $isSuccess = in_array($attendance->status, ['hadir', 'terlambat', 'menunggu_approval']);

        return response()->json([
            'success' => $isSuccess,
            'message' => match ($attendance->status) {
                'hadir' => 'Presensi masuk berhasil (Tepat Waktu).',
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
            'face_score' => 'required|numeric',
            'is_mock_location' => 'required|boolean',
            'timestamp' => 'nullable',
        ]);

        $employee = Pegawai::where('user_id', $request->user()->id)->firstOrFail();

        $attendance = $this->attendanceService->processClockOut($employee, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Presensi pulang berhasil dicatat.',
            'data' => $attendance,
        ]);
    }

    /**
     * Riwayat presensi milik karyawan yang sedang login
     */
    public function history(Request $request): JsonResponse
    {
        $employee = Pegawai::where('user_id', $request->user()->id)->firstOrFail();

        $query = Attendance::where('pegawai_id', $employee->id)->orderByDesc('tanggal');

        if ($request->has('month') && $request->has('year')) {
            $query->whereMonth('tanggal', $request->month)
                  ->whereYear('tanggal', $request->year);
        }

        $history = $query->limit(31)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'attendances' => $history,
                'total' => $history->count(),
            ],
        ]);
    }

    /**
     * Rekap bulanan untuk karyawan yang login
     */
    public function recap(Request $request): JsonResponse
    {
        $employee = Pegawai::where('user_id', $request->user()->id)->firstOrFail();

        $month = (int) $request->input('month', Carbon::now()->month);
        $year = (int) $request->input('year', Carbon::now()->year);

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $recap = $this->recapService->generateRecap($employee, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $recap,
        ]);
    }
}
