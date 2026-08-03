<?php

namespace App\Services\Spmb;

use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\HasilSeleksi;
use Illuminate\Support\Facades\DB;
use App\Events\Spmb\MahasiswaDiterima;
use Illuminate\Validation\ValidationException;

class SpmbPendaftaranService
{
    /**
     * Submit pendaftaran dari draft ke submitted
     */
    public function submitPendaftaran(PendaftaranCalonMhs $pendaftaran): void
    {
        if ($pendaftaran->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Pendaftaran tidak dalam status draft.'
            ]);
        }

        // TODO: Validasi kelengkapan biodata dan dokumen wajib
        
        $pendaftaran->update(['status' => 'submitted']);
    }

    /**
     * Verifikasi administrasi oleh Admin
     */
    public function verifikasiAdministrasi(PendaftaranCalonMhs $pendaftaran, bool $isLulus, string $catatan = null, int $adminId): void
    {
        if ($pendaftaran->status !== 'submitted') {
            throw ValidationException::withMessages([
                'status' => 'Pendaftaran belum disubmit oleh calon mahasiswa.'
            ]);
        }

        $pendaftaran->update([
            'status' => $isLulus ? 'lulus_administrasi' : 'gagal_administrasi',
            'catatan_verifikasi' => $catatan,
            'diverifikasi_oleh' => $adminId,
            'diverifikasi_at' => now(),
        ]);
    }

    /**
     * Menetapkan hasil seleksi kelulusan
     */
    public function tetapkanKelulusan(PendaftaranCalonMhs $pendaftaran, array $dataSeleksi): HasilSeleksi
    {
        return DB::transaction(function () use ($pendaftaran, $dataSeleksi) {
            $hasil = HasilSeleksi::updateOrCreate(
                ['pendaftaran_id' => $pendaftaran->id],
                [
                    'program_studi_diterima_id' => $dataSeleksi['program_studi_diterima_id'] ?? null,
                    'nilai_total' => $dataSeleksi['nilai_total'],
                    'peringkat' => $dataSeleksi['peringkat'] ?? null,
                    'status' => $dataSeleksi['status'], // 'lulus', 'tidak_lulus', 'cadangan'
                    'catatan' => $dataSeleksi['catatan'] ?? null,
                    'diumumkan_at' => $dataSeleksi['diumumkan_at'] ?? now(),
                ]
            );

            // Jika status lulus, trigger event untuk konversi mahasiswa dan pembuatan akun (async)
            if ($hasil->status === 'lulus') {
                event(new MahasiswaDiterima($pendaftaran));
            }

            return $hasil;
        });
    }
}
