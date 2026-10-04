<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\TandaTanganPegawai;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Storage\FileStorageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TandaTanganService
{
    public function __construct(
        protected FileStorageService $fileStorage
    ) {}

    /**
     * Mengambil daftar tanda tangan dengan filter dan pagination.
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = TandaTanganPegawai::with([
            'user:id,name,email,username',
            'pegawai:id,user_id,nip,nidn,nama_lengkap',
        ]);

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['pegawai_id'])) {
            $query->where('pegawai_id', $filters['pegawai_id']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhere('qr_token', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%")
                         ->orWhere('username', 'like', "%{$search}%");
                  })
                  ->orWhereHas('pegawai', function ($pq) use ($search) {
                      $pq->where('nama_lengkap', 'like', "%{$search}%")
                         ->orWhere('nip', 'like', "%{$search}%")
                         ->orWhere('nidn', 'like', "%{$search}%");
                  });
            });
        }

        $allowedSort = ['id', 'created_at', 'judul', 'is_active'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSort, true) ? $filters['sort_by'] : 'created_at';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    /**
     * Menyimpan tanda tangan baru untuk user/pegawai.
     */
    public function store(array $data, UploadedFile $file): TandaTanganPegawai
    {
        return DB::transaction(function () use ($data, $file) {
            $userId = (int) $data['user_id'];
            $pegawaiId = $data['pegawai_id'] ?? null;

            // Jika pegawai_id tidak disediakan, coba resolve dari user_id
            if (!$pegawaiId) {
                $pegawai = Pegawai::where('user_id', $userId)->first();
                $pegawaiId = $pegawai?->id;
            }

            // Simpan file ke private disk via FileStorageService
            $storedPath = $this->fileStorage->store($file, 'simpeg/tanda-tangan', private: true);

            $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : true;

            // Jika diset aktif, nonaktifkan tanda tangan lama milik user ini
            if ($isActive) {
                TandaTanganPegawai::where('user_id', $userId)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            $tandaTangan = TandaTanganPegawai::create([
                'user_id' => $userId,
                'pegawai_id' => $pegawaiId,
                'file_path' => $storedPath,
                'tipe' => $data['tipe'] ?? 'gambar_spesimen',
                'judul' => $data['judul'] ?? 'Tanda Tangan Utama',
                'qr_token' => Str::uuid()->toString(),
                'is_active' => $isActive,
            ]);

            AuditLogService::record(
                module: 'SIMPEG',
                action: 'create',
                tableName: 'simpeg_tanda_tangan',
                recordId: $tandaTangan->id,
                oldValues: [],
                newValues: $tandaTangan->toArray()
            );

            return $tandaTangan->load(['user', 'pegawai']);
        });
    }

    /**
     * Memperbarui informasi tanda tangan.
     */
    public function update(TandaTanganPegawai $tandaTangan, array $data, ?UploadedFile $file = null): TandaTanganPegawai
    {
        return DB::transaction(function () use ($tandaTangan, $data, $file) {
            $oldValues = $tandaTangan->toArray();

            if ($file) {
                // Hapus berkas lama jika ada
                if ($tandaTangan->file_path) {
                    $this->fileStorage->delete($tandaTangan->file_path);
                }
                $storedPath = $this->fileStorage->store($file, 'simpeg/tanda-tangan', private: true);
                $tandaTangan->file_path = $storedPath;
            }

            if (isset($data['judul'])) {
                $tandaTangan->judul = $data['judul'];
            }

            if (isset($data['tipe'])) {
                $tandaTangan->tipe = $data['tipe'];
            }

            if (isset($data['is_active'])) {
                $isActive = (bool) $data['is_active'];
                if ($isActive && !$tandaTangan->is_active) {
                    // Nonaktifkan tanda tangan aktif lain untuk user ini
                    TandaTanganPegawai::where('user_id', $tandaTangan->user_id)
                        ->where('id', '!=', $tandaTangan->id)
                        ->where('is_active', true)
                        ->update(['is_active' => false]);
                }
                $tandaTangan->is_active = $isActive;
            }

            $tandaTangan->save();

            AuditLogService::record(
                module: 'SIMPEG',
                action: 'update',
                tableName: 'simpeg_tanda_tangan',
                recordId: $tandaTangan->id,
                oldValues: $oldValues,
                newValues: $tandaTangan->fresh()->toArray()
            );

            return $tandaTangan->fresh(['user', 'pegawai']);
        });
    }

    /**
     * Mengaktifkan/menonaktifkan status tanda tangan.
     */
    public function toggleActive(TandaTanganPegawai $tandaTangan): TandaTanganPegawai
    {
        return DB::transaction(function () use ($tandaTangan) {
            $oldValues = $tandaTangan->toArray();
            $newStatus = !$tandaTangan->is_active;

            if ($newStatus) {
                TandaTanganPegawai::where('user_id', $tandaTangan->user_id)
                    ->where('id', '!=', $tandaTangan->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            $tandaTangan->is_active = $newStatus;
            $tandaTangan->save();

            AuditLogService::record(
                module: 'SIMPEG',
                action: 'update',
                tableName: 'simpeg_tanda_tangan',
                recordId: $tandaTangan->id,
                oldValues: $oldValues,
                newValues: $tandaTangan->fresh()->toArray()
            );

            return $tandaTangan->fresh(['user', 'pegawai']);
        });
    }

    /**
     * Menghapus tanda tangan.
     */
    public function destroy(TandaTanganPegawai $tandaTangan): void
    {
        DB::transaction(function () use ($tandaTangan) {
            $oldValues = $tandaTangan->toArray();

            if ($tandaTangan->file_path) {
                $this->fileStorage->delete($tandaTangan->file_path);
            }

            $tandaTangan->delete();

            AuditLogService::record(
                module: 'SIMPEG',
                action: 'delete',
                tableName: 'simpeg_tanda_tangan',
                recordId: $tandaTangan->id,
                oldValues: $oldValues,
                newValues: []
            );
        });
    }

    /**
     * Mengambil tanda tangan aktif untuk suatu user.
     */
    public function getActiveByUser(int $userId): ?TandaTanganPegawai
    {
        return TandaTanganPegawai::with(['user', 'pegawai'])
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->latest()
            ->first();
    }

    /**
     * Mengambil tanda tangan aktif untuk suatu pegawai.
     */
    public function getActiveByPegawai(int $pegawaiId): ?TandaTanganPegawai
    {
        return TandaTanganPegawai::with(['user', 'pegawai'])
            ->where('pegawai_id', $pegawaiId)
            ->where('is_active', true)
            ->latest()
            ->first();
    }
}
