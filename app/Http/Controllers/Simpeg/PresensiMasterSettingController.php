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
     */
    public function listShiftTemplates(): JsonResponse
    {
        $shifts = ShiftTemplate::with('days')->withCount('employees')->get();

        return response()->json([
            'status' => 'success',
            'data' => $shifts,
        ]);
    }

    /**
     * Update Master Shift Kerja & Hari Kerja
     */
    public function updateShiftTemplate(Request $request, int $id): JsonResponse
    {
        $shift = ShiftTemplate::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'late_tolerance_minutes' => 'nullable|integer',
            'early_leave_tolerance_minutes' => 'nullable|integer',
            'max_early_clock_in_minutes' => 'nullable|integer',
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
     * Daftar Hari Libur Nasional
     */
    public function listNationalHolidays(Request $request): JsonResponse
    {
        $year = $request->input('year', now()->year);
        $holidays = NationalHoliday::whereYear('holiday_date', $year)
            ->orderBy('holiday_date', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $holidays,
        ]);
    }
}
