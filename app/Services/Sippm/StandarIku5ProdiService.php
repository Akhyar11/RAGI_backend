<?php

namespace App\Services\Sippm;

use App\Models\Sippm\StandarIku5Prodi;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class StandarIku5ProdiService
{
    /**
     * Get paginated list of IKU 5 standards with filtering and sorting.
     */
    public function getPaginatedList(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = StandarIku5Prodi::with('unitKerja');

        if (!empty($filters['tahun_akademik'])) {
            $query->where('tahun_akademik', $filters['tahun_akademik']);
        }

        if (!empty($filters['unit_kerja_id'])) {
            $query->where('unit_kerja_id', $filters['unit_kerja_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('unitKerja', function ($q) use ($search) {
                $q->where('nama_unit', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%");
            });
        }

        $sortBy = in_array($filters['sort_by'] ?? '', ['created_at', 'tahun_akademik', 'target_publikasi_scopus', 'target_publikasi_sinta'])
            ? $filters['sort_by']
            : 'tahun_akademik';

        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    /**
     * Create or update IKU 5 standard target for a study program.
     */
    public function upsertStandar(array $data): StandarIku5Prodi
    {
        return DB::transaction(function () use ($data) {
            $standar = StandarIku5Prodi::updateOrCreate(
                [
                    'unit_kerja_id' => $data['unit_kerja_id'],
                    'tahun_akademik' => $data['tahun_akademik'],
                ],
                [
                    'target_publikasi_scopus' => $data['target_publikasi_scopus'],
                    'target_publikasi_sinta' => $data['target_publikasi_sinta'],
                    'target_hki_paten' => $data['target_hki_paten'],
                    'target_buku_isbn' => $data['target_buku_isbn'],
                ]
            );

            return $standar->fresh('unitKerja');
        });
    }

    /**
     * Update an existing IKU 5 standard by ID.
     */
    public function updateStandar(StandarIku5Prodi $standar, array $data): StandarIku5Prodi
    {
        return DB::transaction(function () use ($standar, $data) {
            $standar->update($data);
            return $standar->fresh('unitKerja');
        });
    }

    /**
     * Delete an IKU 5 standard record.
     */
    public function deleteStandar(StandarIku5Prodi $standar): bool
    {
        return DB::transaction(function () use ($standar) {
            return (bool) $standar->delete();
        });
    }
}
