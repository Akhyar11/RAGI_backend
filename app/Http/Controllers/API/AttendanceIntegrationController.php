<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Simpeg\Pegawai;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceIntegrationController extends Controller
{
    /**
     * Mengambil daftar data absensi karyawan dengan filter lengkap.
     * Endpoint: GET /api/v1/integration/attendances
     */
    public function index(Request $request): JsonResponse
    {
        $query = Attendance::with(['employee.user', 'officeLocation']);

        // 1. Filter Tanggal
        if ($request->filled('date')) {
            $query->whereDate('tanggal', $request->date);
        } elseif ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('tanggal', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('tanggal', $request->month)
                  ->whereYear('tanggal', $request->year);
        }

        // 2. Filter NIP / Kode Pegawai
        if ($request->filled('employee_code') || $request->filled('nip')) {
            $nip = $request->input('employee_code') ?: $request->input('nip');
            $query->whereHas('employee', function ($q) use ($nip) {
                $q->where('nip', $nip);
            });
        }

        // 3. Filter Departemen / Unit Kerja
        if ($request->filled('department')) {
            $dept = $request->department;
            $query->whereHas('employee.unitKerja', function ($q) use ($dept) {
                $q->where('nama', 'like', "%{$dept}%");
            });
        }

        // 4. Filter Status Kehadiran ('hadir', 'terlambat', 'ditolak', 'menunggu_approval', dll)
        if ($request->filled('status')) {
            $status = strtolower($request->status);
            if ($status === 'present') {
                $query->whereIn('status_kehadiran', ['hadir', 'terlambat']);
            } elseif ($status === 'late') {
                $query->where('status_kehadiran', 'terlambat');
            } elseif ($status === 'on_time') {
                $query->where('status_kehadiran', 'hadir');
            } else {
                $query->where('status_kehadiran', $status);
            }
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));
        $paginator = $query->orderByDesc('tanggal')
                           ->orderByDesc('clock_in')
                           ->paginate($perPage);

        $items = $paginator->getCollection()->map(function (Attendance $att) {
            return $this->formatAttendanceItem($att);
        });

        return response()->json([
            'success' => true,
            'message' => 'Data absensi berhasil diambil.',
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total_records' => $paginator->total(),
                'total_pages' => $paginator->lastPage(),
            ],
            'data' => $items,
        ]);
    }

    /**
     * Mengambil detail satu data log absensi berdasarkan ID.
     * Endpoint: GET /api/v1/integration/attendances/{id}
     */
    public function show(int $id): JsonResponse
    {
        $attendance = Attendance::with(['employee.user', 'officeLocation'])->find($id);

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'message' => "Data absensi dengan ID {$id} tidak ditemukan.",
            ], 404);
        }

        $formatted = $this->formatAttendanceItem($attendance);
        $formatted['details'] = [
            'clock_in_distance_meters' => $attendance->clock_in_distance_meters,
            'clock_out_distance_meters' => $attendance->clock_out_distance_meters,
            'clock_in_is_mock_location' => (bool) $attendance->clock_in_is_mock_location,
            'clock_out_is_mock_location' => (bool) $attendance->clock_out_is_mock_location,
            'notes' => $attendance->notes,
            'approved_by' => $attendance->approved_by,
            'approved_at' => $attendance->approved_at?->toIso8601String(),
        ];

        return response()->json([
            'success' => true,
            'data' => $formatted,
        ]);
    }

    /**
     * Menghasilkan rekapitulasi statistik kehadiran bulanan/tahunan.
     * Endpoint: GET /api/v1/integration/attendances/recap
     */
    public function recap(Request $request): JsonResponse
    {
        $month = $request->input('month', Carbon::now()->month);
        $year = $request->input('year', Carbon::now()->year);

        $query = Attendance::whereMonth('tanggal', $month)
            ->whereYear('tanggal', $year);

        if ($request->filled('nip') || $request->filled('employee_code')) {
            $nip = $request->input('nip') ?: $request->input('employee_code');
            $query->whereHas('employee', fn($q) => $q->where('nip', $nip));
        }

        if ($request->filled('department')) {
            $dept = $request->department;
            $query->whereHas('employee.unitKerja', fn($q) => $q->where('nama', 'like', "%{$dept}%"));
        }

        $allRecords = $query->with('employee.unitKerja')->get();

        $totalPresent = $allRecords->whereIn('status_kehadiran', ['hadir', 'terlambat'])->count();
        $totalLate = $allRecords->where('status_kehadiran', 'terlambat')->count();
        $totalOnTime = $allRecords->where('status_kehadiran', 'hadir')->count();
        $totalRejected = $allRecords->where('status_kehadiran', 'ditolak')->count();
        $totalLateMinutes = (int) $allRecords->sum('late_minutes');

        $groupedByEmployee = $allRecords->groupBy('pegawai_id');
        $employeeStats = [];

        foreach ($groupedByEmployee as $empId => $records) {
            $first = $records->first();
            $emp = $first->employee;
            if (!$emp) continue;

            $empHadir = $records->whereIn('status_kehadiran', ['hadir', 'terlambat'])->count();
            $empLate = $records->where('status_kehadiran', 'terlambat')->count();
            $empOnTime = $records->where('status_kehadiran', 'hadir')->count();
            $empLateMins = (int) $records->sum('late_minutes');

            $employeeStats[] = [
                'employee_id' => $emp->id,
                'nip' => $emp->nip,
                'name' => $emp->nama_lengkap,
                'department' => $emp->department,
                'total_hadir' => $empHadir,
                'total_terlambat' => $empLate,
                'total_tepat_waktu' => $empOnTime,
                'total_menit_terlambat' => $empLateMins,
            ];
        }

        return response()->json([
            'success' => true,
            'period' => [
                'month' => (int) $month,
                'year' => (int) $year,
            ],
            'summary' => [
                'total_records' => $allRecords->count(),
                'total_present' => $totalPresent,
                'total_on_time' => $totalOnTime,
                'total_late' => $totalLate,
                'total_rejected' => $totalRejected,
                'total_late_minutes' => $totalLateMinutes,
            ],
            'employees' => $employeeStats,
        ]);
    }

    /**
     * Format entitas Attendance ke format JSON respons standar.
     */
    protected function formatAttendanceItem(Attendance $att): array
    {
        $emp = $att->employee;
        $user = $emp?->user;
        $office = $att->officeLocation;

        $clockInTime = $att->clock_in ? $att->clock_in->format('H:i:s') : null;
        $clockOutTime = $att->clock_out ? $att->clock_out->format('H:i:s') : null;

        $workMinutes = 0;
        if ($att->clock_in && $att->clock_out) {
            $workMinutes = max(0, (int) $att->clock_in->diffInMinutes($att->clock_out));
        }

        return [
            'id' => $att->id,
            'attendance_date' => $att->tanggal?->toDateString(),
            'status' => $att->status_kehadiran,
            'is_late' => $att->status_kehadiran === 'terlambat',
            'employee' => $emp ? [
                'id' => $emp->id,
                'nip' => $emp->nip,
                'name' => $emp->nama_lengkap ?: $user?->username,
                'email' => $user?->email,
                'department' => $emp->department,
                'position' => $emp->position,
            ] : null,
            'office' => $office ? [
                'id' => $office->id,
                'name' => $office->name,
                'radius_meters' => $office->radius_meters,
            ] : null,
            'clock_in' => $att->clock_in ? [
                'time' => $clockInTime,
                'datetime' => $att->clock_in->toIso8601String(),
                'status' => $att->status_kehadiran === 'terlambat' ? 'late' : 'on_time',
                'latitude' => $att->clock_in_latitude,
                'longitude' => $att->clock_in_longitude,
                'accuracy' => $att->clock_in_accuracy,
                'distance_meters' => $att->clock_in_distance_meters,
                'face_score' => $att->clock_in_face_score,
                'is_mock_location' => (bool) $att->clock_in_is_mock_location,
            ] : null,
            'clock_out' => $att->clock_out ? [
                'time' => $clockOutTime,
                'datetime' => $att->clock_out->toIso8601String(),
                'latitude' => $att->clock_out_latitude,
                'longitude' => $att->clock_out_longitude,
                'accuracy' => $att->clock_out_accuracy,
                'distance_meters' => $att->clock_out_distance_meters,
                'face_score' => $att->clock_out_face_score,
                'is_mock_location' => (bool) $att->clock_out_is_mock_location,
            ] : null,
            'durations' => [
                'work_minutes' => $workMinutes,
                'work_hours' => round($workMinutes / 60, 2),
                'late_minutes' => (int) $att->late_minutes,
            ],
            'rejection_reason' => $att->rejection_reason,
            'notes' => $att->notes,
            'is_approved_by_admin' => $att->is_approved_by_admin,
            'created_at' => $att->created_at?->toIso8601String(),
            'updated_at' => $att->updated_at?->toIso8601String(),
        ];
    }
}
