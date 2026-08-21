<?php

namespace Database\Seeders\IAM;

use Illuminate\Database\Seeder;
use App\Models\Module;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Module::updateOrCreate(
            ['code' => 'sso'],
            [
                'name' => 'IAM & Auth Center',
                'description' => 'Modul inti untuk Single Sign-On dan Manajemen Pengguna (IAM).',
                'primary_color' => '#3b82f6',
                'is_active' => true,
            ]
        );

        Module::updateOrCreate(
            ['code' => 'simpeg'],
            [
                'name' => 'SIMPEG (Kepegawaian)',
                'description' => 'Sistem Informasi Manajemen Kepegawaian Kampus.',
                'primary_color' => '#4f46e5',
                'is_active' => true,
            ]
        );

        Module::updateOrCreate(
            ['code' => 'sippm'],
            [
                'name' => 'SIPPM Kampus',
                'description' => 'Sistem Informasi Penelitian dan Pengabdian Masyarakat.',
                'primary_color' => '#0d9488',
                'is_active' => true,
            ]
        );

        Module::updateOrCreate(
            ['code' => 'spmb'],
            [
                'name' => 'SPMB (Penerimaan Mahasiswa)',
                'description' => 'Sistem Penerimaan Mahasiswa Baru Terpadu.',
                'primary_color' => '#e11d48',
                'is_active' => true,
            ]
        );

        Module::updateOrCreate(
            ['code' => 'sikeu'],
            [
                'name' => 'SIKEU Kampus',
                'description' => 'Sistem Informasi Keuangan & Akuntansi Kampus.',
                'primary_color' => '#059669',
                'is_active' => true,
            ]
        );

        Module::updateOrCreate(
            ['code' => 'sinapra'],
            [
                'name' => 'SINAPRA (Sarana & Prasarana)',
                'description' => 'Sistem Informasi Management Sarana, Prasarana, Aset, & Pengadaan Kampus.',
                'primary_color' => '#d97706',
                'is_active' => true,
            ]
        );

        Module::updateOrCreate(
            ['code' => 'siakad'],
            [
                'name' => 'SIAKAD (Akademik)',
                'description' => 'Sistem Informasi Akademik dan Perkuliahan Kampus.',
                'primary_color' => '#2563eb',
                'is_active' => true,
            ]
        );
    }
}
