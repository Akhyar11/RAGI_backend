<?php

namespace App\Services\Sippm;

use App\Models\Sippm\PublikasiIlmiah;
use App\Models\Sippm\HkiDanBuku;
use App\Services\Core\AuditLogService;
use Illuminate\Support\Facades\Log;

class LuaranService
{
    /**
     * Store a new publication output.
     */
    public function createPublikasi(array $data): PublikasiIlmiah
    {
        $publikasi = PublikasiIlmiah::create([
            'proposal_id' => $data['proposal_id'] ?? null,
            'pegawai_id' => $data['pegawai_id'],
            'judul_artikel' => $data['judul_artikel'],
            'jenis_publikasi' => $data['jenis_publikasi'],
            'nama_jurnal_prosiding' => $data['nama_jurnal_prosiding'],
            'indexing' => $data['indexing'] ?? 'lainnya',
            'volume_issue_tahun' => $data['volume_issue_tahun'],
            'doi' => $data['doi'] ?? null,
            'url_artikel' => $data['url_artikel'] ?? null,
            'file_artikel' => $data['file_artikel'] ?? null,
            'is_verified_lppm' => false,
        ]);

        try {
            AuditLogService::record(
                action: 'create',
                module: 'sippm',
                entityType: PublikasiIlmiah::class,
                entityId: $publikasi->id,
                oldValues: null,
                newValues: $publikasi->toArray(),
                description: "Mendaftarkan publikasi ilmiah baru: {$publikasi->judul_artikel}"
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log createPublikasi: ' . $e->getMessage());
        }

        return $publikasi;
    }

    /**
     * Store a new HKI or Book output.
     */
    public function createHkiDanBuku(array $data): HkiDanBuku
    {
        $hkiDanBuku = HkiDanBuku::create([
            'proposal_id' => $data['proposal_id'] ?? null,
            'pegawai_id' => $data['pegawai_id'],
            'jenis_luaran' => $data['jenis_luaran'],
            'judul' => $data['judul'],
            'nomor_pencatatan_isbn' => $data['nomor_pencatatan_isbn'],
            'penerbit_lembaga' => $data['penerbit_lembaga'],
            'tgl_terbit_catat' => $data['tgl_terbit_catat'],
            'file_sertifikat_buku' => $data['file_sertifikat_buku'] ?? null,
            'is_verified_lppm' => false,
        ]);

        try {
            AuditLogService::record(
                action: 'create',
                module: 'sippm',
                entityType: HkiDanBuku::class,
                entityId: $hkiDanBuku->id,
                oldValues: null,
                newValues: $hkiDanBuku->toArray(),
                description: "Mendaftarkan HKI / Buku baru: {$hkiDanBuku->judul}"
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log createHkiDanBuku: ' . $e->getMessage());
        }

        return $hkiDanBuku;
    }

    /**
     * Verify publication or HKI output (LPPM Admin).
     */
    public function verifyPublikasi(PublikasiIlmiah $publikasi): PublikasiIlmiah
    {
        $old = $publikasi->toArray();
        $publikasi->update(['is_verified_lppm' => true]);

        try {
            AuditLogService::record(
                action: 'verify',
                module: 'sippm',
                entityType: PublikasiIlmiah::class,
                entityId: $publikasi->id,
                oldValues: $old,
                newValues: $publikasi->toArray(),
                description: "Memverifikasi publikasi ilmiah ID: {$publikasi->id}"
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log verifyPublikasi: ' . $e->getMessage());
        }

        return $publikasi;
    }

    /**
     * Verify HKI or Book output (LPPM Admin).
     */
    public function verifyHkiDanBuku(HkiDanBuku $hkiDanBuku): HkiDanBuku
    {
        $old = $hkiDanBuku->toArray();
        $hkiDanBuku->update(['is_verified_lppm' => true]);

        try {
            AuditLogService::record(
                action: 'verify',
                module: 'sippm',
                entityType: HkiDanBuku::class,
                entityId: $hkiDanBuku->id,
                oldValues: $old,
                newValues: $hkiDanBuku->toArray(),
                description: "Memverifikasi HKI / Buku ID: {$hkiDanBuku->id}"
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log verifyHkiDanBuku: ' . $e->getMessage());
        }

        return $hkiDanBuku;
    }
}
