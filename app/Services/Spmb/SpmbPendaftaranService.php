<?php

namespace App\Services\Spmb;

use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\HasilSeleksi;
use Illuminate\Support\Facades\DB;
use App\Events\Spmb\MahasiswaDiterima;
use Illuminate\Validation\ValidationException;

use App\Models\Spmb\PendaftaranAlur;
use App\Models\Spmb\MasterTipeJalurAlur;

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

    public function generateProgressAlur(PendaftaranCalonMhs $pendaftaran): void
    {
        // Pastikan tidak menduplikasi jika sudah ada
        if (PendaftaranAlur::where('pendaftaran_id', $pendaftaran->id)->exists()) {
            return;
        }

        if (!$pendaftaran->master_tipe_jalur_id) {
            return;
        }

        $masterAlurs = MasterTipeJalurAlur::where('master_tipe_jalur_id', $pendaftaran->master_tipe_jalur_id)
            ->orderBy('urutan', 'asc')
            ->get();

        $alursData = [];
        foreach ($masterAlurs as $index => $masterAlur) {
            $alursData[] = [
                'pendaftaran_id' => $pendaftaran->id,
                'master_tipe_jalur_alur_id' => $masterAlur->id,
                'status' => $index === 0 ? PendaftaranAlur::STATUS_IN_PROGRESS : PendaftaranAlur::STATUS_PENDING,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($alursData)) {
            PendaftaranAlur::insert($alursData);
        }
    }

    /**
     * Menetapkan hasil seleksi kelulusan
     */
    public function tetapkanKelulusan(PendaftaranCalonMhs $pendaftaran, array $dataSeleksi): HasilSeleksi
    {
        return DB::transaction(function () use ($pendaftaran, $dataSeleksi) {
            
            // Cek Kuota jika meluluskan
            if ($dataSeleksi['status'] === 'lulus' && !empty($dataSeleksi['program_studi_diterima_id'])) {
                $gelombang = $pendaftaran->gelombangPenerimaan;
                $kuotaProdi = \App\Models\Spmb\SpmbKuotaProdi::where('tahun_akademik_id', $gelombang->tahun_akademik_id ?? 1)
                    ->where('program_studi_id', $dataSeleksi['program_studi_diterima_id'])
                    ->first();
                
                if ($kuotaProdi && $kuotaProdi->kuota_terisi >= $kuotaProdi->kuota_total) {
                    throw ValidationException::withMessages([
                        'status' => 'Kuota untuk Program Studi ini sudah penuh (' . $kuotaProdi->kuota_total . ').'
                    ]);
                }

                if ($kuotaProdi) {
                    $kuotaProdi->increment('kuota_terisi');
                }
            }

            $hasil = HasilSeleksi::updateOrCreate(
                ['pendaftaran_id' => $pendaftaran->id],
                [
                    'program_studi_diterima_id' => $dataSeleksi['program_studi_diterima_id'] ?? null,
                    'nilai_total' => $dataSeleksi['nilai_total'],
                    'peringkat' => $dataSeleksi['peringkat'] ?? null,
                    'status' => $dataSeleksi['status'], // 'lulus', 'tidak_lulus', 'cadangan'
                    'status_daftar_ulang' => $dataSeleksi['status'] === 'lulus' ? 'belum' : 'belum',
                    'catatan' => $dataSeleksi['catatan'] ?? null,
                    'diumumkan_at' => $dataSeleksi['diumumkan_at'] ?? now(),
                ]
            );

            return $hasil;
        });
    }
}
