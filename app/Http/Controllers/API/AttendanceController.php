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
        $employee = Pegawai::with(['officeLocation', 'shiftTemplate.days', 'user'])
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
            $employee->load(['officeLocation', 'shiftTemplate.days', 'user']);
        }

        return $employee;
    }

    /**
     * Ambil status presensi dan jadwal kerja hari ini
     */
    public function todayStatus(Request $request): JsonResponse
    {
        $employee = $this->getOrCreateEmployee($request->user());

        $today = Carbon::today();
        $dayOfWeek = $today->dayOfWeek; // 0=Minggu, 1=Senin, ..., 6=Sabtu

        $shiftTemplate = $employee->shiftTemplate ?? \App\Models\ShiftTemplate::where('is_active', true)->first();
        $schedule = null;
        if ($shiftTemplate) {
            $schedule = $shiftTemplate->getScheduleForDay($dayOfWeek);
        }

        $office = $employee->officeLocation ?? \App\Models\OfficeLocation::where('is_active', true)->first();

        $nationalHoliday = NationalHoliday::isHoliday($today);

        $attendance = Attendance::where('pegawai_id', $employee->id)
            ->where('tanggal', $today->toDateString())
            ->first();

        $appliesNationalHoliday = $schedule
            ? $schedule->appliesNationalHolidays()
            : ($shiftTemplate ? $shiftTemplate->applies_national_holidays : true);

        $isDutyOnHoliday = ($nationalHoliday !== null && !$appliesNationalHoliday);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $employee->user ? $employee->user->id : $request->user()->id,
                    'name' => $employee->nama_lengkap ?: $request->user()->username,
                    'email' => $request->user()->email,
                ],
                'employee' => [
                    'id' => $employee->id,
                    'employee_code' => $employee->nip,
                    'nip' => $employee->nip,
                    'position' => $employee->position,
                    'department' => $employee->department,
                    'is_face_enrolled' => !empty($employee->face_embedding),
                    'consent_pdp_at' => $employee->consent_pdp_at,
                    'office' => $office,
                ],
                'date' => $today->toDateString(),
                'day_name' => $schedule ? $schedule->day_name : 'Hari Ini',
                'schedule' => $schedule,
                'office' => $office,
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
            'is_mock_location' => 'nullable|boolean',
            'face_embedding' => 'nullable',
            'timestamp' => 'nullable',
        ]);

        $validated['is_mock_location'] = (bool) ($validated['is_mock_location'] ?? false);

        $employee = $this->getOrCreateEmployee($request->user());

        $attendance = $this->attendanceService->processClockIn($employee, $validated);

        $isSuccess = in_array($attendance->status, ['hadir', 'terlambat', 'menunggu_approval']);

        return response()->json([
            'status' => $isSuccess ? 'success' : 'error',
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
            'face_score' => 'nullable|numeric',
            'is_mock_location' => 'nullable|boolean',
            'timestamp' => 'nullable',
        ]);

        $validated['is_mock_location'] = (bool) ($validated['is_mock_location'] ?? false);
        $validated['face_score'] = (float) ($validated['face_score'] ?? 0.85);

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
        $validated = $request->validate([
            'tanggal' => 'required|date',
            'status_kehadiran' => 'required|string|in:izin,sakit,dinas',
            'catatan' => 'required|string|max:500',
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

        $payload = [
            'status' => $validated['status_kehadiran'],
            'notes' => $validated['catatan'],
            'is_approved_by_admin' => false,
        ];

        if ($existing) {
            $existing->update($payload);
            $attendance = $existing->fresh();
        } else {
            $attendance = Attendance::create(array_merge($payload, [
                'pegawai_id' => $employee->id,
                'tanggal' => $validated['tanggal'],
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
