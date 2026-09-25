<?php

namespace App\Services\Sinapra;

use App\Models\AlatKalibrasi;
use App\Models\BebasTanggungan;
use App\Models\LabBhp;
use App\Models\LabBhpTransaksi;
use App\Models\LaboranRuangan;
use App\Models\PeminjamanAset;
use App\Models\PeminjamanRuangan;
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
     * Mengambil ringkasan peringatan dini (Early Warning System) laboratorium:
     * 1. BHP yang stoknya menipis (stok <= stok_minimum)
     * 2. Instrumen presisi yang masa kalibrasinya kedaluwarsa atau mendekati kedaluwarsa (<= 30 hari)
     * 3. Permohonan peminjaman ruangan yang bentrok atau butuh persetujuan cepat (pending/pending_laboran)
     */
    public function getEarlyWarnings(User $user): array
    {
        // 1. BHP Stok Menipis
        $bhpQuery = $this->getScopedBhpQuery($user)
            ->with(['ruangan.gedung', 'kategoriBhp', 'satuanData'])
            ->whereColumn('stok_saat_ini', '<=', 'stok_minimum')
            ->orderBy('stok_saat_ini', 'asc');

        $bhpCritical = $bhpQuery->get();

        // 2. Kalibrasi Alat Presisi
        $today = now()->toDateString();
        $thirtyDaysAhead = now()->addDays(30)->toDateString();

        $kalibrasiQuery = $this->getScopedKalibrasiQuery($user)
            ->with(['aset.ruangan.gedung', 'vendor'])
            ->where(function (Builder $q) use ($today, $thirtyDaysAhead) {
                $q->where('tanggal_kadaluarsa', '<', $today)
                  ->orWhereBetween('tanggal_kadaluarsa', [$today, $thirtyDaysAhead])
                  ->orWhere('status_kelayakan', '!=', 'laik');
            })
            ->orderBy('tanggal_kadaluarsa', 'asc');

        $kalibrasiCritical = $kalibrasiQuery->get()->map(function (AlatKalibrasi $item) use ($today) {
            $kadaluarsa = $item->tanggal_kadaluarsa ? $item->tanggal_kadaluarsa->format('Y-m-d') : null;
            $isExpired = $kadaluarsa && $kadaluarsa < $today;
            return [
                'id' => $item->id,
                'aset_id' => $item->aset_id,
                'kode_aset' => $item->aset?->kode_aset ?? '-',
                'nama_aset' => $item->aset?->nama ?? '-',
                'ruangan_nama' => $item->aset?->ruangan?->nama ?? '-',
                'gedung_nama' => $item->aset?->ruangan?->gedung?->nama ?? '-',
                'institusi_kalibrasi' => $item->institusi_kalibrasi,
                'nomor_sertifikat' => $item->nomor_sertifikat,
                'tanggal_kadaluarsa' => $kadaluarsa,
                'status_kelayakan' => $item->status_kelayakan,
                'is_expired' => $isExpired,
                'days_remaining' => $kadaluarsa ? (int) now()->diffInDays($item->tanggal_kadaluarsa, false) : 0,
            ];
        });

        // 3. Peminjaman Ruangan yang Butuh Persetujuan Cepat / Berpotensi Bentrok
        $peminjamanQuery = PeminjamanRuangan::with(['ruangan.gedung', 'user'])
            ->whereIn('status', ['pending', 'pending_laboran', 'pending_admin_sinapra'])
            ->whereDate('tanggal', '>=', $today)
            ->orderBy('tanggal', 'asc')
            ->orderBy('jam_mulai', 'asc');

        if ($this->isLaboranRestricted($user)) {
            $labRuanganIds = LaboranRuangan::where('user_id', $user->id)->pluck('ruangan_id');
            $peminjamanQuery->whereIn('ruangan_id', $labRuanganIds);
        }

        $pendingPeminjaman = $peminjamanQuery->get()->map(function (PeminjamanRuangan $p) {
            return [
                'id' => $p->id,
                'ruangan_id' => $p->ruangan_id,
                'ruangan_nama' => $p->ruangan?->nama ?? '-',
                'gedung_nama' => $p->ruangan?->gedung?->nama ?? '-',
                'peminjam_nama' => $p->user?->name ?? 'Civitas Kampus',
                'keperluan' => $p->keperluan,
                'tanggal' => $p->tanggal ? $p->tanggal->format('Y-m-d') : null,
                'jam_mulai' => substr((string) $p->jam_mulai, 0, 5),
                'jam_selesai' => substr((string) $p->jam_selesai, 0, 5),
                'status' => $p->status,
            ];
        });

        return [
            'summary' => [
                'total_bhp_critical' => $bhpCritical->count(),
                'total_kalibrasi_critical' => $kalibrasiCritical->count(),
                'total_pending_peminjaman' => $pendingPeminjaman->count(),
                'total_warnings' => $bhpCritical->count() + $kalibrasiCritical->count() + $pendingPeminjaman->count(),
            ],
            'bhp_critical' => $bhpCritical,
            'kalibrasi_critical' => $kalibrasiCritical,
            'pending_peminjaman' => $pendingPeminjaman,
        ];
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
