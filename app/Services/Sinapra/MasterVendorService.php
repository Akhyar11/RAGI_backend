<?php

namespace App\Services\Sinapra;

use App\Models\Sinapra\MasterVendor;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MasterVendorService
{
    public function getAll(array $params = []): LengthAwarePaginator|Collection
    {
        $query = MasterVendor::query();

        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('kode', 'like', "%{$search}%")
                  ->orWhere('nama', 'like', "%{$search}%")
                  ->orWhere('alamat', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('pic_nama', 'like', "%{$search}%");
            });
        }

        if (!empty($params['jenis_rekanan'])) {
            $query->where('jenis_rekanan', $params['jenis_rekanan']);
        }

        if (isset($params['is_active']) && $params['is_active'] !== '' && $params['is_active'] !== null) {
            $isActive = filter_var($params['is_active'], FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        $sortBy = $params['sort_by'] ?? 'urutan';
        $sortOrder = $params['sort_order'] ?? 'asc';
        $allowedSorts = ['id', 'kode', 'nama', 'jenis_rekanan', 'alamat', 'telepon', 'email', 'pic_nama', 'is_active', 'urutan', 'created_at'];

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

    public function getById(int $id): MasterVendor
    {
        return MasterVendor::findOrFail($id);
    }

    public function create(array $data): MasterVendor
    {
        if (empty($data['urutan'])) {
            $data['urutan'] = (MasterVendor::max('urutan') ?? 0) + 1;
        }

        return MasterVendor::create($data);
    }

    public function update(int $id, array $data): MasterVendor
    {
        $vendor = $this->getById($id);
        $vendor->update($data);
        return $vendor;
    }

    public function delete(int $id): bool
    {
        $vendor = $this->getById($id);
        return (bool) $vendor->delete();
    }
}
