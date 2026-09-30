<?php

namespace App\Services\Sinapra;

use App\Models\KategoriAset;
use App\Models\Aset;
use App\Models\Sinapra\RiwayatPenyusutanAset;
use App\Services\Sikeu\JurnalSikeuService;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

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

            if (empty($data['program_studi_id']) && !empty($data['ruangan_id'])) {
                $ruangan = \App\Models\Ruangan::find($data['ruangan_id']);
                if ($ruangan && $ruangan->program_studi_id) {
                    $data['program_studi_id'] = $ruangan->program_studi_id;
                }
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

    /**
     * Generate metadata label barcode & QR code untuk satu aset fisik.
     */
    public function generateLabelData(Aset $aset): array
    {
        $aset->loadMissing(['kategori', 'ruangan.gedung']);

        $qrContent = config('app.url') . '/sinapra/aset/' . $aset->id;

        $renderer = new ImageRenderer(
            new RendererStyle(180, 1),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrSvg = $writer->writeString($qrContent);

        return [
            'id' => $aset->id,
            'kode_aset' => $aset->kode_aset,
            'nama' => $aset->nama,
            'merk' => $aset->merk,
            'model' => $aset->model,
            'serial_number' => $aset->serial_number,
            'kategori' => $aset->kategori?->nama,
            'ruangan_id' => $aset->ruangan_id,
            'lokasi_ruangan' => $aset->ruangan?->nama,
            'lokasi_gedung' => $aset->ruangan?->gedung?->nama,
            'tanggal_perolehan' => $aset->tanggal_perolehan?->format('Y-m-d'),
            'kondisi' => $aset->kondisi,
            'status' => $aset->status,
            'qr_content' => $qrContent,
            'qr_code_svg' => $qrSvg,
            'instansi' => config('app.name', 'SISTEM SARANA & PRASARANA KAMPUS'),
        ];
    }

    /**
     * Generate metadata label barcode & QR code untuk sekumpulan aset fisik (batch).
     */
    public function generateBatchLabelData(array $asetIds): array
    {
        $asets = Aset::with(['kategori', 'ruangan.gedung'])
            ->whereIn('id', $asetIds)
            ->get();

        return $asets->map(fn (Aset $aset) => $this->generateLabelData($aset))->values()->all();
    }

    /**
     * Memposting jurnal penyusutan aset ke Jurnal Umum SIKEU.
     */
    public function postJurnalPenyusutan(
        Aset $aset,
        int $tahun,
        int $userId,
        ?string $catatan = null
    ): RiwayatPenyusutanAset {
        return DB::transaction(function () use ($aset, $tahun, $userId, $catatan) {
            // 1. Cek apakah sudah pernah diposting untuk periode tahun ini
            $existing = RiwayatPenyusutanAset::where('aset_id', $aset->id)
                ->where('periode_tahun', $tahun)
                ->first();
            if ($existing) {
                throw new \InvalidArgumentException("Aset [{$aset->kode_aset}] {$aset->nama} sudah diposting jurnal penyusutannya untuk periode tahun {$tahun}.");
            }

            // 2. Hitung beban penyusutan
            $aset->loadMissing('kategori');
            $hargaPerolehan = (float) $aset->harga_perolehan;
            $tarifPenyusutan = $aset->kategori?->tarif_penyusutan_persen ? (float) $aset->kategori->tarif_penyusutan_persen : 0;

            if ($tarifPenyusutan <= 0) {
                throw new \InvalidArgumentException("Kategori aset [{$aset->kategori?->nama}] tidak memiliki persentase tarif penyusutan.");
            }

            $bebanPenyusutanTahun = round($hargaPerolehan * ($tarifPenyusutan / 100), 2);
            if ($bebanPenyusutanTahun <= 0) {
                throw new \InvalidArgumentException("Nilai beban penyusutan tahunan bernilai nol.");
            }

            // Hitung akumulasi penyusutan yang sudah ada di database
            $totalSusutSebelumnya = (float) RiwayatPenyusutanAset::where('aset_id', $aset->id)->sum('beban_penyusutan');
            $nilaiBukuSebelum = max(0, $hargaPerolehan - $totalSusutSebelumnya);

            if ($nilaiBukuSebelum <= 0) {
                throw new \InvalidArgumentException("Nilai buku aset [{$aset->kode_aset}] sudah nol (telah disusutkan penuh).");
            }

            // Beban tidak boleh melebihi sisa nilai buku
            $bebanPenyusutan = min($nilaiBukuSebelum, $bebanPenyusutanTahun);
            $nilaiBukuSetelah = max(0, $nilaiBukuSebelum - $bebanPenyusutan);

            // 3. Posting Jurnal ke SIKEU
            $keterangan = "Penyusutan Aset {$aset->kode_aset} - {$aset->nama} (Tahun {$tahun})";
            $jurnal = JurnalSikeuService::jurnalPenyusutanAset($aset->id, $bebanPenyusutan, $keterangan);

            // 4. Catat riwayat penyusutan di SINAPRA
            $riwayat = RiwayatPenyusutanAset::create([
                'aset_id' => $aset->id,
                'jurnal_umum_id' => $jurnal->id,
                'periode_tahun' => $tahun,
                'nilai_perolehan' => $hargaPerolehan,
                'persentase_penyusutan' => $tarifPenyusutan,
                'beban_penyusutan' => $bebanPenyusutan,
                'nilai_buku_setelah' => $nilaiBukuSetelah,
                'tanggal_posting' => now()->toDateString(),
                'catatan' => $catatan,
                'diposting_oleh' => $userId,
            ]);

            // 5. Update estimasi nilai buku pada aset fisik
            $aset->update(['nilai_buku' => $nilaiBukuSetelah]);

            AuditLogService::record(
                module: 'SINAPRA',
                action: 'post_penyusutan',
                tableName: 'sinapra_riwayat_penyusutan_aset',
                recordId: $riwayat->id,
                newValues: $riwayat->toArray()
            );

            return $riwayat->load(['jurnalUmum', 'poster']);
        });
    }

    /**
     * Mengambil daftar riwayat penyusutan untuk aset tertentu.
     */
    public function getRiwayatPenyusutan(Aset $aset)
    {
        return RiwayatPenyusutanAset::where('aset_id', $aset->id)
            ->with(['jurnalUmum', 'poster'])
            ->orderBy('periode_tahun', 'desc')
            ->get();
    }
}
