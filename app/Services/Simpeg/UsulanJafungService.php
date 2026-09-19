<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\UsulanJafung;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UsulanJafungService
{
    /**
     * Simpan usulan kenaikan jafung baru.
     */
    public function create(array $data): UsulanJafung
    {
        return DB::transaction(function () use ($data) {
            $file = $data['file_sk_hasil'] ?? null;
            if ($file instanceof UploadedFile) {
                $path = $file->store('simpeg/usulan_jafung', 'public');
                $data['file_sk_hasil'] = $path;
            }

            if (empty($data['status_usulan'])) {
                $data['status_usulan'] = 'submitted';
            }

            $usulan = UsulanJafung::create($data);
            $usulan->load([
                'pegawai.unitKerja',
                'pegawai.dosen.programStudi',
                'jafungAsal',
                'jafungTujuan'
            ]);

            return $usulan;
        });
    }

    /**
     * Perbarui data usulan kenaikan jafung.
     */
    public function update(UsulanJafung $usulan, array $data): UsulanJafung
    {
        return DB::transaction(function () use ($usulan, $data) {
            $file = $data['file_sk_hasil'] ?? null;
            if ($file instanceof UploadedFile) {
                if ($usulan->file_sk_hasil && Storage::disk('public')->exists($usulan->file_sk_hasil)) {
                    Storage::disk('public')->delete($usulan->file_sk_hasil);
                }
                $path = $file->store('simpeg/usulan_jafung', 'public');
                $data['file_sk_hasil'] = $path;
            }

            $usulan->update($data);
            $usulan->load([
                'pegawai.unitKerja',
                'pegawai.dosen.programStudi',
                'jafungAsal',
                'jafungTujuan'
            ]);

            return $usulan;
        });
    }

    /**
     * Hapus usulan kenaikan jafung beserta berkas pendukungnya.
     */
    public function delete(UsulanJafung $usulan): bool
    {
        return DB::transaction(function () use ($usulan) {
            if ($usulan->status_usulan === 'disetujui') {
                throw ValidationException::withMessages([
                    'status_usulan' => ['Usulan jafung yang telah disetujui tidak dapat dihapus.'],
                ]);
            }

            if ($usulan->file_sk_hasil && Storage::disk('public')->exists($usulan->file_sk_hasil)) {
                Storage::disk('public')->delete($usulan->file_sk_hasil);
            }

            return (bool) $usulan->delete();
        });
    }
}
