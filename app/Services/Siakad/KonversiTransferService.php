<?php

namespace App\Services\Siakad;

use App\Models\Siakad\Dosen;
use App\Models\Siakad\KonversiTransfer;
use App\Models\Siakad\KonversiTransferDetail;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\ProgramStudi;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KonversiTransferService
{
    public function isGlobalManager(?User $user): bool
    {
        return (bool) ($user && (
            $user->isSuperAdmin()
            || $user->hasRole('admin')
            || $user->hasPermission('siakad.master.manage')
        ));
    }

    public function canManageProgram(?User $user, Mahasiswa $mahasiswa): bool
    {
        if ($this->isGlobalManager($user)) {
            return true;
        }

        if (!$user) {
            return false;
        }

        $programStudiId = (int) $mahasiswa->program_studi_id;
        if ($user->siakadAdminProdis()
            ->where('program_studi_id', $programStudiId)
            ->where('is_active', true)
            ->exists()) {
            return true;
        }

        $dosen = Dosen::where('user_id', $user->id)->first();
        $programStudi = ProgramStudi::find($programStudiId);

        return $dosen !== null
            && $programStudi !== null
            && (int) $programStudi->kaprodi_id === (int) $dosen->id;
    }

    public function isDosenPa(?User $user, Mahasiswa $mahasiswa): bool
    {
        if (!$user) {
            return false;
        }

        $dosen = Dosen::where('user_id', $user->id)->first();
        return $dosen && (int) $mahasiswa->dosen_wali_id === (int) $dosen->id;
    }

    public function canView(?User $user, Mahasiswa $mahasiswa): bool
    {
        return $this->canManageProgram($user, $mahasiswa)
            || $this->isDosenPa($user, $mahasiswa)
            || ($user && (int) $mahasiswa->user_id === (int) $user->id);
    }

    /**
     * Ambil rincian usulan konversi transfer beserta verifikasi otorisasi user.
     * Dosen murni hanya dapat mengakses data mahasiswa bimbingan (dosen wali).
     */
    public function getDetail(int|string $id, ?User $user = null): KonversiTransfer
    {
        $konversi = KonversiTransfer::with(['mahasiswa.programStudi', 'diprosesOleh', 'details.mataKuliahDiakui', 'arsipNomorSurat'])
            ->findOrFail($id);

        if (!$konversi->mahasiswa || !$this->canView($user, $konversi->mahasiswa)) {
            abort(403, 'Anda tidak memiliki akses ke usulan konversi mahasiswa ini.');
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

            $isStudent = $user && (int) $mhs->user_id === (int) $user->id;
            $canManage = $this->canManageProgram($user, $mhs);

            if ($isStudent) {
                if (!in_array($konversi->status, ['draft', 'diajukan'], true)) {
                    abort(403, 'Usulan sudah masuk tahap verifikasi dan tidak dapat diubah mahasiswa.');
                }
            } elseif (!$canManage || !in_array($konversi->status, ['draft', 'diajukan'], true)) {
                abort(403, 'Hanya pengelola program studi yang dapat memperbarui pemetaan pada tahap pengajuan.');
            }

            $status = $data['status'] ?? $konversi->status;
            if (!in_array($status, ['draft', 'diajukan'], true)) {
                abort(422, 'Status hanya dapat diubah melalui tahapan verifikasi konversi.');
            }

            $oldValues = $konversi->getOriginal();

            $konversi->update([
                'kampus_asal' => $data['kampus_asal'] ?? $konversi->kampus_asal,
                'prodi_asal' => $data['prodi_asal'] ?? $konversi->prodi_asal,
                'nomor_sk' => array_key_exists('nomor_sk', $data) ? $data['nomor_sk'] : $konversi->nomor_sk,
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
                        'status' => $isStudent ? 'menunggu' : 'diakui',
                    ]);
                }
            }

            return $konversi->load(['details.mataKuliahDiakui', 'arsipNomorSurat']);
        });
    }

    public function transition(int|string $id, array $data, ?User $user = null): KonversiTransfer
    {
        return DB::transaction(function () use ($id, $data, $user) {
            $konversi = KonversiTransfer::with('details')->lockForUpdate()->findOrFail($id);
            $mahasiswa = Mahasiswa::findOrFail($konversi->mahasiswa_id);
            $targetStatus = $data['status'];
            $oldStatus = $konversi->status;
            $isManager = $this->canManageProgram($user, $mahasiswa);
            $isPa = $this->isDosenPa($user, $mahasiswa);

            if ($targetStatus === 'menunggu_pa') {
                if (!$isManager || !in_array($oldStatus, ['draft', 'diajukan'], true)) {
                    abort(403, 'Hanya pengelola program studi yang dapat mengirim pemetaan ke Dosen PA.');
                }
            } elseif ($targetStatus === 'menunggu_kaprodi' || ($targetStatus === 'ditolak' && $oldStatus === 'menunggu_pa')) {
                if (!$isPa || $oldStatus !== 'menunggu_pa') {
                    abort(403, 'Hanya Dosen PA mahasiswa yang dapat memverifikasi pemetaan ini.');
                }
            } elseif (in_array($targetStatus, ['disetujui', 'ditolak'], true)) {
                $validFinalDecision = $targetStatus === 'disetujui'
                    ? $oldStatus === 'menunggu_kaprodi'
                    : in_array($oldStatus, ['draft', 'diajukan', 'menunggu_kaprodi'], true);
                if (!$isManager || !$validFinalDecision) {
                    abort(403, 'Keputusan akhir hanya dapat diberikan pengelola program studi setelah persetujuan PA.');
                }
            } else {
                abort(422, 'Perubahan status tidak sesuai tahapan konversi yang berlaku.');
            }

            foreach ($data['details'] ?? [] as $item) {
                $detail = $konversi->details->firstWhere('id', $item['id']);
                if (!$detail) {
                    abort(422, 'Rincian mata kuliah tidak termasuk dalam usulan ini.');
                }
                $detail->update([
                    'status' => $item['status'],
                    'catatan_penolakan' => $item['status'] === 'ditolak'
                        ? ($item['catatan_penolakan'] ?? null)
                        : null,
                ]);
            }

            if (in_array($targetStatus, ['menunggu_pa', 'menunggu_kaprodi', 'disetujui'], true)) {
                $hasRecognizedCourse = $konversi->details()
                    ->where('status', 'diakui')
                    ->whereNotNull('mata_kuliah_diakui_id')
                    ->exists();
                if (!$hasRecognizedCourse) {
                    abort(422, 'Minimal satu mata kuliah harus dipetakan dan diakui untuk melanjutkan proses.');
                }
            }

            $update = [
                'status' => $targetStatus,
                'diproses_oleh' => $user?->id,
                'catatan' => $data['catatan'] ?? $konversi->catatan,
            ];
            if (array_key_exists('nomor_sk', $data)) {
                $update['nomor_sk'] = $data['nomor_sk'];
            }
            if ($targetStatus === 'menunggu_kaprodi') {
                $update['disetujui_pa_oleh'] = $user?->id;
                $update['disetujui_pa_pada'] = now();
            }
            if (in_array($targetStatus, ['disetujui', 'ditolak'], true) && $oldStatus === 'menunggu_kaprodi') {
                $update['disetujui_kaprodi_oleh'] = $user?->id;
                $update['disetujui_kaprodi_pada'] = now();
            }

            $konversi->update($update);

            try {
                AuditLogService::record(
                    module: 'SIAKAD',
                    action: $targetStatus === 'ditolak' ? 'reject' : ($targetStatus === 'disetujui' ? 'approve' : 'update'),
                    tableName: 'siakad_konversi_transfer',
                    recordId: (int) $konversi->id,
                    oldValues: ['status' => $oldStatus],
                    newValues: ['status' => $targetStatus, 'actor_id' => $user?->id]
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log verifikasi konversi transfer: ' . $e->getMessage());
            }

            return $konversi->fresh()->load(['details.mataKuliahDiakui', 'arsipNomorSurat']);
        });
    }
}
