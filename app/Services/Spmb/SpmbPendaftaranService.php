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
    public function __construct(private SpmbReferralService $referralService) {}

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
    public function verifikasiAdministrasi(PendaftaranCalonMhs $pendaftaran, bool $isLulus, ?string $catatan, int $adminId): void
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

        // Sinkronkan status referral: qualified bila lolos, dibatalkan bila gagal.
        if ($isLulus) {
            $this->referralService->qualify($pendaftaran);
        } else {
            $this->referralService->cancel($pendaftaran);
        }
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
                $tahunAkademikId = \App\Models\Siakad\TahunAkademik::query()
                    ->where('is_active', true)
                    ->orderByDesc('kode')
                    ->value('id') ?? 1;
                $kuotaProdi = \App\Models\Spmb\SpmbKuotaProdi::where('tahun_akademik_id', $tahunAkademikId)
                    ->where('program_studi_id', $dataSeleksi['program_studi_diterima_id'])
                    ->lockForUpdate()
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

    /**
     * Simpan biodata pendaftaran (draft) beserta kode referral.
     */
    public function saveBiodata(\App\Models\User $user, array $validated): PendaftaranCalonMhs
    {
        return DB::transaction(function () use ($user, $validated) {
            // Kode referral ditangani service (validasi exists + anti self-referral),
            // jangan ikut di-mass-assign agar tidak menyimpan kode tak valid.
            $referralCode = $validated['used_referral_code'] ?? null;
            unset($validated['used_referral_code']);

            $existing = PendaftaranCalonMhs::where('user_id', $user->id)->first();
            $noPendaftaran = ($existing && ! empty($existing->no_pendaftaran))
                ? $existing->no_pendaftaran
                : ('REG-'.date('Ymd').'-'.rand(1000, 9999));

            // GELOMBANG IMMUTABILITY PROTECTION:
            // If candidate already registered/paid in a gelombang, lock gelombang_id so future admin gelombang changes won't affect them.
            if ($existing && ! empty($existing->gelombang_id)) {
                $validated['gelombang_id'] = $existing->gelombang_id;
            }

            // Sanitasi input: jika nik kosong string, jadikan null agar tidak memicu duplikat unique key
            if (array_key_exists('nik', $validated)) {
                if (empty(trim((string) $validated['nik']))) {
                    $validated['nik'] = $existing ? $existing->nik : null;
                }
            }

            // Pastikan nama_lengkap memiliki nilai representatif meskipun pada Step 1 awal
            $namaLengkap = ! empty($validated['nama_lengkap'])
                ? $validated['nama_lengkap']
                : ($existing->nama_lengkap ?? $user->name ?? $user->username ?? 'Calon Mahasiswa');

            $pendaftaran = PendaftaranCalonMhs::updateOrCreate(
                ['user_id' => $user->id],
                array_merge($validated, [
                    'nama_lengkap' => $namaLengkap,
                    'no_pendaftaran' => $noPendaftaran,
                    'kewarganegaraan' => $validated['kewarganegaraan'] ?? $existing->kewarganegaraan ?? 'WNI',
                    'status' => $existing ? $existing->status : 'draft',
                    'status_pembayaran' => $existing ? $existing->status_pembayaran : 'belum_bayar',
                ])
            );

            // Terapkan / perbarui kode referral bila dikirim dari wizard.
            if ($referralCode !== null && trim($referralCode) !== '') {
                $this->referralService->attachToPendaftaran($pendaftaran, $referralCode);
                $pendaftaran->refresh();
            }

            return $pendaftaran;
        });
    }

    /**
     * Perbarui status pendaftaran sekaligus sinkronkan status referral.
     */
    public function updateStatusWithReferral(PendaftaranCalonMhs $pendaftaran, array $validated, int $adminId): PendaftaranCalonMhs
    {
        return DB::transaction(function () use ($pendaftaran, $validated, $adminId) {
            $pendaftaran->status = $validated['status'];
            if (isset($validated['catatan_verifikasi'])) {
                $pendaftaran->catatan_verifikasi = $validated['catatan_verifikasi'];
            }
            $pendaftaran->diverifikasi_oleh = $adminId;
            $pendaftaran->diverifikasi_at = now();
            $pendaftaran->save();

            // Sinkronkan status referral mengikuti keputusan verifikasi.
            if ($validated['status'] === PendaftaranCalonMhs::STATUS_LULUS_ADMINISTRASI) {
                $this->referralService->qualify($pendaftaran);
            } elseif ($validated['status'] === PendaftaranCalonMhs::STATUS_GAGAL_ADMINISTRASI) {
                $this->referralService->cancel($pendaftaran);
            }

            return $pendaftaran->refresh();
        });
    }
}
