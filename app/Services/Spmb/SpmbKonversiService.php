<?php

namespace App\Services\Spmb;

use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\KonversiMahasiswa;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Role;
use App\Models\Siakad\Mahasiswa;
use App\Services\IAM\GoogleWorkspaceService;
use Illuminate\Support\Facades\Log;

class SpmbKonversiService
{
    /**
     * Konversi pendaftar yang lulus menjadi Mahasiswa (Generate NIM jika belum ada dan update Role).
     */
    public function prosesKonversi(PendaftaranCalonMhs $pendaftaran, int $diprosesOlehId = null): KonversiMahasiswa
    {
        // Karena ada call eksternal (Google API), kita pisahkan dari DB Transaction jika tidak ingin transaction menggantung
        // Namun demi konsistensi data internal, DB Transaction dijalankan terlebih dahulu
        $konversi = DB::transaction(function () use ($pendaftaran, $diprosesOlehId, &$userToUpdateEmail, &$nimGenerate) {
            $angkatan = (int)date('Y');
            $prodiId = $pendaftaran->hasilSeleksi->program_studi_diterima_id ?? $pendaftaran->program_studi_id ?? 1;

            // 1. Gunakan NIM eksisting atau generate baru hanya jika belum ada
            $nim = $pendaftaran->nim;
            if (empty($nim)) {
                $nim = $this->generateNIM($angkatan, $prodiId);
                $pendaftaran->update(['nim' => $nim, 'status' => PendaftaranCalonMhs::STATUS_MAHASISWA_BARU]);
            }
            $nimGenerate = $nim;

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
            $konversiData = KonversiMahasiswa::updateOrCreate(
                ['pendaftaran_id' => $pendaftaran->id],
                [
                    'mahasiswa_id' => $mahasiswa->id,
                    'nim_diterbitkan' => $nim,
                    'diproses_oleh' => $diprosesOlehId,
                ]
            );

            // 4. Update Role IAM
            if ($pendaftaran->user_id) {
                $user = User::find($pendaftaran->user_id);
                if ($user) {
                    $mhsRole = Role::where('slug', 'mahasiswa')->first();
                    if ($mhsRole && !$user->roles->contains('id', $mhsRole->id)) {
                        $user->roles()->syncWithoutDetaching([$mhsRole->id]);
                    }
                    
                    // Kita oper object user keluar dari closure untuk dikerjakan API external
                    if (empty($user->email_kampus)) {
                        $userToUpdateEmail = $user;
                    }
                }
            }

            return $konversiData;
        });

        // 5. Eksekusi API External Google Workspace di luar DB Transaction
        if (isset($userToUpdateEmail) && isset($nimGenerate)) {
            $this->assignGoogleWorkspaceEmail($userToUpdateEmail, $pendaftaran->nama_lengkap, $nimGenerate);
        }

        return $konversi;
    }

    /**
     * Memproses pembuatan Email Kampus menggunakan Google Workspace API
     */
    protected function assignGoogleWorkspaceEmail(User $user, string $namaLengkap, string $nim): void
    {
        $domainKampus = config('services.google_workspace.domain', 'student.campus.ac.id');
        $namaParts = explode(' ', trim($namaLengkap));
        $firstNameRaw = $namaParts[0];
        $lastNameRaw = count($namaParts) > 1 ? end($namaParts) : $firstNameRaw;

        $firstName = strtolower(preg_replace('/[^a-zA-Z]/', '', $firstNameRaw));
        $lastName = preg_replace('/[^a-zA-Z]/', '', $lastNameRaw);
        
        $emailPrefix = $firstName . '.' . strtolower($nim);
        
        $googleService = new GoogleWorkspaceService();
        $emailKampus = null;

        $counter = 0;
        $maxRetries = 3;
        $finalPrefix = $emailPrefix;

        // Coba memanggil API, retry dengan angka jika duplicate
        while ($counter < $maxRetries && !$emailKampus) {
            try {
                $emailKampus = $googleService->createUser($firstName, $lastName, $finalPrefix);
                // Jika null (misal karena service di-mock/tidak ada credentials), kita set manual by string saja di lokal.
                if (!$emailKampus && !config('services.google_workspace.credentials_json')) {
                    $emailKampus = $finalPrefix . '@' . $domainKampus;
                }
            } catch (\Exception $e) {
                if ($e->getMessage() === 'EmailAlreadyExists') {
                    $counter++;
                    $finalPrefix = $firstName . $counter . '.' . strtolower($nim);
                } else {
                    Log::error("Failed Google Workspace Assignment: " . $e->getMessage());
                    break;
                }
            }
        }

        if ($emailKampus) {
            $user->update([
                'email_kampus' => $emailKampus,
                'username' => $nim, // username diganti menjadi nim mahasiswa
            ]);
        }
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
