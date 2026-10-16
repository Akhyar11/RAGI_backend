<?php

namespace App\Services\Sikeu;

use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\TransaksiKasUnit;
use App\Models\Sikeu\TutupKasUnit;
use App\Models\Sikeu\UangMukaKerja;
use App\Models\Sikeu\MahasiswaBeasiswa;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Laporan Bendahara SIKEU (parity legacy routes/laporan/laporan.js).
 *
 * Basis KAS (cash basis), bukan akrual:
 * - Masuk  = Pembayaran success (mahasiswa) + TransaksiKasUnit debet_pemasukan.
 * - Keluar = TransaksiKasUnit kredit_pengeluaran.
 * Pembayaran kasir loket tidak menduplikasi TransaksiKasUnit, sehingga
 * penjumlahan kedua sumber tidak ganda.
 *
 * Laporan akuntansi akrual tetap di AkuntansiController::laporanKeuangan.
 */
class LaporanBendaharaService
{
    public const CHANNEL_TUNAI = ['LOKET_TUNAI'];

    public function isTunai(?string $metode): bool
    {
        return strtoupper((string) ($metode ?? 'OFFLINE')) === 'OFFLINE'
            || strtoupper((string) $metode) === 'TUNAI'
            || strtoupper((string) $metode) === 'LOKET_TUNAI';
    }

    /**
     * Saldo awal bulan berjalan = tutup kas akhir bulan lalu (per unit).
     * Melempar RuntimeException bila unit tunai belum tutup bulan lalu (parity legacy).
     */
    public function saldoAwal(?int $unitKasId, string $tanggal, bool $isTunai): array
    {
        if (!$isTunai) {
            return ['saldo_awal' => 0.0, 'sumber' => null, 'tanpa_saldo_awal' => true];
        }

        $tgl = Carbon::parse(substr($tanggal, 0, 10));
        $akhirBulanLalu = $tgl->copy()->firstOfMonth()->subDay()->toDateString();

        if ($unitKasId) {
            $tutup = TutupKasUnit::where('unit_kas_id', $unitKasId)
                ->where('is_locked', true)
                ->whereDate('tanggal', '<=', $akhirBulanLalu)
                ->orderByDesc('tanggal')
                ->first();

            if (!$tutup) {
                throw new \RuntimeException('Bulan kemarin belum tutup buku.');
            }

            return [
                'saldo_awal' => (float) $tutup->saldo_akhir,
                'sumber' => $tutup->only(['unit_kas_id', 'saldo_akhir']) + ['tanggal' => $tutup->tanggal->toDateString()],
                'tanpa_saldo_awal' => false,
            ];
        }

        $total = (float) TutupKasUnit::where('is_locked', true)
            ->whereDate('tanggal', '<=', $akhirBulanLalu)
            ->whereIn('id', function ($q) use ($akhirBulanLalu) {
                $q->selectRaw('MAX(id)')
                    ->from('sikeu_tutup_kas')
                    ->where('is_locked', true)
                    ->whereDate('tanggal', '<=', $akhirBulanLalu)
                    ->groupBy('unit_kas_id');
            })
            ->sum('saldo_akhir');

        return ['saldo_awal' => $total, 'sumber' => null, 'tanpa_saldo_awal' => $total <= 0];
    }

    public function saldoPeriode(string $awal, string $akhir, ?int $unitKasId, ?string $metode): array
    {
        $tunai = $this->isTunai($metode);

        $bayar = Pembayaran::where('status', 'success')
            ->whereDate('waktu_bayar', '>=', substr($awal, 0, 10))
            ->whereDate('waktu_bayar', '<=', substr($akhir, 0, 10));
        if ($unitKasId) {
            $bayar->where('unit_kas_id', $unitKasId);
        }
        if ($tunai) {
            $bayar->whereIn('channel_bayar', self::CHANNEL_TUNAI);
        } else {
            $bayar->whereNotIn('channel_bayar', self::CHANNEL_TUNAI);
        }
        $masukBayar = (float) $bayar->sum('jumlah_bayar');

        $kasMasuk = TransaksiKasUnit::where('jenis_transaksi', 'debet_pemasukan')
            ->whereDate('tanggal_transaksi', '>=', substr($awal, 0, 10))
            ->whereDate('tanggal_transaksi', '<=', substr($akhir, 0, 10));
        $kasKeluar = TransaksiKasUnit::where('jenis_transaksi', 'kredit_pengeluaran')
            ->whereDate('tanggal_transaksi', '>=', substr($awal, 0, 10))
            ->whereDate('tanggal_transaksi', '<=', substr($akhir, 0, 10));
        if ($unitKasId) {
            $kasMasuk->where('unit_kas_id', $unitKasId);
            $kasKeluar->where('unit_kas_id', $unitKasId);
        }
        $masukKas = (float) $kasMasuk->sum('nominal');
        $keluarKas = (float) $kasKeluar->sum('nominal');

        $masuk = $masukBayar + $masukKas;

        return [
            'saldo_masuk' => $masuk,
            'saldo_keluar' => $keluarKas,
            'saldo_bersih' => $masuk - $keluarKas,
            'rincian' => [
                'pembayaran_mahasiswa' => $masukBayar,
                'kas_masuk_lain' => $masukKas,
                'kas_keluar' => $keluarKas,
            ],
        ];
    }

    public function umkAktif(?int $unitKasId): array
    {
        $q = UangMukaKerja::with('unitKas:id,nama_kas')
            ->where('status', 'aktif')
            ->orderByDesc('tanggal');
        if ($unitKasId) {
            $q->where('unit_kas_id', $unitKasId);
        }

        return $q->limit(200)->get()->toArray();
    }

    /**
     * Baris transaksi harian/bulanan: pembayaran + koreksi negatif + mutasi kas unit.
     */
    public function lines(string $awal, string $akhir, ?int $unitKasId, ?string $metode, ?string $search = null, int $perPage = 15, int $page = 1, bool $semuaMetode = false, string $sortBy = 'tanggal', string $sortOrder = 'asc'): LengthAwarePaginator
    {
        $tunai = $this->isTunai($metode);
        $sortBy = in_array($sortBy, ['tanggal', 'jumlah', 'id'], true) ? $sortBy : 'tanggal';
        $descending = strtolower($sortOrder) === 'desc';

        $bayarQ = Pembayaran::with(['tagihan.mahasiswa.programStudi', 'tagihan.calonMahasiswa', 'unitKas.akunKeuangan'])
            ->where('status', 'success')
            ->whereDate('waktu_bayar', '>=', substr($awal, 0, 10))
            ->whereDate('waktu_bayar', '<=', substr($akhir, 0, 10));
        $koreksiQ = Pembayaran::with(['tagihan.mahasiswa.programStudi', 'tagihan.calonMahasiswa'])
            ->where('status', 'reversed')
            ->whereDate('updated_at', '>=', substr($awal, 0, 10))
            ->whereDate('updated_at', '<=', substr($akhir, 0, 10));
        $kasQ = TransaksiKasUnit::with(['unitKas.akunKeuangan'])
            ->whereDate('tanggal_transaksi', '>=', substr($awal, 0, 10))
            ->whereDate('tanggal_transaksi', '<=', substr($akhir, 0, 10));

        foreach ([$bayarQ, $koreksiQ] as $q) {
            if ($unitKasId) {
                $q->where('unit_kas_id', $unitKasId);
            }
            if (!$semuaMetode) {
                if ($tunai) {
                    $q->whereIn('channel_bayar', self::CHANNEL_TUNAI);
                } else {
                    $q->whereNotIn('channel_bayar', self::CHANNEL_TUNAI);
                }
            }
        }
        if ($unitKasId) {
            $kasQ->where('unit_kas_id', $unitKasId);
        }

        $lines = collect();

        foreach ($bayarQ->orderBy('waktu_bayar')->get() as $p) {
            $mhs = $p->tagihan?->mahasiswa;
            $lines->push([
                'id' => 'bayar-' . $p->id,
                'tanggal' => $p->waktu_bayar?->toDateTimeString(),
                'kode_transaksi' => $p->kode_transaksi,
                'nomor_tagihan' => $p->tagihan?->nomor_tagihan,
                'mahasiswa_nim' => $mhs?->nim ?? $p->tagihan?->calonMahasiswa?->nim,
                'mahasiswa_nama' => $mhs?->nama_lengkap ?? $p->tagihan?->calonMahasiswa?->nama_lengkap,
                'mahasiswa_prodi' => $mhs?->programStudi?->nama ?? null,
                'kode_prodi' => $mhs?->program_studi_id,
                'uraian' => 'Pembayaran ' . ($p->tagihan?->nomor_tagihan ?? ''),
                'kode_akun' => $p->unitKas?->akunKeuangan?->kode_akun,
                'jumlah' => (float) $p->jumlah_bayar,
                'jenis_transaksi' => 'masuk',
                'channel' => $p->channel_bayar,
            ]);
        }

        foreach ($koreksiQ->orderBy('updated_at')->get() as $p) {
            $mhs = $p->tagihan?->mahasiswa;
            $lines->push([
                'id' => 'koreksi-' . $p->id,
                'tanggal' => $p->updated_at?->toDateTimeString(),
                'kode_transaksi' => $p->kode_transaksi,
                'nomor_tagihan' => $p->tagihan?->nomor_tagihan,
                'mahasiswa_nim' => $mhs?->nim,
                'mahasiswa_nama' => $mhs?->nama_lengkap,
                'mahasiswa_prodi' => $mhs?->programStudi?->nama ?? null,
                'kode_prodi' => $mhs?->program_studi_id,
                'uraian' => 'KOREKSI ' . ($p->kode_transaksi ?? ''),
                'kode_akun' => null,
                'jumlah' => -1 * (float) $p->jumlah_bayar,
                'jenis_transaksi' => 'masuk',
                'channel' => 'KOREKSI',
            ]);
        }

        foreach ($kasQ->orderBy('tanggal_transaksi')->orderBy('id')->get() as $k) {
            $masuk = $k->jenis_transaksi === 'debet_pemasukan';
            $lines->push([
                'id' => 'kas-' . $k->id,
                'tanggal' => $k->tanggal_transaksi?->toDateString(),
                'kode_transaksi' => $k->kode_transaksi,
                'nomor_tagihan' => null,
                'mahasiswa_nim' => null,
                'mahasiswa_nama' => null,
                'mahasiswa_prodi' => null,
                'kode_prodi' => null,
                'uraian' => $k->keterangan,
                'kode_akun' => $k->unitKas?->akunKeuangan?->kode_akun,
                'jumlah' => ($masuk ? 1 : -1) * (float) $k->nominal,
                'jenis_transaksi' => $masuk ? 'masuk' : 'keluar',
                'channel' => 'KAS_UNIT',
            ]);
        }

        if ($search) {
            $s = mb_strtolower(trim($search));
            $lines = $lines->filter(fn ($l) => str_contains(mb_strtolower((string) ($l['kode_transaksi'] ?? '') . ' ' . ($l['uraian'] ?? '') . ' ' . ($l['mahasiswa_nama'] ?? '') . ' ' . ($l['mahasiswa_nim'] ?? '')), $s))->values();
        }

        $lines = $lines->sortBy(function ($l) use ($sortBy) {
            $primer = $sortBy === 'jumlah' ? (float) ($l['jumlah'] ?? 0) : (string) ($l[$sortBy] ?? '');
            return [$primer, (string) ($l['id'] ?? '')];
        }, SORT_REGULAR, $descending)->values();

        $total = $lines->count();
        $items = $lines->forPage($page, $perPage)->values();

        return new LengthAwarePaginator($items, $total, $perPage, $page, ['path' => request()->url(), 'query' => request()->query()]);
    }

    public function periodik(string $awal, string $akhir, ?string $code, bool $onlyMhs, ?string $search, int $perPage, int $page, string $sortBy = 'tanggal', string $sortOrder = 'asc'): LengthAwarePaginator
    {
        $paginator = $this->lines($awal, $akhir, null, 'SEMUA', $search, PHP_INT_MAX, 1, true, $sortBy, $sortOrder);
        $lines = collect($paginator->items());

        if ($code) {
            $c = trim($code);
            $lines = $lines->filter(fn ($l) => str_starts_with((string) ($l['kode_akun'] ?? ''), $c))->values();
        }
        if ($onlyMhs) {
            $lines = $lines->filter(fn ($l) => !empty($l['nomor_tagihan']))->values();
        }

        $total = $lines->count();
        $items = $lines->forPage($page, $perPage)->values();

        return new LengthAwarePaginator($items, $total, $perPage, $page, ['path' => request()->url(), 'query' => request()->query()]);
    }

    public function perAkun(string $awal, string $akhir, ?string $code): array
    {
        $paginator = $this->lines($awal, $akhir, null, 'SEMUA', null, PHP_INT_MAX, 1, true);

        return collect($paginator->items())
            ->groupBy(fn ($l) => $l['kode_akun'] ?? 'TANPA_KODE')
            ->map(fn ($rows, $kode) => [
                'kode_akun' => $kode,
                'jumlah' => round($rows->sum('jumlah'), 2),
                'jumlah_transaksi' => $rows->count(),
            ])
            ->when($code, fn ($c) => $c->filter(fn ($r) => str_starts_with((string) $r['kode_akun'], trim((string) $code))))
            ->sortBy('kode_akun')
            ->values()
            ->toArray();
    }

    /**
     * Rekap per mahasiswa: tagihan + bayar (success, hormati cutoff) + potongan + kekurangan.
     */
    public function rekapMahasiswa(int $mahasiswaId, ?string $cutoff = null): array
    {
        $tagihans = TagihanMahasiswa::with(['details.masterBiaya', 'potonganTagihan', 'pembayarans', 'tahunAkademik'])
            ->where('mahasiswa_id', $mahasiswaId)
            ->orderBy('id')
            ->get();

        $beasiswa = MahasiswaBeasiswa::with('beasiswa')
            ->where('mahasiswa_id', $mahasiswaId)
            ->where(function ($q) {
                $q->where('status', 'aktif')->orWhereNull('status');
            })
            ->first();

        $rows = [];
        $totalTagihan = 0;
        $totalBayar = 0;
        $totalPotongan = 0;
        $totalKekurangan = 0;

        foreach ($tagihans as $t) {
            $bayar = $cutoff
                ? (float) $t->pembayarans->where('status', 'success')->filter(function ($p) use ($cutoff) {
                    $tgl = $p->waktu_bayar ? $p->waktu_bayar->toDateString() : null;
                    return $tgl && $tgl <= $cutoff;
                })->sum('jumlah_bayar')
                : (float) $t->total_bayar;

            $bersih = (float) ($t->total_tagihan + $t->total_denda - $t->total_potongan);
            $kekurangan = max(0, $bersih - $bayar);
            if ($beasiswa) {
                $kekurangan = 0;
            }

            $totalTagihan += (float) $t->total_tagihan;
            $totalBayar += $bayar;
            $totalPotongan += (float) $t->total_potongan;
            $totalKekurangan += $kekurangan;

            $rows[] = [
                'tagihan_id' => $t->id,
                'nomor_tagihan' => $t->nomor_tagihan,
                'periode' => $t->tahunAkademik?->nama,
                'status' => $t->status,
                'total_tagihan' => (float) $t->total_tagihan,
                'total_denda' => (float) $t->total_denda,
                'total_potongan' => (float) $t->total_potongan,
                'total_bayar' => $bayar,
                'kekurangan' => $kekurangan,
                'beasiswa' => $beasiswa?->beasiswa?->nama,
                'details' => $t->details->map(fn ($d) => [
                    'komponen' => $d->keterangan ?: ($d->masterBiaya?->nama ?? 'Komponen'),
                    'kode' => $d->masterBiaya?->kode,
                    'nominal_bersih' => (float) $d->nominal_bersih,
                    'terbayar' => (float) $d->terbayar,
                ])->values(),
            ];
        }

        $history = Pembayaran::whereIn('tagihan_id', $tagihans->pluck('id'))
            ->when($cutoff, fn ($q) => $q->whereDate('waktu_bayar', '<=', $cutoff))
            ->orderBy('waktu_bayar')
            ->get()
            ->map(fn ($p) => [
                'kode_transaksi' => $p->kode_transaksi,
                'waktu_bayar' => $p->waktu_bayar?->toDateTimeString(),
                'channel' => $p->channel_bayar,
                'jumlah' => ($p->status === 'reversed' ? -1 : 1) * (float) $p->jumlah_bayar,
                'status' => $p->status,
            ])
            ->values();

        return [
            'mahasiswa_id' => $mahasiswaId,
            'ada_beasiswa' => (bool) $beasiswa,
            'beasiswa' => $beasiswa?->beasiswa?->nama,
            'ringkasan' => [
                'total_tagihan' => $totalTagihan,
                'total_bayar' => $totalBayar,
                'total_potongan' => $totalPotongan,
                'total_kekurangan' => $totalKekurangan,
                'jumlah_tagihan' => count($rows),
            ],
            'tagihan' => $rows,
            'history' => $history,
        ];
    }

    /**
     * Rekap per prodi/kelas (parity legacy rekapperprodikelas): input daftar mahasiswa,
     * hitung dari Tagihan + Pembayaran success + Potongan; beasiswa aktif menolkan kekurangan.
     */
    public function rekapPerprodiKelas(array $items, ?int $batasSemester = null, ?string $batasTanggal = null): array
    {
        if (count($items) > 500) {
            throw new \InvalidArgumentException('Maksimal 500 mahasiswa per rekap.');
        }

        $ids = collect($items)->pluck('mahasiswa_id')->filter()->map(fn ($v) => (int) $v)->unique()->values();
        if ($ids->isEmpty()) {
            return ['data' => [], 'header' => [], 'ringkasan' => ['mahasiswa' => 0, 'total_tagihan' => 0, 'total_bayar' => 0, 'total_kekurangan' => 0]];
        }

        $tagihanQ = TagihanMahasiswa::with(['mahasiswa.programStudi', 'details.masterBiaya', 'pembayarans', 'potonganTagihan'])
            ->whereIn('mahasiswa_id', $ids);
        if ($batasSemester) {
            $tagihanQ->where(function ($q) use ($batasSemester) {
                $q->whereNull('semester')->orWhere('semester', '<=', $batasSemester);
            });
        }
        $tagihans = $tagihanQ->get()->groupBy('mahasiswa_id');

        $beasiswaIds = MahasiswaBeasiswa::whereIn('mahasiswa_id', $ids)
            ->where(function ($q) {
                $q->where('status', 'aktif')->orWhereNull('status');
            })
            ->pluck('mahasiswa_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        $header = [];
        $data = [];
        $sumTagihan = 0;
        $sumBayar = 0;
        $sumKurang = 0;

        foreach ($items as $item) {
            $mid = (int) ($item['mahasiswa_id'] ?? 0);
            $rows = $tagihans->get($mid, collect());
            $tTagihan = 0;
            $tBayar = 0;
            $tPotongan = 0;
            $tKurang = 0;
            $komponen = [];

            foreach ($rows as $t) {
                $bayar = $batasTanggal
                    ? (float) $t->pembayarans->where('status', 'success')->filter(function ($p) use ($batasTanggal) {
                        $tgl = $p->waktu_bayar ? $p->waktu_bayar->toDateString() : null;
                        return $tgl && $tgl <= $batasTanggal;
                    })->sum('jumlah_bayar')
                    : (float) $t->total_bayar;

                $bersih = (float) ($t->total_tagihan + $t->total_denda - $t->total_potongan);
                $kurang = max(0, $bersih - $bayar);
                if (in_array($mid, $beasiswaIds, true)) {
                    $kurang = 0;
                }

                $tTagihan += (float) $t->total_tagihan;
                $tBayar += $bayar;
                $tPotongan += (float) $t->total_potongan;
                $tKurang += $kurang;

                foreach ($t->details as $d) {
                    $nama = $d->keterangan ?: ($d->masterBiaya?->nama ?? 'Komponen');
                    $header[$nama] = $nama;
                    $komponen[$nama] = ($komponen[$nama] ?? 0) + max(0, (float) $d->nominal_bersih - (float) $d->terbayar);
                }
            }

            $sumTagihan += $tTagihan;
            $sumBayar += $tBayar;
            $sumKurang += $tKurang;

            $data[] = [
                'mahasiswa_id' => $mid,
                'nim' => $item['nim'] ?? $rows->first()?->mahasiswa?->nim,
                'nama' => $item['nama'] ?? $rows->first()?->mahasiswa?->nama_lengkap,
                'program_studi' => $rows->first()?->mahasiswa?->programStudi?->nama,
                'beasiswa' => in_array($mid, $beasiswaIds, true),
                'total_tagihan' => $tTagihan,
                'total_bayar' => $tBayar,
                'total_potongan' => $tPotongan,
                'kekurangan' => $tKurang,
                'komponen' => $komponen,
            ];
        }

        return [
            'data' => $data,
            'header' => array_values($header),
            'ringkasan' => [
                'mahasiswa' => count($data),
                'total_tagihan' => $sumTagihan,
                'total_bayar' => $sumBayar,
                'total_kekurangan' => $sumKurang,
            ],
        ];
    }
}
