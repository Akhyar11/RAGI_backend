<?php

namespace App\Services\Arsip;

use App\Models\Arsip\NomorSurat;
use App\Services\AuditLogService;
use App\Services\Storage\FileStorageService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NomorSuratService
{
    public function __construct(
        protected KopSuratService $kopSuratService,
        protected FileStorageService $fileStorage
    ) {}

    /**
     * Konversi angka bulan (1-12) ke angka Romawi.
     */
    public static function formatBulanRomawi(int $bulan): string
    {
        $romawiMap = [
            1 => 'I',
            2 => 'II',
            3 => 'III',
            4 => 'IV',
            5 => 'V',
            6 => 'VI',
            7 => 'VII',
            8 => 'VIII',
            9 => 'IX',
            10 => 'X',
            11 => 'XI',
            12 => 'XII',
        ];

        return $romawiMap[$bulan] ?? 'I';
    }

    /**
     * Mengambil nomor urut berikutnya untuk tahun tertentu.
     */
    public function getNextNomorUrut(int $tahun): int
    {
        $maxUrut = NomorSurat::where('tahun', $tahun)
            ->max('nomor_urut');

        return ($maxUrut ?? 0) + 1;
    }

    /**
     * Format string nomor surat standar.
     * Contoh: 2/DII/INDO/X/2026
     */
    public static function formatNomorSurat(int $nomorUrut, string $kodeUnit, string $kodeKlasifikasi, string $bulanRomawi, int $tahun): string
    {
        $unit = strtoupper(trim($kodeUnit));
        $klasifikasi = strtoupper(trim($kodeKlasifikasi));
        return "{$nomorUrut}/{$klasifikasi}/{$unit}/{$bulanRomawi}/{$tahun}";
    }

    /**
     * Mengambil daftar nomor surat dengan filter & pagination.
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = NomorSurat::with([
            'pembuat:id,name,email,username',
            'pegawai:id,nama_lengkap,nip,nidn,unit_kerja_id',
            'pegawai.unitKerja:id,nama',
            'fileUploader:id,name,email',
            'kopSurat:id,nama,versi,tahun_mulai,tahun_selesai,file_path,nama_institusi',
            'request:id,kode_request,module_origin,status',
        ]);

        if (!empty($filters['tahun'])) {
            $query->where('tahun', $filters['tahun']);
        }

        if (!empty($filters['kode_unit'])) {
            $query->where('kode_unit', $filters['kode_unit']);
        }

        if (!empty($filters['kode_klasifikasi'])) {
            $query->where('kode_klasifikasi', $filters['kode_klasifikasi']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['module_origin'])) {
            $query->where('module_origin', $filters['module_origin']);
        }

        if (!empty($filters['pegawai_id'])) {
            $query->where('pegawai_id', $filters['pegawai_id']);
        }

        if (isset($filters['has_file'])) {
            if ($filters['has_file'] === true || $filters['has_file'] === '1' || $filters['has_file'] === 'true') {
                $query->whereNotNull('file_surat_path');
            } elseif ($filters['has_file'] === false || $filters['has_file'] === '0' || $filters['has_file'] === 'false') {
                $query->whereNull('file_surat_path');
            }
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nomor_surat', 'like', "%{$search}%")
                  ->orWhere('perihal', 'like', "%{$search}%")
                  ->orWhere('tujuan', 'like', "%{$search}%")
                  ->orWhere('catatan', 'like', "%{$search}%")
                  ->orWhereHas('pegawai', function ($pq) use ($search) {
                      $pq->where('nama_lengkap', 'like', "%{$search}%")
                         ->orWhere('nip', 'like', "%{$search}%");
                  });
            });
        }

        $allowedSort = ['id', 'nomor_urut', 'tanggal_surat', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSort, true) ? $filters['sort_by'] : 'created_at';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    /**
     * Generate 1 nomor surat (satuan).
     */
    public function generateSatuan(array $data, int $userId): NomorSurat
    {
        return DB::transaction(function () use ($data, $userId) {
            $tanggal = Carbon::parse($data['tanggal_surat'] ?? now());
            $tahun = (int) $tanggal->year;
            $bulanRomawi = self::formatBulanRomawi((int) $tanggal->month);

            // Kunci baris untuk mengamankan nomor urut berikutnya tanpa collision
            $maxUrut = DB::table('core_arsip_nomor_surat')
                ->where('tahun', $tahun)
                ->lockForUpdate()
                ->max('nomor_urut');

            $nomorUrut = ($maxUrut ?? 0) + 1;
            $kodeUnit = strtoupper(trim($data['kode_unit']));
            $kodeKlasifikasi = strtoupper(trim($data['kode_klasifikasi']));

            $formatted = self::formatNomorSurat($nomorUrut, $kodeUnit, $kodeKlasifikasi, $bulanRomawi, $tahun);

            // Dapatkan kop surat yang berlaku sesuai tahun surat
            $kop = $this->kopSuratService->getKopSuratByTahun($tahun);

            $filePath = null;
            if (!empty($data['file_surat']) && $data['file_surat'] instanceof UploadedFile) {
                $filePath = $this->fileStorage->store($data['file_surat'], 'arsip/dokumen-surat', private: true);
            }

            $nomorSurat = NomorSurat::create([
                'nomor_surat' => $formatted,
                'nomor_urut' => $nomorUrut,
                'kode_unit' => $kodeUnit,
                'kode_klasifikasi' => $kodeKlasifikasi,
                'bulan_romawi' => $bulanRomawi,
                'tahun' => $tahun,
                'tanggal_surat' => $tanggal->toDateString(),
                'perihal' => $data['perihal'],
                'tujuan' => $data['tujuan'] ?? null,
                'status' => $data['status'] ?? 'terpakai',
                'module_origin' => $data['module_origin'] ?? 'arsip',
                'request_id' => $data['request_id'] ?? null,
                'pegawai_id' => !empty($data['pegawai_id']) ? (int) $data['pegawai_id'] : null,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'kop_surat_id' => $kop?->id,
                'file_surat_path' => $filePath,
                'file_surat_uploaded_at' => $filePath ? Carbon::now() : null,
                'file_surat_uploaded_by' => $filePath ? $userId : null,
                'catatan' => $data['catatan'] ?? null,
                'created_by' => $userId,
            ]);

            try {
                AuditLogService::record(
                    module: 'ARSIP',
                    action: 'generate_nomor_surat_satuan',
                    tableName: 'core_arsip_nomor_surat',
                    recordId: $nomorSurat->id,
                    oldValues: [],
                    newValues: $nomorSurat->toArray()
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log generate satuan: ' . $e->getMessage());
            }

            return $nomorSurat->load(['pembuat', 'kopSurat', 'pegawai', 'fileUploader']);
        });
    }

    /**
     * Generate banyak nomor surat berurutan (Bulk / Sekaligus Banyak).
     *
     * @return Collection<int, NomorSurat>
     */
    public function generateBulk(array $data, int $userId): Collection
    {
        $jumlah = (int) ($data['jumlah_nomor'] ?? 1);
        if ($jumlah < 1) {
            throw new InvalidArgumentException('Jumlah nomor surat minimal 1.');
        }

        return DB::transaction(function () use ($data, $userId, $jumlah) {
            $tanggal = Carbon::parse($data['tanggal_surat'] ?? now());
            $tahun = (int) $tanggal->year;
            $bulanRomawi = self::formatBulanRomawi((int) $tanggal->month);

            // Kunci baris nomor urut
            $maxUrut = DB::table('core_arsip_nomor_surat')
                ->where('tahun', $tahun)
                ->lockForUpdate()
                ->max('nomor_urut');

            $currentUrut = ($maxUrut ?? 0);
            $kodeUnit = strtoupper(trim($data['kode_unit']));
            $kodeKlasifikasi = strtoupper(trim($data['kode_klasifikasi']));

            // Dapatkan kop surat yang berlaku
            $kop = $this->kopSuratService->getKopSuratByTahun($tahun);

            $filePath = null;
            if (!empty($data['file_surat']) && $data['file_surat'] instanceof UploadedFile) {
                $filePath = $this->fileStorage->store($data['file_surat'], 'arsip/dokumen-surat', private: true);
            }

            $createdCollection = new Collection();
            $basePerihal = $data['perihal'];

            for ($i = 1; $i <= $jumlah; $i++) {
                $currentUrut++;
                $formatted = self::formatNomorSurat($currentUrut, $kodeUnit, $kodeKlasifikasi, $bulanRomawi, $tahun);

                // Jika perihal bulk berupa template / batch
                $perihalItem = $jumlah > 1 && !empty($data['keterangan_item'][$i - 1])
                    ? $data['keterangan_item'][$i - 1]
                    : ($jumlah > 1 ? "{$basePerihal} (Bagian {$i}/{$jumlah})" : $basePerihal);

                $tujuanItem = $jumlah > 1 && !empty($data['tujuan_item'][$i - 1])
                    ? $data['tujuan_item'][$i - 1]
                    : ($data['tujuan'] ?? null);

                $item = NomorSurat::create([
                    'nomor_surat' => $formatted,
                    'nomor_urut' => $currentUrut,
                    'kode_unit' => $kodeUnit,
                    'kode_klasifikasi' => $kodeKlasifikasi,
                    'bulan_romawi' => $bulanRomawi,
                    'tahun' => $tahun,
                    'tanggal_surat' => $tanggal->toDateString(),
                    'perihal' => $perihalItem,
                    'tujuan' => $tujuanItem,
                    'status' => $data['status'] ?? 'terpakai',
                    'module_origin' => $data['module_origin'] ?? 'arsip',
                    'request_id' => $data['request_id'] ?? null,
                    'pegawai_id' => !empty($data['pegawai_id']) ? (int) $data['pegawai_id'] : null,
                    'reference_type' => $data['reference_type'] ?? null,
                    'reference_id' => $data['reference_id'] ?? null,
                    'kop_surat_id' => $kop?->id,
                    'file_surat_path' => $filePath,
                    'file_surat_uploaded_at' => $filePath ? Carbon::now() : null,
                    'file_surat_uploaded_by' => $filePath ? $userId : null,
                    'catatan' => $data['catatan'] ?? null,
                    'created_by' => $userId,
                ]);

                $createdCollection->push($item);
            }

            try {
                AuditLogService::record(
                    module: 'ARSIP',
                    action: 'generate_nomor_surat_bulk',
                    tableName: 'core_arsip_nomor_surat',
                    recordId: $createdCollection->first()->id,
                    oldValues: [],
                    newValues: [
                        'jumlah_nomor' => $jumlah,
                        'nomor_awal' => $createdCollection->first()->nomor_surat,
                        'nomor_akhir' => $createdCollection->last()->nomor_surat,
                    ]
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log generate bulk: ' . $e->getMessage());
            }

            return $createdCollection->load(['pembuat', 'kopSurat', 'pegawai', 'fileUploader']);
        });
    }

    /**
     * Upload berkas scan dokumen surat resmi untuk nomor surat yang telah terbit.
     */
    public function uploadFileSurat(int $id, UploadedFile $file, int $userId, string $moduleOrigin = 'ARSIP'): NomorSurat
    {
        return DB::transaction(function () use ($id, $file, $userId, $moduleOrigin) {
            $nomor = NomorSurat::findOrFail($id);
            $oldValues = $nomor->getOriginal();

            // Hapus file lama jika ada
            if (!empty($nomor->file_surat_path)) {
                $this->fileStorage->delete($nomor->file_surat_path, private: true);
            }

            $path = $this->fileStorage->store($file, 'arsip/dokumen-surat', private: true);

            $nomor->update([
                'file_surat_path' => $path,
                'file_surat_uploaded_at' => Carbon::now(),
                'file_surat_uploaded_by' => $userId,
            ]);

            $newValues = $nomor->getChanges();

            try {
                AuditLogService::record(
                    module: $moduleOrigin,
                    action: 'upload_file_surat',
                    tableName: 'core_arsip_nomor_surat',
                    recordId: $nomor->id,
                    oldValues: $oldValues,
                    newValues: $newValues
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log upload file surat: ' . $e->getMessage());
            }

            return $nomor->fresh(['pembuat', 'kopSurat', 'pegawai', 'fileUploader']);
        });
    }

    /**
     * Memperbarui informasi perihal / catatan nomor surat.
     */
    public function update(int $id, array $data): NomorSurat
    {
        return DB::transaction(function () use ($id, $data) {
            $nomor = NomorSurat::findOrFail($id);
            $oldValues = $nomor->getOriginal();

            $nomor->update([
                'perihal' => $data['perihal'] ?? $nomor->perihal,
                'tujuan' => array_key_exists('tujuan', $data) ? $data['tujuan'] : $nomor->tujuan,
                'pegawai_id' => array_key_exists('pegawai_id', $data) ? $data['pegawai_id'] : $nomor->pegawai_id,
                'status' => $data['status'] ?? $nomor->status,
                'catatan' => array_key_exists('catatan', $data) ? $data['catatan'] : $nomor->catatan,
            ]);

            $newValues = $nomor->getChanges();

            try {
                AuditLogService::record(
                    module: 'ARSIP',
                    action: 'update',
                    tableName: 'core_arsip_nomor_surat',
                    recordId: $nomor->id,
                    oldValues: $oldValues,
                    newValues: $newValues
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log update nomor surat: ' . $e->getMessage());
            }

            return $nomor;
        });
    }

    /**
     * Membatalkan nomor surat.
     */
    public function batalkan(int $id, string $alasan): NomorSurat
    {
        return DB::transaction(function () use ($id, $alasan) {
            $nomor = NomorSurat::findOrFail($id);
            $oldValues = $nomor->getOriginal();

            $nomor->status = 'dibatalkan';
            $nomor->catatan = trim($nomor->catatan . "\n[Dibatalkan]: " . $alasan);
            $nomor->save();

            $newValues = $nomor->getChanges();

            try {
                AuditLogService::record(
                    module: 'ARSIP',
                    action: 'batalkan_nomor_surat',
                    tableName: 'core_arsip_nomor_surat',
                    recordId: $nomor->id,
                    oldValues: $oldValues,
                    newValues: $newValues
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log batalkan nomor surat: ' . $e->getMessage());
            }

            return $nomor;
        });
    }
}
