<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\OfficeLocation;
use App\Models\ShiftTemplate;
use App\Models\NationalHoliday;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PresensiMasterSettingController extends Controller
{
    /**
     * Dapatkan seluruh setting presensi
     */
    public function getSettings(): JsonResponse
    {
        $settings = SystemSetting::whereIn('key', [
            'face_score_threshold',
            'gps_accuracy_threshold_meters',
            'late_tolerance_minutes',
            'max_early_clock_in_minutes',
            'early_leave_tolerance_minutes',
            'applies_national_holidays',
        ])->get()->keyBy('key');

        return response()->json([
            'status' => 'success',
            'data' => [
                'face_score_threshold' => (float) ($settings->get('face_score_threshold')?->value ?? 0.80),
                'gps_accuracy_threshold_meters' => (float) ($settings->get('gps_accuracy_threshold_meters')?->value ?? 50.0),
                'late_tolerance_minutes' => (int) ($settings->get('late_tolerance_minutes')?->value ?? 15),
                'max_early_clock_in_minutes' => (int) ($settings->get('max_early_clock_in_minutes')?->value ?? 60),
                'early_leave_tolerance_minutes' => (int) ($settings->get('early_leave_tolerance_minutes')?->value ?? 15),
                'applies_national_holidays' => filter_var($settings->get('applies_national_holidays')?->value ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
        ]);
    }

    /**
     * Perbarui parameter sistem presensi
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'face_score_threshold' => 'required|numeric|min:0.1|max:1.0',
            'gps_accuracy_threshold_meters' => 'required|numeric|min:5|max:500',
            'late_tolerance_minutes' => 'required|integer|min:0|max:120',
            'max_early_clock_in_minutes' => 'required|integer|min:0|max:240',
            'early_leave_tolerance_minutes' => 'required|integer|min:0|max:120',
            'applies_national_holidays' => 'required|boolean',
        ]);

        foreach ($validated as $key => $val) {
            SystemSetting::set($key, (string) $val);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Parameter presensi sistem berhasil diperbarui',
            'data' => $validated,
        ]);
    }

    /**
     * Daftar Lokasi Kantor (Office Locations)
     */
    public function listOfficeLocations(): JsonResponse
    {
        $locations = OfficeLocation::withCount('employees')->orderBy('name')->get();

        return response()->json([
            'status' => 'success',
            'data' => $locations,
        ]);
    }

    /**
     * Simpan / Tambah Lokasi Kantor
     */
    public function storeOfficeLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius_meters' => 'required|integer|min:10|max:5000',
            'is_active' => 'required|boolean',
        ]);

        $office = OfficeLocation::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Lokasi kantor berhasil ditambahkan',
            'data' => $office,
        ], 201);
    }

    /**
     * Update Lokasi Kantor
     */
    public function updateOfficeLocation(Request $request, int $id): JsonResponse
    {
        $office = OfficeLocation::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius_meters' => 'required|integer|min:10|max:5000',
            'is_active' => 'required|boolean',
        ]);

        $office->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Lokasi kantor berhasil diperbarui',
            'data' => $office,
        ]);
    }

    /**
     * Hapus Lokasi Kantor
     */
    public function destroyOfficeLocation(int $id): JsonResponse
    {
        $office = OfficeLocation::withCount('employees')->findOrFail($id);

        if ($office->employees_count > 0) {
            return response()->json([
                'status' => 'error',
                'message' => "Lokasi kantor '{$office->name}' tidak dapat dihapus karena masih digunakan oleh {$office->employees_count} pegawai.",
            ], 422);
        }

        $office->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Lokasi kantor berhasil dihapus',
        ]);
    }

    /**
     * Daftar Master Shift Kerja
     * Auto-seed template default bila tabel masih kosong agar UI tidak kosong.
     */
    public function listShiftTemplates(): JsonResponse
    {
        if (ShiftTemplate::count() === 0) {
            $this->ensureDefaultShiftTemplate();
        }

        // Backfill 7 hari bila ada template yang days-nya belum lengkap
        foreach (ShiftTemplate::with('days')->get() as $tpl) {
            if ($tpl->days->count() < 7) {
                $this->ensureShiftDays($tpl->id);
            }
        }

        $shifts = ShiftTemplate::with('days')->withCount('employees')->orderBy('id')->get();

        return response()->json([
            'status' => 'success',
            'data' => $shifts,
        ]);
    }

    /**
     * Tambah tipe shift kerja baru (nama + jam berbeda-beda per tipe).
     * Days opsional: bila tidak dikirim, dibuatkan 7 hari default
     * (Senin-Jumat 08:00-17:00, Sabtu-Minggu libur) yang bisa diubah lewat update.
     */
    public function storeShiftTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:simpeg_shift_templates,name',
            'description' => 'nullable|string',
            'late_tolerance_minutes' => 'nullable|integer|min:0|max:120',
            'early_leave_tolerance_minutes' => 'nullable|integer|min:0|max:120',
            'max_early_clock_in_minutes' => 'nullable|integer|min:0|max:240',
            'applies_national_holidays' => 'nullable|boolean',
            'is_active' => 'required|boolean',
            'days' => 'nullable|array|size:7',
            'days.*.day_of_week' => 'required|integer|min:0|max:6',
            'days.*.start_time' => 'nullable|string',
            'days.*.end_time' => 'nullable|string',
            'days.*.is_day_off' => 'required|boolean',
        ]);

        $shift = ShiftTemplate::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'late_tolerance_minutes' => $validated['late_tolerance_minutes'] ?? 15,
            'early_leave_tolerance_minutes' => $validated['early_leave_tolerance_minutes'] ?? 15,
            'max_early_clock_in_minutes' => $validated['max_early_clock_in_minutes'] ?? 60,
            'applies_national_holidays' => $validated['applies_national_holidays'] ?? true,
            'is_active' => $validated['is_active'],
        ]);

        if (!empty($validated['days'])) {
            foreach ($validated['days'] as $day) {
                $shift->days()->create([
                    'day_of_week' => $day['day_of_week'],
                    'start_time' => $day['start_time'] ?? null,
                    'end_time' => $day['end_time'] ?? null,
                    'is_day_off' => $day['is_day_off'],
                ]);
            }
        } else {
            $this->ensureShiftDays($shift->id);
        }

        return response()->json([
            'status' => 'success',
            'message' => "Tipe shift '{$shift->name}' berhasil ditambahkan",
            'data' => $shift->load('days'),
        ], 201);
    }

    /**
     * Update Master Shift Kerja & Hari Kerja
     */
    public function updateShiftTemplate(Request $request, int $id): JsonResponse
    {
        $shift = ShiftTemplate::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:simpeg_shift_templates,name,' . $shift->id,
            'description' => 'nullable|string',
            'late_tolerance_minutes' => 'nullable|integer',
            'early_leave_tolerance_minutes' => 'nullable|integer',
            'max_early_clock_in_minutes' => 'nullable|integer',
            'applies_national_holidays' => 'nullable|boolean',
            'is_active' => 'required|boolean',
            'days' => 'nullable|array',
            'days.*.id' => 'required|integer',
            'days.*.start_time' => 'nullable|string',
            'days.*.end_time' => 'nullable|string',
            'days.*.is_day_off' => 'required|boolean',
        ]);

        $shift->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'late_tolerance_minutes' => $validated['late_tolerance_minutes'] ?? $shift->late_tolerance_minutes,
            'early_leave_tolerance_minutes' => $validated['early_leave_tolerance_minutes'] ?? $shift->early_leave_tolerance_minutes,
            'max_early_clock_in_minutes' => $validated['max_early_clock_in_minutes'] ?? $shift->max_early_clock_in_minutes,
            'applies_national_holidays' => $validated['applies_national_holidays'] ?? $shift->applies_national_holidays,
            'is_active' => $validated['is_active'],
        ]);

        if (!empty($validated['days'])) {
            foreach ($validated['days'] as $dayData) {
                $shift->days()->where('id', $dayData['id'])->update([
                    'start_time' => $dayData['start_time'] ?? null,
                    'end_time' => $dayData['end_time'] ?? null,
                    'is_day_off' => $dayData['is_day_off'],
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Jadwal shift berhasil diperbarui',
            'data' => $shift->load('days'),
        ]);
    }

    /**
     * Hapus tipe shift. Ditolak bila masih dipakai pegawai.
     */
    public function destroyShiftTemplate(int $id): JsonResponse
    {
        $shift = ShiftTemplate::withCount('employees')->findOrFail($id);

        if ($shift->employees_count > 0) {
            return response()->json([
                'status' => 'error',
                'message' => "Tipe shift '{$shift->name}' tidak dapat dihapus karena masih digunakan oleh {$shift->employees_count} pegawai.",
            ], 422);
        }

        $name = $shift->name;
        $shift->delete();

        return response()->json([
            'status' => 'success',
            'message' => "Tipe shift '{$name}' berhasil dihapus",
        ]);
    }

    /**
     * Daftar Hari Libur Nasional
     */
    public function listNationalHolidays(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', now()->year);
        $holidays = NationalHoliday::whereYear('holiday_date', $year)
            ->orderBy('holiday_date', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $holidays,
        ]);
    }

    /**
     * Tambah tanggal libur manual (tanggal merah / cuti bersama / libur kampus).
     */
    public function storeNationalHoliday(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'holiday_date' => 'required|date_format:Y-m-d|unique:simpeg_national_holidays,holiday_date',
            'name' => 'required|string|max:255',
            'is_mass_leave' => 'required|boolean',
            'description' => 'nullable|string',
        ]);

        $holiday = NationalHoliday::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => "Tanggal libur {$holiday->holiday_date->format('d M Y')} berhasil ditambahkan",
            'data' => $holiday,
        ], 201);
    }

    /**
     * Ubah tanggal libur.
     */
    public function updateNationalHoliday(Request $request, int $id): JsonResponse
    {
        $holiday = NationalHoliday::findOrFail($id);

        $validated = $request->validate([
            'holiday_date' => 'required|date_format:Y-m-d|unique:simpeg_national_holidays,holiday_date,' . $holiday->id,
            'name' => 'required|string|max:255',
            'is_mass_leave' => 'required|boolean',
            'description' => 'nullable|string',
        ]);

        $holiday->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Tanggal libur berhasil diperbarui',
            'data' => $holiday->fresh(),
        ]);
    }

    /**
     * Sinkronisasi kalender libur dari API publik Hari Libur Nasional Indonesia.
     * Data manual yang sudah ada tidak dihapus (updateOrCreate per tanggal).
     */
    public function syncNationalHolidays(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'nullable|integer|min:2020|max:2035',
        ]);

        $year = (int) ($validated['year'] ?? now()->year);
        $count = (new \App\Services\HolidaySyncService())->syncAndCount($year);

        if ($count === 0) {
            return response()->json([
                'status' => 'error',
                'message' => "Sinkronisasi libur nasional {$year} gagal — API publik tidak dapat dijangkau. Data manual tetap aman.",
            ], 502);
        }

        $total = NationalHoliday::whereYear('holiday_date', $year)->count();

        return response()->json([
            'status' => 'success',
            'message' => "Sinkronisasi libur nasional {$year} berhasil — {$count} tanggal diproses, total {$total} tanggal tersimpan.",
            'data' => [
                'year' => $year,
                'synced' => $count,
                'total' => $total,
            ],
        ]);
    }

    /**
     * Hapus tanggal libur.
     */
    public function destroyNationalHoliday(int $id): JsonResponse
    {
        $holiday = NationalHoliday::findOrFail($id);
        $label = $holiday->name . ' (' . $holiday->holiday_date->format('d M Y') . ')';
        $holiday->delete();

        return response()->json([
            'status' => 'success',
            'message' => "Tanggal libur '{$label}' berhasil dihapus",
        ]);
    }

    /**
     * Buat template default "Shift Reguler 5 Hari" + 7 hari kerja.
     * Dipakai saat tabel masih kosong agar frontend tidak kosong.
     */
    private function ensureDefaultShiftTemplate(): void
    {
        $shift = ShiftTemplate::firstOrCreate(
            ['name' => 'Shift Reguler 5 Hari'],
            [
                'description' => 'Jam kerja standar 08:00 s/d 17:00 (Senin - Jumat)',
                'is_active' => true,
                'late_tolerance_minutes' => 15,
                'early_leave_tolerance_minutes' => 15,
                'max_early_clock_in_minutes' => 60,
                'applies_national_holidays' => true,
            ]
        );

        $this->ensureShiftDays($shift->id);
    }

    /**
     * Pastikan sebuah template memiliki 7 baris hari (0=Minggu..6=Sabtu).
     */
    private function ensureShiftDays(int $shiftId): void
    {
        $defaults = [
            1 => ['start_time' => '08:00:00', 'end_time' => '17:00:00', 'is_day_off' => false],
            2 => ['start_time' => '08:00:00', 'end_time' => '17:00:00', 'is_day_off' => false],
            3 => ['start_time' => '08:00:00', 'end_time' => '17:00:00', 'is_day_off' => false],
            4 => ['start_time' => '08:00:00', 'end_time' => '17:00:00', 'is_day_off' => false],
            5 => ['start_time' => '08:00:00', 'end_time' => '17:00:00', 'is_day_off' => false],
            6 => ['start_time' => null, 'end_time' => null, 'is_day_off' => true],
            0 => ['start_time' => null, 'end_time' => null, 'is_day_off' => true],
        ];

        foreach ($defaults as $dayOfWeek => $attr) {
            \App\Models\ShiftScheduleDay::firstOrCreate(
                ['shift_template_id' => $shiftId, 'day_of_week' => $dayOfWeek],
                $attr
            );
        }
    }
}
