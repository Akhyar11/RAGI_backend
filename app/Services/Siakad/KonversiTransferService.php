<?php

namespace App\Services\Siakad;

use App\Models\Siakad\Dosen;
use App\Models\Siakad\KonversiTransfer;
use App\Models\Siakad\KonversiTransferDetail;
use App\Models\Siakad\Mahasiswa;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KonversiTransferService
{
    /**
     * Ambil rincian usulan konversi transfer beserta verifikasi otorisasi user.
     * Dosen murni hanya dapat mengakses data mahasiswa bimbingan (dosen wali).
     */
    public function getDetail(int|string $id, ?User $user = null): KonversiTransfer
    {
        $konversi = KonversiTransfer::with(['mahasiswa.programStudi', 'diprosesOleh', 'details.mataKuliahDiakui'])
            ->findOrFail($id);

        $isPriv = $user && ($user->isSuperAdmin() || $user->hasRole('admin') || $user->hasRole('kaprodi') || $user->hasRole('wakil_prodi'));
        $isDosenMurni = $user && $user->hasRole('dosen') && !$isPriv;

        if ($isDosenMurni) {
            $dosen = Dosen::where('user_id', $user->id)->first();
            if (!$dosen || (int) $konversi->mahasiswa?->dosen_wali_id !== (int) $dosen->id) {
                abort(403, 'Anda tidak memiliki akses ke usulan konversi mahasiswa ini.');
            }
        }

        return $konversi;
    }

    /**
     * Update usulan konversi transfer dan rincian mata kuliah penyetaraan.
     */
    public function update(int|string $id, array $data, ?User $user = null): KonversiTransfer
    {
        return DB::transaction(function () use ($id, $data, $user) {
            $konversi = KonversiTransfer::findOrFail($id);
            $mhs = Mahasiswa::findOrFail($konversi->mahasiswa_id);

            $isPrivileged = $user && ($user->isSuperAdmin() || $user->hasRole('admin') || $user->hasRole('kaprodi') || $user->hasRole('wakil_prodi'));
            $isPa = false;
            if ($user && !$isPrivileged) {
                $dosenLogin = Dosen::where('user_id', $user->id)->first();
                $isPa = $dosenLogin && (int) $mhs->dosen_wali_id === (int) $dosenLogin->id;
            }

            if ($konversi->status === 'disetujui' && !$isPrivileged && !$isPa) {
                abort(403, 'Konversi sudah disetujui dan tidak dapat diubah.');
            }

            $status = $data['status'] ?? $konversi->status;
            if ($isPrivileged || $isPa) {
                $status = $status ?? 'disetujui';
            } else {
                if (!in_array($status, ['draft', 'diajukan'])) {
                    $status = 'draft';
                }
            }

            $oldValues = $konversi->getOriginal();

            $konversi->update([
                'kampus_asal' => $data['kampus_asal'] ?? $konversi->kampus_asal,
                'prodi_asal' => $data['prodi_asal'] ?? $konversi->prodi_asal,
                'diproses_oleh' => $user?->id,
                'status' => $status,
                'catatan' => $data['catatan'] ?? $konversi->catatan,
            ]);

            try {
                AuditLogService::record(
                    module: 'SIAKAD',
                    action: 'update',
                    tableName: 'siakad_konversi_transfer',
                    recordId: (int) $konversi->id,
                    oldValues: $oldValues,
                    newValues: $konversi->getChanges(),
                    request: request()
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log konversi transfer: ' . $e->getMessage());
            }

            if (isset($data['details']) && is_array($data['details'])) {
                $konversi->details()->delete();
                foreach ($data['details'] as $item) {
                    KonversiTransferDetail::create([
                        'konversi_id' => $konversi->id,
                        'mata_kuliah_diakui_id' => $item['mata_kuliah_diakui_id'],
                        'kode_mk_asal' => $item['kode_mk_asal'],
                        'nama_mk_asal' => $item['nama_mk_asal'],
                        'sks_asal' => $item['sks_asal'],
                        'nilai_huruf_asal' => $item['nilai_huruf_asal'],
                    ]);
                }
            }

            return $konversi->load('details.mataKuliahDiakui');
        });
    }
}
