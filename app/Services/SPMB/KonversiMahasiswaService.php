<?php

namespace App\Services\SPMB;

use App\Models\PendaftaranCalonMhs;
use App\Models\KonversiMahasiswa;
use App\Models\Mahasiswa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class KonversiMahasiswaService
{
    /**
     * Mengkonversi Calon Mahasiswa yang lulus menjadi Mahasiswa aktif (SIAKAD)
     */
    public function konversi(PendaftaranCalonMhs $pendaftaran, User $adminSpmb): KonversiMahasiswa
    {
        return DB::transaction(function () use ($pendaftaran, $adminSpmb) {
            // 1. Validasi: Pastikan pendaftaran sudah punya hasil lulus
            $hasilSeleksi = $pendaftaran->hasilSeleksi;
            if (!$hasilSeleksi || $hasilSeleksi->status !== 'lulus') {
                throw new Exception("Hanya pendaftar yang berstatus 'lulus' yang dapat dikonversi.");
            }

            // 2. Generate NIM berdasarkan aturan kampus (Tahun + Kode Prodi + Urut)
            // Mocking rule generate NIM
            $tahun = date('y'); // 2 digit tahun
            $kodeProdi = str_pad($hasilSeleksi->program_studi_diterima_id, 3, '0', STR_PAD_LEFT);
            $urut = mt_rand(100, 999);
            $nimBaru = $tahun . $kodeProdi . $urut;

            // 3. Masukkan ke tabel `mahasiswa` (SIAKAD Core)
            $mahasiswa = Mahasiswa::create([
                'user_id' => $pendaftaran->user_id,
                'program_studi_id' => $hasilSeleksi->program_studi_diterima_id,
                'nim' => $nimBaru,
                'nama_lengkap' => $pendaftaran->nama_lengkap,
                'nik' => $pendaftaran->nik,
                'tanggal_lahir' => $pendaftaran->tanggal_lahir,
                'tempat_lahir' => $pendaftaran->tempat_lahir,
                'jenis_kelamin' => $pendaftaran->jenis_kelamin,
                'agama' => null, // Belum diisi di SPMB
                'alamat' => $pendaftaran->alamat,
                'angkatan' => date('Y'),
                'tanggal_masuk' => now(),
                'status' => 'aktif',
            ]);

            // 4. Update tabel `users` (IAM)
            $user = $pendaftaran->user;
            if ($user) {
                // Ubah role user menjadi mahasiswa
                $user->update([
                    'user_type' => 'mahasiswa'
                ]);
            }

            // 5. Catat ke log konversi_mahasiswa
            $konversi = KonversiMahasiswa::create([
                'pendaftaran_id' => $pendaftaran->id,
                'mahasiswa_id' => $mahasiswa->id,
                'nim_diterbitkan' => $nimBaru,
                'diproses_oleh' => $adminSpmb->id,
            ]);

            // Update PendaftaranCalonMhs status
            $pendaftaran->update(['status' => 'mahasiswa_baru']);

            return $konversi;
        });
    }
}
