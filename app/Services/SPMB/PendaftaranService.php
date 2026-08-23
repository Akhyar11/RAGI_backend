<?php

namespace App\Services\SPMB;

use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\User;
use App\Notifications\PendaftaranSuksesNotification;
use App\Services\SIKEU\PembayaranSpmbService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class PendaftaranService
{
    public function __construct(
        private PembayaranSpmbService $pembayaranService
    ) {}

    /**
     * Membuat pendaftaran baru
     */
    public function create(array $data, User $user): PendaftaranCalonMhs
    {
        return DB::transaction(function () use ($data, $user) {
            // 1. Generate Nomor Pendaftaran
            $noPendaftaran = $this->generateNomorPendaftaran($data['gelombang_id']);

            // 2. Simpan Data Pendaftaran
            $pendaftaran = PendaftaranCalonMhs::create([
                'user_id' => $user->id,
                'gelombang_id' => $data['gelombang_id'],
                'program_studi_id' => $data['program_studi_id'],
                'program_studi_pilihan2_id' => $data['program_studi_pilihan2_id'] ?? null,
                'no_pendaftaran' => $noPendaftaran,
                'nama_lengkap' => $data['nama_lengkap'],
                'nik' => $data['nik'],
                'tanggal_lahir' => $data['tanggal_lahir'] ?? null,
                'tempat_lahir' => $data['tempat_lahir'] ?? null,
                'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
                'asal_sekolah' => $data['asal_sekolah'] ?? null,
                'status' => 'draft',
            ]);

            // 3. Simpan Riwayat Rapor (jika ada jalur prestasi)
            if (isset($data['riwayat_rapor']) && is_array($data['riwayat_rapor'])) {
                $pendaftaran->riwayatRapor()->createMany($data['riwayat_rapor']);
            }

            // 4. Generate Tagihan VA melalui PembayaranSpmbService
            // Kita asumsikan tagihan di-generate setelah pendaftaran dibuat
            $this->pembayaranService->generateTagihanPendaftaran($pendaftaran);

            // 5. Kirim Notifikasi via EMAIL
            try {
                // Notifiable must be the user email. Since PendaftaranCalonMhs belongs to User
                $user->notify(new PendaftaranSuksesNotification($pendaftaran));
            } catch (Exception $e) {
                Log::error('Gagal mengirim email pendaftaran: ' . $e->getMessage());
                // Tidak menggagalkan transaksi jika email gagal
            }

            return $pendaftaran;
        });
    }

    /**
     * Memvalidasi apakah syarat prodi terpenuhi
     */
    public function validateSyaratProdi(int $prodiId, array $data): bool
    {
        // Contoh logika: Cek ke tabel spmb_syarat_prodi
        // Untuk saat ini di-mocking true
        return true;
    }

    /**
     * Update status verifikasi
     */
    public function updateVerifikasi(PendaftaranCalonMhs $pendaftaran, array $data, User $admin): PendaftaranCalonMhs
    {
        return DB::transaction(function () use ($pendaftaran, $data, $admin) {
            $pendaftaran->update([
                'status' => $data['status'], // e.g. 'verified', 'gagal_administrasi'
                'catatan_verifikasi' => $data['catatan'] ?? null,
                'diverifikasi_oleh' => $admin->id,
                'diverifikasi_at' => now(),
            ]);

            return $pendaftaran->fresh();
        });
    }

    /**
     * Helper untuk generate nomor
     */
    private function generateNomorPendaftaran(int $gelombangId): string
    {
        $prefix = date('Y') . sprintf("%02d", $gelombangId);
        $random = mt_rand(1000, 9999);
        return 'PMB-' . $prefix . '-' . $random;
    }
}
