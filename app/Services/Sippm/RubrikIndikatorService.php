<?php

namespace App\Services\Sippm;

use App\Models\Sippm\RubrikIndikator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RubrikIndikatorService
{
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = RubrikIndikator::query();

        if (!empty($filters['tipe_reviewer'])) {
            $query->where('tipe_reviewer', $filters['tipe_reviewer']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nama_indikator', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $sortBy = in_array($filters['sort_by'] ?? '', ['id', 'tipe_reviewer', 'nama_indikator', 'bobot', 'skor_minimal_default', 'created_at'])
            ? $filters['sort_by']
            : 'id';

        $sortOrder = strtolower($filters['sort_order'] ?? '') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    public function create(array $data): RubrikIndikator
    {
        return RubrikIndikator::create($data);
    }

    public function update(RubrikIndikator $rubrik, array $data): RubrikIndikator
    {
        $rubrik->update($data);
        return $rubrik;
    }

    public function delete(RubrikIndikator $rubrik): bool
    {
        return $rubrik->delete();
    }
}
