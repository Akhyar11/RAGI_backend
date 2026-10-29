<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\RiwayatJabatan;
use App\Models\Simpeg\UsulanJafung;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UsulanJafungService
{
    public function __construct(
        protected FileStorageService $files
    ) {}

    /**
     * Simpan data penetapan JAD dan SK baru.
     */
    public function create(array $data): UsulanJafung
    {
        return DB::transaction(function () use ($data) {
            // Cegah duplikasi: dosen yang sama tidak boleh menginput JAD yang sama
            $exists = UsulanJafung::where('pegawai_id', $data['pegawai_id'])
                ->where('jafung_tujuan_id', $data['jafung_tujuan_id'])
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'jafung_tujuan_id' => 'Jabatan Akademik ini sudah pernah tercatat untuk dosen yang bersangkutan.',
                ]);
            }

            $file = $data['file_sk_hasil'] ?? null;
            if ($file instanceof UploadedFile) {
                $path = $this->files->store($file, 'simpeg/usulan_jafung', private: true);
                $data['file_sk_hasil'] = $path;
            }

            if (empty($data['jafung_asal_id']) && !empty($data['pegawai_id'])) {
                $pegawai = Pegawai::find($data['pegawai_id']);
                if ($pegawai && $pegawai->jabatan_fungsional_id) {
                    $data['jafung_asal_id'] = $pegawai->jabatan_fungsional_id;
                }
            }

            if (!isset($data['angka_kredit_usulan']) || $data['angka_kredit_usulan'] === null) {
                $jafung = \App\Models\Simpeg\JabatanFungsionalAkademik::find($data['jafung_tujuan_id']);
                $data['angka_kredit_usulan'] = $jafung?->angka_kredit_min ?? 0;
            }

            // Penetapan SK yang diunggah otomatis berstatus disetujui jika tidak ditentukan lain
            if (empty($data['status_usulan'])) {
                $data['status_usulan'] = 'disetujui';
            }

            $usulan = UsulanJafung::create($data);

            // Sinkronkan JAD ke biodata pegawai dan tabel RiwayatJabatan jika status disetujui
            if ($usulan->status_usulan === 'disetujui' && $usulan->jafung_tujuan_id && $usulan->pegawai_id) {
                Pegawai::where('id', $usulan->pegawai_id)->update([
                    'jabatan_fungsional_id' => $usulan->jafung_tujuan_id,
                ]);

                $tmt = $usulan->tanggal_mulai_berlaku ?: ($usulan->created_at ? $usulan->created_at->format('Y-m-d') : now()->toDateString());
                RiwayatJabatan::updateOrCreate(
                    [
                        'pegawai_id' => $usulan->pegawai_id,
                        'jabatan_fungsional_id' => $usulan->jafung_tujuan_id,
                    ],
                    [
                        'mulai_jabatan' => $tmt,
                        'sk_nomor' => $usulan->nomor_sk,
                        'sk_tanggal' => $tmt,
                        'file_sk' => $usulan->file_sk_hasil,
                        'is_active' => true,
                    ]
                );

                RiwayatJabatan::where('pegawai_id', $usulan->pegawai_id)
                    ->whereNotNull('jabatan_fungsional_id')
                    ->where('jabatan_fungsional_id', '!=', $usulan->jafung_tujuan_id)
                    ->update(['is_active' => false]);
            }

            $usulan->load([
                'pegawai.unitKerja',
                'pegawai.dosen.programStudi',
                'jafungAsal',
                'jafungTujuan',
            ]);

            return $usulan;
        });
    }

    /**
     * Perbarui data penetapan JAD.
     */
    public function update(UsulanJafung $usulan, array $data): UsulanJafung
    {
        return DB::transaction(function () use ($usulan, $data) {
            $pegawaiId = $data['pegawai_id'] ?? $usulan->pegawai_id;
            $jafungTujuanId = $data['jafung_tujuan_id'] ?? $usulan->jafung_tujuan_id;

            // Validasi duplikasi saat update
            if ($jafungTujuanId != $usulan->jafung_tujuan_id || $pegawaiId != $usulan->pegawai_id) {
                $exists = UsulanJafung::where('pegawai_id', $pegawaiId)
                    ->where('jafung_tujuan_id', $jafungTujuanId)
                    ->where('id', '!=', $usulan->id)
                    ->exists();
                if ($exists) {
                    throw ValidationException::withMessages([
                        'jafung_tujuan_id' => 'Jabatan Akademik ini sudah pernah tercatat untuk dosen yang bersangkutan.',
                    ]);
                }
            }

            $file = $data['file_sk_hasil'] ?? null;
            if ($file instanceof UploadedFile) {
                if ($usulan->file_sk_hasil) {
                    $this->files->delete($usulan->file_sk_hasil);
                }
                $path = $this->files->store($file, 'simpeg/usulan_jafung', private: true);
                $data['file_sk_hasil'] = $path;
            }

            $oldJafungTujuanId = $usulan->jafung_tujuan_id;
            $usulan->update($data);

            if (($data['status_usulan'] ?? $usulan->status_usulan) === 'disetujui' && $usulan->jafung_tujuan_id && $usulan->pegawai_id) {
                Pegawai::where('id', $usulan->pegawai_id)->update([
                    'jabatan_fungsional_id' => $usulan->jafung_tujuan_id,
                ]);

                // Jika jafung_tujuan berubah, hapus / update riwayat lama
                if ($oldJafungTujuanId && $oldJafungTujuanId != $usulan->jafung_tujuan_id) {
                    RiwayatJabatan::where('pegawai_id', $usulan->pegawai_id)
                        ->where('jabatan_fungsional_id', $oldJafungTujuanId)
                        ->delete();
                }

                $tmt = $usulan->tanggal_mulai_berlaku ?: ($usulan->created_at ? $usulan->created_at->format('Y-m-d') : now()->toDateString());
                RiwayatJabatan::updateOrCreate(
                    [
                        'pegawai_id' => $usulan->pegawai_id,
                        'jabatan_fungsional_id' => $usulan->jafung_tujuan_id,
                    ],
                    [
                        'mulai_jabatan' => $tmt,
                        'sk_nomor' => $usulan->nomor_sk,
                        'sk_tanggal' => $tmt,
                        'file_sk' => $usulan->file_sk_hasil,
                        'is_active' => true,
                    ]
                );

                RiwayatJabatan::where('pegawai_id', $usulan->pegawai_id)
                    ->whereNotNull('jabatan_fungsional_id')
                    ->where('jabatan_fungsional_id', '!=', $usulan->jafung_tujuan_id)
                    ->update(['is_active' => false]);
            }

            $usulan->load([
                'pegawai.unitKerja',
                'pegawai.dosen.programStudi',
                'jafungAsal',
                'jafungTujuan',
            ]);

            return $usulan;
        });
    }

    /**
     * Hapus data penetapan JAD beserta berkas pendukungnya.
     */
    public function delete(UsulanJafung $usulan): bool
    {
        return DB::transaction(function () use ($usulan) {
            if ($usulan->file_sk_hasil) {
                $this->files->delete($usulan->file_sk_hasil);
            }

            $pegawaiId = $usulan->pegawai_id;
            $jafungId = $usulan->jafung_tujuan_id;

            $deleted = (bool) $usulan->delete();

            // Hapus sinkronisasi riwayat jabatan terkait
            if ($pegawaiId && $jafungId) {
                RiwayatJabatan::where('pegawai_id', $pegawaiId)
                    ->where('jabatan_fungsional_id', $jafungId)
                    ->delete();

                // Kembalikan jabatan fungsional aktif ke JAD terakhir yang tersisa jika ada
                $latestJad = UsulanJafung::where('pegawai_id', $pegawaiId)
                    ->where('status_usulan', 'disetujui')
                    ->latest('tanggal_mulai_berlaku')
                    ->first();

                Pegawai::where('id', $pegawaiId)->update([
                    'jabatan_fungsional_id' => $latestJad?->jafung_tujuan_id,
                ]);

                if ($latestJad) {
                    RiwayatJabatan::where('pegawai_id', $pegawaiId)
                        ->where('jabatan_fungsional_id', $latestJad->jafung_tujuan_id)
                        ->update(['is_active' => true]);
                }
            }

            return $deleted;
        });
    }
}
