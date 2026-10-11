<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Simpeg\KoreksiPresensiBundleRequest;
use App\Http\Requests\Simpeg\SetKeteranganRequest;
use App\Http\Requests\Simpeg\UpdatePresensiLogRequest;
use App\Models\Attendance;
use App\Models\NationalHoliday;
use App\Models\Simpeg\GajiDetail;
use App\Models\Simpeg\GajiPegawai;
use App\Models\Simpeg\MasterKomponenGaji;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PegawaiKomponenGaji;
use App\Models\Simpeg\PresensiPegawai;
use App\Models\Simpeg\PresensiPeriode;
use App\Services\AttendanceCutoffService;
use App\Services\AttendanceRecapService;
use App\Services\AttendanceService;
use App\Services\FingerprintSyncService;
use App\Services\Simpeg\PresensiBundleService;
use App\Services\Storage\FileStorageService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PresensiController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService,
        protected AttendanceRecapService $recapService,
        protected FileStorageService $files
    ) {}

    /**
     * Daftar log presensi atau bundle presensi
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. Mode Bundle (PresensiPeriode)
        if ($request->input('type') === 'bundle' || $request->has('bundle') || $request->input('view') === 'bundle') {
            if (!$user->hasPermission('simpeg.presensi.read') && !$user->hasPermission('simpeg.presensi.manage') && !$user->isAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki hak akses (permission) untuk melihat Presensi.'
                ], 403);
            }

            // Sinkronisasi otomatis bundle dari log realtime sebelum mengambil data
            app(PresensiBundleService::class)->autoSyncBundlesFromRealtime($user->id);

            $query = PresensiPeriode::with('creator');

            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('nama_periode', 'like', "%{$search}%")
                      ->orWhere('catatan', 'like', "%{$search}%");
                });
            }

            $orderBy = $request->input('sort_by', $request->input('orderBy', 'created_at'));
            $orderDir = strtolower($request->input('sort_dir', $request->input('orderDir', 'desc')));
            
            $allowedSorts = ['id', 'nama_periode', 'tanggal_awal', 'tanggal_akhir', 'total_record', 'created_at'];
            if (!in_array($orderBy, $allowedSorts)) {
                $orderBy = 'created_at';
            }
            if (!in_array($orderDir, ['asc', 'desc'])) {
                $orderDir = 'desc';
            }

            $query->orderBy($orderBy, $orderDir);

            $limit = (int) $request->input('limit', $request->input('per_page', 15));
            if ($limit <= 0) $limit = 15;

            $periodes = $query->paginate($limit);

            // Append total distinct pegawai for each periode
            $periodes->getCollection()->transform(function ($p) {
                $data = $p->toArray();
                $data['total_pegawai'] = PresensiPegawai::where('presensi_periode_id', $p->id)->distinct('pegawai_id')->count('pegawai_id');
                return $data;
            });

            return response()->json([
                'status' => 'success',
                'data' => $periodes->items(),
                'meta' => [
                    'current_page' => $periodes->currentPage(),
                    'from' => $periodes->firstItem(),
                    'last_page' => $periodes->lastPage(),
                    'per_page' => $periodes->perPage(),
                    'to' => $periodes->lastItem(),
                    'total' => $periodes->total(),
                ]
            ]);
        }

        // 2. Mode Realtime Biometric Attendance
        $isManager = $user->hasPermission('simpeg.presensi.manage') || $user->isAdmin() || $user->hasRole('admin_simpeg') || $user->hasRole('admin');

        $query = Attendance::with(['employee.unitKerja', 'officeLocation']);

        // Jika bukan manager/admin, hanya bisa melihat data milik sendiri
        if (!$isManager) {
            $pegId = $user->pegawai?->id;
            if (!$pegId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Profil pegawai tidak ditemukan.',
                ], 404);
            }
            $query->where('pegawai_id', $pegId);
        } elseif ($request->filled('pegawai_id')) {
            $query->where('pegawai_id', $request->pegawai_id);
        }

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        } elseif ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('tanggal', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('tanggal', $request->month)
                  ->whereYear('tanggal', $request->year);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status_kehadiran', $request->status);
        }

        $orderBy = $request->input('sort_by', $request->input('orderBy', 'tanggal'));
        $orderDir = strtolower($request->input('sort_dir', $request->input('orderDir', 'desc')));

        $allowedSorts = [
            'tanggal' => 'tanggal',
            'clock_in' => 'clock_in',
            'clock_out' => 'clock_out',
            'status_kehadiran' => 'status_kehadiran',
            'id' => 'id',
            'created_at' => 'created_at',
        ];

        if (array_key_exists($orderBy, $allowedSorts)) {
            $sortCol = $allowedSorts[$orderBy];
            $orderDir = in_array($orderDir, ['asc', 'desc']) ? $orderDir : 'desc';
            $query->orderBy($sortCol, $orderDir);
            if ($sortCol !== 'clock_in') {
                $query->orderBy('clock_in', 'desc');
            }
        } else {
            $query->orderByDesc('tanggal')->orderByDesc('clock_in');
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', $request->input('limit', 15))));
        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data presensi berhasil diambil',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
        ]);
    }

    /**
     * Detail log presensi atau detail bundle presensi
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        // Jika request secara eksplisit meminta data bundle presensi
        if ($request->has('bundle') || $request->input('type') === 'bundle' || $request->filled('status_kehadiran')) {
            if (!$user->hasPermission('simpeg.presensi.read') && !$user->hasPermission('simpeg.presensi.manage') && !$user->isAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki hak akses (permission) untuk melihat Presensi.'
                ], 403);
            }

            $periode = PresensiPeriode::with('creator')->findOrFail($id);

            // JIKA VIEW = MATRIX: Tampilkan data matriks pegawai dan log presensi per hari
            if ($request->input('view') === 'matrix' || $request->input('format') === 'matrix') {
                $startDate = Carbon::parse($periode->tanggal_awal)->startOfDay();
                $endDate = Carbon::parse($periode->tanggal_akhir)->startOfDay();
                $today = Carbon::today();

                // Ambil daftar libur nasional jika tabel model tersedia
                $holidaysMap = [];
                $holidayTable = (new NationalHoliday())->getTable();
                if (Schema::hasTable($holidayTable)) {
                    $holidays = NationalHoliday::whereBetween('holiday_date', [$startDate->toDateString(), $endDate->toDateString()])->get();
                    foreach ($holidays as $h) {
                        $holidaysMap[Carbon::parse($h->holiday_date)->toDateString()] = $h->name ?? 'Libur Nasional';
                    }
                }

                $days = [];
                $curr = $startDate->copy();
                $dayIndex = 1;
                $namaHari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

                while ($curr->lte($endDate)) {
                    $dateStr = $curr->toDateString();
                    $isSunday = $curr->dayOfWeek === Carbon::SUNDAY;
                    $holidayName = $holidaysMap[$dateStr] ?? null;
                    $isHoliday = $isSunday || !empty($holidayName);

                    $days[] = [
                        'index' => $dayIndex++,
                        'date' => $dateStr,
                        'day' => (int) $curr->format('d'),
                        'month' => (int) $curr->format('m'),
                        'day_name' => $namaHari[$curr->dayOfWeek],
                        'is_sunday' => $isSunday,
                        'is_holiday' => $isHoliday,
                        'holiday_name' => $holidayName,
                    ];
                    $curr->addDay();
                }

                // Query Pegawai aktif atau yang memiliki data di bundle ini
                $pegawaiQuery = Pegawai::query()->where('is_active', true);
                if ($request->filled('search')) {
                    $s = trim($request->search);
                    $pegawaiQuery->where(function ($q) use ($s) {
                        $q->where('nama_lengkap', 'like', "%{$s}%")
                          ->orWhere('nip', 'like', "%{$s}%");
                    });
                }

                $perPage = min(100, max(1, $request->integer('per_page', $request->integer('limit', 15))));

                $paginatedPegawai = $pegawaiQuery->orderBy('nama_lengkap', 'asc')->paginate($perPage);
                $pegawaiIds = $paginatedPegawai->pluck('id')->toArray();

                // Ambil seluruh catatan presensi untuk pegawai pada periode ini
                $presensiRecords = PresensiPegawai::where('presensi_periode_id', $periode->id)
                    ->whereIn('pegawai_id', $pegawaiIds)
                    ->get();

                $presensiMap = [];
                foreach ($presensiRecords as $rec) {
                    $d = Carbon::parse($rec->tanggal)->toDateString();
                    $presensiMap[$rec->pegawai_id][$d] = $rec;
                }

                $matrixRows = [];
                foreach ($paginatedPegawai as $pegawai) {
                    $attendanceMap = [];
                    $totalHadir = 0;
                    $totalTerlambat = 0;
                    $totalPulangCepat = 0;
                    $totalIzin = 0;
                    $totalAlfa = 0;
                    $totalHariKerjaBerjalan = 0;

                    foreach ($days as $d) {
                        $dateStr = $d['date'];
                        $isHoliday = $d['is_holiday'];
                        $carbonDate = Carbon::parse($dateStr);
                        $isFuture = $carbonDate->gt($today);
                        $isPastOrToday = !$isFuture;

                        if (!$isHoliday && $isPastOrToday) {
                            $totalHariKerjaBerjalan++;
                        }

                        $rec = $presensiMap[$pegawai->id][$dateStr] ?? null;

                        if ($rec) {
                            $st = strtolower($rec->status_kehadiran ?? 'hadir');
                            $jamMasuk = $rec->jam_masuk ? substr($rec->jam_masuk, 0, 8) : null;
                            $jamKeluar = $rec->jam_keluar ? substr($rec->jam_keluar, 0, 8) : null;

                            $minutesLate = 0;
                            $minutesEarly = 0;

                            if (in_array($st, ['izin', 'sakit', 'dinas'])) {
                                $inStatus = 'permission';
                                $outStatus = 'permission';
                                $totalIzin++;
                                $totalHadir++;
                            } elseif ($st === 'alfa') {
                                $inStatus = 'absent';
                                $outStatus = 'absent';
                                $totalAlfa++;
                            } else {
                                $hasAnyScan = false;

                                // 1. Kotak Masuk
                                if ($jamMasuk) {
                                    $hasAnyScan = true;
                                    if ($jamMasuk <= '08:15:00') {
                                        $inStatus = 'on_time';
                                    } else {
                                        $inStatus = 'late';
                                        $totalTerlambat++;
                                        $parts = explode(':', $jamMasuk);
                                        $mIn = ((int)($parts[0] ?? 0)) * 60 + ((int)($parts[1] ?? 0));
                                        $minutesLate = max(0, $mIn - (8 * 60));
                                    }
                                } else {
                                    $inStatus = $isPastOrToday ? ($isHoliday ? 'holiday' : 'absent') : 'upcoming';
                                }

                                // 2. Kotak Pulang
                                if ($jamKeluar) {
                                    $hasAnyScan = true;
                                    if ($jamKeluar >= '16:00:00') {
                                        $outStatus = 'on_time';
                                    } else {
                                        $outStatus = 'early';
                                        $totalPulangCepat++;
                                        $parts = explode(':', $jamKeluar);
                                        $mOut = ((int)($parts[0] ?? 0)) * 60 + ((int)($parts[1] ?? 0));
                                        $minutesEarly = max(0, (16 * 60) - $mOut);
                                    }
                                } else {
                                    $outStatus = $isPastOrToday ? ($isHoliday ? 'holiday' : 'absent') : 'upcoming';
                                }

                                if ($hasAnyScan) {
                                    $totalHadir++;
                                } elseif (!$isHoliday && $isPastOrToday) {
                                    $totalAlfa++;
                                }
                            }

                            $isCorrected = ($rec->source === 'koreksi_admin') || str_contains((string)$rec->catatan, '[Koreksi Admin]');

                            $attendanceMap[$dateStr] = [
                                'id' => $rec->id,
                                'date' => $dateStr,
                                'is_holiday' => $isHoliday,
                                'status_kehadiran' => $st,
                                'in_status' => $inStatus,
                                'in_time' => $jamMasuk,
                                'in_minutes_late' => $minutesLate,
                                'out_status' => $outStatus,
                                'out_time' => $jamKeluar,
                                'out_minutes_early' => $minutesEarly,
                                'catatan' => $rec->catatan,
                                'is_corrected' => $isCorrected,
                                'source' => $rec->source,
                            ];
                        } else {
                            if ($isHoliday) {
                                $inStatus = 'holiday';
                                $outStatus = 'holiday';
                            } elseif ($isFuture) {
                                $inStatus = 'upcoming';
                                $outStatus = 'upcoming';
                            } else {
                                $inStatus = 'absent';
                                $outStatus = 'absent';
                                $totalAlfa++;
                            }

                            $attendanceMap[$dateStr] = [
                                'id' => null,
                                'date' => $dateStr,
                                'is_holiday' => $isHoliday,
                                'status_kehadiran' => $isHoliday ? 'libur' : ($isFuture ? 'belum' : 'alfa'),
                                'in_status' => $inStatus,
                                'in_time' => null,
                                'in_minutes_late' => 0,
                                'out_status' => $outStatus,
                                'out_time' => null,
                                'out_minutes_early' => 0,
                                'catatan' => null,
                                'is_corrected' => false,
                                'source' => null,
                            ];
                        }
                    }

                    $persentase = $totalHariKerjaBerjalan > 0
                        ? round(($totalHadir / $totalHariKerjaBerjalan) * 100, 1)
                        : 100.0;

                    $matrixRows[] = [
                        'pegawai_id' => $pegawai->id,
                        'nip' => $pegawai->nip ?? '-',
                        'nama_lengkap' => $pegawai->nama_lengkap,
                        'matrix' => $attendanceMap,
                        'summary' => [
                            'total_hadir' => $totalHadir,
                            'total_terlambat' => $totalTerlambat,
                            'total_pulang_cepat' => $totalPulangCepat,
                            'total_izin' => $totalIzin,
                            'total_alfa' => $totalAlfa,
                            'total_hari_kerja' => $totalHariKerjaBerjalan,
                            'persentase' => $persentase,
                        ]
                    ];
                }

                $totalPegawaiCount = Pegawai::where('is_active', true)->count();
                $bundleData = $periode->toArray();
                $bundleData['total_pegawai'] = $totalPegawaiCount;

                return response()->json([
                    'status' => 'success',
                    'message' => 'Data matriks presensi berhasil diambil',
                    'data' => [
                        'periode' => $bundleData,
                        'days' => $days,
                        'matrix' => $matrixRows,
                    ],
                    'meta' => [
                        'current_page' => $paginatedPegawai->currentPage(),
                        'from' => $paginatedPegawai->firstItem(),
                        'last_page' => $paginatedPegawai->lastPage(),
                        'per_page' => $paginatedPegawai->perPage(),
                        'to' => $paginatedPegawai->lastItem(),
                        'total' => $paginatedPegawai->total(),
                    ]
                ], 200);
            }

            // Mode Flat List (Default / Legacy)
            $query = PresensiPegawai::with('pegawai')->where('presensi_periode_id', $periode->id);

            if ($request->filled('search')) {
                $query->where('pegawai_id', '=', trim($request->search));
            }

            if ($request->filled('status_kehadiran')) {
                $query->where('status_kehadiran', $request->status_kehadiran);
            }

            $orderBy = $request->input('sort_by', $request->input('orderBy', 'tanggal'));
            $orderDir = strtolower($request->input('sort_dir', $request->input('orderDir', 'asc')));
            
            $allowedSorts = ['id', 'pegawai_id', 'tanggal', 'jam_masuk', 'jam_keluar', 'status_kehadiran'];
            if (!in_array($orderBy, $allowedSorts)) {
                $orderBy = 'tanggal';
            }
            if (!in_array($orderDir, ['asc', 'desc'])) {
                $orderDir = 'asc';
            }

            $query->orderBy($orderBy, $orderDir);

            $limit = (int) $request->input('limit', 15);
            if ($limit <= 0) $limit = 15;

            $presensiList = $query->paginate($limit);

            $totalPegawai = PresensiPegawai::where('presensi_periode_id', $periode->id)
                ->distinct('pegawai_id')
                ->count('pegawai_id');

            $bundleData = $periode->toArray();
            $bundleData['total_pegawai'] = $totalPegawai;

            return response()->json([
                'status' => 'success',
                'bundle' => $bundleData,
                'data' => $presensiList->items(),
                'meta' => [
                    'current_page' => $presensiList->currentPage(),
                    'from' => $presensiList->firstItem(),
                    'last_page' => $presensiList->lastPage(),
                    'per_page' => $presensiList->perPage(),
                    'to' => $presensiList->lastItem(),
                    'total' => $presensiList->total(),
                ]
            ]);
        }

        // Attendance record detail
        $attendance = Attendance::with(['employee.unitKerja', 'officeLocation', 'approver'])->find($id);

        if (!$attendance) {
            // Fallback cek apakah merupakan bundle ID
            $periode = PresensiPeriode::with('creator')->find($id);
            if ($periode) {
                $reqClone = clone $request;
                $reqClone->merge(['bundle' => 1]);
                return $this->show($reqClone, $id);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Data presensi tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $attendance,
        ]);
    }

    /**
     * Status presensi & jadwal hari ini
     */
    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        $pegawaiId = $request->input('pegawai_id', $user->pegawai?->id);

        $employee = Pegawai::with(['officeLocation', 'additionalOffices', 'shiftTemplate.days'])
            ->find($pegawaiId);

        if (!$employee) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data pegawai tidak ditemukan',
            ], 404);
        }

        $now = Carbon::now();
        $today = $now->toDateString();
        $dayOfWeek = $now->dayOfWeek;

        $schedule = $employee->shiftTemplate?->getScheduleForDay($dayOfWeek);
        $attendance = Attendance::where('pegawai_id', $employee->id)
            ->whereDate('tanggal', $today)
            ->first();

        // Shift lintas hari: bila tidak ada record hari ini, tampilkan record
        // shift malam kemarin yang masih terbuka (belum clock-out).
        $dutyDate = $today;
        if (!$attendance && $employee->shiftTemplate) {
            $yesterday = $now->copy()->subDay();
            $ySchedule = $employee->shiftTemplate->getScheduleForDay($yesterday->dayOfWeek);
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
        $holiday = NationalHoliday::isHoliday(Carbon::parse($today));

        $isClockedIn = ($attendance && $attendance->clock_in !== null);
        $isClockedOut = ($attendance && $attendance->clock_out !== null);
        $hasValidClockIn = ($attendance && in_array($attendance->status, ['hadir', 'terlambat', 'menunggu_approval']) && $attendance->clock_in !== null);

        // Tentukan window presensi masuk dan presensi pulang
        $isPastShiftEnd = false;
        $isInClockOutWindow = false;
        $isPastClockInCutoff = false;
        $isInClockInWindow = false;

        $scheduledStart = null;
        $scheduledEnd = null;
        $earliestClockIn = null;
        $latestClockIn = null;
        $earliestClockOut = null;
        $latestClockOut = null;

        if ($schedule && !$schedule->is_day_off && $schedule->start_time && $schedule->end_time) {
            $dutyDateStr = $attendance ? Carbon::parse($attendance->getAttribute('tanggal'))->toDateString() : $dutyDate;
            $scheduledStart = $schedule->getScheduledStartForDate($dutyDateStr);
            $scheduledEnd = $schedule->getScheduledEndForDate($dutyDateStr);

            $maxEarlyClockIn = $schedule->getMaxEarlyClockInMinutes();
            $maxLateClockIn = $schedule->getMaxLateClockInMinutes();
            $earliestClockIn = $scheduledStart->copy()->subMinutes($maxEarlyClockIn);
            $latestClockIn = ($maxLateClockIn > 0) ? $scheduledStart->copy()->addMinutes($maxLateClockIn) : null;

            $maxEarlyClockOut = $schedule->getMaxEarlyClockOutMinutes();
            $maxLateClockOut = $schedule->getMaxLateClockOutMinutes();
            $earlyLeaveTolerance = $schedule->getEarlyLeaveToleranceMinutes();

            $earlyTolerance = ($maxEarlyClockOut !== null && $maxEarlyClockOut > 0)
                ? $maxEarlyClockOut
                : $earlyLeaveTolerance;
            $earliestClockOut = $scheduledEnd->copy()->subMinutes($earlyTolerance);

            $latestClockOut = ($maxLateClockOut > 0) ? $scheduledEnd->copy()->addMinutes($maxLateClockOut) : null;

            $isInClockInWindow = $now->greaterThanOrEqualTo($earliestClockIn) && ($latestClockIn === null || $now->lessThanOrEqualTo($latestClockIn));
            $isPastClockInCutoff = ($latestClockIn !== null && $now->greaterThan($latestClockIn));
            $isPastShiftEnd = $now->greaterThanOrEqualTo($scheduledEnd);
            $isInClockOutWindow = $now->greaterThanOrEqualTo($earliestClockOut) && ($latestClockOut === null || $now->lessThanOrEqualTo($latestClockOut));
        } elseif (!$schedule) {
            $scheduledStart = Carbon::parse("{$dutyDate} 08:00:00");
            $scheduledEnd = Carbon::parse("{$dutyDate} 17:00:00");
            $maxEarlyClockIn = (int) \App\Models\SystemSetting::get('max_early_clock_in_minutes', 60);
            $maxLateClockIn = (int) \App\Models\SystemSetting::get('max_late_clock_in_minutes', 240);
            $maxEarlyClockOut = (int) \App\Models\SystemSetting::get('max_early_clock_out_minutes', 0);
            $maxLateClockOut = (int) \App\Models\SystemSetting::get('max_late_clock_out_minutes', 240);

            $earliestClockIn = $scheduledStart->copy()->subMinutes($maxEarlyClockIn);
            $latestClockIn = ($maxLateClockIn > 0) ? $scheduledStart->copy()->addMinutes($maxLateClockIn) : null;

            $earlyTolerance = ($maxEarlyClockOut > 0) ? $maxEarlyClockOut : 15;
            $earliestClockOut = $scheduledEnd->copy()->subMinutes($earlyTolerance);
            $latestClockOut = ($maxLateClockOut > 0) ? $scheduledEnd->copy()->addMinutes($maxLateClockOut) : null;

            $isInClockInWindow = $now->greaterThanOrEqualTo($earliestClockIn) && ($latestClockIn === null || $now->lessThanOrEqualTo($latestClockIn));
            $isPastClockInCutoff = ($latestClockIn !== null && $now->greaterThan($latestClockIn));
            $isPastShiftEnd = $now->greaterThanOrEqualTo($scheduledEnd);
            $isInClockOutWindow = $now->greaterThanOrEqualTo($earliestClockOut) && ($latestClockOut === null || $now->lessThanOrEqualTo($latestClockOut));
        }

        if ($isClockedOut) {
            $canClockIn = false;
            $canClockOut = false;
        } elseif ($hasValidClockIn) {
            $canClockIn = false;
            // Presensi pulang HANYA dibuka jika waktu saat ini sudah memasuki jendela jam pulang shift
            $canClockOut = $isInClockOutWindow;
        } else {
            // Belum pernah absen masuk
            if ($isPastClockInCutoff || $isPastShiftEnd || $isInClockOutWindow) {
                // Batas waktu absen masuk telah berakhir atau sudah memasuki jam pulang.
                // Tombol presensi otomatis beralih menjadi Presensi Pulang!
                $canClockIn = false;
                $canClockOut = $isInClockOutWindow || $isPastShiftEnd;
            } else {
                $canClockIn = true;
                $canClockOut = false;
            }
        }

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
            'max_early_clock_out_minutes' => $schedule->getMaxEarlyClockOutMinutes(),
            'max_late_clock_out_minutes' => $schedule->getMaxLateClockOutMinutes(),
            'earliest_clock_in' => $earliestClockIn?->format('H:i:s'),
            'latest_clock_in' => $latestClockIn?->format('H:i:s'),
            'earliest_clock_out' => $earliestClockOut?->format('H:i:s'),
            'latest_clock_out' => $latestClockOut?->format('H:i:s'),
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
            'late_tolerance_minutes' => $employee->shiftTemplate ? $employee->shiftTemplate->late_tolerance_minutes : 15,
            'early_leave_tolerance_minutes' => $employee->shiftTemplate ? $employee->shiftTemplate->early_leave_tolerance_minutes : 15,
            'max_early_clock_in_minutes' => $employee->shiftTemplate ? $employee->shiftTemplate->max_early_clock_in_minutes : 60,
            'max_late_clock_in_minutes' => $employee->shiftTemplate ? ($employee->shiftTemplate->max_late_clock_in_minutes ?? 240) : 240,
            'max_early_clock_out_minutes' => $employee->shiftTemplate ? $employee->shiftTemplate->max_early_clock_out_minutes : null,
            'max_late_clock_out_minutes' => $employee->shiftTemplate ? ($employee->shiftTemplate->max_late_clock_out_minutes ?? 240) : 240,
            'earliest_clock_in' => $earliestClockIn?->format('H:i:s'),
            'latest_clock_in' => $latestClockIn?->format('H:i:s'),
            'earliest_clock_out' => $earliestClockOut?->format('H:i:s'),
            'latest_clock_out' => $latestClockOut?->format('H:i:s'),
        ];

        return response()->json([
            'status' => 'success',
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
                'date' => $today,
                'day_name' => $schedulePayload['day_name'],
                'employee' => [
                    'id' => $employee->id,
                    'nip' => $employee->nip,
                    'nama' => $employee->nama_lengkap,
                    'avatar' => $employee->avatar,
                    'foto_url' => $employee->foto_url,
                    'is_face_enrolled' => !empty($employee->face_embedding),
                ],
                'schedule' => $schedulePayload,
                'office' => $employee->officeLocation,
                'allowed_offices' => $employee->getAllowedOfficeLocations()->values(),
                'attendance' => $attendance,
                'holiday' => $holiday,
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
            'pegawai_id' => 'nullable|exists:simpeg_pegawai,id',
            'device_id' => 'nullable|string',
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

        $employee = null;
        if ($request->filled('pegawai_id') && ($request->user()->hasPermission('simpeg.presensi.manage') || $request->user()->isAdmin() || $request->user()->hasRole('admin_simpeg'))) {
            $employee = Pegawai::with(['officeLocation', 'additionalOffices', 'shiftTemplate.days'])->find($request->pegawai_id);
        } else {
            $employee = Pegawai::with(['officeLocation', 'additionalOffices', 'shiftTemplate.days'])->where('user_id', $request->user()->id)->first();
        }

        if (!$employee) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data profil pegawai tidak ditemukan.',
            ], 404);
        }

        $attendance = $this->attendanceService->processClockIn($employee, $validated);

        $message = match ($attendance->status) {
            'hadir' => ($attendance->clock_out && !$attendance->clock_in)
                ? 'Presensi pulang berhasil dicatat (tanpa presensi masuk sebelumnya).'
                : ($attendance->clock_out ? 'Presensi pulang berhasil dicatat.' : 'Presensi masuk berhasil dicatat'),
            'terlambat' => 'Presensi masuk berhasil dicatat (Terlambat)',
            'menunggu_approval' => 'Presensi masuk tersimpan, menunggu persetujuan HR',
            'ditolak' => $attendance->rejection_reason,
            default => 'Status presensi: ' . $attendance->status,
        };

        return response()->json([
            'status' => $attendance->status === 'ditolak' ? 'error' : 'success',
            'message' => $message,
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
            'pegawai_id' => 'nullable|exists:simpeg_pegawai,id',
            'device_id' => 'nullable|string',
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

        $employee = null;
        if ($request->filled('pegawai_id') && ($request->user()->hasPermission('simpeg.presensi.manage') || $request->user()->isAdmin() || $request->user()->hasRole('admin_simpeg'))) {
            $employee = Pegawai::find($request->pegawai_id);
        } else {
            $employee = Pegawai::where('user_id', $request->user()->id)->first();
        }

        if (!$employee) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data profil pegawai tidak ditemukan.',
            ], 404);
        }

        try {
            $attendance = $this->attendanceService->processClockOut($employee, $validated);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first()
                ?? 'Presensi pulang ditolak.';

            // Idempoten: clock-out ganda (balapan 2 request) yang kedua
            // tetap dianggap sukses bila clock_out SUDAH tercatat.
            // Klien membedakan kasus ini via HTTP 200 + pesan khusus
            // (kontrak envelope murni, tanpa flag tambahan).
            if (str_contains(strtolower($msg), 'sudah melakukan presensi pulang')) {
                $existing = Attendance::where('pegawai_id', $employee->id)
                    ->whereDate('tanggal', Carbon::today()->toDateString())
                    ->whereNotNull('clock_out')
                    ->first() ?? Attendance::where('pegawai_id', $employee->id)
                    ->whereDate('tanggal', Carbon::today()->subDay()->toDateString())
                    ->whereNotNull('clock_out')
                    ->first();
                if ($existing) {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Presensi pulang sudah tercatat sebelumnya.',
                        'data' => $existing,
                    ]);
                }
            }

            return response()->json([
                'status' => 'error',
                'message' => $msg,
                'errors' => $e->errors(),
            ], 422);
        }

        $message = ($attendance->clock_out && !$attendance->clock_in)
            ? 'Presensi pulang berhasil dicatat (tanpa presensi masuk sebelumnya).'
            : 'Presensi pulang berhasil dicatat';

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $attendance,
        ]);
    }

    /**
     * Rekap presensi: dukung rentang `?start_date&end_date` (kontrak
     * frontend §3.2) dengan fallback `?month&year`. Respons
     * `{employee, filter, summary, rows}` berkunci alias `period`.
     */
    public function recap(Request $request): JsonResponse
    {
        $user = $request->user();
        $pegawaiId = $request->input('pegawai_id', $user->pegawai?->id);

        $employee = Pegawai::find($pegawaiId);
        if (!$employee) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data pegawai tidak ditemukan',
            ], 404);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
            $endDate = Carbon::parse($request->input('end_date'))->startOfDay();
            if ($endDate->lessThan($startDate)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tanggal akhir tidak boleh lebih awal dari tanggal awal.',
                    'errors' => [
                        'end_date' => ['Tanggal akhir tidak boleh lebih awal dari tanggal awal.'],
                    ],
                ], 422);
            }
        } else {
            $month = (int) $request->input('month', Carbon::now()->month);
            $year = (int) $request->input('year', Carbon::now()->year);

            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
        }

        $recap = $this->recapService->presentRecap($employee, $startDate, $endDate);

        return response()->json([
            'status' => 'success',
            'message' => 'Rekap presensi berhasil diambil',
            'data' => $recap,
        ]);
    }

    /**
     * Persetujuan manual presensi oleh HR / Admin.
     *
     * Scan wajah yang sukses langsung tercatat hadir/terlambat tanpa approval.
     * Approval hanya untuk status menunggu_approval (scan hari libur) atau
     * ditolak (wajah/GPS gagal berulang). Jika record ditolak belum punya
     * clock_in, approval mengisi clock_in dengan waktu approval agar log
     * punya jam masuk yang sah.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('simpeg.presensi.manage') && !$request->user()->isAdmin() && !$request->user()->hasRole('admin_simpeg')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menyetujui presensi.',
            ], 403);
        }

        $attendance = Attendance::findOrFail($id);
        $oldValues = $attendance->getOriginal();
        $now = Carbon::now();
        $payload = [
            'is_approved_by_admin' => true,
            'approved_by' => $request->user()->id,
            'approved_at' => $now,
            'status_kehadiran' => 'hadir',
            'notes' => ($attendance->notes ? $attendance->notes . ' | ' : '') . 'Disetujui oleh HR pada ' . $now->format('d/m/Y H:i'),
        ];

        if (!$attendance->clock_in) {
            $payload['clock_in'] = $now;
        }

        $attendance->update($payload);
        $newValues = $attendance->getChanges();

        try {
            \App\Services\AuditLogService::record(
                'SIMPEG',
                'approve',
                'simpeg_presensi_pegawai',
                (int) $attendance->id,
                $oldValues,
                $newValues,
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log approval presensi: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Presensi berhasil disetujui.',
            'data' => $attendance->fresh(),
        ]);
    }

    /**
     * Edit / reset sebagian log presensi realtime oleh HR / Admin.
     *
     * Untuk kasus salah tekan scan pulang: cukup reset clock_out saja tanpa
     * menghapus seluruh log (clock_in tetap aman). Berlaku sebaliknya untuk
     * reset clock_in. Mendukung juga koreksi jam manual (input WIB).
     */
    public function updateLog(UpdatePresensiLogRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.presensi.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengedit log presensi.',
            ], 403);
        }

        $attendance = Attendance::findOrFail($id);
        $validated = $request->validated();
        $oldValues = $attendance->getOriginal();

        $tanggal = $validated['tanggal'] ?? Carbon::parse($attendance->getAttribute('tanggal'))->toDateString();

        if (!empty($validated['reset_clock_in'])) {
            $attendance->clock_in = null;
            $attendance->jam_masuk = null;
            $attendance->clock_in_latitude = null;
            $attendance->clock_in_longitude = null;
            $attendance->clock_in_distance_meters = null;
            $attendance->clock_in_accuracy = null;
            $attendance->clock_in_face_score = null;
            $attendance->clock_in_is_mock_location = false;
        } elseif (array_key_exists('jam_masuk', $validated) && $validated['jam_masuk'] !== null && $validated['jam_masuk'] !== '') {
            $jamMasuk = $this->normalizeJamInput($validated['jam_masuk']);
            $clockInUtc = Carbon::createFromFormat('Y-m-d H:i:s', "{$tanggal} {$jamMasuk}", 'Asia/Jakarta');
            if (!$clockInUtc) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Format jam_masuk tidak valid. Gunakan HH:MM atau HH:MM:SS (WIB).',
                ], 422);
            }
            $attendance->clock_in = $clockInUtc->setTimezone('UTC');
        }

        if (!empty($validated['reset_clock_out'])) {
            $attendance->clock_out = null;
            $attendance->jam_keluar = null;
            $attendance->clock_out_latitude = null;
            $attendance->clock_out_longitude = null;
            $attendance->clock_out_distance_meters = null;
            $attendance->clock_out_accuracy = null;
            $attendance->clock_out_face_score = null;
            $attendance->clock_out_is_mock_location = false;
            $attendance->early_leave_minutes = 0;
        } elseif (array_key_exists('jam_keluar', $validated) && $validated['jam_keluar'] !== null && $validated['jam_keluar'] !== '') {
            $jamKeluar = $this->normalizeJamInput($validated['jam_keluar']);
            $clockOutUtc = Carbon::createFromFormat('Y-m-d H:i:s', "{$tanggal} {$jamKeluar}", 'Asia/Jakarta');
            if (!$clockOutUtc) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Format jam_keluar tidak valid. Gunakan HH:MM atau HH:MM:SS (WIB).',
                ], 422);
            }
            $attendance->clock_out = $clockOutUtc->setTimezone('UTC');
        }

        if (isset($validated['tanggal'])) {
            $attendance->tanggal = $validated['tanggal'];
        }

        $statusBaru = $validated['status'] ?? $validated['status_kehadiran'] ?? null;
        if ($statusBaru) {
            $attendance->status = $statusBaru;
        }

        $catatanBaru = $validated['catatan'] ?? $validated['notes'] ?? null;
        if ($catatanBaru !== null) {
            $attendance->notes = $catatanBaru;
        }

        $attendance->save();

        $newValues = $attendance->getChanges();
        try {
            \App\Services\AuditLogService::record(
                'SIMPEG',
                'update',
                'simpeg_presensi_pegawai',
                (int) $id,
                $oldValues,
                $newValues,
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log presensi: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Log presensi berhasil diperbarui.',
            'data' => $attendance->fresh(),
        ]);
    }

    /**
     * Koreksi presensi pada matriks bundle oleh Admin dengan audit trail data asal.
     * Merekam status/jam awal sebelum dikoreksi ke catatan/audit logs.
     */
    public function koreksiBundle(KoreksiPresensiBundleRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $periodeId = (int) $validated['presensi_periode_id'];
        $pegawaiId = (int) $validated['pegawai_id'];
        $tanggal = $validated['tanggal'];
        $statusKehadiran = $validated['status_kehadiran'];
        $jamMasuk = !empty($validated['jam_masuk']) ? $this->normalizeJamInput($validated['jam_masuk']) : null;
        $jamKeluar = !empty($validated['jam_keluar']) ? $this->normalizeJamInput($validated['jam_keluar']) : null;
        $alasanKoreksi = trim($validated['alasan_koreksi']);

        $periode = PresensiPeriode::find($periodeId);
        if ($periode && $periode->is_locked) {
            return response()->json([
                'status' => 'error',
                'message' => "Bundle presensi '{$periode->nama_periode}' telah dikunci dan diproses ke Payroll. Data presensi tidak dapat diubah lagi.",
            ], 400);
        }

        // Cari record presensi di simpeg_presensi_pegawai
        $existing = PresensiPegawai::where('presensi_periode_id', $periodeId)
            ->where('pegawai_id', $pegawaiId)
            ->whereDate('tanggal', $tanggal)
            ->first();

        // Rekam data asal
        if ($existing) {
            $statusAwal = $existing->status_kehadiran ?? 'alfa';
            $jamMasukAwal = $existing->jam_masuk ? substr($existing->jam_masuk, 0, 8) : '-';
            $jamKeluarAwal = $existing->jam_keluar ? substr($existing->jam_keluar, 0, 8) : '-';
            $catatanLama = $existing->catatan ? " (Catatan sebelumnya: {$existing->catatan})" : '';
            $dataAwalStr = "Data Awal: Status '{$statusAwal}', Masuk: {$jamMasukAwal}, Pulang: {$jamKeluarAwal}{$catatanLama}";
            $oldValues = $existing->getOriginal();
            $actionAudit = 'update';
        } else {
            $dataAwalStr = "Data Awal: Tidak Absen (Alpa / Belum Tercatat)";
            $oldValues = null;
            $actionAudit = 'create';
        }

        $waktuSekarang = Carbon::now()->format('d/m/Y H:i');
        $namaAdmin = $user->name ?? $user->username ?? 'Admin';
        $auditKeterangan = "[Koreksi Admin] Oleh: {$namaAdmin} ({$waktuSekarang}) | {$dataAwalStr} | Alasan: {$alasanKoreksi}";

        if ($existing) {
            $existing->status_kehadiran = $statusKehadiran;
            $existing->jam_masuk = $jamMasuk;
            $existing->jam_keluar = $jamKeluar;
            $existing->is_approved_by_admin = true;
            $existing->approved_by = $user->id;
            $existing->approved_at = Carbon::now();
            $existing->source = 'koreksi_admin';
            $existing->catatan = $auditKeterangan;
            $existing->save();
            $record = $existing;
        } else {
            $record = PresensiPegawai::create([
                'presensi_periode_id' => $periodeId,
                'pegawai_id'          => $pegawaiId,
                'tanggal'             => $tanggal,
                'jam_masuk'           => $jamMasuk,
                'jam_keluar'          => $jamKeluar,
                'status_kehadiran'    => $statusKehadiran,
                'is_approved_by_admin'=> true,
                'approved_by'         => $user->id,
                'approved_at'         => Carbon::now(),
                'source'              => 'koreksi_admin',
                'catatan'             => $auditKeterangan,
            ]);
        }

        // Catat Audit Log ke tabel audit_logs
        $newValues = $actionAudit === 'create'
            ? $record->getAttributes()
            : $record->getChanges();

        try {
            \App\Services\AuditLogService::record(
                'SIMPEG',
                $actionAudit,
                'simpeg_presensi_pegawai',
                (int) $record->id,
                $oldValues,
                $newValues,
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log koreksi bundle presensi: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => "Presensi tanggal {$tanggal} berhasil dikoreksi dengan audit data asal.",
            'data' => [
                'presensi' => $record,
                'data_awal_keterangan' => $dataAwalStr,
                'audit_catatan' => $auditKeterangan,
            ]
        ], 200);
    }

    protected function normalizeJamInput(string $jam): string
    {
        $jam = trim($jam);
        if (preg_match('/^\d{2}:\d{2}$/', $jam)) {
            return $jam . ':00';
        }
        return $jam;
    }

    /**
     * Tetapkan keterangan ketidakhadiran oleh HR / Admin untuk pegawai yang
     * terjadwal masuk (referensi shift) tetapi tidak memiliki log presensi.
     * Idempotent per (pegawai, tanggal). Data hasil scan (clock_in/clock_out)
     * tidak boleh ditimpa lewat endpoint ini.
     */
    public function setKeterangan(SetKeteranganRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.presensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menetapkan keterangan ketidakhadiran.',
            ], 403);
        }

        $validated = $request->validated();

        $existing = Attendance::where('pegawai_id', $validated['pegawai_id'])
            ->whereDate('tanggal', $validated['tanggal'])
            ->first();

        if ($existing && ($existing->clock_in || $existing->clock_out)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tanggal tersebut sudah memiliki data hasil scan presensi dan tidak dapat ditimpa dengan keterangan manual.',
            ], 422);
        }

        $payload = [
            'status' => $validated['status_kehadiran'],
            'notes' => $validated['catatan'] ?? null,
            'is_approved_by_admin' => true,
            'approved_by' => $user->id,
            'approved_at' => Carbon::now(),
        ];

        if ($existing) {
            $existing->update($payload);
            $attendance = $existing->fresh();
        } else {
            $attendance = Attendance::create(array_merge($payload, [
                'pegawai_id' => $validated['pegawai_id'],
                'tanggal' => $validated['tanggal'],
            ]));
        }

        return response()->json([
            'status' => 'success',
            'message' => "Keterangan ketidakhadiran ({$attendance->status}) berhasil disimpan.",
            'data' => $attendance->fresh(),
        ], 201);
    }

    /**
     * Simpan presensi manual (Admin / HR)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'tanggal' => 'required|date',
            'jam_masuk' => 'nullable|string',
            'jam_keluar' => 'nullable|string',
            'status_kehadiran' => 'required|in:hadir,izin,sakit,alfa,dinas,terlambat',
            'catatan' => 'nullable|string',
        ]);

        $presensi = Attendance::create([
            'pegawai_id' => $validated['pegawai_id'],
            'tanggal' => $validated['tanggal'],
            'clock_in' => !empty($validated['jam_masuk']) ? Carbon::parse("{$validated['tanggal']} {$validated['jam_masuk']}") : null,
            'clock_out' => !empty($validated['jam_keluar']) ? Carbon::parse("{$validated['tanggal']} {$validated['jam_keluar']}") : null,
            'status_kehadiran' => $validated['status_kehadiran'],
            'notes' => $validated['catatan'] ?? null,
            'is_approved_by_admin' => true,
            'approved_by' => $request->user()->id,
            'approved_at' => Carbon::now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Presensi berhasil dicatat manual',
            'data' => $presensi,
        ], 201);
    }

    /**
     * Upload Rekap Presensi File & Create Bundle
     */
    public function uploadRekap(Request $request): JsonResponse
    {
        ini_set('max_execution_time', '600');
        set_time_limit(600);
        ini_set('memory_limit', '512M');

        $user = $request->user();
        if (!$user->hasPermission('simpeg.presensi.create') && !$user->hasPermission('simpeg.presensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk mengunggah rekap presensi.'
            ], 403);
        }

        $validated = $request->validate([
            'nama_periode' => 'required|string|max:255',
            'tanggal_awal' => 'required|date',
            'tanggal_akhir' => 'required|date|after_or_equal:tanggal_awal',
            'file_rekap' => 'required|file|mimes:csv,xlsx,xls,pdf,txt,sql|max:102400',
            'catatan' => 'nullable|string',
        ]);

        $file = $request->file('file_rekap');
        $path = $this->files->store($file, 'simpeg/presensi_rekap');
        // Salin ke file lokal sementara agar parsing (fopen) bekerja untuk local maupun R2.
        $fullPath = $this->files->temporaryLocalPath($path);

        // 1. Create Bundle / Periode Record
        $periode = PresensiPeriode::create([
            'nama_periode' => $validated['nama_periode'],
            'tanggal_awal' => $validated['tanggal_awal'],
            'tanggal_akhir' => $validated['tanggal_akhir'],
            'bulan_tahun' => date('Y-m', strtotime($validated['tanggal_akhir'])),
            'total_record' => 0,
            'catatan' => $validated['catatan'] ?? null,
            'created_by' => $user->id,
        ]);

        $importedCount = 0;
        $tglAwal = $validated['tanggal_awal'];
        $tglAkhir = $validated['tanggal_akhir'];

        Schema::disableForeignKeyConstraints();

        try {
            $extension = strtolower($file->getClientOriginalExtension());
            if (!$extension) {
                $extension = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
            }

            // 2. Parse SQL Dump (shift_result.sql / backupfinger.sql)
            if ($extension === 'sql' || str_contains(strtolower($file->getClientOriginalName()), '.sql')) {
                $handle = fopen($fullPath, 'r');
                if ($handle) {
                    $inShiftResult = false;
                    while (($line = fgets($handle)) !== false) {
                        $trimmed = trim($line);

                        if (str_contains(strtolower($trimmed), 'insert into shift_result') || str_contains(strtolower($trimmed), 'lock tables shift_result')) {
                            $inShiftResult = true;
                        } elseif ($inShiftResult && (str_contains(strtolower($trimmed), 'unlock tables') || str_contains(strtolower($trimmed), 'create table `'))) {
                            $inShiftResult = false;
                        }

                        if ($inShiftResult || str_starts_with($trimmed, '(') || str_contains($trimmed, "('20")) {
                            preg_match_all('/\(([^()]+)\)/', $trimmed, $tupleMatches);
                            foreach ($tupleMatches[1] as $rawTuple) {
                                $fields = str_getcsv($rawTuple, ',', "'");
                                $count = count($fields);
                                if ($count >= 4) {
                                    $rawPegId = (int)trim($fields[0]);
                                    $tglShift = trim($fields[1]);

                                    // Filter by date range (between tanggal_awal and tanggal_akhir)
                                    if ($rawPegId > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglShift)) {
                                        if ($tglShift >= $tglAwal && $tglShift <= $tglAkhir) {
                                            $scanInStr = '';
                                            $scanOutStr = '';

                                            if ($count >= 30) {
                                                $scanInStr = trim($fields[13]);
                                                $scanOutStr = trim($fields[29]);
                                            } else {
                                                $scanInStr = trim($fields[2]);
                                                $scanOutStr = trim($fields[3]);
                                            }

                                            $pegId = $rawPegId;
                                            if (!Pegawai::where('id', $pegId)->exists()) {
                                                try {
                                                    $p = new Pegawai();
                                                    $p->id = $pegId;
                                                    $p->nip = 'PEG-' . sprintf('%05d', $pegId);
                                                    $p->nama_lengkap = 'Pegawai ID ' . $pegId;
                                                    $p->jenis_pegawai = 'tendik';
                                                    $p->status_kepegawaian = 'tetap_yayasan';
                                                    $p->status = 'aktif';
                                                    $p->save();
                                                } catch (\Exception $e) {
                                                    // ignore
                                                }
                                            }

                                            $hasScanIn = ($scanInStr !== '0000-00-00 00:00:00' && !empty($scanInStr));
                                            $hasScanOut = ($scanOutStr !== '0000-00-00 00:00:00' && !empty($scanOutStr));

                                            $jamMasukTime = $hasScanIn ? date('H:i:s', strtotime($scanInStr)) : null;
                                            $jamKeluarTime = $hasScanOut ? date('H:i:s', strtotime($scanOutStr)) : null;

                                            $statusKehadiran = 'alfa';
                                            $catatanList = [];

                                            if ($hasScanIn || $hasScanOut) {
                                                $statusKehadiran = 'hadir';

                                                if ($hasScanIn) {
                                                    if ($jamMasukTime <= '08:15:00') {
                                                        $catatanList[] = "Tepat Waktu (Scan In: {$jamMasukTime})";
                                                    } else {
                                                        $scanInTs = strtotime($scanInStr);
                                                        $targetTs = strtotime($tglShift . ' 08:00:00');
                                                        $diffSeconds = max(0, $scanInTs - $targetTs);
                                                        $diffMinutes = (int)ceil($diffSeconds / 60);

                                                        $catatanList[] = "Terlambat {$diffMinutes} menit (Scan In: {$jamMasukTime})";
                                                    }
                                                } else {
                                                    $catatanList[] = "Tidak Tap Masuk";
                                                }

                                                if ($hasScanOut) {
                                                    if ($jamKeluarTime < '16:00:00') {
                                                        $scanOutTs = strtotime($scanOutStr);
                                                        $targetPulangTs = strtotime($tglShift . ' 16:00:00');
                                                        $diffEarlySeconds = max(0, $targetPulangTs - $scanOutTs);
                                                        $diffEarlyMinutes = (int)ceil($diffEarlySeconds / 60);

                                                        $catatanList[] = "Pulang Awal {$diffEarlyMinutes} menit (Scan Out: {$jamKeluarTime})";
                                                    } else {
                                                        $catatanList[] = "Jam Pulang Terpenuhi (Scan Out: {$jamKeluarTime})";
                                                    }
                                                } else {
                                                    $catatanList[] = "Tidak Tap Pulang";
                                                }
                                            } else {
                                                $statusKehadiran = 'alfa';
                                                $catatanList[] = "Tidak Hadir / Alpha (Tanpa Scan)";
                                            }

                                            $catatanStr = implode(' | ', $catatanList);

                                            PresensiPegawai::updateOrCreate(
                                                [
                                                    'presensi_periode_id' => $periode->id,
                                                    'pegawai_id' => $pegId,
                                                    'tanggal' => $tglShift,
                                                ],
                                                [
                                                    'jam_masuk' => $jamMasukTime,
                                                    'jam_keluar' => $jamKeluarTime,
                                                    'status_kehadiran' => $statusKehadiran,
                                                    'catatan' => $catatanStr,
                                                ]
                                            );
                                            $importedCount++;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    fclose($handle);
                }
            }

            // Update total record imported
            $periode->update(['total_record' => $importedCount]);

        } finally {
            Schema::enableForeignKeyConstraints();
            if (isset($fullPath) && is_string($fullPath) && $fullPath !== '') {
                @unlink($fullPath);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "Bundle '{$periode->nama_periode}' berhasil dibuat dan {$importedCount} data absensi berhasil di-import.",
            'data' => $periode,
        ], 201);
    }

    /**
     * Sinkronisasi Otomatis Log Realtime ke Rekap Bundle Periode (Siklus 26 - 25)
     */
    public function syncBundleFromRealtime(Request $request, PresensiBundleService $bundleService): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.presensi.create') && !$user->hasPermission('simpeg.presensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menyinkronkan bundle presensi.'
            ], 403);
        }

        $result = $bundleService->autoSyncBundlesFromRealtime($user->id);

        $bundleIds = array_column($result['bundles'] ?? [], 'id');
        try {
            \App\Services\AuditLogService::record(
                'SIMPEG',
                'sync',
                'simpeg_presensi_periode',
                $bundleIds[0] ?? null,
                null,
                array_merge($result, ['synced_bundle_ids' => $bundleIds]),
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log sync bundle presensi: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Seluruh log presensi realtime berhasil disinkronkan ke Rekap Bundle sesuai siklus periode 26 s/d 25.',
            'data' => $result,
        ]);
    }

    /**
     * Delete an individual attendance log (Attendance)
     */
    public function destroyLog(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.presensi.delete') && !$user->hasPermission('simpeg.presensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk menghapus Log Presensi.'
            ], 403);
        }

        $attendance = Attendance::findOrFail($id);

        \App\Services\AuditLogService::record(
            'SIMPEG',
            'delete',
            'simpeg_presensi_pegawai',
            (int) $id,
            $attendance->toArray(),
            null,
            $request
        );

        $attendance->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Log presensi berhasil dihapus.',
        ]);
    }

    /**
     * Delete a Presensi Bundle or Attendance log by ID
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.presensi.delete') && !$user->hasPermission('simpeg.presensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk menghapus Presensi.'
            ], 403);
        }

        // Jika request secara eksplisit bertipe log
        if ($request->input('type') === 'log' || $request->has('log')) {
            return $this->destroyLog($request, $id);
        }

        // Jika request secara eksplisit bertipe bundle
        if ($request->input('type') === 'bundle' || $request->has('bundle')) {
            $periode = PresensiPeriode::findOrFail($id);
            $nama = $periode->nama_periode;
            $periode->delete();

            return response()->json([
                'status' => 'success',
                'message' => "Bundle presensi '{$nama}' beserta seluruh log absensi di dalamnya berhasil dihapus.",
            ]);
        }

        // Smart lookup: coba cari di Attendance dulu, jika ada hapus log
        $attendance = Attendance::find($id);
        if ($attendance) {
            return $this->destroyLog($request, $id);
        }

        // Fallback cari di PresensiPeriode
        $periode = PresensiPeriode::find($id);
        if ($periode) {
            $nama = $periode->nama_periode;
            $periode->delete();

            return response()->json([
                'status' => 'success',
                'message' => "Bundle presensi '{$nama}' beserta seluruh log absensi di dalamnya berhasil dihapus.",
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Data presensi tidak ditemukan.',
        ], 404);
    }

    /**
     * Process / Generate Payroll for a specific Bundle
     */
    public function processPayroll(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.presensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk memproses Payroll.'
            ], 403);
        }

        $periode = PresensiPeriode::findOrFail($id);

        if ($periode->is_locked) {
            return response()->json([
                'status' => 'error',
                'message' => "Bundle presensi '{$periode->nama_periode}' sudah pernah diproses ke Payroll dan berstatus terkunci.",
            ], 400);
        }

        $bulanTahun = $periode->bulan_tahun ?: Carbon::parse($periode->tanggal_akhir)->format('Y-m');

        // Ambil semua pegawai aktif
        $pegawais = Pegawai::where('is_active', true)->get();

        // Hitung hari hadir valid per pegawai dalam rentang tanggal bundle:
        // Syarat kehadiran valid yang ter-record:
        // 1. Lengkap absen masuk dan keluar (jam_masuk IS NOT NULL AND jam_keluar IS NOT NULL)
        // 2. ATAU dinas luar yang telah disetujui (status_kehadiran = 'dinas' AND is_approved_by_admin = 1)
        // Izin dan sakit tidak ter-record.
        $startDate = $periode->tanggal_awal ? Carbon::parse($periode->tanggal_awal)->toDateString() : null;
        $endDate = $periode->tanggal_akhir ? Carbon::parse($periode->tanggal_akhir)->toDateString() : null;

        $attendanceQuery = PresensiPegawai::query();
        if ($startDate && $endDate) {
            $attendanceQuery->whereBetween('tanggal', [$startDate, $endDate]);
        } else {
            $attendanceQuery->where('presensi_periode_id', $periode->id);
        }

        $attendanceLogs = $attendanceQuery->get()->groupBy('pegawai_id');

        $activeKolomKomponens = MasterKomponenGaji::where('is_active', true)
            ->where('is_kolom_aktif', true)
            ->get();

        $allCustoms = PegawaiKomponenGaji::whereIn('pegawai_id', $pegawais->pluck('id'))
            ->where('is_active', true)
            ->get()
            ->groupBy('pegawai_id');

        $processedCount = 0;

        DB::transaction(function () use ($pegawais, $attendanceLogs, $bulanTahun, $periode, $user, $activeKolomKomponens, $allCustoms, &$processedCount) {
            foreach ($pegawais as $peg) {
                $logs = $attendanceLogs->get($peg->id, collect());

                // Filter hanya hari yang memenuhi kriteria kehadiran valid
                $validAttendanceDays = $logs->filter(function ($att) {
                    // Status dinas luar yang disetujui admin
                    if ($att->status_kehadiran === 'dinas' && !empty($att->is_approved_by_admin)) {
                        return true;
                    }
                    // Absen masuk dan pulang lengkap
                    if (!empty($att->jam_masuk) && !empty($att->jam_keluar)) {
                        return true;
                    }
                    // Izin, sakit, alpa, atau absen tidak lengkap tidak dihitung
                    return false;
                })->count();

                // Ambil kustom komponen pegawai
                $pegCustoms = $allCustoms->get($peg->id, collect())->keyBy('komponen_gaji_id');

                $gajiPokok = 0.0;
                $totalTunjangan = 0.0;
                $totalPotongan = 0.0;
                $detailItems = [];

                foreach ($activeKolomKomponens as $komp) {
                    $cust = $pegCustoms->get($komp->id);
                    $nominal = $cust && $cust->nominal_kustom !== null
                        ? (float) $cust->nominal_kustom
                        : (float) $komp->nilai_default;

                    // Jika komponen adalah rumus kehadiran / presensi, nominal rupiah awal dibiarkan 0
                    // dan jumlah hari disimpan di detail & jumlah_hari_hadir_tepat_waktu
                    $isPresensiKomp = ($komp->tipe_nilai === 'rumus_kehadiran') || str_contains(strtolower($komp->nama), 'presensi') || str_contains(strtolower($komp->kode), 'PRESENSI');

                    if ($isPresensiKomp) {
                        $detailItems[] = [
                            'komponen_gaji_id' => $komp->id,
                            'nama_komponen' => $komp->nama,
                            'jenis' => $komp->jenis,
                            'nominal' => 0.0,
                            'keterangan' => "Presensi: {$validAttendanceDays} Hari",
                        ];
                    } else {
                        if ($komp->kode === 'GAJI_POKOK') {
                            $gajiPokok = $nominal;
                        } elseif ($komp->jenis === 'pendapatan') {
                            $totalTunjangan += $nominal;
                        } elseif ($komp->jenis === 'potongan') {
                            $totalPotongan += $nominal;
                        }

                        if ($nominal > 0) {
                            $detailItems[] = [
                                'komponen_gaji_id' => $komp->id,
                                'nama_komponen' => $komp->nama,
                                'jenis' => $komp->jenis,
                                'nominal' => $nominal,
                                'keterangan' => $komp->keterangan ?: $komp->nama,
                            ];
                        }
                    }
                }

                $gajiBersih = $gajiPokok + $totalTunjangan - $totalPotongan;

                $gajiPegawai = GajiPegawai::updateOrCreate(
                    [
                        'pegawai_id' => $peg->id,
                        'periode_bulan_tahun' => $bulanTahun,
                    ],
                    [
                        'gaji_pokok' => $gajiPokok,
                        'tunjangan_tetap' => $totalTunjangan,
                        'total_biaya_transport' => 0.0,
                        'total_honor_sks' => 0.0,
                        'total_sks_diampu' => 0.0,
                        'total_tunjangan_fungsional' => 0.0,
                        'jumlah_hari_hadir_tepat_waktu' => $validAttendanceDays,
                        'total_tunjangan' => $totalTunjangan,
                        'total_potongan' => $totalPotongan,
                        'total_pph21' => 0.0,
                        'total_bpjs' => 0.0,
                        'gaji_bersih' => $gajiBersih,
                        'status_transfer' => 'draft',
                        'nomor_rekening' => $peg->nomor_rekening,
                        'bank_nama' => $peg->nama_bank,
                        'catatan' => "Draft Payroll dari Bundle {$periode->nama_periode} (Presensi: {$validAttendanceDays} Hari Hadir Valid)",
                    ]
                );

                GajiDetail::where('gaji_pegawai_id', $gajiPegawai->id)->delete();
                foreach ($detailItems as $item) {
                    $item['gaji_pegawai_id'] = $gajiPegawai->id;
                    GajiDetail::create($item);
                }

                $processedCount++;
            }

            // Ambil data asli sebelum update untuk audit log
            $oldValues = $periode->getOriginal();

            // Kunci bundle presensi secara permanen
            $periode->update([
                'is_locked' => true,
                'locked_at' => Carbon::now(),
                'locked_by' => $user->id,
            ]);

            $newValues = array_merge($periode->getChanges(), [
                'total_pegawai_processed' => $processedCount,
                'periode_bulan_tahun' => $bulanTahun,
            ]);

            try {
                AuditLogService::record(
                    'SIMPEG',
                    'process_payroll',
                    'simpeg_presensi_periode',
                    $periode->id,
                    $oldValues,
                    $newValues,
                    $request
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log process payroll: ' . $e->getMessage());
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => "Payroll untuk bundle '{$periode->nama_periode}' berhasil diproses dan dikunci. Data kehadiran {$processedCount} pegawai berhasil masuk ke halaman Payroll & Slip Gaji.",
            'data' => [
                'bundle_id' => $periode->id,
                'nama_periode' => $periode->nama_periode,
                'total_pegawai' => $processedCount,
                'periode_bulan_tahun' => $bulanTahun,
                'is_locked' => true,
                'status_payroll' => 'Diproses ke Payroll',
            ]
        ]);
    }

    /**
     * Fallback Reset Data
     */
    public function resetData(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.presensi.delete') && !$user->hasPermission('simpeg.presensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk menghapus data presensi.'
            ], 403);
        }

        PresensiPegawai::truncate();
        PresensiPeriode::truncate();

        return response()->json([
            'status' => 'success',
            'message' => 'Seluruh bundle & data presensi berhasil di-reset.',
        ]);
    }

    /**
     * Sinkronisasi Batch Punch Log Mesin Fingerprint / Biometrik Terminal
     */
    public function syncFingerprint(Request $request, FingerprintSyncService $syncService): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.presensi.manage') && !$user->hasPermission('simpeg.presensi.create') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk sinkronisasi log mesin fingerprint.'
            ], 403);
        }

        $validated = $request->validate([
            'device_id' => 'nullable|string|max:50',
            'device_code' => 'nullable|string|max:50',
            'device_ip' => 'nullable|string|max:50',
            'logs' => 'required|array|min:1',
            'logs.*.pin' => 'nullable|string',
            'logs.*.nip' => 'nullable|string',
            'logs.*.pegawai_id' => 'nullable',
            'logs.*.timestamp' => 'required|string',
            'logs.*.verify_mode' => 'nullable|integer',
            'logs.*.in_out_mode' => 'nullable',
        ]);

        $result = $syncService->syncPunchLogs($validated);

        return response()->json([
            'status' => 'success',
            'message' => "Sinkronisasi mesin selesai: {$result['synced_count']} log berhasil diproses, {$result['skipped_count']} dilewati.",
            'data' => $result,
        ]);
    }

    /**
     * Jalankan Otomasi Presensi Cut-off Harian (Auto-Alfa)
     */
    public function runDailyCutoff(Request $request, AttendanceCutoffService $cutoffService): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.presensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menjalankan cut-off presensi harian.'
            ], 403);
        }

        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'unit_kerja_id' => 'nullable|exists:simpeg_unit_kerja,id',
        ]);

        $report = $cutoffService->runDailyCutoff($validated['date'] ?? null, $validated['unit_kerja_id'] ?? null);

        return response()->json([
            'status' => 'success',
            'message' => "Cut-off presensi tanggal {$report['date']} selesai. {$report['total_marked_alfa']} pegawai ditandai Alfa dari {$report['total_evaluated']} pegawai dievaluasi.",
            'data' => $report,
        ]);
    }

    /**
     * Penugasan Kelompok Shift Kerja Secara Massal (Bulk Assign Shift)
     */
    public function assignShiftBulk(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.presensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menugaskan shift pegawai secara massal.'
            ], 403);
        }

        $validated = $request->validate([
            'shift_template_id' => 'required|exists:simpeg_shift_templates,id',
            'unit_kerja_id' => 'nullable|exists:simpeg_unit_kerja,id',
            'jenis_pegawai' => 'nullable|in:dosen,tendik',
            'pegawai_ids' => 'nullable|array',
            'pegawai_ids.*' => 'exists:simpeg_pegawai,id',
        ]);

        $query = Pegawai::query();

        if (!empty($validated['pegawai_ids'])) {
            $query->whereIn('id', $validated['pegawai_ids']);
        } else {
            if (!empty($validated['unit_kerja_id'])) {
                $query->where('unit_kerja_id', $validated['unit_kerja_id']);
            }
            if (!empty($validated['jenis_pegawai'])) {
                $query->where('jenis_pegawai', $validated['jenis_pegawai']);
            }
        }

        $count = $query->count();
        if ($count === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak ada pegawai yang memenuhi kriteria filter untuk ditugaskan shift.'
            ], 422);
        }

        $query->update(['shift_template_id' => $validated['shift_template_id']]);

        $shift = \App\Models\ShiftTemplate::find($validated['shift_template_id']);

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil menugaskan template shift '{$shift->name}' ke {$count} pegawai.",
            'data' => [
                'shift_template_id' => $shift->id,
                'shift_name' => $shift->name,
                'total_assigned' => $count,
            ],
        ]);
    }

    /**
     * Lokasi absen yang sah untuk satu pegawai (utama + tambahan multi-lokasi).
     */
    public function pegawaiOfficeLocations(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.presensi.manage') && !$user->hasPermission('simpeg.presensi.read') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk melihat lokasi absen pegawai.'
            ], 403);
        }

        $pegawai = Pegawai::with(['officeLocation', 'additionalOffices'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => [
                'pegawai_id' => $pegawai->id,
                'nip' => $pegawai->nip,
                'nama_lengkap' => $pegawai->nama_lengkap,
                'primary_office' => $pegawai->officeLocation,
                'additional_offices' => $pegawai->additionalOffices,
                'allowed_offices' => $pegawai->getAllowedOfficeLocations()->values(),
            ],
        ]);
    }

    /**
     * Atur lokasi absen tambahan (multi-lokasi) untuk satu pegawai.
     * Lokasi utama (office_location_id) tidak diubah di sini.
     */
    public function updatePegawaiOfficeLocations(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.presensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengatur lokasi absen pegawai.'
            ], 403);
        }

        $validated = $request->validate([
            'office_location_ids' => 'required|array',
            'office_location_ids.*' => 'exists:simpeg_office_locations,id',
        ]);

        $pegawai = Pegawai::findOrFail($id);

        // Lokasi utama tidak perlu disimpan ganda di pivot.
        $additionalIds = array_values(array_unique(array_diff(
            $validated['office_location_ids'],
            [(int) $pegawai->office_location_id]
        )));

        $pegawai->additionalOffices()->sync($additionalIds);

        return response()->json([
            'status' => 'success',
            'message' => "Lokasi absen tambahan '{$pegawai->nama_lengkap}' berhasil diperbarui (" . count($additionalIds) . ' lokasi).',
            'data' => [
                'pegawai_id' => $pegawai->id,
                'primary_office' => $pegawai->fresh()->officeLocation,
                'additional_offices' => $pegawai->fresh()->additionalOffices,
            ],
        ]);
    }

    /**
     * Penugasan Lokasi Absen Tambahan Secara Massal (Bulk Assign Offices).
     * Contoh: seluruh dosen diberi akses absen di kampus/gedung tempat mengajar.
     */
    public function assignOfficesBulk(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.presensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menugaskan lokasi absen secara massal.'
            ], 403);
        }

        $validated = $request->validate([
            'office_location_ids' => 'required|array|min:1',
            'office_location_ids.*' => 'exists:simpeg_office_locations,id',
            'unit_kerja_id' => 'nullable|exists:simpeg_unit_kerja,id',
            'jenis_pegawai' => 'nullable|in:dosen,tendik',
            'pegawai_ids' => 'nullable|array',
            'pegawai_ids.*' => 'exists:simpeg_pegawai,id',
            'mode' => 'nullable|in:attach,sync',
        ]);

        $query = Pegawai::query();

        if (!empty($validated['pegawai_ids'])) {
            $query->whereIn('id', $validated['pegawai_ids']);
        } else {
            if (!empty($validated['unit_kerja_id'])) {
                $query->where('unit_kerja_id', $validated['unit_kerja_id']);
            }
            if (!empty($validated['jenis_pegawai'])) {
                $query->where('jenis_pegawai', $validated['jenis_pegawai']);
            }
        }

        $pegawaiList = $query->get();
        if ($pegawaiList->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak ada pegawai yang memenuhi kriteria filter untuk ditugaskan lokasi.'
            ], 422);
        }

        $mode = $validated['mode'] ?? 'attach';
        foreach ($pegawaiList as $pegawai) {
            $ids = array_values(array_unique(array_diff(
                $validated['office_location_ids'],
                [(int) $pegawai->office_location_id]
            )));
            if ($mode === 'sync') {
                $pegawai->additionalOffices()->sync($ids);
            } else {
                $pegawai->additionalOffices()->syncWithoutDetaching($ids);
            }
        }

        $offices = \App\Models\OfficeLocation::whereIn('id', $validated['office_location_ids'])->pluck('name');

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil menugaskan lokasi absen (" . $offices->implode(', ') . ") ke {$pegawaiList->count()} pegawai.",
            'data' => [
                'office_location_ids' => $validated['office_location_ids'],
                'total_assigned' => $pegawaiList->count(),
                'mode' => $mode,
            ],
        ]);
    }
}
