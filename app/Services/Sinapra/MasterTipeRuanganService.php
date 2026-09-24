<?php

namespace App\Services\Sinapra;

use App\Models\Sinapra\MasterTipeRuangan;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class MasterTipeRuanganService
{
    public function getList(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = MasterTipeRuangan::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('kode', 'like', "%{$search}%")
                  ->orWhere('nama', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $sortBy = $filters['sort_by'] ?? 'urutan';
        $sortOrder = ($filters['sort_order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['id', 'kode', 'nama', 'urutan', 'is_active', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('urutan', 'asc');
        }

        if (!empty($filters['all'])) {
            return $query->get();
        }

        $perPage = min(100, max(1, (int)($filters['per_page'] ?? 15)));
        return $query->paginate($perPage);
    }

    public function create(array $data): MasterTipeRuangan
    {
        return MasterTipeRuangan::create($data);
    }

    public function update(MasterTipeRuangan $tipeRuangan, array $data): MasterTipeRuangan
    {
        $tipeRuangan->update($data);
        return $tipeRuangan->fresh();
    }

    public function delete(MasterTipeRuangan $tipeRuangan): bool
    {
        return (bool) $tipeRuangan->delete();
    }
}
