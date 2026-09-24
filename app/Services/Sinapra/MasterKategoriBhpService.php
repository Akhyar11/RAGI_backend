<?php

namespace App\Services\Sinapra;

use App\Models\Sinapra\MasterKategoriBhp;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MasterKategoriBhpService
{
    public function getAll(array $params = []): LengthAwarePaginator|Collection
    {
        $query = MasterKategoriBhp::query()->withCount('labBhp');

        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('kode', 'like', "%{$search}%")
                  ->orWhere('nama', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        if (isset($params['is_active']) && $params['is_active'] !== '' && $params['is_active'] !== null) {
            $isActive = filter_var($params['is_active'], FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        $sortBy = $params['sort_by'] ?? 'urutan';
        $sortOrder = $params['sort_order'] ?? 'asc';
        $allowedSorts = ['id', 'kode', 'nama', 'deskripsi', 'is_active', 'urutan', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('urutan', 'asc')->orderBy('nama', 'asc');
        }

        if (!empty($params['all'])) {
            return $query->get();
        }

        $perPage = min((int) ($params['per_page'] ?? 15), 100);
        return $query->paginate($perPage);
    }

    public function getById(int $id): MasterKategoriBhp
    {
        return MasterKategoriBhp::withCount('labBhp')->findOrFail($id);
    }

    public function create(array $data): MasterKategoriBhp
    {
        if (empty($data['urutan'])) {
            $data['urutan'] = (MasterKategoriBhp::max('urutan') ?? 0) + 1;
        }

        return MasterKategoriBhp::create($data);
    }

    public function update(int $id, array $data): MasterKategoriBhp
    {
        $kategori = MasterKategoriBhp::findOrFail($id);
        $kategori->update($data);
        return $this->getById($id);
    }

    public function delete(int $id): bool
    {
        $kategori = MasterKategoriBhp::withCount('labBhp')->findOrFail($id);

        if ($kategori->lab_bhp_count > 0) {
            throw new \Exception('Kategori BHP tidak dapat dihapus karena masih digunakan oleh data stok BHP.');
        }

        return (bool) $kategori->delete();
    }
}
