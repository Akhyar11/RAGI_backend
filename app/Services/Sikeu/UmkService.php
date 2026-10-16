<?php

namespace App\Services\Sikeu;

use App\Models\Sikeu\UangMukaKerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UmkService
{
    public function index(array $filters = [], int $perPage = 15)
    {
        $query = UangMukaKerja::query()->with(['unitKas:id,nama_kas,tipe_kas']);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('nama_penerima', 'like', "%{$search}%")
                    ->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['status']) && in_array($filters['status'], ['aktif', 'selesai'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['unit_kas_id'])) {
            $query->where('unit_kas_id', (int) $filters['unit_kas_id']);
        }

        if (!empty($filters['tanggal_awal'])) {
            $query->whereDate('tanggal', '>=', $filters['tanggal_awal']);
        }

        if (!empty($filters['tanggal_akhir'])) {
            $query->whereDate('tanggal', '<=', $filters['tanggal_akhir']);
        }

        $allowedSort = ['tanggal', 'total', 'created_at', 'nama_penerima'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSort) ? $filters['sort_by'] : 'tanggal';
        $sortOrder = ($filters['sort_order'] ?? '') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    public function store(array $data, ?Request $request = null): UangMukaKerja
    {
        return DB::transaction(function () use ($data, $request) {
            if (!empty($data['unit_kas_id']) && !empty($data['tanggal'])
                && TutupKasService::isTerkunci((int) $data['unit_kas_id'], (string) $data['tanggal'])) {
                throw new \RuntimeException('Unit kas sudah tutup buku pada tanggal tersebut. UMK tidak dapat dicatat mundur.');
            }

            $umk = UangMukaKerja::create([
                'unit_kas_id' => $data['unit_kas_id'] ?? null,
                'nama_penerima' => trim($data['nama_penerima']),
                'tanggal' => substr((string) $data['tanggal'], 0, 10),
                'tanggal_selesai' => !empty($data['tanggal_selesai']) ? substr((string) $data['tanggal_selesai'], 0, 10) : null,
                'keterangan' => $data['keterangan'] ?? null,
                'total' => (float) $data['total'],
                'status' => 'aktif',
                'created_by' => auth()->id(),
            ]);

            return $umk->load('unitKas');
        });
    }

    public function update(UangMukaKerja $umk, array $data): UangMukaKerja
    {
        return DB::transaction(function () use ($umk, $data) {
            if ($umk->status === 'selesai') {
                throw new \RuntimeException('UMK yang sudah selesai tidak dapat diubah. Buat UMK baru bila diperlukan.');
            }

            $umk->update([
                'unit_kas_id' => array_key_exists('unit_kas_id', $data) ? $data['unit_kas_id'] : $umk->unit_kas_id,
                'nama_penerima' => isset($data['nama_penerima']) ? trim($data['nama_penerima']) : $umk->nama_penerima,
                'tanggal' => isset($data['tanggal']) ? substr((string) $data['tanggal'], 0, 10) : $umk->tanggal,
                'tanggal_selesai' => array_key_exists('tanggal_selesai', $data)
                    ? (!empty($data['tanggal_selesai']) ? substr((string) $data['tanggal_selesai'], 0, 10) : null)
                    : $umk->tanggal_selesai,
                'keterangan' => array_key_exists('keterangan', $data) ? $data['keterangan'] : $umk->keterangan,
                'total' => isset($data['total']) ? (float) $data['total'] : $umk->total,
            ]);

            return $umk->fresh('unitKas');
        });
    }

    public function updateStatus(UangMukaKerja $umk, string $status, ?string $tanggalSelesai = null): UangMukaKerja
    {
        return DB::transaction(function () use ($umk, $status, $tanggalSelesai) {
            if (!in_array($status, ['aktif', 'selesai'], true)) {
                throw new \InvalidArgumentException('Status UMK tidak valid.');
            }

            $umk->status = $status;
            $umk->tanggal_selesai = $status === 'selesai'
                ? (substr((string) ($tanggalSelesai ?? now()->toDateString()), 0, 10))
                : null;
            $umk->save();

            return $umk->fresh('unitKas');
        });
    }

    public function destroy(UangMukaKerja $umk): bool
    {
        return DB::transaction(function () use ($umk) {
            if ($umk->status === 'selesai') {
                throw new \RuntimeException('UMK yang sudah selesai tidak dapat dihapus.');
            }

            return (bool) $umk->delete();
        });
    }
}
