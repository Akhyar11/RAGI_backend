<?php

namespace Database\Seeders\Simpeg;

use App\Models\OfficeLocation;
use App\Models\ShiftScheduleDay;
use App\Models\ShiftTemplate;
use App\Models\Simpeg\FingerprintDevice;
use Illuminate\Database\Seeder;

class SimpegShiftUniversitySeeder extends Seeder
{
    /**
     * Seed template shift kerja multi-profesi kampus & perangkat biometrik.
     */
    public function run(): void
    {
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

        // 1. Shift Tendik Reguler 5 Hari
        $shiftTendik = ShiftTemplate::updateOrCreate(
            ['name' => 'Shift Tendik Reguler (5 Hari)'],
            [
                'description' => 'Jam kerja standar tenaga kependidikan 08:00 s/d 17:00 (Senin - Jumat)',
                'is_active' => true,
                'late_tolerance_minutes' => 15,
                'early_leave_tolerance_minutes' => 15,
                'max_early_clock_in_minutes' => 60,
                'applies_national_holidays' => true,
            ]
        );
        $this->seedDays($shiftTendik->id, [
            1 => ['start_time' => '08:00:00', 'end_time' => '17:00:00', 'break_start' => '12:00:00', 'break_end' => '13:00:00', 'is_day_off' => false],
            2 => ['start_time' => '08:00:00', 'end_time' => '17:00:00', 'break_start' => '12:00:00', 'break_end' => '13:00:00', 'is_day_off' => false],
            3 => ['start_time' => '08:00:00', 'end_time' => '17:00:00', 'break_start' => '12:00:00', 'break_end' => '13:00:00', 'is_day_off' => false],
            4 => ['start_time' => '08:00:00', 'end_time' => '17:00:00', 'break_start' => '12:00:00', 'break_end' => '13:00:00', 'is_day_off' => false],
            5 => ['start_time' => '08:00:00', 'end_time' => '17:00:00', 'break_start' => '11:30:00', 'break_end' => '13:00:00', 'is_day_off' => false],
            6 => ['start_time' => null, 'end_time' => null, 'is_day_off' => true],
            0 => ['start_time' => null, 'end_time' => null, 'is_day_off' => true],
        ]);

        // 2. Shift Dosen Fleksibel (Tridharma)
        $shiftDosen = ShiftTemplate::updateOrCreate(
            ['name' => 'Shift Dosen Fleksibel (Tridharma)'],
            [
                'description' => 'Jadwal kerja mandiri dosen untuk kegiatan Tridharma (pengajaran, penelitian, pengabdian & bimbingan)',
                'is_active' => true,
                'late_tolerance_minutes' => 30,
                'early_leave_tolerance_minutes' => 30,
                'max_early_clock_in_minutes' => 90,
                'applies_national_holidays' => true,
            ]
        );
        $this->seedDays($shiftDosen->id, [
            1 => ['start_time' => '07:30:00', 'end_time' => '18:00:00', 'break_start' => '12:00:00', 'break_end' => '13:00:00', 'is_day_off' => false],
            2 => ['start_time' => '07:30:00', 'end_time' => '18:00:00', 'break_start' => '12:00:00', 'break_end' => '13:00:00', 'is_day_off' => false],
            3 => ['start_time' => '07:30:00', 'end_time' => '18:00:00', 'break_start' => '12:00:00', 'break_end' => '13:00:00', 'is_day_off' => false],
            4 => ['start_time' => '07:30:00', 'end_time' => '18:00:00', 'break_start' => '12:00:00', 'break_end' => '13:00:00', 'is_day_off' => false],
            5 => ['start_time' => '07:30:00', 'end_time' => '18:00:00', 'break_start' => '11:30:00', 'break_end' => '13:00:00', 'is_day_off' => false],
            6 => ['start_time' => '07:30:00', 'end_time' => '16:00:00', 'break_start' => '12:00:00', 'break_end' => '13:00:00', 'is_day_off' => false],
            0 => ['start_time' => null, 'end_time' => null, 'is_day_off' => true],
        ]);

        // 3. Shift Satpam Pagi (Shift 1)
        $shiftSatpam1 = ShiftTemplate::updateOrCreate(
            ['name' => 'Shift Satpam / Security Pagi (Shift 1)'],
            [
                'description' => 'Shift pengamanan pagi 06:00 - 14:00 (tetap berlaku pada hari libur nasional)',
                'is_active' => true,
                'late_tolerance_minutes' => 10,
                'early_leave_tolerance_minutes' => 5,
                'max_early_clock_in_minutes' => 45,
                'applies_national_holidays' => false,
            ]
        );
        $this->seedDays($shiftSatpam1->id, [
            1 => ['start_time' => '06:00:00', 'end_time' => '14:00:00', 'is_day_off' => false],
            2 => ['start_time' => '06:00:00', 'end_time' => '14:00:00', 'is_day_off' => false],
            3 => ['start_time' => '06:00:00', 'end_time' => '14:00:00', 'is_day_off' => false],
            4 => ['start_time' => '06:00:00', 'end_time' => '14:00:00', 'is_day_off' => false],
            5 => ['start_time' => '06:00:00', 'end_time' => '14:00:00', 'is_day_off' => false],
            6 => ['start_time' => '06:00:00', 'end_time' => '14:00:00', 'is_day_off' => false],
            0 => ['start_time' => '06:00:00', 'end_time' => '14:00:00', 'is_day_off' => false],
        ]);

        // 4. Shift Satpam Siang/Sore (Shift 2)
        $shiftSatpam2 = ShiftTemplate::updateOrCreate(
            ['name' => 'Shift Satpam / Security Siang (Shift 2)'],
            [
                'description' => 'Shift pengamanan sore 14:00 - 22:00 (tetap berlaku pada hari libur nasional)',
                'is_active' => true,
                'late_tolerance_minutes' => 10,
                'early_leave_tolerance_minutes' => 5,
                'max_early_clock_in_minutes' => 45,
                'applies_national_holidays' => false,
            ]
        );
        $this->seedDays($shiftSatpam2->id, [
            1 => ['start_time' => '14:00:00', 'end_time' => '22:00:00', 'is_day_off' => false],
            2 => ['start_time' => '14:00:00', 'end_time' => '22:00:00', 'is_day_off' => false],
            3 => ['start_time' => '14:00:00', 'end_time' => '22:00:00', 'is_day_off' => false],
            4 => ['start_time' => '14:00:00', 'end_time' => '22:00:00', 'is_day_off' => false],
            5 => ['start_time' => '14:00:00', 'end_time' => '22:00:00', 'is_day_off' => false],
            6 => ['start_time' => '14:00:00', 'end_time' => '22:00:00', 'is_day_off' => false],
            0 => ['start_time' => '14:00:00', 'end_time' => '22:00:00', 'is_day_off' => false],
        ]);

        // 5. Shift Satpam Malam (Shift 3)
        $shiftSatpam3 = ShiftTemplate::updateOrCreate(
            ['name' => 'Shift Satpam / Security Malam (Shift 3)'],
            [
                'description' => 'Shift pengamanan malam 22:00 - 06:00 lintas hari (tetap berlaku pada hari libur nasional)',
                'is_active' => true,
                'late_tolerance_minutes' => 10,
                'early_leave_tolerance_minutes' => 5,
                'max_early_clock_in_minutes' => 45,
                'applies_national_holidays' => false,
            ]
        );
        $this->seedDays($shiftSatpam3->id, [
            1 => ['start_time' => '22:00:00', 'end_time' => '06:00:00', 'is_day_off' => false],
            2 => ['start_time' => '22:00:00', 'end_time' => '06:00:00', 'is_day_off' => false],
            3 => ['start_time' => '22:00:00', 'end_time' => '06:00:00', 'is_day_off' => false],
            4 => ['start_time' => '22:00:00', 'end_time' => '06:00:00', 'is_day_off' => false],
            5 => ['start_time' => '22:00:00', 'end_time' => '06:00:00', 'is_day_off' => false],
            6 => ['start_time' => '22:00:00', 'end_time' => '06:00:00', 'is_day_off' => false],
            0 => ['start_time' => '22:00:00', 'end_time' => '06:00:00', 'is_day_off' => false],
        ]);

        // 6. Shift Laboran & Teknisi (6 Hari)
        $shiftLaboran = ShiftTemplate::updateOrCreate(
            ['name' => 'Shift Laboran & Teknisi (6 Hari)'],
            [
                'description' => 'Jadwal operasional laboratorium komputer, bengkel & sains 6 hari (Senin-Jumat 08:00-15:30, Sabtu 08:00-13:00)',
                'is_active' => true,
                'late_tolerance_minutes' => 15,
                'early_leave_tolerance_minutes' => 10,
                'max_early_clock_in_minutes' => 60,
                'applies_national_holidays' => true,
            ]
        );
        $this->seedDays($shiftLaboran->id, [
            1 => ['start_time' => '08:00:00', 'end_time' => '15:30:00', 'break_start' => '12:00:00', 'break_end' => '13:00:00', 'is_day_off' => false],
            2 => ['start_time' => '08:00:00', 'end_time' => '15:30:00', 'break_start' => '12:00:00', 'break_end' => '13:00:00', 'is_day_off' => false],
            3 => ['start_time' => '08:00:00', 'end_time' => '15:30:00', 'break_start' => '12:00:00', 'break_end' => '13:00:00', 'is_day_off' => false],
            4 => ['start_time' => '08:00:00', 'end_time' => '15:30:00', 'break_start' => '12:00:00', 'break_end' => '13:00:00', 'is_day_off' => false],
            5 => ['start_time' => '08:00:00', 'end_time' => '15:30:00', 'break_start' => '11:30:00', 'break_end' => '13:00:00', 'is_day_off' => false],
            6 => ['start_time' => '08:00:00', 'end_time' => '13:00:00', 'is_day_off' => false],
            0 => ['start_time' => null, 'end_time' => null, 'is_day_off' => true],
        ]);

        // 7. Master Mesin Fingerprint Default
        FingerprintDevice::updateOrCreate(
            ['device_code' => 'FP-UTAMA-REKTORAT'],
            [
                'device_name' => 'Terminal Fingerprint Rektorat Lt. 1',
                'ip_address' => '192.168.1.201',
                'port' => 4370,
                'location' => 'Lobi Utama Gedung Rektorat',
                'office_location_id' => $office->id,
                'device_model' => 'ZKTeco ProCapture-X',
                'is_active' => true,
                'last_status' => 'online',
                'last_sync_at' => now(),
            ]
        );

        FingerprintDevice::updateOrCreate(
            ['device_code' => 'FP-LAB-KOMPUTER'],
            [
                'device_name' => 'Terminal Biometrik Lab Terpadu Lt. 2',
                'ip_address' => '192.168.1.202',
                'port' => 4370,
                'location' => 'Gedung Laboratorium Terpadu Lt. 2',
                'office_location_id' => $office->id,
                'device_model' => 'Solution X105-C Biometric',
                'is_active' => true,
                'last_status' => 'online',
                'last_sync_at' => now(),
            ]
        );
    }

    private function seedDays(int $shiftTemplateId, array $days): void
    {
        foreach ($days as $dayOfWeek => $attr) {
            ShiftScheduleDay::updateOrCreate(
                ['shift_template_id' => $shiftTemplateId, 'day_of_week' => $dayOfWeek],
                array_merge($attr, ['day_of_week' => $dayOfWeek, 'shift_template_id' => $shiftTemplateId])
            );
        }
    }
}
