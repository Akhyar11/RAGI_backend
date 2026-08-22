<?php

namespace App\Services\Spmb;

use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\KonversiMahasiswa;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Role;
use App\Models\Siakad\Mahasiswa;

class SpmbKonversiService
{
    /**
     * Konversi pendaftar yang lulus menjadi Mahasiswa (Generate NIM jika belum ada dan update Role).
     */
    public function prosesKonversi(PendaftaranCalonMhs $pendaftaran, int $diprosesOlehId = null): KonversiMahasiswa
    {
        return DB::transaction(function () use ($pendaftaran, $diprosesOlehId) {
            $angkatan = (int)date('Y');
            $prodiId = $pendaftaran->hasilSeleksi->program_studi_diterima_id ?? $pendaftaran->program_studi_id ?? 1;

            // 1. Gunakan NIM eksisting atau generate baru hanya jika belum ada
            $nim = $pendaftaran->nim;
            if (empty($nim)) {
                $nim = $this->generateNIM($angkatan, $prodiId);
                $pendaftaran->update(['nim' => $nim, 'status' => PendaftaranCalonMhs::STATUS_MAHASISWA_BARU]);
            }

            // 2. Insert / Update ke tabel siakad_mahasiswa
            $mahasiswa = Mahasiswa::where('nim', $nim)
                ->orWhere(function ($q) use ($pendaftaran) {
                    if (!empty($pendaftaran->nik)) {
                        $q->where('nik', $pendaftaran->nik);
                    }
                    if (!empty($pendaftaran->user_id)) {
                        $q->orWhere('user_id', $pendaftaran->user_id);
                    }
                })->first();

            if (!$mahasiswa) {
                $mahasiswa = Mahasiswa::create([
                    'user_id' => $pendaftaran->user_id,
                    'program_studi_id' => $prodiId,
                    'nim' => $nim,
                    'nama_lengkap' => $pendaftaran->nama_lengkap,
                    'nik' => $pendaftaran->nik,
                    'tanggal_lahir' => $pendaftaran->tanggal_lahir,
                    'tempat_lahir' => $pendaftaran->tempat_lahir,
                    'jenis_kelamin' => $pendaftaran->jenis_kelamin ?? 'L',
                    'alamat' => $pendaftaran->alamat,
                    'telepon' => $pendaftaran->no_hp,
                    'angkatan' => $angkatan,
                    'tanggal_masuk' => now()->toDateString(),
                    'status' => 'aktif',
                ]);
            } else {
                if (empty($mahasiswa->nim)) {
                    $mahasiswa->update(['nim' => $nim]);
                }
            }

            // 3. Catat di tabel konversi_mahasiswa
            $konversi = KonversiMahasiswa::updateOrCreate(
                ['pendaftaran_id' => $pendaftaran->id],
                [
                    'mahasiswa_id' => $mahasiswa->id,
                    'nim_diterbitkan' => $nim,
                    'diproses_oleh' => $diprosesOlehId,
                ]
            );

            // 4. Update Email Kampus dan Berikan role 'mahasiswa' ke User IAM jika user_id ada
            if ($pendaftaran->user_id) {
                $user = User::find($pendaftaran->user_id);
                if ($user) {
                    $mhsRole = Role::where('slug', 'mahasiswa')->first();
                    if ($mhsRole && !$user->roles->contains('id', $mhsRole->id)) {
                        $user->roles()->syncWithoutDetaching([$mhsRole->id]);
                    }

                    // Generate email kampus dan update username jika belum ada
                    if (empty($user->email_kampus)) {
                        $domainKampus = config('app.domain_kampus', 'student.campus.ac.id');
                        $firstName = strtolower(preg_replace('/[^a-zA-Z]/', '', explode(' ', trim($pendaftaran->nama_lengkap))[0]));
                        $emailKampus = $firstName . '.' . strtolower($nim) . '@' . $domainKampus;

                        // Pastikan email kampus unik
                        $counter = 1;
                        $baseEmail = $emailKampus;
                        while (User::where('email_kampus', $emailKampus)->exists()) {
                            $emailKampus = $firstName . $counter . '.' . strtolower($nim) . '@' . $domainKampus;
                            $counter++;
                        }

                        $user->update([
                            'email_kampus' => $emailKampus,
                            'username' => $nim,
                        ]);
                    }
                }
            }

            return $konversi;
        });
    }

    /**
     * Algoritma pembuatan NIM: {2-digit tahun}{2-digit kode prodi}{4-digit sequential}.
     */
    public function generateNIM(int $angkatan, int $prodiId): string
    {
        $prefix = substr((string)$angkatan, -2) . str_pad((string)$prodiId, 2, '0', STR_PAD_LEFT);
        
        $lastMhs = Mahasiswa::where('nim', 'like', "{$prefix}%")
            ->orderBy('nim', 'desc')
            ->first();

        $nextSeq = 1;
        if ($lastMhs && preg_match('/^' . preg_quote($prefix, '/') . '(\d{4})$/', $lastMhs->nim, $matches)) {
            $nextSeq = ((int)$matches[1]) + 1;
        }

        return $prefix . str_pad((string)$nextSeq, 4, '0', STR_PAD_LEFT);
    }
}
