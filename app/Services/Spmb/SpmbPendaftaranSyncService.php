<?php

namespace App\Services\Spmb;

use App\Models\MasterTipeJalur;
use App\Models\Role;
use App\Models\Siakad\ProgramStudi;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\IAM\ApiIntegrationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class SpmbPendaftaranSyncService
{
    /**
     * Sinkronisasi data pendaftaran SPMB dari API eksternal via IAM ApiIntegrationService.
     *
     * @param array $queryParams Parameter query opsional untuk endpoint eksternal
     * @param int|null $actorId ID pengguna yang menjalankan sinkronisasi
     * @return array Ringkasan hasil sinkronisasi
     */
    public function sync(array $queryParams = [], ?int $actorId = null): array
    {
        $response = ApiIntegrationService::call('spmb_pendaftaran', $queryParams);

        if (!$response->successful()) {
            throw new RuntimeException(
                "Gagal memanggil API integrasi SPMB (HTTP {$response->status()}): " . $response->body(),
                $response->status()
            );
        }

        $payload = $response->json();
        $items = [];

        if (is_array($payload)) {
            if (isset($payload['data']) && is_array($payload['data'])) {
                $items = $payload['data'];
            } elseif (array_is_list($payload)) {
                $items = $payload;
            }
        }

        $totalFetched = count($items);
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        if ($totalFetched === 0) {
            return [
                'total_fetched' => 0,
                'inserted'      => 0,
                'updated'       => 0,
                'skipped'       => 0,
                'errors'        => [],
            ];
        }

        // Resolusi peran default calon mahasiswa
        $calonMhsRole = Role::where('slug', 'calon_mhs')->first();

        // Resolusi gelombang aktif default
        $defaultGelombang = GelombangPenerimaan::where('status', 'aktif')->first();

        // Cache prodi untuk efisiensi
        $allProdis = ProgramStudi::all();

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                $skipped++;
                $errors[] = "Baris ke-{$index}: Data bukan berupa objek/array valid.";
                continue;
            }

            $noPendaftaran = isset($item['no_pendaftaran']) ? trim((string) $item['no_pendaftaran']) : null;
            $nik = isset($item['nik']) ? trim((string) $item['nik']) : null;

            if (empty($noPendaftaran) && empty($nik)) {
                $skipped++;
                $errors[] = "Baris ke-{$index}: no_pendaftaran atau nik tidak ditemukan.";
                continue;
            }

            try {
                DB::transaction(function () use (
                    $item,
                    $noPendaftaran,
                    $nik,
                    $calonMhsRole,
                    $defaultGelombang,
                    $allProdis,
                    &$inserted,
                    &$updated
                ) {
                    // 1. Akun Portal Calon Mahasiswa (User)
                    $email = isset($item['email']) && filter_var($item['email'], FILTER_VALIDATE_EMAIL)
                        ? trim(strtolower((string) $item['email']))
                        : null;
                    $username = $noPendaftaran ?: ($nik ?: ('calon_' . Str::random(8)));

                    $user = null;
                    if ($email) {
                        $user = User::where('email', $email)->first();
                    }
                    if (!$user && $username) {
                        $user = User::where('username', $username)->first();
                    }

                    if (!$user) {
                        $user = User::create([
                            'username'    => $username,
                            'email'       => $email ?: ($username . '@spmb.local'),
                            'password'    => Hash::make(Str::random(16)),
                            'phone'       => isset($item['no_hp']) ? substr(trim((string) $item['no_hp']), 0, 30) : null,
                            'is_active'   => true,
                            'is_verified' => true,
                        ]);

                        if ($calonMhsRole) {
                            $user->roles()->syncWithoutDetaching([$calonMhsRole->id]);
                        }
                    }

                    // 2. Pencarian Entri Pendaftaran Eksisting
                    $existing = null;
                    if ($noPendaftaran) {
                        $existing = PendaftaranCalonMhs::where('no_pendaftaran', $noPendaftaran)->first();
                    }
                    if (!$existing && $nik) {
                        $existing = PendaftaranCalonMhs::where('nik', $nik)->first();
                    }

                    $isUpdate = (bool) $existing;

                    // 3. Dynamic Entity Resolution (Zero Hardcode & Strict Validation)
                    // Resolusi Program Studi Pilihan 1
                    $prodi1Id = null;
                    if (!empty($item['pilihan_prodi_1'])) {
                        $prodiKey = trim((string) $item['pilihan_prodi_1']);
                        $matchedProdi = $allProdis->first(function ($p) use ($prodiKey) {
                            return strcasecmp($p->kode_prodi, $prodiKey) === 0
                                || stripos($p->nama, $prodiKey) !== false;
                        });
                        $prodi1Id = $matchedProdi?->id;
                    }

                    $finalProdi1Id = $prodi1Id ?: $existing?->program_studi_id;
                    if (!$finalProdi1Id) {
                        $labelProdi = $item['pilihan_prodi_1'] ?? '-';
                        throw new InvalidArgumentException("Program Studi pilihan 1 ('{$labelProdi}') tidak valid atau belum terdaftar di sistem.");
                    }

                    // Resolusi Program Studi Pilihan 2 (opsional)
                    $prodi2Id = null;
                    if (!empty($item['pilihan_prodi_2'])) {
                        $prodiKey2 = trim((string) $item['pilihan_prodi_2']);
                        $matchedProdi2 = $allProdis->first(function ($p) use ($prodiKey2) {
                            return strcasecmp($p->kode_prodi, $prodiKey2) === 0
                                || stripos($p->nama, $prodiKey2) !== false;
                        });
                        $prodi2Id = $matchedProdi2?->id;
                    }

                    // Resolusi Gelombang
                    $gelombangId = null;
                    if (!empty($item['gelombang'])) {
                        $gelombangKey = trim((string) $item['gelombang']);
                        $matchedGelombang = GelombangPenerimaan::where('nama', 'like', "%{$gelombangKey}%")->first();
                        if ($matchedGelombang) {
                            $gelombangId = $matchedGelombang->id;
                        }
                    }

                    $finalGelombangId = $gelombangId ?: ($defaultGelombang?->id ?: $existing?->gelombang_id);
                    if (!$finalGelombangId) {
                        $labelGelombang = $item['gelombang'] ?? '-';
                        throw new InvalidArgumentException("Gelombang penerimaan ('{$labelGelombang}') tidak ditemukan atau tidak ada gelombang aktif.");
                    }

                    // Resolusi Master Tipe Jalur
                    $tipeJalurId = null;
                    if (!empty($item['jalur_pendaftaran'])) {
                        $jalurKey = trim((string) $item['jalur_pendaftaran']);
                        $matchedTipe = MasterTipeJalur::where('nama', 'like', "%{$jalurKey}%")
                            ->orWhere('kode', 'like', "%{$jalurKey}%")
                            ->first();
                        if ($matchedTipe) {
                            $tipeJalurId = $matchedTipe->id;
                        }
                    }

                    $finalTipeJalurId = $tipeJalurId ?: $existing?->master_tipe_jalur_id;
                    if (!$finalTipeJalurId) {
                        $labelJalur = $item['jalur_pendaftaran'] ?? '-';
                        throw new InvalidArgumentException("Tipe jalur pendaftaran ('{$labelJalur}') tidak valid atau belum terdaftar di master data.");
                    }

                    // 4. Susun Atribut Pendaftaran
                    $attributes = [
                        'user_id'                   => $user->id,
                        'gelombang_id'              => $finalGelombangId,
                        'program_studi_id'          => $finalProdi1Id,
                        'program_studi_pilihan2_id' => $prodi2Id ?: $existing?->program_studi_pilihan2_id,
                        'master_tipe_jalur_id'      => $finalTipeJalurId,
                        'no_pendaftaran'            => $noPendaftaran ?: ($existing?->no_pendaftaran ?? ('REG-' . date('Ymd') . '-' . rand(1000, 9999))),
                        'nama_lengkap'              => isset($item['nama_lengkap']) ? trim((string) $item['nama_lengkap']) : ($existing?->nama_lengkap ?? '-'),
                        'nik'                       => $nik ?: ($existing?->nik ?? '-'),
                        'tempat_lahir'              => isset($item['tempat_lahir']) ? trim((string) $item['tempat_lahir']) : $existing?->tempat_lahir,
                        'tanggal_lahir'             => isset($item['tanggal_lahir']) ? trim((string) $item['tanggal_lahir']) : $existing?->tanggal_lahir,
                        'jenis_kelamin'             => isset($item['jenis_kelamin']) && in_array(strtoupper((string) $item['jenis_kelamin']), ['L', 'P'])
                            ? strtoupper((string) $item['jenis_kelamin'])
                            : ($existing?->jenis_kelamin ?? 'L'),
                        'agama'                     => isset($item['agama']) ? trim((string) $item['agama']) : $existing?->agama,
                        'status_sipil'              => isset($item['status_sipil']) ? trim((string) $item['status_sipil']) : $existing?->status_sipil,
                        'kewarganegaraan'           => isset($item['kewarganegaraan']) ? trim((string) $item['kewarganegaraan']) : ($existing?->kewarganegaraan ?? 'WNI'),
                        'no_hp'                     => isset($item['no_hp']) ? trim((string) $item['no_hp']) : $existing?->no_hp,
                        'alamat'                    => isset($item['alamat']) ? trim((string) $item['alamat']) : $existing?->alamat,
                        'provinsi'                  => isset($item['provinsi']) ? trim((string) $item['provinsi']) : $existing?->provinsi,
                        'kota_kabupaten'            => isset($item['kota_kabupaten']) ? trim((string) $item['kota_kabupaten']) : $existing?->kota_kabupaten,
                        'kecamatan'                 => isset($item['kecamatan']) ? trim((string) $item['kecamatan']) : $existing?->kecamatan,
                        'kode_pos'                  => isset($item['kode_pos']) ? trim((string) $item['kode_pos']) : $existing?->kode_pos,
                        'asal_sekolah'              => isset($item['asal_sekolah']) ? trim((string) $item['asal_sekolah']) : $existing?->asal_sekolah,
                        'alamat_sekolah'            => isset($item['alamat_sekolah']) ? trim((string) $item['alamat_sekolah']) : $existing?->alamat_sekolah,
                        'jurusan_sekolah'           => isset($item['jurusan_sekolah']) ? trim((string) $item['jurusan_sekolah']) : $existing?->jurusan_sekolah,
                        'nilai_rata_rapor'          => isset($item['nilai_rata_rapor']) ? (float) $item['nilai_rata_rapor'] : $existing?->nilai_rata_rapor,
                        'tahun_lulus'               => isset($item['tahun_lulus']) ? (int) $item['tahun_lulus'] : $existing?->tahun_lulus,
                        'npsn_sekolah'              => isset($item['npsn_sekolah']) ? trim((string) $item['npsn_sekolah']) : $existing?->npsn_sekolah,
                        'nama_ayah'                 => isset($item['nama_ayah']) ? trim((string) $item['nama_ayah']) : $existing?->nama_ayah,
                        'pekerjaan_ayah'            => isset($item['pekerjaan_ayah']) ? trim((string) $item['pekerjaan_ayah']) : $existing?->pekerjaan_ayah,
                        'nama_ibu'                  => isset($item['nama_ibu']) ? trim((string) $item['nama_ibu']) : $existing?->nama_ibu,
                        'pekerjaan_ibu'             => isset($item['pekerjaan_ibu']) ? trim((string) $item['pekerjaan_ibu']) : $existing?->pekerjaan_ibu,
                        'penghasilan_ortu'          => isset($item['penghasilan_ortu']) ? trim((string) $item['penghasilan_ortu']) : $existing?->penghasilan_ortu,
                        'nama_ortu'                 => isset($item['nama_ortu']) ? trim((string) $item['nama_ortu']) : ($existing?->nama_ortu ?? ($item['nama_ayah'] ?? null)),
                        'alamat_ortu'               => isset($item['alamat_ortu']) ? trim((string) $item['alamat_ortu']) : $existing?->alamat_ortu,
                        'telp_ortu'                 => isset($item['telp_ortu']) ? trim((string) $item['telp_ortu']) : $existing?->telp_ortu,
                        'nama_wali'                 => isset($item['nama_wali']) ? trim((string) $item['nama_wali']) : $existing?->nama_wali,
                        'telepon_wali'              => isset($item['telepon_wali']) ? trim((string) $item['telepon_wali']) : $existing?->telepon_wali,
                        'status'                    => isset($item['status']) ? trim((string) $item['status']) : ($existing?->status ?? PendaftaranCalonMhs::STATUS_SUBMITTED),
                        'status_pembayaran'         => isset($item['status_pembayaran']) ? trim((string) $item['status_pembayaran']) : ($existing?->status_pembayaran ?? PendaftaranCalonMhs::STATUS_PEMBAYARAN_BELUM),
                        'catatan_verifikasi'        => isset($item['catatan_verifikasi']) ? trim((string) $item['catatan_verifikasi']) : $existing?->catatan_verifikasi,
                        'used_referral_code'        => isset($item['used_referral_code']) ? trim((string) $item['used_referral_code']) : $existing?->used_referral_code,
                    ];

                    // Data Riwayat Pendidikan Tinggi Asal jika tipe transfer / lulusan PT
                    if (isset($item['asal_lulusan'])) {
                        $attributes['asal_lulusan'] = trim((string) $item['asal_lulusan']);
                    }
                    if (isset($item['asal_pt'])) {
                        $attributes['asal_pt'] = trim((string) $item['asal_pt']);
                    }
                    if (isset($item['jenjang_pt'])) {
                        $attributes['jenjang_pt'] = trim((string) $item['jenjang_pt']);
                    }
                    if (isset($item['progdi_pt'])) {
                        $attributes['progdi_pt'] = trim((string) $item['progdi_pt']);
                    }
                    if (isset($item['ipk_pt'])) {
                        $attributes['ipk_pt'] = (float) $item['ipk_pt'];
                    }
                    if (isset($item['nim_pt'])) {
                        $attributes['nim_pt'] = trim((string) $item['nim_pt']);
                    }
                    if (isset($item['tahun_lulus_pt'])) {
                        $attributes['tahun_lulus_pt'] = (int) $item['tahun_lulus_pt'];
                    }

                    if ($isUpdate) {
                        $oldValues = $existing->getOriginal();
                        $existing->update($attributes);
                        $newValues = $existing->getChanges();

                        try {
                            AuditLogService::record(
                                module: 'spmb',
                                action: 'update',
                                tableName: 'spmb_pendaftaran_calon_mhs',
                                recordId: $existing->id,
                                oldValues: $oldValues,
                                newValues: $newValues
                            );
                        } catch (\Throwable $e) {
                            Log::warning('Gagal mencatat audit log update sync pendaftar: ' . $e->getMessage());
                        }

                        $updated++;
                    } else {
                        $pendaftaran = PendaftaranCalonMhs::create($attributes);

                        try {
                            AuditLogService::record(
                                module: 'spmb',
                                action: 'create',
                                tableName: 'spmb_pendaftaran_calon_mhs',
                                recordId: $pendaftaran->id,
                                oldValues: null,
                                newValues: $pendaftaran->toArray()
                            );
                        } catch (\Throwable $e) {
                            Log::warning('Gagal mencatat audit log create sync pendaftar: ' . $e->getMessage());
                        }

                        $inserted++;
                    }
                });
            } catch (\Throwable $e) {
                $skipped++;
                $identifier = $noPendaftaran ?: ($nik ?: "index {$index}");
                $errors[] = "Pendaftar [{$identifier}]: " . $e->getMessage();
                Log::warning("Gagal sinkronisasi baris data SPMB [{$identifier}]: " . $e->getMessage());
            }
        }

        // Catat jejak audit ringkasan penarikan data
        try {
            AuditLogService::record(
                module: 'spmb',
                action: 'sync_external',
                tableName: 'spmb_pendaftaran_calon_mhs',
                recordId: 0,
                oldValues: null,
                newValues: [
                    'total_fetched' => $totalFetched,
                    'inserted'      => $inserted,
                    'updated'       => $updated,
                    'skipped'       => $skipped,
                    'actor_id'      => $actorId,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log summary sync SPMB: ' . $e->getMessage());
        }

        return [
            'total_fetched' => $totalFetched,
            'inserted'      => $inserted,
            'updated'       => $updated,
            'skipped'       => $skipped,
            'errors'        => $errors,
        ];
    }
}
