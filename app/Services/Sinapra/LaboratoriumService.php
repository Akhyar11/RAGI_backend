<?php

namespace App\Services\Sinapra;

use App\Models\AlatKalibrasi;
use App\Models\BebasTanggungan;
use App\Models\LabBhp;
use App\Models\LabBhpTransaksi;
use App\Models\LaboranRuangan;
use App\Models\PeminjamanAset;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaboratoriumService
{
    /**
     * Dapatkan query builder Lab BHP dengan auto-scoping laboran.
     */
    public function getScopedBhpQuery(User $user): Builder
    {
        $query = LabBhp::query()->with('ruangan.gedung');

        if ($this->isLaboranRestricted($user)) {
            $labRuanganIds = LaboranRuangan::where('user_id', $user->id)->pluck('ruangan_id');
            $query->whereIn('ruangan_id', $labRuanganIds);
        }

        return $query;
    }

    /**
     * Catat transaksi mutasi stok BHP (masuk / keluar).
     */
    public function catatTransaksiBhp(LabBhp $bhp, array $data, User $user): LabBhpTransaksi
    {
        return DB::transaction(function () use ($bhp, $data, $user) {
            $jenis = $data['jenis_transaksi'];
            $jumlah = (float) $data['jumlah'];

            if ($jenis === 'keluar' && $bhp->stok_saat_ini < $jumlah) {
                throw ValidationException::withMessages([
                    'jumlah' => ["Stok BHP '{$bhp->nama_bhp}' tidak mencukupi (sisa stok: {$bhp->stok_saat_ini} {$bhp->satuan})."],
                ]);
            }

            if ($jenis === 'masuk') {
                $bhp->stok_saat_ini += $jumlah;
            } else {
                $bhp->stok_saat_ini -= $jumlah;
            }
            $bhp->save();

            return LabBhpTransaksi::create([
                'bhp_id' => $bhp->id,
                'user_id' => $user->id,
                'jenis_transaksi' => $jenis,
                'jumlah' => $jumlah,
                'tanggal' => $data['tanggal'] ?? now()->toDateString(),
                'keterangan' => $data['keterangan'] ?? null,
            ]);
        });
    }

    /**
     * Cek apakah mahasiswa memiliki pinjaman aset laboratorium yang belum selesai/kembali.
     */
    public function checkKelayakanBebasTanggungan(User $mahasiswa): array
    {
        $activeLoans = PeminjamanAset::with(['aset.ruangan'])
            ->where('user_id', $mahasiswa->id)
            ->whereIn('status', ['pending_laboran', 'pending_admin_sinapra', 'disetujui'])
            ->get();

        return [
            'is_clean' => $activeLoans->isEmpty(),
            'total_active_loans' => $activeLoans->count(),
            'active_loans' => $activeLoans,
        ];
    }

    /**
     * Mahasiswa mengajukan surat bebas tanggungan lab.
     */
    public function applyBebasTanggungan(User $mahasiswa, array $data): BebasTanggungan
    {
        $existing = BebasTanggungan::where('user_id', $mahasiswa->id)
            ->whereIn('status', ['diajukan', 'disetujui'])
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'user_id' => ['Anda telah memiliki permohonan bebas tanggungan lab yang sedang aktif atau sudah disetujui.'],
            ]);
        }

        return BebasTanggungan::create([
            'user_id' => $mahasiswa->id,
            'tanggal_pengajuan' => now()->toDateString(),
            'status' => 'diajukan',
            'catatan' => $data['catatan'] ?? null,
        ]);
    }

    /**
     * Laboran / Admin menyetujui atau menolak permohonan bebas tanggungan lab.
     */
    public function approveBebasTanggungan(BebasTanggungan $bebasTanggungan, array $data, User $approver): BebasTanggungan
    {
        $isApproved = (bool) $data['is_approved'];
        $oldValues = $bebasTanggungan->toArray();

        if ($isApproved) {
            // Verifikasi bahwa mahasiswa benar-benar bersih dari pinjaman alat lab
            $kelayakan = $this->checkKelayakanBebasTanggungan($bebasTanggungan->mahasiswa);
            if (! $kelayakan['is_clean']) {
                throw ValidationException::withMessages([
                    'status' => [
                        "Persetujuan ditolak: Mahasiswa bersangkutan masih memiliki {$kelayakan['total_active_loans']} tanggungan peminjaman alat laboratorium yang belum dikembalikan.",
                    ],
                ]);
            }

            $nomorSurat = 'SBT/' . date('Y') . '/' . date('m') . '/' . str_pad((string) $bebasTanggungan->id, 5, '0', STR_PAD_LEFT);

            $bebasTanggungan->update([
                'status' => 'disetujui',
                'nomor_surat' => $nomorSurat,
                'tanggal_disetujui' => now()->toDateString(),
                'disetujui_oleh' => $approver->id,
                'catatan' => $data['catatan'] ?? $bebasTanggungan->catatan,
            ]);
        } else {
            $bebasTanggungan->update([
                'status' => 'ditolak',
                'disetujui_oleh' => $approver->id,
                'catatan' => $data['catatan'] ?? 'Permohonan ditolak oleh petugas laboratorium.',
            ]);
        }

        try {
            AuditLogService::record(
                module: 'SIAKAD',
                action: $isApproved ? 'approve' : 'reject',
                tableName: 'sinapra_bebas_tanggungan',
                recordId: $bebasTanggungan->id,
                oldValues: $oldValues,
                newValues: $bebasTanggungan->fresh()->toArray(),
                request: request()
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return $bebasTanggungan->fresh(['mahasiswa', 'approver']);
    }

    /**
     * Dapatkan query builder Kalibrasi Alat Presisi dengan auto-scoping laboran.
     */
    public function getScopedKalibrasiQuery(User $user): Builder
    {
        $query = AlatKalibrasi::query()->with(['aset.ruangan.gedung']);

        if ($this->isLaboranRestricted($user)) {
            $labRuanganIds = LaboranRuangan::where('user_id', $user->id)->pluck('ruangan_id');
            $query->whereHas('aset', function (Builder $q) use ($labRuanganIds) {
                $q->whereIn('ruangan_id', $labRuanganIds);
            });
        }

        return $query;
    }

    /**
     * Cek apakah akun adalah laboran dan bukan superadmin / admin sarpras.
     */
    private function isLaboranRestricted(User $user): bool
    {
        $hasFullAccess = $user->hasRole('superadmin') || $user->hasRole('admin') || $user->hasRole('admin_sarpras');

        return ! $hasFullAccess && $user->hasRole('admin_laboratorium');
    }
}
