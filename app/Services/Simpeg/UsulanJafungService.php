<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\UsulanJafung;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

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

            // Penetapan SK yang diunggah otomatis berstatus disetujui jika tidak ditentukan lain
            if (empty($data['status_usulan'])) {
                $data['status_usulan'] = 'disetujui';
            }

            $usulan = UsulanJafung::create($data);

            // Sinkronkan JAD ke biodata pegawai jika status disetujui
            if ($usulan->status_usulan === 'disetujui' && $usulan->jafung_tujuan_id && $usulan->pegawai_id) {
                Pegawai::where('id', $usulan->pegawai_id)->update([
                    'jabatan_fungsional_id' => $usulan->jafung_tujuan_id,
                ]);
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
            $file = $data['file_sk_hasil'] ?? null;
            if ($file instanceof UploadedFile) {
                if ($usulan->file_sk_hasil) {
                    $this->files->delete($usulan->file_sk_hasil);
                }
                $path = $this->files->store($file, 'simpeg/usulan_jafung', private: true);
                $data['file_sk_hasil'] = $path;
            }

            $usulan->update($data);

            if (($data['status_usulan'] ?? $usulan->status_usulan) === 'disetujui' && $usulan->jafung_tujuan_id && $usulan->pegawai_id) {
                Pegawai::where('id', $usulan->pegawai_id)->update([
                    'jabatan_fungsional_id' => $usulan->jafung_tujuan_id,
                ]);
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

            return (bool) $usulan->delete();
        });
    }
}
