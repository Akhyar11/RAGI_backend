<?php

namespace Database\Seeders\Simpeg;

use App\Models\OfficeLocation;
use App\Models\ShiftScheduleDay;
use App\Models\ShiftTemplate;
use App\Models\Simpeg\Pegawai;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SimpegPresensiSettingSeeder extends Seeder
{
    /**
     * Seed parameter sistem presensi & biometrik sesuai standar blueprint.
     */
    public function run(): void
    {
        // 1. Parameter Sistem (Persis sesuai tangkapan layar user di admin/system-settings)
        SystemSetting::set('face_score_threshold', '0.80', 'Ambang batas minimal skor kecocokan biometrik wajah (0.00 - 1.00)');
        SystemSetting::set('gps_accuracy_threshold_meters', '50.0', 'Toleransi akurasi GPS maksimal dalam meter');
        SystemSetting::set('late_tolerance_minutes', '15', 'Toleransi keterlambatan presensi masuk dalam menit');
        SystemSetting::set('max_early_clock_in_minutes', '60', 'Batas waktu paling awal presensi masuk dibuka sebelum jam shift (dalam menit)');
        SystemSetting::set('early_leave_tolerance_minutes', '15', 'Toleransi kepulangan lebih cepat sebelum jam selesai shift (dalam menit)');

        // 2. Lokasi Kantor Default (Politeknik Indonusa Surakarta)
        $office = OfficeLocation::firstOrCreate(
            ['name' => 'Politeknik Indonusa Surakarta'],
            [
                'address' => 'Jl. KH Samanhudi No.84, Sondakan, Laweyan, Surakarta, Jawa Tengah 57147',
                'latitude' => -7.5675000,
                'longitude' => 110.8036000,
                'radius_meters' => 150,
                'is_active' => true,
            ]
        );

        // 3. Shift Template Reguler (Senin - Jumat 08:00 - 17:00, Sabtu & Minggu Libur)
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

        $days = [
            ['day_of_week' => 1, 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'break_start' => '12:00:00', 'break_end' => '13:00:00', 'is_day_off' => false], // Senin
            ['day_of_week' => 2, 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'break_start' => '12:00:00', 'break_end' => '13:00:00', 'is_day_off' => false], // Selasa
            ['day_of_week' => 3, 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'break_start' => '12:00:00', 'break_end' => '13:00:00', 'is_day_off' => false], // Rabu
            ['day_of_week' => 4, 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'break_start' => '12:00:00', 'break_end' => '13:00:00', 'is_day_off' => false], // Kamis
            ['day_of_week' => 5, 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'break_start' => '11:30:00', 'break_end' => '13:00:00', 'is_day_off' => false], // Jumat
            ['day_of_week' => 6, 'start_time' => null, 'end_time' => null, 'break_start' => null, 'break_end' => null, 'is_day_off' => true],                               // Sabtu
            ['day_of_week' => 0, 'start_time' => null, 'end_time' => null, 'break_start' => null, 'break_end' => null, 'is_day_off' => true],                               // Minggu
        ];

        foreach ($days as $day) {
            ShiftScheduleDay::updateOrCreate(
                ['shift_template_id' => $shift->id, 'day_of_week' => $day['day_of_week']],
                $day
            );
        }

        // 4. Asosiasikan pegawai yang belum memiliki kantor atau shift ke default
        Pegawai::whereNull('office_location_id')->update(['office_location_id' => $office->id]);
        Pegawai::whereNull('shift_template_id')->update(['shift_template_id' => $shift->id]);
    }
}
