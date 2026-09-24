<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Skala nilai mutu standar universitas (berlaku umum, program_studi_id null).
     * Wajib ada agar konversi nilai KHS/Transkrip berfungsi di instalasi baru.
     * Skala khusus prodi dikelola via UI oleh BAAK.
     */
    public function up(): void
    {
        if (!Schema::hasTable('siakad_skala_nilai')) {
            return;
        }

        $now = now();
        $standards = [
            ['nilai_huruf' => 'A', 'bobot_indeks' => 4.00, 'batas_bawah' => 85.00, 'batas_atas' => 100.00, 'is_lulus' => true, 'keterangan' => 'Sangat Baik (Istimewa)'],
            ['nilai_huruf' => 'A-', 'bobot_indeks' => 3.75, 'batas_bawah' => 80.00, 'batas_atas' => 84.99, 'is_lulus' => true, 'keterangan' => 'Sangat Baik'],
            ['nilai_huruf' => 'B+', 'bobot_indeks' => 3.25, 'batas_bawah' => 75.00, 'batas_atas' => 79.99, 'is_lulus' => true, 'keterangan' => 'Baik Sekali'],
            ['nilai_huruf' => 'B', 'bobot_indeks' => 3.00, 'batas_bawah' => 70.00, 'batas_atas' => 74.99, 'is_lulus' => true, 'keterangan' => 'Baik'],
            ['nilai_huruf' => 'B-', 'bobot_indeks' => 2.75, 'batas_bawah' => 65.00, 'batas_atas' => 69.99, 'is_lulus' => true, 'keterangan' => 'Cukup Baik'],
            ['nilai_huruf' => 'C+', 'bobot_indeks' => 2.25, 'batas_bawah' => 60.00, 'batas_atas' => 64.99, 'is_lulus' => true, 'keterangan' => 'Lebih Dari Cukup'],
            ['nilai_huruf' => 'C', 'bobot_indeks' => 2.00, 'batas_bawah' => 55.00, 'batas_atas' => 59.99, 'is_lulus' => true, 'keterangan' => 'Cukup'],
            ['nilai_huruf' => 'D', 'bobot_indeks' => 1.00, 'batas_bawah' => 40.00, 'batas_atas' => 54.99, 'is_lulus' => false, 'keterangan' => 'Kurang (Tidak Lulus)'],
            ['nilai_huruf' => 'E', 'bobot_indeks' => 0.00, 'batas_bawah' => 0.00, 'batas_atas' => 39.99, 'is_lulus' => false, 'keterangan' => 'Gagal'],
        ];

        foreach ($standards as $row) {
            DB::table('siakad_skala_nilai')->updateOrInsert(
                ['program_studi_id' => null, 'nilai_huruf' => $row['nilai_huruf']],
                array_merge($row, ['is_active' => true, 'created_at' => $now, 'updated_at' => $now])
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('siakad_skala_nilai')) {
            return;
        }

        DB::table('siakad_skala_nilai')
            ->whereNull('program_studi_id')
            ->whereIn('nilai_huruf', ['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'D', 'E'])
            ->delete();
    }
};
