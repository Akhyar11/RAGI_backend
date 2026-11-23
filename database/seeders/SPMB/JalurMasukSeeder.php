<?php

namespace Database\Seeders\SPMB;

use App\Models\Spmb\JalurMasuk;
use Illuminate\Database\Seeder;

class JalurMasukSeeder extends Seeder
{
    public function run(): void
    {
        $jalurMasuk = [
            ['kode' => 'reguler', 'nama' => 'Reguler'],
            ['kode' => 'transfer', 'nama' => 'Transfer / Pindahan'],
            ['kode' => 'beasiswa', 'nama' => 'Beasiswa'],
            ['kode' => 'internasional', 'nama' => 'Internasional'],
            ['kode' => 'rpla', 'nama' => 'RPL / Rekognisi Pembelajaran Lampau'],
        ];

        foreach ($jalurMasuk as $jalur) {
            JalurMasuk::updateOrCreate(
                ['kode' => $jalur['kode']],
                [
                    'nama' => $jalur['nama'],
                    'is_active' => true,
                ],
            );
        }
    }
}