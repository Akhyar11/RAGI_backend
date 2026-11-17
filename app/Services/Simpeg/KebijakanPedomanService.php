<?php

namespace App\Services\Simpeg;

use App\Models\Arsip\NomorSurat;
use App\Models\Simpeg\KebijakanPedoman;
use App\Models\Simpeg\MasterKategoriKebijakan;
use App\Services\AuditLogService;
use App\Services\Storage\FileStorageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KebijakanPedomanService
{
    public function __construct(
        protected FileStorageService $fileStorage
    ) {}

    /**
     * Mengambil daftar master kategori kebijakan aktif.
     */
    public function getMasters(): Collection
    {
        return MasterKategoriKebijakan::where('is_active', true)
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();
    }

    /**
     * Mengambil daftar kebijakan dan pedoman dengan filter & pagination.
     */
    public function getPaginated(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = KebijakanPedoman::with([
            'kategori:id,kode,nama',
            'nomorSuratArsip:id,nomor_surat,perihal,kode_unit,kode_klasifikasi,tanggal_surat,status',
            'pembuat:id,name,email,username',
        ]);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('nomor_surat', 'like', "%{$search}%")
                    ->orWhere('judul', 'like', "%{$search}%")
                    ->orWhere('pejabat_penetap', 'like', "%{$search}%")
                    ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        $kategoriId = $filters['kategori_kebijakan_id'] ?? ($filters['kategori_id'] ?? null);
        if (!empty($kategoriId)) {
            $query->where('kategori_kebijakan_id', $kategoriId);
        }

        if (!empty($filters['tahun'])) {
            $query->whereYear('tanggal_penetapan', $filters['tahun']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $allowedSortColumns = ['created_at', 'tanggal_penetapan', 'tanggal_berlaku', 'judul', 'nomor_surat'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSortColumns, true) ? $filters['sort_by'] : 'created_at';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    /**
     * Mengambil rincian kebijakan berdasarkan ID.
     */
    public function getById(int $id): KebijakanPedoman
    {
        return KebijakanPedoman::with([
            'kategori:id,kode,nama,deskripsi',
            'nomorSuratArsip:id,nomor_surat,perihal,kode_unit,kode_klasifikasi,tanggal_surat,status,file_surat_path',
            'pembuat:id,name,email,username',
            'pengubah:id,name,email,username',
        ])->findOrFail($id);
    }

    /**
     * Menyimpan dokumen kebijakan dan menyinkronkan berkas ke Modul Arsip.
     */
    public function create(array $data, UploadedFile $file, int $userId): KebijakanPedoman
    {
        return DB::transaction(function () use ($data, $file, $userId) {
            // Resolusi data dari modul arsip jika belum terisi
            $nomorArsip = null;
            if (!empty($data['nomor_surat_arsip_id'])) {
                $nomorArsip = NomorSurat::find($data['nomor_surat_arsip_id']);
            } elseif (!empty($data['nomor_surat'])) {
                $nomorArsip = NomorSurat::where('nomor_surat', $data['nomor_surat'])->first();
                if ($nomorArsip) {
                    $data['nomor_surat_arsip_id'] = $nomorArsip->id;
                }
            }

            if ($nomorArsip) {
                if (empty($data['judul'])) {
                    $data['judul'] = $nomorArsip->perihal ?: $data['nomor_surat'];
                }
                if (empty($data['tanggal_penetapan'])) {
                    $data['tanggal_penetapan'] = $nomorArsip->tanggal_surat 
                        ? \Carbon\Carbon::parse($nomorArsip->tanggal_surat)->format('Y-m-d') 
                        : now()->format('Y-m-d');
                }
            } else {
                if (empty($data['judul'])) {
                    $data['judul'] = $data['nomor_surat'] ?? 'Kebijakan dan Pedoman';
                }
                if (empty($data['tanggal_penetapan'])) {
                    $data['tanggal_penetapan'] = now()->format('Y-m-d');
                }
            }

            if (empty($data['tanggal_berlaku'])) {
                $data['tanggal_berlaku'] = $data['tanggal_penetapan'];
            }
            if (!isset($data['is_active'])) {
                $data['is_active'] = true;
            }

            // 1. Simpan berkas scan ke disk privat
            $path = $this->fileStorage->store($file, 'simpeg/kebijakan_pedoman', private: true);

            // 2. Buat entitas kebijakan dan pedoman
            $data['file_dokumen_path'] = $path;
            $data['created_by'] = $userId;
            $data['updated_by'] = $userId;

            $kebijakan = KebijakanPedoman::create($data);

            // 3. SINKRONISASI OTOMATIS KE MODUL ARSIP
            if ($nomorArsip) {
                $nomorArsip->update([
                    'file_surat_path' => $path,
                    'file_path' => $path,
                    'status' => 'terpakai',
                    'reference_type' => 'kebijakan_pedoman',
                    'reference_id' => $kebijakan->id,
                    'file_surat_uploaded_at' => now(),
                    'file_surat_uploaded_by' => $userId,
                ]);
            }

            // 4. Catat jejak audit
            try {
                AuditLogService::record(
                    module: 'simpeg',
                    action: 'create_kebijakan_pedoman',
                    tableName: 'simpeg_kebijakan_pedoman',
                    recordId: $kebijakan->id,
                    oldValues: null,
                    newValues: $kebijakan->toArray()
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log kebijakan pedoman: ' . $e->getMessage());
            }

            return $kebijakan->load(['kategori', 'nomorSuratArsip', 'pembuat']);
        });
    }

    /**
     * Memperbarui dokumen kebijakan dan menyinkronkan pembaruan berkas ke Arsip jika ada file baru.
     */
    public function update(int $id, array $data, ?UploadedFile $file, int $userId): KebijakanPedoman
    {
        return DB::transaction(function () use ($id, $data, $file, $userId) {
            $kebijakan = KebijakanPedoman::findOrFail($id);
            $oldValues = $kebijakan->getOriginal();

            if ($file) {
                $path = $this->fileStorage->store($file, 'simpeg/kebijakan_pedoman', private: true);
                $data['file_dokumen_path'] = $path;

                // Update juga berkas di Nomor Surat Arsip
                $nomorArsipId = $data['nomor_surat_arsip_id'] ?? $kebijakan->nomor_surat_arsip_id;
                if (!empty($nomorArsipId)) {
                    $nomorSurat = NomorSurat::find($nomorArsipId);
                    if ($nomorSurat) {
                        $nomorSurat->update([
                            'file_surat_path' => $path,
                            'file_path' => $path,
                            'status' => 'terpakai',
                            'file_surat_uploaded_at' => now(),
                            'file_surat_uploaded_by' => $userId,
                        ]);
                    }
                }
            }

            $data['updated_by'] = $userId;
            $kebijakan->fill($data);
            $kebijakan->save();
            $newValues = $kebijakan->getChanges();

            try {
                AuditLogService::record(
                    module: 'simpeg',
                    action: 'update_kebijakan_pedoman',
                    tableName: 'simpeg_kebijakan_pedoman',
                    recordId: $kebijakan->id,
                    oldValues: $oldValues,
                    newValues: $newValues
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log update kebijakan pedoman: ' . $e->getMessage());
            }

            return $kebijakan->load(['kategori', 'nomorSuratArsip', 'pembuat', 'pengubah']);
        });
    }

    /**
     * Soft delete dokumen kebijakan.
     */
    public function delete(int $id, int $userId): bool
    {
        $kebijakan = KebijakanPedoman::findOrFail($id);
        $oldValues = $kebijakan->getOriginal();

        $deleted = $kebijakan->delete();

        try {
            AuditLogService::record(
                module: 'simpeg',
                action: 'delete_kebijakan_pedoman',
                tableName: 'simpeg_kebijakan_pedoman',
                recordId: $id,
                oldValues: $oldValues,
                newValues: ['deleted_at' => now()->toIso8601String()]
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log delete kebijakan pedoman: ' . $e->getMessage());
        }

        return (bool) $deleted;
    }
}
