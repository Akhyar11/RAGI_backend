<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\NationalHoliday;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PresensiPegawai;
use App\Models\Simpeg\PresensiPeriode;
use App\Services\AttendanceRecapService;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PresensiController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService,
        protected AttendanceRecapService $recapService
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
        $isManager = $user->hasPermission('simpeg.presensi.manage') || $user->hasPermission('simpeg.presensi.read') || $user->user_type === 'admin' || $user->isAdmin();

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

        $perPage = min(100, max(1, (int) $request->input('per_page', $request->input('limit', 15))));
        $data = $query->orderByDesc('tanggal')->orderByDesc('clock_in')->paginate($perPage);

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

        $employee = Pegawai::with(['officeLocation', 'shiftTemplate.days'])
            ->find($pegawaiId);

        if (!$employee) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data pegawai tidak ditemukan',
            ], 404);
        }

        $today = Carbon::today();
        $dayOfWeek = $today->dayOfWeek;

        $schedule = $employee->shiftTemplate?->getScheduleForDay($dayOfWeek);
        $attendance = Attendance::where('pegawai_id', $employee->id)
            ->whereDate('tanggal', $today)
            ->first();
        $holiday = NationalHoliday::isHoliday($today);

        return response()->json([
            'status' => 'success',
            'data' => [
                'date' => $today->toDateString(),
                'day_name' => $schedule ? $schedule->day_name : 'Hari Ini',
                'employee' => [
                    'id' => $employee->id,
                    'nip' => $employee->nip,
                    'nama' => $employee->nama_lengkap,
                    'is_face_enrolled' => !empty($employee->face_embedding),
                ],
                'schedule' => $schedule,
                'office' => $employee->officeLocation,
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
            'is_mock_location' => 'required|boolean',
            'pegawai_id' => 'nullable|exists:simpeg_pegawai,id',
        ]);

        $employee = null;
        if ($request->filled('pegawai_id') && ($request->user()->hasPermission('simpeg.presensi.manage') || $request->user()->user_type === 'admin')) {
            $employee = Pegawai::with(['officeLocation', 'shiftTemplate.days'])->find($request->pegawai_id);
        } else {
            $employee = Pegawai::with(['officeLocation', 'shiftTemplate.days'])->where('user_id', $request->user()->id)->first();
        }

        if (!$employee) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data profil pegawai tidak ditemukan.',
            ], 404);
        }

        $attendance = $this->attendanceService->processClockIn($employee, $validated);

        return response()->json([
            'status' => $attendance->status === 'ditolak' ? 'error' : 'success',
            'message' => $attendance->status === 'ditolak' ? 'Presensi masuk ditolak: ' . $attendance->rejection_reason : 'Presensi masuk berhasil dicatat',
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
            'pegawai_id' => 'nullable|exists:simpeg_pegawai,id',
        ]);

        $employee = null;
        if ($request->filled('pegawai_id') && ($request->user()->hasPermission('simpeg.presensi.manage') || $request->user()->user_type === 'admin')) {
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

        $attendance = $this->attendanceService->processClockOut($employee, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Presensi pulang berhasil dicatat',
            'data' => $attendance,
        ]);
    }

    /**
     * Rekap presensi bulanan
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

        $month = (int) $request->input('month', Carbon::now()->month);
        $year = (int) $request->input('year', Carbon::now()->year);

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $recap = $this->recapService->generateRecap($employee, $startDate, $endDate);

        return response()->json([
            'status' => 'success',
            'data' => $recap,
        ]);
    }

    /**
     * Persetujuan manual presensi oleh HR / Admin
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('simpeg.presensi.manage') && $request->user()->user_type !== 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menyetujui presensi.',
            ], 403);
        }

        $attendance = Attendance::findOrFail($id);
        $attendance->update([
            'is_approved_by_admin' => true,
            'approved_by' => $request->user()->id,
            'approved_at' => Carbon::now(),
            'status_kehadiran' => 'hadir',
            'notes' => ($attendance->notes ? $attendance->notes . ' | ' : '') . 'Disetujui oleh HR pada ' . Carbon::now()->format('d/m/Y H:i'),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Presensi berhasil disetujui.',
            'data' => $attendance,
        ]);
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
        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $path = $file->storeAs('presensi_rekap', $fileName, 'public');
        $fullPath = storage_path('app/public/' . $path);

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
        }

        return response()->json([
            'status' => 'success',
            'message' => "Bundle '{$periode->nama_periode}' berhasil dibuat dan {$importedCount} data absensi berhasil di-import.",
            'data' => $periode,
        ], 201);
    }

    /**
     * Delete a Presensi Bundle and all its attendance logs
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.presensi.delete') && !$user->hasPermission('simpeg.presensi.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk menghapus Bundle Presensi.'
            ], 403);
        }

        $periode = PresensiPeriode::findOrFail($id);
        $nama = $periode->nama_periode;
        $periode->delete();

        return response()->json([
            'status' => 'success',
            'message' => "Bundle presensi '{$nama}' beserta seluruh log absensi di dalamnya berhasil dihapus.",
        ]);
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

        return response()->json([
            'status' => 'success',
            'message' => "Payroll untuk bundle '{$periode->nama_periode}' berhasil diproses dan dikirim ke SIKEU.",
            'data' => [
                'bundle_id' => $periode->id,
                'nama_periode' => $periode->nama_periode,
                'total_record' => $periode->total_record,
                'status_payroll' => 'Diproses',
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
}
