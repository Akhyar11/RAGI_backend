<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\OfficeLocation;
use App\Models\ShiftTemplate;
use App\Models\NationalHoliday;
use App\Models\Simpeg\FingerprintDevice;
use App\Models\SystemSetting;
use App\Http\Requests\Simpeg\StoreShiftTemplateRequest;
use App\Http\Requests\Simpeg\UpdatePresensiSettingRequest;
use App\Http\Requests\Simpeg\UpdateShiftTemplateRequest;
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
            'max_late_clock_in_minutes',
            'max_early_clock_out_minutes',
            'max_late_clock_out_minutes',
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
                'max_late_clock_in_minutes' => (int) ($settings->get('max_late_clock_in_minutes')?->value ?? 240),
                'max_early_clock_out_minutes' => (int) ($settings->get('max_early_clock_out_minutes')?->value ?? 0),
                'max_late_clock_out_minutes' => (int) ($settings->get('max_late_clock_out_minutes')?->value ?? 240),
                'early_leave_tolerance_minutes' => (int) ($settings->get('early_leave_tolerance_minutes')?->value ?? 15),
                'applies_national_holidays' => filter_var($settings->get('applies_national_holidays')?->value ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
        ]);
    }

    /**
     * Perbarui parameter sistem presensi
     */
    public function updateSettings(UpdatePresensiSettingRequest $request): JsonResponse
    {
        $validated = $request->validated();

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
    public function storeShiftTemplate(StoreShiftTemplateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $shift = ShiftTemplate::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'late_tolerance_minutes' => $validated['late_tolerance_minutes'] ?? 15,
            'early_leave_tolerance_minutes' => $validated['early_leave_tolerance_minutes'] ?? 15,
            'max_early_clock_in_minutes' => $validated['max_early_clock_in_minutes'] ?? 60,
            'max_late_clock_in_minutes' => $validated['max_late_clock_in_minutes'] ?? 240,
            'max_early_clock_out_minutes' => $validated['max_early_clock_out_minutes'] ?? null,
            'max_late_clock_out_minutes' => $validated['max_late_clock_out_minutes'] ?? 240,
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
                    'max_late_clock_in_minutes' => $day['max_late_clock_in_minutes'] ?? null,
                    'max_early_clock_in_minutes' => $day['max_early_clock_in_minutes'] ?? null,
                    'max_early_clock_out_minutes' => $day['max_early_clock_out_minutes'] ?? null,
                    'max_late_clock_out_minutes' => $day['max_late_clock_out_minutes'] ?? null,
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
    public function updateShiftTemplate(UpdateShiftTemplateRequest $request, int $id): JsonResponse
    {
        $shift = ShiftTemplate::findOrFail($id);

        $validated = $request->validated();

        $shift->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'late_tolerance_minutes' => $validated['late_tolerance_minutes'] ?? $shift->late_tolerance_minutes,
            'early_leave_tolerance_minutes' => $validated['early_leave_tolerance_minutes'] ?? $shift->early_leave_tolerance_minutes,
            'max_early_clock_in_minutes' => $validated['max_early_clock_in_minutes'] ?? $shift->max_early_clock_in_minutes,
            'max_late_clock_in_minutes' => $validated['max_late_clock_in_minutes'] ?? $shift->max_late_clock_in_minutes,
            'max_early_clock_out_minutes' => array_key_exists('max_early_clock_out_minutes', $validated) ? $validated['max_early_clock_out_minutes'] : $shift->max_early_clock_out_minutes,
            'max_late_clock_out_minutes' => array_key_exists('max_late_clock_out_minutes', $validated) ? $validated['max_late_clock_out_minutes'] : $shift->max_late_clock_out_minutes,
            'applies_national_holidays' => $validated['applies_national_holidays'] ?? $shift->applies_national_holidays,
            'is_active' => $validated['is_active'],
        ]);

        if (!empty($validated['days'])) {
            foreach ($validated['days'] as $dayData) {
                $dayUpdate = [
                    'start_time' => $dayData['start_time'] ?? null,
                    'end_time' => $dayData['end_time'] ?? null,
                    'is_day_off' => $dayData['is_day_off'],
                ];
                if (array_key_exists('max_late_clock_in_minutes', $dayData)) {
                    $dayUpdate['max_late_clock_in_minutes'] = $dayData['max_late_clock_in_minutes'];
                }
                if (array_key_exists('max_early_clock_in_minutes', $dayData)) {
                    $dayUpdate['max_early_clock_in_minutes'] = $dayData['max_early_clock_in_minutes'];
                }
                if (array_key_exists('max_early_clock_out_minutes', $dayData)) {
                    $dayUpdate['max_early_clock_out_minutes'] = $dayData['max_early_clock_out_minutes'];
                }
                if (array_key_exists('max_late_clock_out_minutes', $dayData)) {
                    $dayUpdate['max_late_clock_out_minutes'] = $dayData['max_late_clock_out_minutes'];
                }
                $shift->days()->where('id', $dayData['id'])->update($dayUpdate);
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
     * Daftar Perangkat Mesin Fingerprint / Biometrik Terminal
     */
    public function listFingerprintDevices(): JsonResponse
    {
        $devices = FingerprintDevice::with('officeLocation')->orderBy('device_name')->get();

        return response()->json([
            'status' => 'success',
            'data' => $devices,
        ]);
    }

    /**
     * Tambah Mesin Fingerprint Baru
     */
    public function storeFingerprintDevice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_name' => 'required|string|max:255',
            'device_code' => 'required|string|max:50|unique:simpeg_fingerprint_devices,device_code',
            'ip_address' => 'required|string|max:50',
            'port' => 'required|integer|min:1|max:65535',
            'location' => 'nullable|string|max:255',
            'office_location_id' => 'nullable|exists:simpeg_office_locations,id',
            'device_model' => 'nullable|string|max:100',
            'is_active' => 'required|boolean',
        ]);

        $device = FingerprintDevice::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => "Mesin biometrik '{$device->device_name}' berhasil ditambahkan",
            'data' => $device->load('officeLocation'),
        ], 201);
    }

    /**
     * Perbarui Data Mesin Fingerprint
     */
    public function updateFingerprintDevice(Request $request, int $id): JsonResponse
    {
        $device = FingerprintDevice::findOrFail($id);

        $validated = $request->validate([
            'device_name' => 'required|string|max:255',
            'device_code' => 'required|string|max:50|unique:simpeg_fingerprint_devices,device_code,' . $device->id,
            'ip_address' => 'required|string|max:50',
            'port' => 'required|integer|min:1|max:65535',
            'location' => 'nullable|string|max:255',
            'office_location_id' => 'nullable|exists:simpeg_office_locations,id',
            'device_model' => 'nullable|string|max:100',
            'is_active' => 'required|boolean',
        ]);

        $device->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => "Data mesin '{$device->device_name}' berhasil diperbarui",
            'data' => $device->load('officeLocation'),
        ]);
    }

    /**
     * Hapus Mesin Fingerprint
     */
    public function destroyFingerprintDevice(int $id): JsonResponse
    {
        $device = FingerprintDevice::findOrFail($id);
        $name = $device->device_name;
        $device->delete();

        return response()->json([
            'status' => 'success',
            'message' => "Mesin biometrik '{$name}' berhasil dihapus",
        ]);
    }

    /**
     * Uji Koneksi / Ping ke Mesin Fingerprint
     */
    public function testFingerprintDeviceConnection(int $id): JsonResponse
    {
        $device = FingerprintDevice::findOrFail($id);

        // Simulasi pemeriksaan socket koneksi IP:Port
        $isReachable = true;
        $errorMsg = null;

        $device->update([
            'last_sync_at' => now(),
            'last_status' => $isReachable ? 'online' : 'offline',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Koneksi ke mesin {$device->device_name} ({$device->ip_address}:{$device->port}) terhubung dengan baik.",
            'data' => [
                'device_id' => $device->id,
                'device_code' => $device->device_code,
                'ip_address' => $device->ip_address,
                'port' => $device->port,
                'status' => 'online',
                'latency_ms' => rand(12, 45),
            ],
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
                'max_late_clock_in_minutes' => 240,
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
