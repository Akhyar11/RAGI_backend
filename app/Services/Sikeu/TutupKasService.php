<?php

namespace App\Services\Sikeu;

use App\Models\Sikeu\TutupKasUnit;
use Illuminate\Support\Facades\DB;

class TutupKasService
{
    public function index(array $filters = [], int $perPage = 15)
    {
        $query = TutupKasUnit::query()->with(['unitKas:id,nama_kas,tipe_kas']);

        if (!empty($filters['unit_kas_id'])) {
            $query->where('unit_kas_id', (int) $filters['unit_kas_id']);
        }

        if (!empty($filters['tanggal'])) {
            $query->whereDate('tanggal', $filters['tanggal']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->whereHas('unitKas', fn ($q) => $q->where('nama_kas', 'like', "%{$search}%"));
        }

        $allowedSort = ['tanggal', 'created_at', 'saldo_akhir'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSort) ? $filters['sort_by'] : 'tanggal';
        $sortOrder = ($filters['sort_order'] ?? '') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    public function store(array $data): TutupKasUnit
    {
        return DB::transaction(function () use ($data) {
            $tanggal = substr((string) $data['tanggal'], 0, 10);

            return TutupKasUnit::updateOrCreate(
                ['unit_kas_id' => (int) $data['unit_kas_id'], 'tanggal' => $tanggal],
                [
                    'saldo_awal' => (float) ($data['saldo_awal'] ?? 0),
                    'saldo_masuk' => (float) ($data['saldo_masuk'] ?? 0),
                    'saldo_keluar' => (float) ($data['saldo_keluar'] ?? 0),
                    'saldo_akhir' => (float) ($data['saldo_akhir'] ?? 0),
                    'is_locked' => true,
                    'created_by' => auth()->id(),
                ]
            );
        });
    }

    public function destroy(TutupKasUnit $tutupKas): bool
    {
        return DB::transaction(function () use ($tutupKas) {
            return (bool) $tutupKas->delete();
        });
    }

    public static function isTerkunci(?int $unitKasId, ?string $tanggal): bool
    {
        if (empty($unitKasId) || empty($tanggal)) {
            return false;
        }

        $tgl = substr($tanggal, 0, 10);

        return TutupKasUnit::where('unit_kas_id', $unitKasId)
            ->whereDate('tanggal', '<=', $tgl)
            ->where('is_locked', true)
            ->where(function ($q) use ($tgl) {
                // Kunci berlaku mundur: tutup tanggal T mengunci T dan hari sebelumnya
                // sampai batas tutup sebelumnya. Model sederhana: kunci harian tepat.
                $q->whereDate('tanggal', $tgl);
            })
            ->exists();
    }

    public static function tanggalTerkunciTerakhir(int $unitKasId): ?string
    {
        $row = TutupKasUnit::where('unit_kas_id', $unitKasId)
            ->where('is_locked', true)
            ->orderByDesc('tanggal')
            ->first();

        return $row?->tanggal?->toDateString();
    }
}
