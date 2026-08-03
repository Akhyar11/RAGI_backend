<?php

namespace App\Services\Spmb;

use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\KonversiMahasiswa;
use Illuminate\Support\Facades\DB;
use App\Models\User;
// use App\Models\Siakad\Mahasiswa;

class SpmbKonversiService
{
    /**
     * Konversi pendaftar yang lulus menjadi Mahasiswa (Generate NIM dan update Role).
     * Service ini akan dipanggil oleh event listener.
     */
    public function prosesKonversi(PendaftaranCalonMhs $pendaftaran, int $diprosesOlehId = null): KonversiMahasiswa
    {
        return DB::transaction(function () use ($pendaftaran, $diprosesOlehId) {
            // 1. Generate NIM
            $nim = $this->generateNIM($pendaftaran);

            // 2. Insert ke tabel siakad.mahasiswa (Placeholder untuk integrasi SIAKAD)
            /*
            $mahasiswa = Mahasiswa::create([
                'user_id' => $pendaftaran->user_id,
                'program_studi_id' => $pendaftaran->hasilSeleksi->program_studi_diterima_id,
                'nim' => $nim,
                'nama_lengkap' => $pendaftaran->nama_lengkap,
                'nik' => $pendaftaran->nik,
                'tanggal_lahir' => $pendaftaran->tanggal_lahir,
                'tempat_lahir' => $pendaftaran->tempat_lahir,
                'jenis_kelamin' => $pendaftaran->jenis_kelamin,
                'alamat' => $pendaftaran->alamat,
                'angkatan' => date('Y'),
                'status' => 'aktif',
            ]);
            */
            // Mocking Mahasiswa ID untuk sementara
            $mahasiswaId = 1;

            // 3. Catat di tabel konversi_mahasiswa
            $konversi = KonversiMahasiswa::create([
                'pendaftaran_id' => $pendaftaran->id,
                'mahasiswa_id' => $mahasiswaId,
                'nim_diterbitkan' => $nim,
                'diproses_oleh' => $diprosesOlehId,
            ]);

            // 4. Update Role User di IAM dari 'calon_mhs' menjadi 'mahasiswa'
            $user = User::find($pendaftaran->user_id);
            if ($user) {
                // Hapus role calon_mhs (opsional, tergantung kebijakan, misal role_id 5)
                // $user->roles()->detach(5); 
                // Tambah role mahasiswa (misal role_id 3)
                // $user->roles()->attach(3);
            }

            return $konversi;
        });
    }

    /**
     * Algoritma pembuatan NIM berdasarkan tahun, kode fakultas, prodi, dan nomor urut.
     */
    private function generateNIM(PendaftaranCalonMhs $pendaftaran): string
    {
        $tahun = date('y'); // 2 digit tahun
        $kodeProdi = str_pad($pendaftaran->hasilSeleksi->program_studi_diterima_id ?? '00', 2, '0', STR_PAD_LEFT);
        
        // Cari nomor urut terakhir di tahun dan prodi ini
        // Simulasi auto-increment
        $urut = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

        return $tahun . $kodeProdi . $urut;
    }
}
