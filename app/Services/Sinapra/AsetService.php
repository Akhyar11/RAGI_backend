<?php

namespace App\Services\Sinapra;

use App\Models\KategoriAset;
use App\Models\Aset;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AsetService
{
    /**
     * Membuat Kategori Aset baru.
     */
    public function createKategoriAset(array $data): KategoriAset
    {
        return DB::transaction(function () use ($data) {
            $kategori = KategoriAset::create($data);

            AuditLogService::record(
                module: 'SINAPRA',
                action: 'create',
                tableName: 'kategori_aset',
                recordId: $kategori->id,
                newValues: $kategori->toArray()
            );

            return $kategori;
        });
    }

    /**
     * Mengubah Kategori Aset.
     */
    public function updateKategoriAset(KategoriAset $kategori, array $data): KategoriAset
    {
        return DB::transaction(function () use ($kategori, $data) {
            $oldValues = $kategori->toArray();
            $kategori->update($data);

            AuditLogService::record(
                module: 'SINAPRA',
                action: 'update',
                tableName: 'kategori_aset',
                recordId: $kategori->id,
                oldValues: $oldValues,
                newValues: $kategori->fresh()->toArray()
            );

            return $kategori->fresh();
        });
    }

    /**
     * Menghapus Kategori Aset.
     */
    public function deleteKategoriAset(KategoriAset $kategori): void
    {
        DB::transaction(function () use ($kategori) {
            $oldValues = $kategori->toArray();
            $kategori->delete();

            AuditLogService::record(
                module: 'SINAPRA',
                action: 'delete',
                tableName: 'kategori_aset',
                recordId: $kategori->id,
                oldValues: $oldValues
            );
        });
    }

    /**
     * Membuat Aset baru.
     */
    public function createAset(array $data): Aset
    {
        return DB::transaction(function () use ($data) {
            if (!isset($data['nilai_buku']) && isset($data['harga_perolehan'])) {
                $data['nilai_buku'] = $data['harga_perolehan'];
            }

            $aset = Aset::create($data);

            AuditLogService::record(
                module: 'SINAPRA',
                action: 'create',
                tableName: 'aset',
                recordId: $aset->id,
                newValues: $aset->toArray()
            );

            return $aset;
        });
    }

    /**
     * Mengubah Aset.
     */
    public function updateAset(Aset $aset, array $data): Aset
    {
        return DB::transaction(function () use ($aset, $data) {
            $oldValues = $aset->toArray();
            $aset->update($data);

            AuditLogService::record(
                module: 'SINAPRA',
                action: 'update',
                tableName: 'aset',
                recordId: $aset->id,
                oldValues: $oldValues,
                newValues: $aset->fresh()->toArray()
            );

            return $aset->fresh();
        });
    }

    /**
     * Menghapus Aset.
     */
    public function deleteAset(Aset $aset): void
    {
        DB::transaction(function () use ($aset) {
            $oldValues = $aset->toArray();
            $aset->delete();

            AuditLogService::record(
                module: 'SINAPRA',
                action: 'delete',
                tableName: 'aset',
                recordId: $aset->id,
                oldValues: $oldValues
            );
        });
    }

    /**
     * Hitung estimasi nilai buku (penyusutan) aset berdasarkan umur perolehan.
     */
    public function hitungNilaiBuku(Aset $aset): float
    {
        $aset->loadMissing('kategori');
        $hargaPerolehan = (float) $aset->harga_perolehan;
        $tanggalPerolehan = $aset->tanggal_perolehan ? Carbon::parse($aset->tanggal_perolehan) : null;
        $tarifPenyusutan = $aset->kategori?->tarif_penyusutan_persen ? (float) $aset->kategori->tarif_penyusutan_persen : 0;

        if (!$tanggalPerolehan || $tarifPenyusutan <= 0 || $hargaPerolehan <= 0) {
            return $hargaPerolehan;
        }

        $tahunDipakai = $tanggalPerolehan->startOfDay()->diffInYears(now()->startOfDay());
        $totalPenyusutan = $hargaPerolehan * ($tarifPenyusutan / 100) * $tahunDipakai;
        $nilaiBuku = max(0, $hargaPerolehan - $totalPenyusutan);

        return round($nilaiBuku, 2);
    }
}
