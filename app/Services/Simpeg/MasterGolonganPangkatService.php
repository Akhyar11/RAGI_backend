<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\MasterGolonganPangkat;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MasterGolonganPangkatService
{
    /**
     * Dapatkan daftar master jenjang golongan & pangkat dengan filter dan pagination.
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = MasterGolonganPangkat::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('kode', 'like', "%{$search}%")
                  ->orWhere('nama', 'like', "%{$search}%")
                  ->orWhere('pangkat', 'like', "%{$search}%")
                  ->orWhere('ruang', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $allowedSorts = ['created_at', 'updated_at', 'nama', 'kode', 'urutan', 'pangkat', 'ruang', 'is_active'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts, true) ? $filters['sort_by'] : 'created_at';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortOrder)->orderBy('id', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Dapatkan semua golongan aktif untuk dropdown / options.
     */
    public function getActiveOptions(): Collection
    {
        return MasterGolonganPangkat::where('is_active', true)
            ->orderBy('urutan', 'asc')
            ->orderBy('kode', 'asc')
            ->get();
    }

    /**
     * Buat data master golongan baru.
     */
    public function create(array $data): MasterGolonganPangkat
    {
        return DB::transaction(function () use ($data) {
            return MasterGolonganPangkat::create([
                'kode' => $data['kode'],
                'nama' => $data['nama'],
                'pangkat' => $data['pangkat'] ?? null,
                'ruang' => $data['ruang'] ?? null,
                'urutan' => $data['urutan'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    /**
     * Update data master golongan.
     */
    public function update(MasterGolonganPangkat $golongan, array $data): MasterGolonganPangkat
    {
        return DB::transaction(function () use ($golongan, $data) {
            $golongan->update($data);
            return $golongan->fresh();
        });
    }

    /**
     * Hapus data master golongan (soft delete).
     */
    public function delete(MasterGolonganPangkat $golongan): bool
    {
        return DB::transaction(function () use ($golongan) {
            return (bool) $golongan->delete();
        });
    }
}
