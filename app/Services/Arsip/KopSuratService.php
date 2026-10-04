<?php

namespace App\Services\Arsip;

use App\Models\Arsip\KopSurat;
use App\Services\AuditLogService;
use App\Services\Storage\FileStorageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class KopSuratService
{
    public function __construct(
        protected FileStorageService $fileStorage
    ) {}

    /**
     * Mengambil daftar master kop surat dengan filter dan pagination.
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = KopSurat::query();

        if (!empty($filters['versi'])) {
            $query->where('versi', $filters['versi']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nama_institusi', 'like', "%{$search}%")
                  ->orWhere('alamat_institusi', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('tahun_mulai', 'desc')->paginate($perPage);
    }

    /**
     * Mengambil semua master kop surat aktif.
     */
    public function getAllActive(): Collection
    {
        return KopSurat::where('is_active', true)->orderBy('tahun_mulai', 'desc')->get();
    }

    /**
     * Mengambil kop surat yang berlaku berdasarkan tahun surat:
     * - Tahun < 2021: Versi Lama
     * - Tahun >= 2021: Versi Baru
     */
    public function getKopSuratByTahun(int $tahun): ?KopSurat
    {
        $versi = ($tahun < 2021) ? 'lama' : 'baru';

        // Cari yang aktif untuk versi tersebut
        $kop = KopSurat::where('versi', $versi)
            ->where('is_active', true)
            ->first();

        // Fallback jika tidak ditemukan yang aktif spesifik versi
        if (!$kop) {
            $kop = KopSurat::where('is_active', true)->first();
        }

        return $kop;
    }

    /**
     * Menyimpan berkas kop surat baru.
     */
    public function store(array $data, UploadedFile $file): KopSurat
    {
        return DB::transaction(function () use ($data, $file) {
            $storedPath = $this->fileStorage->store($file, 'arsip/kop-surat', private: true);

            $versi = $data['versi'] ?? (($data['tahun_mulai'] ?? 2021) < 2021 ? 'lama' : 'baru');
            $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : true;

            // Jika diset aktif, nonaktifkan kop surat lain dengan versi yang sama
            if ($isActive) {
                KopSurat::where('versi', $versi)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            $kopSurat = KopSurat::create([
                'nama' => $data['nama'],
                'versi' => $versi,
                'tahun_mulai' => $data['tahun_mulai'] ?? ($versi === 'lama' ? 2000 : 2021),
                'tahun_selesai' => $data['tahun_selesai'] ?? ($versi === 'lama' ? 2020 : null),
                'file_path' => $storedPath,
                'nama_institusi' => $data['nama_institusi'] ?? null,
                'alamat_institusi' => $data['alamat_institusi'] ?? null,
                'kontak_institusi' => $data['kontak_institusi'] ?? null,
                'website_institusi' => $data['website_institusi'] ?? null,
                'is_active' => $isActive,
            ]);

            AuditLogService::record(
                module: 'ARSIP',
                action: 'create',
                tableName: 'core_arsip_kop_surat',
                recordId: $kopSurat->id,
                oldValues: [],
                newValues: $kopSurat->toArray()
            );

            return $kopSurat;
        });
    }

    /**
     * Memperbarui data kop surat atau mengganti berkasnya.
     */
    public function update(int $id, array $data, ?UploadedFile $file = null): KopSurat
    {
        return DB::transaction(function () use ($id, $data, $file) {
            $kop = KopSurat::findOrFail($id);
            $oldValues = $kop->toArray();

            if ($file) {
                if ($kop->file_path) {
                    $this->fileStorage->delete($kop->file_path);
                }
                $kop->file_path = $this->fileStorage->store($file, 'arsip/kop-surat', private: true);
            }

            if (isset($data['is_active']) && $data['is_active']) {
                $versi = $data['versi'] ?? $kop->versi;
                KopSurat::where('versi', $versi)
                    ->where('id', '!=', $id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            $kop->update([
                'nama' => $data['nama'] ?? $kop->nama,
                'versi' => $data['versi'] ?? $kop->versi,
                'tahun_mulai' => $data['tahun_mulai'] ?? $kop->tahun_mulai,
                'tahun_selesai' => array_key_exists('tahun_selesai', $data) ? $data['tahun_selesai'] : $kop->tahun_selesai,
                'nama_institusi' => $data['nama_institusi'] ?? $kop->nama_institusi,
                'alamat_institusi' => $data['alamat_institusi'] ?? $kop->alamat_institusi,
                'kontak_institusi' => $data['kontak_institusi'] ?? $kop->kontak_institusi,
                'website_institusi' => $data['website_institusi'] ?? $kop->website_institusi,
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $kop->is_active,
            ]);

            AuditLogService::record(
                module: 'ARSIP',
                action: 'update',
                tableName: 'core_arsip_kop_surat',
                recordId: $kop->id,
                oldValues: $oldValues,
                newValues: $kop->fresh()->toArray()
            );

            return $kop;
        });
    }

    /**
     * Toggle status aktif kop surat.
     */
    public function toggleActive(int $id): KopSurat
    {
        return DB::transaction(function () use ($id) {
            $kop = KopSurat::findOrFail($id);
            $newStatus = !$kop->is_active;

            if ($newStatus) {
                KopSurat::where('versi', $kop->versi)
                    ->where('id', '!=', $id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            $kop->is_active = $newStatus;
            $kop->save();

            AuditLogService::record(
                module: 'ARSIP',
                action: 'update_status',
                tableName: 'core_arsip_kop_surat',
                recordId: $kop->id,
                oldValues: ['is_active' => !$newStatus],
                newValues: ['is_active' => $newStatus]
            );

            return $kop;
        });
    }

    /**
     * Menghapus kop surat (soft delete).
     */
    public function destroy(int $id): bool
    {
        $kop = KopSurat::findOrFail($id);
        $oldValues = $kop->toArray();

        $deleted = $kop->delete();

        if ($deleted) {
            AuditLogService::record(
                module: 'ARSIP',
                action: 'delete',
                tableName: 'core_arsip_kop_surat',
                recordId: $id,
                oldValues: $oldValues,
                newValues: []
            );
        }

        return $deleted;
    }
}
