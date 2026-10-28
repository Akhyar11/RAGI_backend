<?php

namespace App\Services\Sikeu;

use App\Models\Siakad\Mahasiswa;
use App\Models\Sikeu\DispensasiTagihan;
use App\Models\Sikeu\MasterBiaya;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Spmb\PendaftaranCalonMhs;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Kompatibilitas legacy SIKEU (cek_pembayaran, cek_tagihan_ujian, biaya_lain, pendaftar).
 *
 * Endpoint server-to-server untuk gate SIAKAD/SPMB (auth:api + throttle).
 * Aturan keuangan: sisa = tagihan+denda-potongan-bayar; koreksi (reversed)
 * tidak dihitung bayar karena status pembayaran sudah dibalik.
 */
class SikeuCompatService
{
    public const STATUS_AKTIF = ['belum_bayar', 'sebagian', 'dispensasi'];

    public function findMahasiswa(?int $mahasiswaId, ?string $nim): ?Mahasiswa
    {
        if ($mahasiswaId) {
            return Mahasiswa::with('programStudi')->find($mahasiswaId);
        }
        if ($nim) {
            return Mahasiswa::with('programStudi')->where('nim', trim($nim))->first();
        }

        return null;
    }

    public function ringkasan(?int $mahasiswaId, ?string $nim): array
    {
        $mhs = $this->findMahasiswa($mahasiswaId, $nim);
        if (!$mhs) {
            throw new \InvalidArgumentException('Mahasiswa tidak ditemukan.');
        }

        $tagihans = TagihanMahasiswa::where('mahasiswa_id', $mhs->id)->get();
        $bersih = (float) $tagihans->sum(fn ($t) => (float) $t->total_tagihan + (float) $t->total_denda - (float) $t->total_potongan);
        $bayar = (float) $tagihans->sum('total_bayar');
        $sisa = max(0, $bersih - $bayar);

        return [
            'mahasiswa_id' => $mhs->id,
            'nim' => $mhs->nim,
            'nama' => $mhs->nama_lengkap,
            'program_studi' => $mhs->programStudi?->nama,
            'total_tagihan' => $bersih,
            'total_bayar' => $bayar,
            'sisa' => $sisa,
            'lunas' => $sisa <= 0,
            'jumlah_tagihan_aktif' => $tagihans->whereIn('status', self::STATUS_AKTIF)->count(),
        ];
    }

    public function tagihanAktif(?int $mahasiswaId, ?string $nim, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $mhs = $this->findMahasiswa($mahasiswaId, $nim);
        if (!$mhs) {
            throw new \InvalidArgumentException('Mahasiswa tidak ditemukan.');
        }

        $query = TagihanMahasiswa::with(['details.masterBiaya', 'virtualAccount'])
            ->where('mahasiswa_id', $mhs->id)
            ->whereIn('status', self::STATUS_AKTIF)
            ->orderBy('jatuh_tempo')
            ->orderBy('id');

        $total = $query->count();
        $items = $query->forPage($page, $perPage)->get()->map(fn ($t) => [
            'tagihan_id' => $t->id,
            'nomor_tagihan' => $t->nomor_tagihan,
            'status' => $t->status,
            'jatuh_tempo' => $t->jatuh_tempo?->toDateString(),
            'total_tagihan' => (float) $t->total_tagihan,
            'total_potongan' => (float) $t->total_potongan,
            'total_denda' => (float) $t->total_denda,
            'total_bayar' => (float) $t->total_bayar,
            'sisa' => max(0, (float) ($t->total_tagihan + $t->total_denda - $t->total_potongan) - (float) $t->total_bayar),
            'va_number' => $t->virtualAccount?->va_number,
            'bank_kode' => $t->virtualAccount?->bank_kode,
        ])->values();

        return new LengthAwarePaginator($items, $total, $perPage, $page, ['path' => request()->url(), 'query' => request()->query()]);
    }

    /**
     * Eligibilitas ujian (parity legacy /ujian/.../tipe_ujian UTS/UAS/ALL).
     * Lulus bila sisa 0, atau ada dispensasi approved dengan bypass KRS/ujian.
     */
    public function eligibilitasUjian(int $mahasiswaId, string $tipeUjian, ?int $semester = null): array
    {
        $tipe = strtoupper(trim($tipeUjian));
        if (!in_array($tipe, ['UTS', 'UAS', 'ALL'], true)) {
            throw new \InvalidArgumentException('tipe_ujian harus UTS, UAS, atau ALL.');
        }

        $mhs = Mahasiswa::find($mahasiswaId);
        if (!$mhs) {
            throw new \InvalidArgumentException('Mahasiswa tidak ditemukan.');
        }

        $tagihanQ = TagihanMahasiswa::where('mahasiswa_id', $mahasiswaId)
            ->whereIn('status', self::STATUS_AKTIF);
        if ($semester) {
            $tagihanQ->where(function ($q) use ($semester) {
                $q->whereNull('semester')->orWhere('semester', $semester);
            });
        }
        $tagihans = $tagihanQ->get();
        $sisa = max(0, (float) $tagihans->sum(fn ($t) => (float) $t->total_tagihan + (float) $t->total_denda - (float) $t->total_potongan - (float) $t->total_bayar));

        $bypass = DispensasiTagihan::berlakuUntukKrs()
            ->where('mahasiswa_id', $mahasiswaId)
            ->exists();

        $eligible = $sisa <= 0 || $bypass;

        return [
            'mahasiswa_id' => $mahasiswaId,
            'nim' => $mhs->nim,
            'tipe_ujian' => $tipe,
            'semester' => $semester,
            'eligible' => $eligible,
            'sisa_tunggakan' => $sisa,
            'bypass_dispensasi' => $bypass,
            'alasan' => $eligible
                ? ($sisa <= 0 ? 'Lunas, kartu ujian dapat diterbitkan.' : 'Dispensasi bypass aktif.')
                : 'Masih ada tunggakan Rp ' . number_format($sisa, 0, ',', '.') . '.',
        ];
    }

    /**
     * Katalog biaya lain publik (parity legacy /biayalain/:prodi/:akun).
     * Scoping prodi modern lives in SettingTarif/katalog-biaya; di sini katalog master.
     */
    public function biayaLain(?string $tipe, ?string $search, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $query = MasterBiaya::where('is_active', true);
        if ($tipe) {
            $query->where('tipe', trim($tipe));
        }
        if ($search) {
            $s = trim($search);
            $query->where(fn ($q) => $q->where('nama', 'like', "%{$s}%")->orWhere('kode', 'like', "%{$s}%"));
        }
        $query->orderBy('nama');

        $total = $query->count();
        $items = $query->forPage($page, $perPage)->get()->map(fn ($b) => [
            'id' => $b->id,
            'kode' => $b->kode,
            'nama' => $b->nama,
            'tipe' => $b->tipe,
            'nominal_standar' => $b->nominal_standar !== null ? (float) $b->nominal_standar : null,
        ])->values();

        return new LengthAwarePaginator($items, $total, $perPage, $page, ['path' => request()->url(), 'query' => request()->query()]);
    }

    /**
     * Pendaftar belum daftar ulang (parity legacy /pendaftar/belumdu):
     * hasil seleksi lulus tetapi status_daftar_ulang belum lunas.
     */
    public function belumDaftarUlang(?string $search, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $query = PendaftaranCalonMhs::with(['hasilSeleksi', 'programStudi'])
            ->whereHas('hasilSeleksi', function ($q) {
                $q->where('status', 'lulus')
                    ->where(function ($s) {
                        $s->whereNull('status_daftar_ulang')->orWhere('status_daftar_ulang', '!=', 'lunas');
                    });
            })
            ->orderByDesc('id');

        if ($search) {
            $s = trim($search);
            $query->where(fn ($q) => $q->where('nama_lengkap', 'like', "%{$s}%")
                ->orWhere('no_pendaftaran', 'like', "%{$s}%")
                ->orWhere('nim', 'like', "%{$s}%"));
        }

        $total = $query->count();
        $items = $query->forPage($page, $perPage)->get()->map(function ($p) {
            $du = TagihanMahasiswa::where('calon_mahasiswa_id', $p->id)
                ->where('tipe_referensi', TagihanMahasiswa::TIPE_SPMB_DAFTAR_ULANG)
                ->latest('id')
                ->first();

            return [
                'pendaftaran_id' => $p->id,
                'no_pendaftaran' => $p->no_pendaftaran,
                'nama' => $p->nama_lengkap,
                'program_studi' => $p->programStudi?->nama,
                'status_daftar_ulang' => $p->hasilSeleksi?->status_daftar_ulang,
                'tagihan_du' => $du ? [
                    'nomor_tagihan' => $du->nomor_tagihan,
                    'status' => $du->status,
                    'sisa' => max(0, (float) ($du->total_tagihan + $du->total_denda - $du->total_potongan) - (float) $du->total_bayar),
                ] : null,
            ];
        })->values();

        return new LengthAwarePaginator($items, $total, $perPage, $page, ['path' => request()->url(), 'query' => request()->query()]);
    }
}
