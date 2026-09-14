<?php

namespace Database\Seeders;

use App\Models\NationalHoliday;
use Illuminate\Database\Seeder;

class NationalHolidaySeeder extends Seeder
{
    /**
     * Seed daftar Hari Libur Nasional & Cuti Bersama resmi Indonesia (2025, 2026, 2027).
     */
    public function run(): void
    {
        $holidays = [
            // Tahun 2025
            ['holiday_date' => '2025-01-01', 'name' => 'Tahun Baru 2025 Masehi', 'is_mass_leave' => false],
            ['holiday_date' => '2025-01-27', 'name' => 'Isra Mi\'raj Nabi Muhammad SAW', 'is_mass_leave' => false],
            ['holiday_date' => '2025-01-29', 'name' => 'Tahun Baru Imlek 2576 Kongzili', 'is_mass_leave' => false],
            ['holiday_date' => '2025-03-29', 'name' => 'Hari Suci Nyepi Tahun Baru Saka 1947', 'is_mass_leave' => false],
            ['holiday_date' => '2025-03-31', 'name' => 'Hari Raya Idul Fitri 1446 H', 'is_mass_leave' => false],
            ['holiday_date' => '2025-04-01', 'name' => 'Hari Raya Idul Fitri 1446 H', 'is_mass_leave' => false],
            ['holiday_date' => '2025-04-02', 'name' => 'Cuti Bersama Idul Fitri 1446 H', 'is_mass_leave' => true],
            ['holiday_date' => '2025-04-03', 'name' => 'Cuti Bersama Idul Fitri 1446 H', 'is_mass_leave' => true],
            ['holiday_date' => '2025-04-04', 'name' => 'Cuti Bersama Idul Fitri 1446 H', 'is_mass_leave' => true],
            ['holiday_date' => '2025-04-07', 'name' => 'Cuti Bersama Idul Fitri 1446 H', 'is_mass_leave' => true],
            ['holiday_date' => '2025-04-18', 'name' => 'Wafat Yesus Kristus', 'is_mass_leave' => false],
            ['holiday_date' => '2025-04-20', 'name' => 'Hari Paskah', 'is_mass_leave' => false],
            ['holiday_date' => '2025-05-01', 'name' => 'Hari Buruh Internasional', 'is_mass_leave' => false],
            ['holiday_date' => '2025-05-12', 'name' => 'Hari Raya Waisak 2569 BE', 'is_mass_leave' => false],
            ['holiday_date' => '2025-05-29', 'name' => 'Kenaikan Yesus Kristus', 'is_mass_leave' => false],
            ['holiday_date' => '2025-06-01', 'name' => 'Hari Lahir Pancasila', 'is_mass_leave' => false],
            ['holiday_date' => '2025-06-06', 'name' => 'Hari Raya Idul Adha 1446 H', 'is_mass_leave' => false],
            ['holiday_date' => '2025-06-27', 'name' => 'Tahun Baru Islam 1447 H', 'is_mass_leave' => false],
            ['holiday_date' => '2025-08-17', 'name' => 'Hari Kemerdekaan RI ke-80', 'is_mass_leave' => false],
            ['holiday_date' => '2025-09-05', 'name' => 'Maulid Nabi Muhammad SAW', 'is_mass_leave' => false],
            ['holiday_date' => '2025-12-25', 'name' => 'Hari Raya Natal', 'is_mass_leave' => false],
            ['holiday_date' => '2025-12-26', 'name' => 'Cuti Bersama Hari Raya Natal', 'is_mass_leave' => true],

            // Tahun 2026
            ['holiday_date' => '2026-01-01', 'name' => 'Tahun Baru 2026 Masehi', 'is_mass_leave' => false],
            ['holiday_date' => '2026-01-16', 'name' => 'Isra Mi\'raj Nabi Muhammad SAW', 'is_mass_leave' => false],
            ['holiday_date' => '2026-02-17', 'name' => 'Tahun Baru Imlek 2577 Kongzili', 'is_mass_leave' => false],
            ['holiday_date' => '2026-03-20', 'name' => 'Hari Raya Idul Fitri 1447 H', 'is_mass_leave' => false],
            ['holiday_date' => '2026-03-21', 'name' => 'Hari Raya Idul Fitri 1447 H', 'is_mass_leave' => false],
            ['holiday_date' => '2026-03-23', 'name' => 'Cuti Bersama Idul Fitri 1447 H', 'is_mass_leave' => true],
            ['holiday_date' => '2026-03-24', 'name' => 'Cuti Bersama Idul Fitri 1447 H', 'is_mass_leave' => true],
            ['holiday_date' => '2026-03-19', 'name' => 'Hari Suci Nyepi Saka 1948', 'is_mass_leave' => false],
            ['holiday_date' => '2026-04-03', 'name' => 'Wafat Yesus Kristus', 'is_mass_leave' => false],
            ['holiday_date' => '2026-05-01', 'name' => 'Hari Buruh Internasional', 'is_mass_leave' => false],
            ['holiday_date' => '2026-05-14', 'name' => 'Kenaikan Yesus Kristus', 'is_mass_leave' => false],
            ['holiday_date' => '2026-05-27', 'name' => 'Hari Raya Idul Adha 1447 H', 'is_mass_leave' => false],
            ['holiday_date' => '2026-05-31', 'name' => 'Hari Raya Waisak 2570 BE', 'is_mass_leave' => false],
            ['holiday_date' => '2026-06-01', 'name' => 'Hari Lahir Pancasila', 'is_mass_leave' => false],
            ['holiday_date' => '2026-06-16', 'name' => 'Tahun Baru Islam 1448 H', 'is_mass_leave' => false],
            ['holiday_date' => '2026-08-17', 'name' => 'Hari Kemerdekaan RI ke-81', 'is_mass_leave' => false],
            ['holiday_date' => '2026-08-25', 'name' => 'Maulid Nabi Muhammad SAW', 'is_mass_leave' => false],
            ['holiday_date' => '2026-12-25', 'name' => 'Hari Raya Natal', 'is_mass_leave' => false],
            ['holiday_date' => '2026-12-26', 'name' => 'Cuti Bersama Hari Raya Natal', 'is_mass_leave' => true],

            // Tahun 2027
            ['holiday_date' => '2027-01-01', 'name' => 'Tahun Baru 2027 Masehi', 'is_mass_leave' => false],
            ['holiday_date' => '2027-01-05', 'name' => 'Isra Mi\'raj Nabi Muhammad SAW', 'is_mass_leave' => false],
            ['holiday_date' => '2027-02-06', 'name' => 'Tahun Baru Imlek 2578 Kongzili', 'is_mass_leave' => false],
            ['holiday_date' => '2027-03-09', 'name' => 'Hari Raya Idul Fitri 1448 H', 'is_mass_leave' => false],
            ['holiday_date' => '2027-03-10', 'name' => 'Hari Raya Idul Fitri 1448 H', 'is_mass_leave' => false],
            ['holiday_date' => '2027-03-11', 'name' => 'Cuti Bersama Idul Fitri 1448 H', 'is_mass_leave' => true],
            ['holiday_date' => '2027-03-12', 'name' => 'Cuti Bersama Idul Fitri 1448 H', 'is_mass_leave' => true],
            ['holiday_date' => '2027-03-26', 'name' => 'Wafat Yesus Kristus', 'is_mass_leave' => false],
            ['holiday_date' => '2027-05-01', 'name' => 'Hari Buruh Internasional', 'is_mass_leave' => false],
            ['holiday_date' => '2027-05-06', 'name' => 'Kenaikan Yesus Kristus', 'is_mass_leave' => false],
            ['holiday_date' => '2027-05-16', 'name' => 'Hari Raya Idul Adha 1448 H', 'is_mass_leave' => false],
            ['holiday_date' => '2027-05-20', 'name' => 'Hari Raya Waisak 2571 BE', 'is_mass_leave' => false],
            ['holiday_date' => '2027-06-01', 'name' => 'Hari Lahir Pancasila', 'is_mass_leave' => false],
            ['holiday_date' => '2027-06-06', 'name' => 'Tahun Baru Islam 1449 H', 'is_mass_leave' => false],
            ['holiday_date' => '2027-08-15', 'name' => 'Maulid Nabi Muhammad SAW', 'is_mass_leave' => false],
            ['holiday_date' => '2027-08-17', 'name' => 'Hari Kemerdekaan RI ke-82', 'is_mass_leave' => false],
            ['holiday_date' => '2027-12-25', 'name' => 'Hari Raya Natal', 'is_mass_leave' => false],
        ];

        foreach ($holidays as $holiday) {
            NationalHoliday::updateOrCreate(
                ['holiday_date' => $holiday['holiday_date']],
                $holiday
            );
        }
    }
}
