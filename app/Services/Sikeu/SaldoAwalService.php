<?php

namespace App\Services\Sikeu;

use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\DetailJurnalUmum;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\PeriodeAkuntansi;
use App\Models\Sikeu\SaldoAwal;
use App\Models\Sikeu\SaldoAwalDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Service saldo awal (opening balance) akuntansi SIKEU.
 *
 * Hanya akun neraca (aset, liabilitas, ekuitas) yang boleh diisi. Selisih
 * debet-kredit otomatis diseimbangkan ke akun ekuitas 301.03 agar neraca
 * tetap balance tanpa campur tangan manual.
 */
class SaldoAwalService
{
    public const AKUN_PENYEIMBANG = '301.03';

    public const KELOMPOK_VALID = ['aset', 'liabilitas', 'ekuitas'];

    /**
     * Data halaman saldo awal: header aktif, COA, akun penyeimbang, riwayat.
     */
    public function getSaldoAwal(): array
    {
        $draft = SaldoAwal::with($this->relasi())
            ->where('status', 'draft')
            ->latest('id')
            ->first();

        $posted = SaldoAwal::with($this->relasi())
            ->where('status', 'posted')
            ->latest('id')
            ->first();

        $current = $draft ?? $posted;
        $penyeimbang = $this->akunPenyeimbang();

        $coa = AkunKeuangan::whereIn('kelompok', self::KELOMPOK_VALID)
            ->where('is_active', true)
            ->where('id', '!=', $penyeimbang->id)
            ->orderBy('kode_akun')
            ->get();

        $tanggalValidasi = $current?->tanggal?->toDateString() ?? now()->toDateString();

        return [
            'saldo_awal' => $current,
            'akun_penyeimbang' => $penyeimbang,
            'coa' => $coa,
            'validasi' => $this->cutOffStatus($tanggalValidasi, $current?->jurnal_id),
            'riwayat' => SaldoAwal::withCount('details')
                ->latest('id')
                ->limit(20)
                ->get(),
        ];
    }

    /**
     * Simpan/perbarui draft saldo awal.
     *
     * @param array{tanggal:string,keterangan?:string|null,details:array<int,array{akun_id:int|string,debet:int|float|string,kredit:int|float|string,keterangan?:string|null}>} $data
     */
    public function saveDraft(array $data, ?int $userId = null): SaldoAwal
    {
        return DB::transaction(function () use ($data, $userId) {
            if (SaldoAwal::where('status', 'posted')->lockForUpdate()->exists()) {
                throw new \DomainException(
                    'Saldo awal sudah diposting. Batalkan posting terlebih dahulu sebelum mengubah data.'
                );
            }

            $tanggal = Carbon::parse($data['tanggal'])->startOfDay();
            $periode = $this->resolvePeriode($tanggal->toDateString());

            $baris = $this->normalisasiBaris($data['details'] ?? []);
            [$debet, $kredit, $selisih, $debetFinal, $kreditFinal] = $this->hitung($baris);
            $penyeimbang = $this->akunPenyeimbang();

            $draft = SaldoAwal::where('status', 'draft')->latest('id')->lockForUpdate()->first()
                ?? new SaldoAwal();

            $draft->fill([
                'tanggal' => $tanggal->toDateString(),
                'periode_id' => $periode?->id,
                'status' => 'draft',
                'keterangan' => $data['keterangan'] ?? null,
                'total_debet' => $debetFinal,
                'total_kredit' => $kreditFinal,
                'nominal_penyeimbang' => $selisih,
                'akun_penyeimbang_id' => $penyeimbang->id,
                'jurnal_id' => null,
                'posted_by' => null,
                'posted_at' => null,
            ]);

            if (! $draft->exists) {
                $draft->created_by = $userId;
            }

            $draft->save();

            $draft->details()->delete();
            foreach ($baris as $row) {
                SaldoAwalDetail::create([
                    'saldo_awal_id' => $draft->id,
                    'akun_id' => $row['akun_id'],
                    'debet' => $row['debet'],
                    'kredit' => $row['kredit'],
                    'keterangan' => $row['keterangan'] ?? null,
                ]);
            }

            return $draft->load($this->relasi());
        });
    }

    /**
     * Posting draft menjadi jurnal umum berstatus posted.
     */
    public function post(?int $userId = null): SaldoAwal
    {
        return DB::transaction(function () use ($userId) {
            $header = SaldoAwal::where('status', 'draft')->latest('id')->lockForUpdate()->first();

            if (! $header) {
                throw new \DomainException('Tidak ada draft saldo awal yang bisa diposting.');
            }

            if ($header->details()->count() === 0) {
                throw new \DomainException('Saldo awal belum memiliki baris akun. Simpan draft terlebih dahulu.');
            }

            $tanggal = Carbon::parse($header->tanggal)->startOfDay();
            $periode = $this->resolvePeriode($tanggal->toDateString());

            // Saldo awal meringkas transaksi sebelum cut-over. Jangan izinkan
            // posting bila sudah ada jurnal lain pada/sebelum tanggal tersebut
            // agar saldo lama tidak terhitung dua kali.
            $this->assertCutOffTerpenuhi($tanggal->toDateString());

            $baris = $this->normalisasiBaris(
                $header->details()->get()->map(fn (SaldoAwalDetail $d) => [
                    'akun_id' => $d->akun_id,
                    'debet' => $d->debet,
                    'kredit' => $d->kredit,
                    'keterangan' => $d->keterangan,
                ])->all()
            );

            [$debet, $kredit, $selisih, $debetFinal, $kreditFinal] = $this->hitung($baris);
            $penyeimbang = $this->akunPenyeimbang();

            $nomor = strtoupper(
                JurnalSikeuService::prefix('saldo_awal')
                . $tanggal->format('Ymd')
                . '-'
                . Str::upper(Str::random(4))
            );

            $jurnal = JurnalUmum::create([
                'nomor_jurnal' => $nomor,
                'tanggal_jurnal' => $tanggal->toDateString(),
                'periode_id' => $periode?->id,
                'jenis_sumber' => 'penyesuaian',
                'referensi_id' => $header->id,
                'keterangan' => $header->keterangan
                    ?: 'Saldo awal akuntansi per ' . $tanggal->format('d/m/Y'),
                'status_posting' => 'posted',
                'total_debet' => $debetFinal,
                'total_kredit' => $kreditFinal,
                'created_by' => $userId,
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            foreach ($baris as $row) {
                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $row['akun_id'],
                    'debet' => $row['debet'],
                    'kredit' => $row['kredit'],
                    'keterangan' => $row['keterangan'] ?? $jurnal->keterangan,
                ]);
            }

            // Baris penyeimbang ekuitas otomatis.
            if (abs($selisih) > 0.005) {
                DetailJurnalUmum::create([
                    'jurnal_id' => $jurnal->id,
                    'akun_id' => $penyeimbang->id,
                    'debet' => $selisih < 0 ? abs($selisih) : 0,
                    'kredit' => $selisih > 0 ? $selisih : 0,
                    'keterangan' => 'Penyeimbang saldo awal',
                ]);
            }

            $header->update([
                'periode_id' => $periode?->id,
                'status' => 'posted',
                'total_debet' => $debetFinal,
                'total_kredit' => $kreditFinal,
                'nominal_penyeimbang' => $selisih,
                'akun_penyeimbang_id' => $penyeimbang->id,
                'jurnal_id' => $jurnal->id,
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            return $header->load($this->relasi());
        });
    }

    /**
     * Cek apakah tanggal saldo awal masih layak menjadi jurnal paling awal.
     *
     * Saldo awal meringkas seluruh transaksi sebelum tanggal cut-over. Karena
     * itu tidak boleh ada jurnal posted lain pada/sebelum tanggal tersebut,
     * supaya transaksi lama tidak terhitung dua kali (saldo "crash").
     *
     * @return array{tanggal_saldo_awal:string,jumlah_jurnal_sebelum:int,tanggal_jurnal_terawal:?string,siap_posting:bool,pesan:string}
     */
    public function cutOffStatus(string $tanggal, ?int $kecualiJurnalId = null): array
    {
        $tgl = Carbon::parse($tanggal)->startOfDay();

        $query = JurnalUmum::where('status_posting', 'posted')
            ->whereDate('tanggal_jurnal', '<=', $tgl->toDateString())
            ->when($kecualiJurnalId !== null, fn ($q) => $q->where('id', '!=', $kecualiJurnalId));

        $jumlah = (clone $query)->count();
        $terawal = (clone $query)->min('tanggal_jurnal');
        $terawal = $terawal ? Carbon::parse($terawal)->toDateString() : null;

        if ($jumlah === 0) {
            $pesan = 'Tidak ada jurnal lain pada/sebelum tanggal saldo awal. Saldo awal siap diposting.';
        } else {
            $pesan = "Ada {$jumlah} jurnal posted pada/sebelum {$tgl->format('d/m/Y')}"
                . ($terawal ? " (jurnal terawal {$this->formatTanggal($terawal)})" : '')
                . '. Saldo awal tidak boleh diposting karena transaksi lama akan terhitung dua kali. '
                . 'Migrasikan data lama terlebih dahulu, lalu tetapkan tanggal saldo awal setelah transaksi terakhir; '
                . 'atau kosongkan jurnal legacy yang sudah tercakup oleh saldo awal.';
        }

        return [
            'tanggal_saldo_awal' => $tgl->toDateString(),
            'jumlah_jurnal_sebelum' => $jumlah,
            'tanggal_jurnal_terawal' => $terawal,
            'siap_posting' => $jumlah === 0,
            'pesan' => $pesan,
        ];
    }

    /**
     * Pastikan tanggal saldo awal belum didahului jurnal posted lain.
     */
    public function assertCutOffTerpenuhi(string $tanggal): void
    {
        $status = $this->cutOffStatus($tanggal);

        if (! $status['siap_posting']) {
            throw new \DomainException($status['pesan']);
        }
    }

    /**
     * Tanggal cut-over saldo awal yang sudah diposting, bila ada.
     */
    public function tanggalSaldoAwalPosted(): ?string
    {
        $posted = SaldoAwal::where('status', 'posted')->latest('id')->first();

        return $posted?->tanggal?->toDateString();
    }

    /**
     * Guard untuk command migrasi/impor jurnal legacy.
     *
     * Jurnal lama hanya boleh masuk setelah cut-over saldo awal. Bila tanggal
     * jurnal berada pada/sebelum cut-over, transaksi tersebut sudah tercakup
     * dalam saldo awal sehingga harus ditolak.
     */
    public function assertTanggalBolehDicatat(string $tanggal): void
    {
        $cutOff = $this->tanggalSaldoAwalPosted();

        if ($cutOff === null) {
            return;
        }

        if (Carbon::parse($tanggal)->startOfDay()->lte(Carbon::parse($cutOff)->startOfDay())) {
            throw new \DomainException(
                'Tanggal ' . Carbon::parse($tanggal)->format('d/m/Y')
                . " berada pada/sebelum cut-off saldo awal ({$this->formatTanggal($cutOff)}). "
                . 'Transaksi lama tersebut sudah tercakup dalam saldo awal dan tidak boleh dijurnal ulang. '
                . 'Batalkan posting saldo awal bila memang ingin memigrasikan jurnal historis.'
            );
        }
    }

    private function formatTanggal(string $tanggal): string
    {
        return Carbon::parse($tanggal)->format('d/m/Y');
    }

    /**
     * Batalkan saldo awal.
     * - Bila masih draft: hapus draft beserta barisnya.
     * - Bila sudah posted: hapus jurnalnya lalu kembalikan status ke draft.
     * Ditolak bila sudah ada jurnal posted setelah tanggal saldo awal atau
     * tanggalnya berada di periode yang sudah ditutup.
     */
    public function cancel(): ?SaldoAwal
    {
        return DB::transaction(function () {
            $draft = SaldoAwal::where('status', 'draft')->latest('id')->lockForUpdate()->first();
            if ($draft) {
                $draft->details()->delete();
                $draft->delete();

                return null;
            }

            $header = SaldoAwal::where('status', 'posted')->latest('id')->lockForUpdate()->first();
            if (! $header) {
                throw new \DomainException('Tidak ada saldo awal yang bisa dibatalkan.');
            }

            $jurnal = $header->jurnal;
            if ($jurnal) {
                $this->assertPeriodeTerbuka(Carbon::parse($jurnal->tanggal_jurnal)->toDateString());

                $adaJurnalSetelahnya = JurnalUmum::where('status_posting', 'posted')
                    ->where('id', '!=', $jurnal->id)
                    ->where(function ($q) use ($jurnal) {
                        $q->whereDate('tanggal_jurnal', '>', $jurnal->tanggal_jurnal->toDateString())
                            ->orWhere(function ($qq) use ($jurnal) {
                                $qq->whereDate('tanggal_jurnal', '=', $jurnal->tanggal_jurnal->toDateString())
                                    ->where('id', '>', $jurnal->id);
                            });
                    })
                    ->exists();

                if ($adaJurnalSetelahnya) {
                    throw new \DomainException(
                        'Saldo awal tidak dapat dibatalkan karena sudah ada jurnal lain setelah tanggal saldo awal. '
                        . 'Batalkan jurnal tersebut terlebih dahulu.'
                    );
                }

                $jurnal->details()->delete();
                $jurnal->delete();
            }

            $header->update([
                'status' => 'draft',
                'jurnal_id' => null,
                'posted_by' => null,
                'posted_at' => null,
            ]);

            return $header->load($this->relasi());
        });
    }

    /**
     * Normalisasi & validasi baris saldo awal.
     *
     * @return array<int,array{akun_id:int,debet:float,kredit:float,keterangan:?string}>
     */
    private function normalisasiBaris(array $details): array
    {
        $penyeimbang = $this->akunPenyeimbang();
        $hasil = [];
        $akunDipakai = [];

        foreach ($details as $row) {
            $akunId = (int) ($row['akun_id'] ?? 0);
            $debet = round((float) ($row['debet'] ?? 0), 2);
            $kredit = round((float) ($row['kredit'] ?? 0), 2);

            if ($debet < 0 || $kredit < 0) {
                throw new \DomainException('Nominal debet/kredit tidak boleh negatif.');
            }

            if ($debet == 0.0 && $kredit == 0.0) {
                continue;
            }

            if ($akunId <= 0) {
                throw new \DomainException('Akun pada baris saldo awal wajib dipilih.');
            }

            if ($akunId === (int) $penyeimbang->id) {
                throw new \DomainException(
                    'Akun Ekuitas Saldo Awal (301.03) diseimbangkan otomatis oleh sistem dan tidak boleh diisi manual.'
                );
            }

            if (isset($akunDipakai[$akunId])) {
                throw new \DomainException('Akun yang sama tidak boleh muncul lebih dari satu baris.');
            }

            $akun = AkunKeuangan::find($akunId);
            if (! $akun) {
                throw new \DomainException('Akun saldo awal tidak ditemukan.');
            }
            if (! $akun->is_active) {
                throw new \DomainException("Akun {$akun->kode_akun} sudah tidak aktif.");
            }
            if (! in_array($akun->kelompok, self::KELOMPOK_VALID, true)) {
                throw new \DomainException(
                    "Akun {$akun->kode_akun} bukan akun neraca. Saldo awal hanya untuk aset, liabilitas, dan ekuitas."
                );
            }

            $akunDipakai[$akunId] = true;
            $hasil[] = [
                'akun_id' => $akunId,
                'debet' => $debet,
                'kredit' => $kredit,
                'keterangan' => $row['keterangan'] ?? null,
            ];
        }

        if ($hasil === []) {
            throw new \DomainException('Isi minimal satu baris saldo awal dengan nominal lebih dari nol.');
        }

        return $hasil;
    }

    /**
     * @return array{0:float,1:float,2:float,3:float,4:float}
     *         [total debet user, total kredit user, selisih, debet final, kredit final]
     */
    private function hitung(array $baris): array
    {
        $debet = round(array_sum(array_column($baris, 'debet')), 2);
        $kredit = round(array_sum(array_column($baris, 'kredit')), 2);
        $selisih = round($debet - $kredit, 2);

        if ($debet <= 0 && $kredit <= 0) {
            throw new \DomainException('Total saldo awal harus lebih dari nol.');
        }

        // Selisih positif => tambah kredit ekuitas; negatif => tambah debet ekuitas.
        $debetFinal = round($debet + ($selisih < 0 ? abs($selisih) : 0), 2);
        $kreditFinal = round($kredit + ($selisih > 0 ? $selisih : 0), 2);

        return [$debet, $kredit, $selisih, $debetFinal, $kreditFinal];
    }

    private function resolvePeriode(string $tanggal): ?PeriodeAkuntansi
    {
        $periode = PeriodeAkuntansi::whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->orderBy('tanggal_mulai')
            ->first();

        if ($periode && $periode->status === 'ditutup') {
            throw new \DomainException(
                'Tanggal ' . Carbon::parse($tanggal)->format('d/m/Y')
                . " berada pada periode {$periode->nama_periode} yang sudah ditutup."
            );
        }

        return $periode;
    }

    private function assertPeriodeTerbuka(string $tanggal): void
    {
        $this->resolvePeriode($tanggal);
    }

    private function akunPenyeimbang(): AkunKeuangan
    {
        $akun = AkunKeuangan::where('kode_akun', self::AKUN_PENYEIMBANG)->first();

        if (! $akun) {
            throw new \RuntimeException(
                'Akun Ekuitas Saldo Awal (301.03) belum dikonfigurasi di COA. Jalankan migrasi/seeder akuntansi.'
            );
        }

        return $akun;
    }

    private function relasi(): array
    {
        return [
            'details.akun',
            'akunPenyeimbang',
            'jurnal',
            'periode',
        ];
    }
}
