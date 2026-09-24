<?php

namespace App\Services\Sinapra;

use App\Models\Aset;
use App\Models\DisposalAset;
use App\Models\LaboranRuangan;
use App\Models\MutasiAset;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuditMutasiDisposalService
{
    /**
     * Cek apakah user adalah laboran yang ditugaskan di ruangan tertentu
     */
    public function isLaboranAssigned(int $userId, int $ruanganId): bool
    {
        return LaboranRuangan::where('user_id', $userId)
            ->where('ruangan_id', $ruanganId)
            ->exists();
    }

    /**
     * Dapatkan daftar ruangan ID yang ditugaskan ke laboran
     */
    public function getAssignedRuanganIds(int $userId): array
    {
        return LaboranRuangan::where('user_id', $userId)->pluck('ruangan_id')->toArray();
    }

    // ─────────────────────────────────────────────────────────────
    // 1. STOCK OPNAME
    // ─────────────────────────────────────────────────────────────

    public function getStockOpnameList(array $filters, User $user, int $perPage = 15): LengthAwarePaginator
    {
        $query = StockOpname::with(['ruangan.gedung', 'petugas']);

        // Scoping role laboran
        $isLaboran = $user->hasRole('admin_laboratorium') && !$user->hasRole('admin_sarpras') && !$user->hasRole('superadmin');
        if ($isLaboran) {
            $assignedIds = $this->getAssignedRuanganIds($user->id);
            $query->whereIn('ruangan_id', $assignedIds);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('kode_opname', 'like', "%{$search}%")
                  ->orWhere('catatan', 'like', "%{$search}%")
                  ->orWhereHas('ruangan', function ($rq) use ($search) {
                      $rq->where('nama', 'like', "%{$search}%")
                         ->orWhere('kode', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($filters['ruangan_id'])) {
            $query->where('ruangan_id', $filters['ruangan_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = strtolower($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (in_array($sortBy, ['id', 'kode_opname', 'tanggal_mulai', 'tanggal_selesai', 'status', 'created_at'])) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    public function getStockOpnameDetail(int $id, User $user): StockOpname
    {
        $opname = StockOpname::with(['ruangan.gedung', 'petugas', 'items.aset'])->findOrFail($id);

        $isLaboran = $user->hasRole('admin_laboratorium') && !$user->hasRole('admin_sarpras') && !$user->hasRole('superadmin');
        if ($isLaboran && !$this->isLaboranAssigned($user->id, $opname->ruangan_id)) {
            throw new HttpException(403, 'Anda tidak memiliki wewenang untuk melihat audit laboratorium ini.');
        }

        return $opname;
    }

    public function createStockOpname(array $data, User $user): StockOpname
    {
        $ruanganId = (int) $data['ruangan_id'];

        $isLaboran = $user->hasRole('admin_laboratorium') && !$user->hasRole('admin_sarpras') && !$user->hasRole('superadmin');
        if ($isLaboran && !$this->isLaboranAssigned($user->id, $ruanganId)) {
            throw new HttpException(403, 'Anda hanya dapat membuat stock opname untuk laboratorium yang Anda kelola.');
        }

        return DB::transaction(function () use ($data, $user, $ruanganId) {
            $tahunBulan = date('Ym');
            $countThisMonth = StockOpname::whereYear('created_at', date('Y'))
                ->whereMonth('created_at', date('m'))
                ->count();
            $seq = str_pad($countThisMonth + 1, 4, '0', STR_PAD_LEFT);
            $kodeOpname = "OPN-{$tahunBulan}-{$seq}";

            $opname = StockOpname::create([
                'ruangan_id' => $ruanganId,
                'kode_opname' => $kodeOpname,
                'tanggal_mulai' => $data['tanggal_mulai'] ?? date('Y-m-d'),
                'petugas_user_id' => $user->id,
                'status' => 'berlangsung',
                'catatan' => $data['catatan'] ?? null,
            ]);

            // Otomatis snapshot seluruh aset aktif yang berada di ruangan ini
            $asets = Aset::where('ruangan_id', $ruanganId)->get();
            foreach ($asets as $aset) {
                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'aset_id' => $aset->id,
                    'status_keberadaan' => 'sesuai',
                    'kondisi_fisik' => $aset->kondisi ?? 'baik',
                    'catatan' => null,
                ]);
            }

            return $opname->load(['ruangan', 'items.aset']);
        });
    }

    public function updateStockOpnameItem(int $opnameId, int $itemId, array $data, User $user): StockOpnameItem
    {
        $opname = StockOpname::findOrFail($opnameId);

        $isLaboran = $user->hasRole('admin_laboratorium') && !$user->hasRole('admin_sarpras') && !$user->hasRole('superadmin');
        if ($isLaboran && !$this->isLaboranAssigned($user->id, $opname->ruangan_id)) {
            throw new HttpException(403, 'Anda tidak memiliki wewenang untuk mengubah data audit opname lab ini.');
        }

        if ($opname->status === 'selesai') {
            throw new HttpException(422, 'Sesi stock opname telah ditutup / selesai.');
        }

        $item = StockOpnameItem::where('stock_opname_id', $opnameId)->findOrFail($itemId);
        $item->update([
            'status_keberadaan' => $data['status_keberadaan'] ?? $item->status_keberadaan,
            'kondisi_fisik' => $data['kondisi_fisik'] ?? $item->kondisi_fisik,
            'catatan' => $data['catatan'] ?? $item->catatan,
        ]);

        return $item->load('aset');
    }

    public function finishStockOpname(int $opnameId, array $data, User $user): StockOpname
    {
        $opname = StockOpname::with('items')->findOrFail($opnameId);

        $isLaboran = $user->hasRole('admin_laboratorium') && !$user->hasRole('admin_sarpras') && !$user->hasRole('superadmin');
        if ($isLaboran && !$this->isLaboranAssigned($user->id, $opname->ruangan_id)) {
            throw new HttpException(403, 'Anda tidak memiliki wewenang untuk menutup stock opname ini.');
        }

        return DB::transaction(function () use ($opname, $data) {
            $opname->update([
                'status' => 'selesai',
                'tanggal_selesai' => date('Y-m-d'),
                'catatan' => $data['catatan'] ?? $opname->catatan,
            ]);

            // Sinkronisasi kondisi fisik aset di tabel sinapra_aset
            foreach ($opname->items as $item) {
                if ($item->status_keberadaan === 'tidak_ditemukan') {
                    Aset::where('id', $item->aset_id)->update([
                        'kondisi' => 'hilang',
                        'is_borrowable' => false,
                    ]);
                } elseif ($item->status_keberadaan === 'rusak') {
                    Aset::where('id', $item->aset_id)->update([
                        'kondisi' => in_array($item->kondisi_fisik, ['rusak_ringan', 'rusak_berat']) ? $item->kondisi_fisik : 'rusak_berat',
                        'status' => 'maintenance',
                        'is_borrowable' => false,
                    ]);
                } else {
                    Aset::where('id', $item->aset_id)->update([
                        'kondisi' => in_array($item->kondisi_fisik, ['baik', 'rusak_ringan', 'rusak_berat', 'hilang']) ? $item->kondisi_fisik : 'baik',
                    ]);
                }
            }

            return $opname->load(['ruangan', 'items.aset']);
        });
    }

    // ─────────────────────────────────────────────────────────────
    // 2. MUTASI ASET ANTAR-RUANGAN
    // ─────────────────────────────────────────────────────────────

    public function getMutasiList(array $filters, User $user, int $perPage = 15): LengthAwarePaginator
    {
        $query = MutasiAset::with(['aset', 'ruanganAsal.gedung', 'ruanganTujuan.gedung', 'pemohon', 'approver']);

        $isLaboran = $user->hasRole('admin_laboratorium') && !$user->hasRole('admin_sarpras') && !$user->hasRole('superadmin');
        if ($isLaboran) {
            $assignedIds = $this->getAssignedRuanganIds($user->id);
            $query->where(function ($q) use ($assignedIds) {
                $q->whereIn('ruangan_asal_id', $assignedIds)
                  ->orWhereIn('ruangan_tujuan_id', $assignedIds);
            });
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('alasan', 'like', "%{$search}%")
                  ->orWhere('catatan', 'like', "%{$search}%")
                  ->orWhereHas('aset', function ($aq) use ($search) {
                      $aq->where('nama', 'like', "%{$search}%")
                         ->orWhere('kode_aset', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($filters['ruangan_asal_id'])) {
            $query->where('ruangan_asal_id', $filters['ruangan_asal_id']);
        }

        if (!empty($filters['ruangan_tujuan_id'])) {
            $query->where('ruangan_tujuan_id', $filters['ruangan_tujuan_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = strtolower($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (in_array($sortBy, ['id', 'tanggal_pengajuan', 'tanggal_disetujui', 'status', 'created_at'])) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    public function createMutasi(array $data, User $user): MutasiAset
    {
        $aset = Aset::findOrFail($data['aset_id']);
        $ruanganAsalId = (int) $aset->ruangan_id;
        $ruanganTujuanId = (int) $data['ruangan_tujuan_id'];

        if ($ruanganAsalId === $ruanganTujuanId) {
            throw new HttpException(422, 'Ruangan tujuan mutasi tidak boleh sama dengan ruangan asal aset.');
        }

        $isLaboran = $user->hasRole('admin_laboratorium') && !$user->hasRole('admin_sarpras') && !$user->hasRole('superadmin');
        if ($isLaboran && !$this->isLaboranAssigned($user->id, $ruanganAsalId)) {
            throw new HttpException(403, 'Anda hanya dapat mengajukan mutasi untuk aset yang berada di laboratorium binaan Anda.');
        }

        return MutasiAset::create([
            'aset_id' => $aset->id,
            'ruangan_asal_id' => $ruanganAsalId,
            'ruangan_tujuan_id' => $ruanganTujuanId,
            'pemohon_id' => $user->id,
            'tanggal_pengajuan' => date('Y-m-d'),
            'status' => 'diajukan',
            'alasan' => $data['alasan'],
            'catatan' => $data['catatan'] ?? null,
        ])->load(['aset', 'ruanganAsal', 'ruanganTujuan']);
    }

    public function approveMutasi(int $id, array $data, User $user): MutasiAset
    {
        $mutasi = MutasiAset::with(['aset', 'ruanganAsal', 'ruanganTujuan'])->findOrFail($id);

        if ($mutasi->status !== 'diajukan') {
            throw new HttpException(422, 'Permohonan mutasi ini sudah diproses sebelumnya.');
        }

        $isLaboran = $user->hasRole('admin_laboratorium') && !$user->hasRole('admin_sarpras') && !$user->hasRole('superadmin');
        // Laboran tujuan atau admin sarpras yang bisa menyetujui penerimaan barang
        if ($isLaboran && !$this->isLaboranAssigned($user->id, $mutasi->ruangan_tujuan_id)) {
            throw new HttpException(403, 'Hanya penanggung jawab ruangan/laboratorium tujuan atau Admin Sarpras yang dapat memproses penerimaan mutasi.');
        }

        return DB::transaction(function () use ($mutasi, $data, $user) {
            $isApproved = filter_var($data['is_approved'] ?? true, FILTER_VALIDATE_BOOLEAN);

            $mutasi->update([
                'status' => $isApproved ? 'disetujui' : 'ditolak',
                'tanggal_disetujui' => date('Y-m-d'),
                'disetujui_oleh' => $user->id,
                'catatan' => $data['catatan'] ?? $mutasi->catatan,
            ]);

            // Jika disetujui, update lokasi ruangan_id di aset
            if ($isApproved) {
                $mutasi->aset->update([
                    'ruangan_id' => $mutasi->ruangan_tujuan_id,
                ]);
            }

            return $mutasi->load(['aset', 'ruanganAsal', 'ruanganTujuan', 'approver']);
        });
    }

    // ─────────────────────────────────────────────────────────────
    // 3. PENGHAPUSAN / DISPOSAL ASET
    // ─────────────────────────────────────────────────────────────

    public function getDisposalList(array $filters, User $user, int $perPage = 15): LengthAwarePaginator
    {
        $query = DisposalAset::with(['aset.ruangan', 'pemohon', 'approver']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nomor_bap', 'like', "%{$search}%")
                  ->orWhere('alasan', 'like', "%{$search}%")
                  ->orWhereHas('aset', function ($aq) use ($search) {
                      $aq->where('nama', 'like', "%{$search}%")
                         ->orWhere('kode_aset', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($filters['metode_disposal'])) {
            $query->where('metode_disposal', $filters['metode_disposal']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = strtolower($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (in_array($sortBy, ['id', 'tanggal_disposal', 'nilai_residu', 'status', 'created_at'])) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    public function createDisposal(array $data, User $user): DisposalAset
    {
        $aset = Aset::findOrFail($data['aset_id']);

        if ($aset->status === 'dihapus') {
            throw new HttpException(422, 'Aset ini telah diproses pemutihan / disposal sebelumnya.');
        }

        $nomorBap = $data['nomor_bap'] ?? ('BAP-DISP/' . date('Y/m/') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT));

        return DisposalAset::create([
            'aset_id' => $aset->id,
            'nomor_bap' => $nomorBap,
            'tanggal_disposal' => $data['tanggal_disposal'] ?? date('Y-m-d'),
            'metode_disposal' => $data['metode_disposal'] ?? 'rusak_total',
            'nilai_residu' => $data['nilai_residu'] ?? 0,
            'alasan' => $data['alasan'],
            'diajukan_oleh' => $user->id,
            'status' => 'diajukan',
            'catatan' => $data['catatan'] ?? null,
        ])->load(['aset', 'pemohon']);
    }

    public function approveDisposal(int $id, array $data, User $user): DisposalAset
    {
        $disposal = DisposalAset::with('aset')->findOrFail($id);

        if ($disposal->status !== 'diajukan') {
            throw new HttpException(422, 'Usulan disposal aset ini telah diproses sebelumnya.');
        }

        // Hanya admin_sarpras dan superadmin yang dapat menyetujui pemutihan aset
        if (!$user->hasRole('admin_sarpras') && !$user->hasRole('superadmin')) {
            throw new HttpException(403, 'Hanya Admin Sarpras atau Superadmin yang berwenang menyetujui pemutihan/penghapusan aset.');
        }

        return DB::transaction(function () use ($disposal, $data, $user) {
            $isApproved = filter_var($data['is_approved'] ?? true, FILTER_VALIDATE_BOOLEAN);

            $disposal->update([
                'status' => $isApproved ? 'disetujui' : 'ditolak',
                'disetujui_oleh' => $user->id,
                'catatan' => $data['catatan'] ?? $disposal->catatan,
            ]);

            // Jika disetujui, ubah status aset menjadi 'dihapus' dan is_borrowable = false
            if ($isApproved) {
                $disposal->aset->update([
                    'status' => 'dihapus',
                    'is_borrowable' => false,
                ]);
            }

            return $disposal->load(['aset', 'pemohon', 'approver']);
        });
    }
}
