<?php

namespace App\Services\SPMB;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UjianSpmbService
{
    /**
     * Set jadwal ujian untuk pendaftar
     */
    public function tetapkanJadwalUjian($pendaftaranId, array $data)
    {
        DB::beginTransaction();
        try {
            // Asumsikan tabel spmb_jadwal_ujian atau serupa ada.
            // Saat ini kita update tabel yang berkaitan. 
            // Mock implementasi untuk service ujian SPMB
            
            DB::table('spmb_pendaftaran')
                ->where('id', $pendaftaranId)
                ->update([
                    'jadwal_ujian_id' => $data['jadwal_id'] ?? null,
                    'lokasi_ujian' => $data['lokasi'] ?? null,
                    'waktu_ujian' => $data['waktu'] ?? null,
                    'updated_at' => now()
                ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to set jadwal ujian: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Input nilai ujian pendaftar
     */
    public function inputNilaiUjian($pendaftaranId, array $data)
    {
        DB::beginTransaction();
        try {
            // Mock: update ke tabel spmb_hasil_seleksi
            $existing = DB::table('spmb_hasil_seleksi')->where('pendaftaran_id', $pendaftaranId)->first();
            
            if ($existing) {
                DB::table('spmb_hasil_seleksi')
                    ->where('pendaftaran_id', $pendaftaranId)
                    ->update([
                        'nilai_tulis' => $data['nilai_tulis'] ?? null,
                        'nilai_wawancara' => $data['nilai_wawancara'] ?? null,
                        'nilai_total' => $data['nilai_total'] ?? null,
                        'updated_at' => now()
                    ]);
            } else {
                DB::table('spmb_hasil_seleksi')->insert([
                    'pendaftaran_id' => $pendaftaranId,
                    'nilai_tulis' => $data['nilai_tulis'] ?? null,
                    'nilai_wawancara' => $data['nilai_wawancara'] ?? null,
                    'nilai_total' => $data['nilai_total'] ?? null,
                    'status' => 'belum_ditetapkan',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to input nilai ujian: ' . $e->getMessage());
            throw $e;
        }
    }
}
