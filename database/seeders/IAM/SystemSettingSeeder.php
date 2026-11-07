<?php

namespace Database\Seeders\IAM;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        SystemSetting::firstOrCreate(
            ['key' => 'third_party_registration_role'],
            [
                'value' => '',
                'description' => 'Slug role otomatis untuk akun pihak ketiga SPMB',
            ],
        );
    }
}
