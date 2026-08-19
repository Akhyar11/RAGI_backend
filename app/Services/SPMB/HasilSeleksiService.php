<?php

namespace App\Services\SPMB;

use App\Models\PendaftaranCalonMhs;
use App\Models\HasilSeleksi;
use App\Notifications\PengumumanLulusNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class HasilSeleksiService
{
    /**
     * Menetapkan hasil seleksi kelulusan untuk seorang calon mahasiswa
     */
    public function tetapkanKelulusan(PendaftaranCalonMhs $pendaftaran, array $data): HasilSeleksi
    {
        return DB::transaction(function () use ($pendaftaran, $data) {
            $hasilSeleksi = HasilSeleksi::updateOrCreate(
                ['pendaftaran_id' => $pendaftaran->id],
                [
                    'program_studi_diterima_id' => $data['program_studi_diterima_id'] ?? null,
                    'nilai_total' => $data['nilai_total'] ?? 0,
                    'peringkat' => $data['peringkat'] ?? null,
                    'status' => $data['status'], // 'lulus', 'tidak_lulus', 'cadangan'
                    'catatan' => $data['catatan'] ?? null,
                    'diumumkan_at' => $data['is_published'] ? now() : null,
                ]
            );

            // Jika langsung diumumkan, kirim email
            if ($hasilSeleksi->diumumkan_at) {
                $this->kirimNotifikasiPengumuman($hasilSeleksi);
            }

            return $hasilSeleksi;
        });
    }

    /**
     * Mengirim notifikasi pengumuman kelulusan
     */
    public function kirimNotifikasiPengumuman(HasilSeleksi $hasilSeleksi): void
    {
        try {
            $user = $hasilSeleksi->pendaftaran->user;
            if ($user) {
                $user->notify(new PengumumanLulusNotification($hasilSeleksi));
            }
        } catch (Exception $e) {
            Log::error('Gagal mengirim email pengumuman kelulusan: ' . $e->getMessage());
        }
    }
}
