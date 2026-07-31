<?php

namespace App\Services\Sippm;

use App\Models\Sippm\PublikasiIlmiah;
use App\Models\Sippm\HkiDanBuku;

class LuaranService
{
    /**
     * Store a new publication output.
     */
    public function createPublikasi(array $data): PublikasiIlmiah
    {
        return PublikasiIlmiah::create([
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
    }

    /**
     * Store a new HKI or Book output.
     */
    public function createHkiDanBuku(array $data): HkiDanBuku
    {
        return HkiDanBuku::create([
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
    }

    /**
     * Verify publication or HKI output (LPPM Admin).
     */
    public function verifyPublikasi(PublikasiIlmiah $publikasi): PublikasiIlmiah
    {
        $publikasi->update(['is_verified_lppm' => true]);
        return $publikasi;
    }

    /**
     * Verify HKI or Book output (LPPM Admin).
     */
    public function verifyHkiDanBuku(HkiDanBuku $hkiDanBuku): HkiDanBuku
    {
        $hkiDanBuku->update(['is_verified_lppm' => true]);
        return $hkiDanBuku;
    }
}
