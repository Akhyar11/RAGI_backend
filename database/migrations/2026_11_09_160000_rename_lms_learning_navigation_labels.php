<?php

use App\Models\Menu;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('core_menus')) {
            return;
        }

        Menu::where('module', 'lms')
            ->where('url', '#pembelajaran_lms')
            ->update(['name' => 'PEMBELAJARAN']);

        $labels = [
            '/lms' => 'Kelas',
            '/lms/pertemuan' => 'Agenda Pertemuan',
            '/lms/forum' => 'Diskusi',
            '/lms/notifikasi' => 'Notifikasi',
        ];

        foreach ($labels as $url => $name) {
            Menu::where('module', 'lms')
                ->where('url', $url)
                ->update(['name' => $name]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('core_menus')) {
            return;
        }

        Menu::where('module', 'lms')
            ->where('url', '#pembelajaran_lms')
            ->update(['name' => 'PEMBELAJARAN & PERKULIAHAN']);

        $labels = [
            '/lms' => 'Kelas Perkuliahan',
            '/lms/pertemuan' => 'Pertemuan',
            '/lms/forum' => 'Forum Diskusi',
            '/lms/notifikasi' => 'Notifikasi LMS',
        ];

        foreach ($labels as $url => $name) {
            Menu::where('module', 'lms')
                ->where('url', $url)
                ->update(['name' => $name]);
        }
    }
};
