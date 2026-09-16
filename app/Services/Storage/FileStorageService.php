<?php

namespace App\Services\Storage;

use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Layanan penyimpanan file dinamis: local / public / s3 / R2.
 *
 * Seluruh upload WAJIB lewat service ini agar pindah storage cukup
 * ganti konfigurasi FILESYSTEM_PUBLIC_DISK / FILESYSTEM_PRIVATE_DISK
 * tanpa mengubah controller.
 *
 * Konvensi (sesuai skill file-upload-standard):
 * - Nama file selalu UUID + ekstensi asli (anti traversal & collision).
 * - Path DB selalu relatif (tanpa domain, tanpa prefix "storage/").
 * - Ekstensi executable (.php, .sh, .exe, ...) selalu ditolak.
 */
class FileStorageService
{
    /**
     * Ekstensi yang tidak boleh disimpan.
     *
     * @var string[]
     */
    protected array $blockedExtensions = [
        'php', 'phtml', 'phar', 'sh', 'exe', 'bat', 'cmd', 'com', 'msi', 'js', 'html', 'htm',
    ];

    /**
     * Nama disk publik aktif (config filesystems.public_disk).
     */
    public function publicDiskName(): string
    {
        return (string) config('filesystems.public_disk', 'public');
    }

    /**
     * Nama disk privat aktif (config filesystems.private_disk).
     */
    public function privateDiskName(): string
    {
        return (string) config('filesystems.private_disk', $this->publicDiskName());
    }

    /**
     * Resolve disk: eksplisit > default. Selalu kembalikan nama disk valid.
     *
     * Safety: bila disk cloud (r2/r2-private/s3) diminta tapi kredensial/
     * bucket belum diisi (mis. .env local), otomatis fallback ke 'public'
     * agar local tetap jalan dengan storage biasa.
     */
    public function resolveDisk(?string $disk = null, bool $private = false): string
    {
        $resolved = $disk ?: ($private ? $this->privateDiskName() : $this->publicDiskName());
        $resolved = strtolower($resolved);

        if (in_array($resolved, ['r2', 'r2-private', 's3'], true) && ! $this->isCloudDiskConfigured($resolved)) {
            \Illuminate\Support\Facades\Log::warning("FileStorage: disk '{$resolved}' belum dikonfigurasi, fallback ke 'public'.");
            return 'public';
        }

        return $resolved;
    }

    /**
     * Cek apakah disk cloud sudah punya bucket + (endpoint/key) minimal.
     */
    public function isCloudDiskConfigured(string $disk): bool
    {
        $disk = strtolower($disk);

        if ($disk === 's3') {
            return (bool) config('filesystems.disks.s3.bucket');
        }

        if (in_array($disk, ['r2', 'r2-private'], true)) {
            $cfg = config("filesystems.disks.{$disk}", []);

            return ! empty($cfg['bucket']) && ! empty($cfg['endpoint']);
        }

        return true;
    }

    /**
     * True bila upload aktif memakai cloud (R2/S3). False = storage biasa.
     */
    public function isCloudActive(): bool
    {
        foreach ([$this->publicDiskName(), $this->privateDiskName()] as $name) {
            $name = strtolower($name);

            if (in_array($name, ['r2', 'r2-private', 's3'], true) && $this->isCloudDiskConfigured($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Simpan UploadedFile dengan nama UUID ke "<baseDir>/Y/m/".
     *
     * @return string Path relatif untuk disimpan ke DB (mis. spmb/dokumen_pendaftaran/2026/07/uuid.pdf)
     */
    public function store(UploadedFile $file, string $baseDir, ?string $disk = null, bool $private = false): string
    {
        $diskName = $this->resolveDisk($disk, $private);
        $extension = strtolower($file->getClientOriginalExtension());

        $this->assertAllowedExtension($extension);

        $directory = trim($baseDir, '/').'/'.date('Y/m');
        $fileName = (string) Str::uuid().($extension !== '' ? '.'.$extension : '');

        // putFileAs bekerja identik di local & S3/R2. Jangan set visibility (R2 menolak ACL per-object).
        Storage::disk($diskName)->putFileAs($directory, $file, $fileName);

        return $directory.'/'.$fileName;
    }

    /**
     * Cek keberadaan file. Otomatis fallback ke disk legacy (public/local)
     * selama masa migrasi local -> R2.
     */
    public function exists(?string $path, ?string $disk = null, bool $private = false): bool
    {
        $relative = $this->normalizePath($path);

        if ($relative === '') {
            return false;
        }

        foreach ($this->candidateDisks($disk, $private) as $candidate) {
            if (Storage::disk($candidate)->exists($relative)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hapus file jika ada. Mengembalikan true bila terhapus dari salah satu disk kandidat.
     */
    public function delete(?string $path, ?string $disk = null, bool $private = false): bool
    {
        $relative = $this->normalizePath($path);

        if ($relative === '') {
            return false;
        }

        $deleted = false;

        foreach ($this->candidateDisks($disk, $private) as $candidate) {
            $store = Storage::disk($candidate);

            if ($store->exists($relative) && $store->delete($relative)) {
                $deleted = true;
            }
        }

        return $deleted;
    }

    /**
     * URL publik untuk file non-rahasia.
     * Di R2 memakai R2_URL (custom domain). Di local memakai Storage::url + asset.
     */
    public function url(?string $path, ?string $disk = null, bool $private = false): ?string
    {
        $relative = $this->normalizePath($path);

        if ($relative === '') {
            return null;
        }

        $diskName = $this->resolveDisk($disk, $private);
        $url = (string) Storage::disk($diskName)->url($relative);

        // Disk local/public mengembalikan path relatif (/storage/...) — jadikan absolut.
        if (str_starts_with($url, '/')) {
            return asset($url);
        }

        return $url;
    }

    /**
     * URL sementara untuk file rahasia (bucket private).
     * Fallback ke url() bila driver tidak mendukung (mis. local).
     */
    public function temporaryUrl(?string $path, ?Carbon $expiresAt = null, ?string $disk = null): ?string
    {
        $relative = $this->normalizePath($path);

        if ($relative === '') {
            return null;
        }

        $diskName = $this->resolveDisk($disk, true);
        $expiresAt ??= now()->addMinutes(15);

        try {
            /** @var Filesystem $store */
            $store = Storage::disk($diskName);

            if (method_exists($store, 'temporaryUrl')) {
                return $store->temporaryUrl($relative, $expiresAt);
            }
        } catch (\Throwable) {
            // Driver local / belum terkonfigurasi — fallback ke URL biasa.
        }

        return $this->url($relative, $diskName, true);
    }

    /**
     * Response download yang bekerja untuk local maupun R2/S3.
     * Otomatis mencari di disk kandidat (mendukung masa migrasi).
     */
    public function download(?string $path, ?string $downloadName = null, ?string $disk = null, bool $private = false): StreamedResponse
    {
        $relative = $this->normalizePath($path);

        if ($relative === '') {
            abort(404, 'Berkas tidak ditemukan.');
        }

        foreach ($this->candidateDisks($disk, $private) as $candidate) {
            if (Storage::disk($candidate)->exists($relative)) {
                return Storage::disk($candidate)->download(
                    $relative,
                    $downloadName ?? basename($relative)
                );
            }
        }

        abort(404, 'File fisik tidak ditemukan pada storage server.');
    }

    /**
     * Salin file storage (termasuk R2) ke file lokal sementara untuk parsing
     * (mis. import SQL presensi). Caller WAJIB @unlink() hasilnya.
     *
     * @return string Absolute path file sementara.
     */
    public function temporaryLocalPath(?string $path, ?string $disk = null, bool $private = false): string
    {
        $relative = $this->normalizePath($path);

        if ($relative === '') {
            throw new InvalidArgumentException('Path file kosong.');
        }

        foreach ($this->candidateDisks($disk, $private) as $candidate) {
            $store = Storage::disk($candidate);

            if (! $store->exists($relative)) {
                continue;
            }

            $tmp = tempnam(sys_get_temp_dir(), 'ragi_');
            file_put_contents($tmp, $store->get($relative));

            return $tmp;
        }

        throw new InvalidArgumentException('File fisik tidak ditemukan pada storage server.');
    }

    /**
     * Normalisasi path legacy ke path relatif kanonis:
     * "storage/dokumen_pegawai/x.pdf" -> "dokumen_pegawai/x.pdf".
     */
    public function normalizePath(?string $path): string
    {
        if ($path === null) {
            return '';
        }

        $path = trim($path);

        if ($path === '') {
            return '';
        }

        // Izinkan URL absolut legacy — ambil path-nya saja.
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $parsed = parse_url($path, PHP_URL_PATH);
            $path = is_string($parsed) ? $parsed : '';
        }

        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');

        foreach (['storage/', 'public/', 'app/public/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
                $path = ltrim($path, '/');
            }
        }

        // Cegah traversal keluar direktori storage.
        if ($path === '' || $path === '.' || str_contains($path, '..')) {
            return '';
        }

        return $path;
    }

    /**
     * Daftar disk kandidat untuk baca/hapus (disk tujuan + legacy).
     *
     * @return string[]
     */
    protected function candidateDisks(?string $disk, bool $private): array
    {
        $candidates = [strtolower($this->resolveDisk($disk, $private))];

        foreach (['public', 'local'] as $legacy) {
            if (! in_array($legacy, $candidates, true)) {
                $candidates[] = $legacy;
            }
        }

        // Untuk file privat, pastikan disk privat ikut dicek walau pemanggil lupa flag.
        $privateDisk = strtolower($this->privateDiskName());
        if ($private && ! in_array($privateDisk, $candidates, true)) {
            array_unshift($candidates, $privateDisk);
        }

        return array_values(array_unique($candidates));
    }

    protected function assertAllowedExtension(string $extension): void
    {
        if ($extension !== '' && in_array(strtolower($extension), $this->blockedExtensions, true)) {
            throw new InvalidArgumentException('Tipe file tidak diizinkan demi keamanan.');
        }
    }
}
