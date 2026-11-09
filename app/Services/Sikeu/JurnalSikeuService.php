<?php

namespace App\Services\Sikeu;

use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\DetailJurnalUmum;
use App\Models\Sikeu\DispensasiTagihan;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\PengeluaranKampus;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\UnitKas;
use Illuminate\Support\Str;

/**
 * Jurnal otomatis SIKEU dengan basis akrual:
 * - Terbit tagihan : Dr Piutang (103.01) / Cr Pendapatan (401.01 UKT / 401.02 SPMB)
 * - Bayar           : Dr Kas/Bank / Cr Piutang (103.01)
 * - Koreksi bayar   : Dr Piutang / Cr Kas
 * - Hapus tagihan   : Dr Pendapatan / Cr Piutang (pembalik penerbitan)
 * - Potongan        : Dr Beban Beasiswa & Potongan (504.01) / Cr Piutang (+ sebaliknya saat batal)
 * - Dispensasi ACC  : jurnal memorandum bernilai nol (tanpa efek saldo, hanya jejak)
 *
 * Semua method berasumsi dipanggil di dalam transaksi database milik controller.
 */
class JurnalSikeuService
{
    /**
     * Kode akun COA baku SIKEU (terpusat di satu lokasi agar tidak
     * tersebar sebagai literal di tiap method jurnal).
     */
    public const AKUN_BEBAN_OPERASIONAL = '502.01';
    public const AKUN_BEBAN_PEMELIHARAAN = '502.02';
    public const AKUN_BEBAN_LABORATORIUM = '502.03';
    public const AKUN_BEBAN_PENYUSUTAN = '505.01';
    public const AKUN_AKUMULASI_PENYUSUTAN = '105.01';
    public const AKUN_PIUTANG = '103.01';
    public const AKUN_PENDAPATAN_UKT = '401.01';
    public const AKUN_PENDAPATAN_SPMB = '401.02';
    public const AKUN_KAS_TUNAI = '101.01';
    public const AKUN_KAS_BANK = '102.01';
    public const AKUN_BEBAN_GAJI = '501.01';
    public const AKUN_BEBAN_TUNJANGAN_SDM = '501.02';
    public const AKUN_UTANG_PPH21 = '202.01';
    public const AKUN_UTANG_PPH23 = '202.02';
    public const AKUN_UTANG_PPN = '202.03';
    public const AKUN_UTANG_POTONGAN = '201.01';
    public const AKUN_BEBAN_POTONGAN = '504.01';

    /**
     * Prefix nomor jurnal per jenis, dapat dikonfigurasi kampus via
     * menu Pengaturan (SystemSetting `sikeu.jurnal_prefix_*`).
     */
    public const PREFIX_DEFAULTS = [
        'pembayaran' => 'JRN-PAY',
        'penerbitan' => 'JRN-TAG',
        'koreksi' => 'JRN-REV',
        'pembatalan_tagihan' => 'JRN-BTL',
        'potongan' => 'JRN-POT',
        'pembatalan_potongan' => 'JRN-BPT',
        'dispensasi_memo' => 'JRN-DSP',
        'penutupan' => 'JRN-TUTUP',
        'pemasukan' => 'JRN-INC',
        'pengeluaran' => 'JRN-EXP',
        'penyetoran_pajak' => 'JRN-TAX',
        'reimbursement' => 'JRN-RMB',
        'penggajian' => 'JRN-GAJI',
        'penyusutan' => 'JRN-DEP',
        'saldo_awal' => 'JRN-OPEN',
        'manual' => 'JRN-',
    ];

    public static function prefix(string $jenis): string
    {
        $stored = \App\Models\SystemSetting::get('sikeu.jurnal_prefix_' . $jenis, self::PREFIX_DEFAULTS[$jenis] ?? 'JRN-');
        $stored = strtoupper(trim((string) $stored));

        if (!preg_match('/^[A-Z0-9-]{1,12}$/', $stored)) {
            return self::PREFIX_DEFAULTS[$jenis] ?? 'JRN-';
        }

        return $stored;
    }

    public static function daftarPrefix(): array
    {
        $out = [];
        foreach (self::PREFIX_DEFAULTS as $jenis => $default) {
            $out[$jenis] = [
                'default' => $default,
                'nilai' => self::prefix($jenis),
            ];
        }

        return $out;
    }
    public static function akunPiutang(): AkunKeuangan
    {
        return self::akunWajib(self::AKUN_PIUTANG, 'Piutang UKT/SPP');
    }

    public static function akunPendapatan(bool $isCalon): AkunKeuangan
    {
        $kode = $isCalon ? self::AKUN_PENDAPATAN_SPMB : self::AKUN_PENDAPATAN_UKT;
        return self::akunWajib($kode, 'Pendapatan mahasiswa');
    }

    public static function akunKas(bool $isTunai): AkunKeuangan
    {
        $kode = $isTunai ? self::AKUN_KAS_TUNAI : self::AKUN_KAS_BANK;
        return self::akunWajib($kode, 'Kas/Bank');
    }

    public static function akunBebanPotongan(): AkunKeuangan
    {
        return self::akunWajib(self::AKUN_BEBAN_POTONGAN, 'Beban Beasiswa & Potongan');
    }

    public static function akunUtangPajak(string $jenisPajak): AkunKeuangan
    {
        $kode = match ($jenisPajak) {
            'pph_21' => self::AKUN_UTANG_PPH21,
            'pph_23' => self::AKUN_UTANG_PPH23,
            'ppn_11' => self::AKUN_UTANG_PPN,
            default => throw new \InvalidArgumentException("Jenis pajak '{$jenisPajak}' tidak memiliki pemetaan akun utang pajak.")
        };

        return self::akunWajib($kode, "Utang pajak {$jenisPajak}");
    }

    protected static function akunWajib(string $kode, string $label): AkunKeuangan
    {
        return AkunKeuangan::where('kode_akun', $kode)->first()
            ?? throw new \DomainException("Akun {$label} ({$kode}) belum dikonfigurasi di COA. Jurnal otomatis dibatalkan.");
    }

    /**
     * Akun kas-bank mengikuti kanal UnitKas (tunai/bank_manual/bank_h2h/xendit).
     * Unit kas harus memiliki pemetaan COA yang eksplisit. Kode default hanya
     * berlaku untuk transaksi yang memang tidak memakai UnitKas.
     */
    public static function akunKasUnit(?UnitKas $unitKas, string $defaultKode = self::AKUN_KAS_TUNAI): AkunKeuangan
    {
        if ($unitKas) {
            $terpetakan = $unitKas->akunKeuangan;
            if ($terpetakan && $terpetakan->kelompok === 'aset') {
                return $terpetakan;
            }

            throw new \DomainException("Unit kas {$unitKas->nama_kas} belum dipetakan ke akun COA aset.");
        }

        return self::akunWajib($defaultKode, 'Kas/Bank unit');
    }

    /**
     * Resolve UnitKas penerima berdasarkan channel pembayaran mahasiswa:
     * - Xendit (VA Xendit / QRIS) -> kanal xendit
     * - VA bank (H2H) -> kanal bank_h2h bank yang sama, fallback bank_manual bank yang sama
     * Mengembalikan null bila tidak ada kanal cocok (caller memakai fallback lama).
     */
    public static function resolveUnitKasUntukChannel(?string $channel, ?string $bankKode): ?UnitKas
    {
        $ch = strtoupper($channel ?? '');
        $bank = strtoupper(trim($bankKode ?? ''));

        if (str_contains($ch, 'XENDIT') || $ch === 'QRIS') {
            return UnitKas::where('kanal', 'xendit')->where('status', true)->first();
        }

        if (in_array($ch, ['TUNAI', 'CASH', 'LOKET_TUNAI'])) {
            return UnitKas::where('kanal', 'tunai')->where('status', true)->first();
        }

        if ($bank !== '') {
            $perBank = fn ($q) => $q->where('bank_name', $bank)->orWhere('bank_name', 'like', "%{$bank}%");

            $h2h = UnitKas::where('kanal', 'bank_h2h')->where('status', true)->where($perBank)->first();
            if ($h2h) {
                return $h2h;
            }

            $manual = UnitKas::where('kanal', 'bank_manual')->where('status', true)->where($perBank)->first();
            if ($manual) {
                return $manual;
            }
        }

        return null;
    }

    protected static function header(
        string $prefix,
        string $jenisSumber,
        ?int $referensiId,
        string $keterangan,
        float $total,
        ?string $tanggal = null
    ): JurnalUmum
    {
        do {
            $nomorJurnal = $prefix . '-' . date('Ymd') . '-' . strtoupper(Str::random(6)) . ($referensiId ? '-' . $referensiId : '');
        } while (JurnalUmum::where('nomor_jurnal', $nomorJurnal)->exists());

        return JurnalUmum::create([
            'nomor_jurnal' => $nomorJurnal,
            'tanggal_jurnal' => $tanggal ?? now()->toDateString(),
            'jenis_sumber' => $jenisSumber,
            'referensi_id' => $referensiId,
            'keterangan' => $keterangan,
            'status_posting' => 'posted',
            'total_debet' => $total,
            'total_kredit' => $total,
            'created_by' => auth()->id(),
            'posted_by' => auth()->id(),
            'posted_at' => now(),
        ]);
    }

    protected static function baris(JurnalUmum $jurnal, int $akunId, float $debet, float $kredit, string $keterangan): void
    {
        DetailJurnalUmum::create([
            'jurnal_id' => $jurnal->id,
            'akun_id' => $akunId,
            'debet' => $debet,
            'kredit' => $kredit,
            'keterangan' => $keterangan,
        ]);
    }

    public static function jurnalPengeluaranKampus(
        PengeluaranKampus $pengeluaran,
        AkunKeuangan $akunBeban,
        AkunKeuangan $akunKas,
        ?AkunKeuangan $akunUtangPajak
    ): JurnalUmum {
        $nominal = (float) $pengeluaran->nominal;
        $pajak = (float) $pengeluaran->nominal_pajak;
        $net = (float) $pengeluaran->net_dibayarkan;
        $beban = $nominal + ($pengeluaran->jenis_pajak === 'ppn_11' ? $pajak : 0);

        if ($nominal <= 0 || $pajak < 0 || $net < 0 || abs($beban - $pajak - $net) > 0.01) {
            throw new \DomainException('Nilai pengeluaran, pajak, dan pembayaran bersih tidak seimbang; jurnal dibatalkan.');
        }
        if ($pajak > 0 && !$akunUtangPajak) {
            throw new \DomainException("Akun utang untuk jenis pajak {$pengeluaran->jenis_pajak} belum dikonfigurasi.");
        }

        $jurnal = self::header(
            self::prefix('pengeluaran'),
            'pengeluaran_manual',
            (int) $pengeluaran->id,
            "Pengeluaran {$pengeluaran->kategori} - {$pengeluaran->nama_vendor}",
            $beban,
            $pengeluaran->tanggal_transaksi ? \Illuminate\Support\Carbon::parse($pengeluaran->tanggal_transaksi)->toDateString() : null
        );

        self::baris($jurnal, $akunBeban->id, $beban, 0, "Beban {$pengeluaran->kategori} {$pengeluaran->nama_vendor}");
        self::baris($jurnal, $akunKas->id, 0, $net, "Pembayaran kas/bank ke {$pengeluaran->nama_vendor}");
        if ($pajak > 0) {
            self::baris($jurnal, $akunUtangPajak->id, 0, $pajak, "Utang Pajak {$pengeluaran->jenis_pajak} atas transaksi {$pengeluaran->nomor_transaksi}");
        }

        return $jurnal;
    }

    public static function jurnalPenyetoranPajak(
        PengeluaranKampus $pengeluaran,
        UnitKas $unitKas,
        string $ntpn,
        string $tanggal
    ): JurnalUmum {
        $nominal = (float) $pengeluaran->nominal_pajak;
        if ($nominal <= 0) {
            throw new \DomainException('Nilai pajak harus lebih dari nol untuk dibuatkan jurnal penyetoran.');
        }

        $akunUtangPajak = self::akunUtangPajak((string) $pengeluaran->jenis_pajak);
        $akunKas = self::akunKasUnit($unitKas);
        $jurnal = self::header(
            self::prefix('penyetoran_pajak'),
            'pengeluaran_manual',
            (int) $pengeluaran->id,
            "Penyetoran Pajak {$pengeluaran->jenis_pajak} ke Kas Negara (NTPN: {$ntpn})",
            $nominal,
            $tanggal
        );

        self::baris($jurnal, $akunUtangPajak->id, $nominal, 0, "Pelunasan Utang Pajak {$pengeluaran->jenis_pajak} NTPN: {$ntpn}");
        self::baris($jurnal, $akunKas->id, 0, $nominal, "Setoran Pajak Kas Negara NTPN: {$ntpn}");

        return $jurnal;
    }

    /**
     * Penerbitan tagihan: Dr Piutang / Cr Pendapatan.
     */
    public static function jurnalPenerbitanTagihan(TagihanMahasiswa $tagihan): JurnalUmum
    {
        $isCalon = (bool) $tagihan->calon_mahasiswa_id;
        $total = (float) $tagihan->total_tagihan;

        $jurnal = self::header(
            self::prefix('penerbitan'),
            'pembayaran_mahasiswa',
            $tagihan->id,
            "Penerbitan tagihan {$tagihan->nomor_tagihan}",
            $total
        );

        self::baris($jurnal, self::akunPiutang()->id, $total, 0, "Piutang tagihan {$tagihan->nomor_tagihan}");
        self::baris($jurnal, self::akunPendapatan($isCalon)->id, 0, $total, "Pendapatan tagihan {$tagihan->nomor_tagihan}");

        return $jurnal;
    }

    /**
     * Pembayaran kasir/loket: Dr Kas/Bank / Cr Piutang.
     */
    public static function jurnalPembayaranKasir(Pembayaran $pembayaran, string $channel, TagihanMahasiswa $tagihan, ?UnitKas $unitKas = null): JurnalUmum
    {
        $isTunai = in_array(strtoupper($channel), ['LOKET_TUNAI', 'TUNAI', 'CASH']);
        $channelLabel = $isTunai ? 'Tunai Loket Kasir' : 'Transfer Bank (Non-Tunai)';
        $total = (float) $pembayaran->jumlah_bayar;

        $jurnal = self::header(
            self::prefix('pembayaran'),
            'pembayaran_mahasiswa',
            $pembayaran->id,
            "Pembayaran {$channelLabel} - {$tagihan->nomor_tagihan}",
            $total
        );

        self::baris($jurnal, self::akunKasUnit($unitKas, $isTunai ? '101.01' : '102.01')->id, $total, 0, "Penerimaan {$channelLabel} pembayaran mahasiswa" . ($unitKas ? " ({$unitKas->nama_kas})" : ''));
        self::baris($jurnal, self::akunPiutang()->id, 0, $total, "Pelunasan piutang {$tagihan->nomor_tagihan}");

        return $jurnal;
    }

    /**
     * Koreksi/pembatalan pembayaran: Dr Piutang / Cr Kas.
     */
    public static function jurnalKoreksiPembayaran(Pembayaran $pembayaran, string $alasan): JurnalUmum
    {
        $channel = $pembayaran->channel_bayar ?? 'LOKET_TUNAI';
        $isTunai = in_array(strtoupper($channel), ['LOKET_TUNAI', 'TUNAI', 'CASH']);
        $total = (float) $pembayaran->jumlah_bayar;

        $jurnal = self::header(
            self::prefix('koreksi'),
            'penyesuaian',
            $pembayaran->id,
            "KOREKSI PEMBATALAN: {$pembayaran->kode_transaksi} - {$alasan}",
            $total
        );

        self::baris($jurnal, self::akunPiutang()->id, $total, 0, "Pengembalian piutang - koreksi {$pembayaran->kode_transaksi}");
        self::baris($jurnal, self::akunKasUnit($pembayaran->unitKas, $isTunai ? '101.01' : '102.01')->id, 0, $total, "Pengembalian kas - koreksi {$pembayaran->kode_transaksi}");

        return $jurnal;
    }

    /**
     * Penghapusan tagihan belum bayar: Dr Pendapatan / Cr Piutang
     * (pembalik jurnal penerbitan). Dilewati bila tidak ada jurnal
     * penerbitan (data lawas pra-akrual) agar buku tidak timpang.
     */
    public static function jurnalPembatalanTagihan(TagihanMahasiswa $tagihan): ?JurnalUmum
    {
        $adaPenerbitan = JurnalUmum::where('jenis_sumber', 'pembayaran_mahasiswa')
            ->where('referensi_id', $tagihan->id)
            ->where('keterangan', 'like', 'Penerbitan tagihan%')
            ->exists();

        if (!$adaPenerbitan) {
            return null;
        }

        $isCalon = (bool) $tagihan->calon_mahasiswa_id;
        $total = (float) $tagihan->total_tagihan;

        $jurnal = self::header(
            self::prefix('pembatalan_tagihan'),
            'penyesuaian',
            $tagihan->id,
            "Pembatalan tagihan {$tagihan->nomor_tagihan}",
            $total
        );

        self::baris($jurnal, self::akunPendapatan($isCalon)->id, $total, 0, "Pembalik pendapatan {$tagihan->nomor_tagihan}");
        self::baris($jurnal, self::akunPiutang()->id, 0, $total, "Pembalik piutang {$tagihan->nomor_tagihan}");

        return $jurnal;
    }

    /**
     * Pemberian potongan: Dr Beban Beasiswa & Potongan / Cr Piutang.
     */
    public static function jurnalPotongan(TagihanMahasiswa $tagihan, float $nominal, string $keterangan): JurnalUmum
    {
        $jurnal = self::header(
            self::prefix('potongan'),
            'penyesuaian',
            $tagihan->id,
            "Potongan {$tagihan->nomor_tagihan}: {$keterangan}",
            $nominal
        );

        self::baris($jurnal, self::akunBebanPotongan()->id, $nominal, 0, "Beban potongan {$tagihan->nomor_tagihan}");
        self::baris($jurnal, self::akunPiutang()->id, 0, $nominal, "Pengurang piutang {$tagihan->nomor_tagihan}");

        return $jurnal;
    }

    /**
     * Pembatalan potongan: Dr Piutang / Cr Beban Beasiswa & Potongan.
     */
    public static function jurnalPembatalanPotongan(TagihanMahasiswa $tagihan, float $nominal, string $keterangan): JurnalUmum
    {
        $jurnal = self::header(
            self::prefix('pembatalan_potongan'),
            'penyesuaian',
            $tagihan->id,
            "Pembatalan potongan {$tagihan->nomor_tagihan}: {$keterangan}",
            $nominal
        );

        self::baris($jurnal, self::akunPiutang()->id, $nominal, 0, "Pengembalian piutang {$tagihan->nomor_tagihan}");
        self::baris($jurnal, self::akunBebanPotongan()->id, 0, $nominal, "Pembalik beban potongan {$tagihan->nomor_tagihan}");

        return $jurnal;
    }

    /**
     * Persetujuan dispensasi: jurnal memorandum bernilai nol.
     * Dispensasi (cicilan/penundaan) tidak memindahkan nilai sehingga tidak
     * boleh mengubah saldo; memorandum ini hanya jejak di buku.
     */
    public static function memoDispensasi(DispensasiTagihan $dispensasi): JurnalUmum
    {
        $tagihan = $dispensasi->tagihan;

        return self::header(
            self::prefix('dispensasi_memo'),
            'penyesuaian',
            $dispensasi->id,
            "MEMO: dispensasi {$dispensasi->tipe_dispensasi} disetujui untuk tagihan {$tagihan?->nomor_tagihan} "
                . "(cicilan " . number_format((float) $dispensasi->nominal_per_cicilan, 0, ',', '.')
                . " x {$dispensasi->jumlah_cicilan}, jatuh tempo baru {$dispensasi->jatuh_tempo_baru}). Tanpa efek saldo.",
            0
        );
    }

    /**
     * Realisasi belanja operasional (LPJ diverifikasi keuangan):
     * Dr Beban Operasional (502.01) / Cr Kas (101.01).
     * Dipakai saat LPJ pengajuan operasional disetujui.
     */
    public static function jurnalRealisasiOperasional(int $referensiId, float $nominal, string $keterangan, string $kodeBeban = '502.01'): JurnalUmum
    {
        if ($nominal <= 0) {
            throw new \RuntimeException('Nominal realisasi LPJ harus lebih dari nol.');
        }

        $akunBeban = self::akunWajib($kodeBeban, 'Beban operasional');
        $akunKas = self::akunWajib(self::AKUN_KAS_TUNAI, 'Kas tunai');

        $jurnal = self::header(
            self::prefix('pengeluaran'),
            'pencairan_kas',
            $referensiId,
            $keterangan,
            $nominal
        );

        self::baris($jurnal, $akunBeban->id, $nominal, 0, "Beban operasional: {$keterangan}");
        self::baris($jurnal, $akunKas->id, 0, $nominal, "Kas keluar realisasi: {$keterangan}");

        return $jurnal;
    }

    /**
     * Pencairan reimbursement (kekurangan bayar LPJ):
     * Dr Beban Operasional (502.01) / Cr Kas unit kas pembayar.
     * jenis_sumber 'reimbursement' agar terpisah dari 'pencairan_kas'
     * sehingga keuangan bisa memfilter ref pengajuan/RMB yang belum cair.
     */
    public static function jurnalReimbursement(int $referensiId, float $nominal, string $keterangan, ?UnitKas $unitKas = null, string $kodeBeban = self::AKUN_BEBAN_OPERASIONAL): JurnalUmum
    {
        if ($nominal <= 0) {
            throw new \RuntimeException('Nominal reimbursement harus lebih dari nol.');
        }

        $akunBeban = self::akunWajib($kodeBeban, 'Beban reimbursement');
        $akunKas = self::akunKasUnit($unitKas, self::AKUN_KAS_TUNAI);

        $jurnal = self::header(
            self::prefix('reimbursement'),
            'reimbursement',
            $referensiId,
            $keterangan,
            $nominal
        );

        self::baris($jurnal, $akunBeban->id, $nominal, 0, "Beban reimbursement: {$keterangan}");
        self::baris($jurnal, $akunKas->id, 0, $nominal, "Kas keluar reimbursement: {$keterangan}");

        return $jurnal;
    }

    /**
     * Jurnal penggajian satu pegawai (dipakai saat pencairan batch gaji):
     *   Dr Beban Gaji & Honorarium (501.01) sebesar pokok + honor SKS
     *   Dr Beban Tunjangan & Insentif SDM (501.02) sebesar tunjangan non-honor
     *   Cr Kas/Bank unit kas pencair sebesar gaji bersih
     *   Cr Utang PPh 21 (202.01) sebesar potongan pajak
     *   Cr Utang Potongan (201.01) sebesar potongan non-pajak
     * Akun wajib ada di COA — gagal eksplisit bila hilang (dilarang silent-skip).
     */
    public static function jurnalGajiPegawai(\App\Models\Simpeg\GajiPegawai $gaji, ?UnitKas $unitKas = null): JurnalUmum
    {
        $pokok = (float) $gaji->gaji_pokok;
        $honor = (float) ($gaji->total_honor_sks ?? 0);
        $tunjanganLain = max(0, (float) ($gaji->total_tunjangan ?? 0) - $honor);
        $bersih = (float) $gaji->gaji_bersih;
        $pph21 = (float) ($gaji->total_pph21 ?? 0);
        $nonPajak = max(0, (float) ($gaji->total_potongan ?? 0) - $pph21);
        $bebanGaji = $pokok + $honor;

        $totalDebet = $bebanGaji + $tunjanganLain;
        $totalKredit = $bersih + $pph21 + $nonPajak;
        if ($totalDebet <= 0) {
            throw new \RuntimeException("Slip gaji ID #{$gaji->id} bernilai nol, jurnal dibatalkan.");
        }
        if (abs($totalDebet - $totalKredit) > 0.01) {
            throw new \RuntimeException("Jurnal gaji ID #{$gaji->id} tidak seimbang (D: {$totalDebet}, K: {$totalKredit}). Periksa kalkulasi payroll.");
        }

        $akunBebanGaji = AkunKeuangan::where('kode_akun', self::AKUN_BEBAN_GAJI)->first()
            ?? throw new \RuntimeException('Akun Beban Gaji (501.01) belum dikonfigurasi di COA. Jurnal otomatis dibatalkan.');
        $akunBebanTunjangan = AkunKeuangan::where('kode_akun', self::AKUN_BEBAN_TUNJANGAN_SDM)->first()
            ?? throw new \RuntimeException('Akun Beban Tunjangan (501.02) belum dikonfigurasi di COA. Jurnal otomatis dibatalkan.');
        $akunKas = self::akunKasUnit($unitKas, self::AKUN_KAS_BANK);
        $akunPajak = AkunKeuangan::where('kode_akun', self::AKUN_UTANG_PPH21)->first()
            ?? throw new \RuntimeException('Akun Utang PPh 21 (202.01) belum dikonfigurasi di COA. Jurnal otomatis dibatalkan.');
        $akunPotongan = AkunKeuangan::where('kode_akun', self::AKUN_UTANG_POTONGAN)->first()
            ?? throw new \RuntimeException('Akun Utang Potongan (201.01) belum dikonfigurasi di COA. Jurnal otomatis dibatalkan.');

        $nama = $gaji->pegawai?->nama_lengkap ?? "Pegawai #{$gaji->pegawai_id}";
        $keterangan = "Penggajian {$gaji->periode_bulan_tahun} — {$nama}";

        // jenis_sumber memakai nilai baku 'pengeluaran_manual' (CHECK constraint
        // tabel jurnal); identitas batch gaji terlihat dari prefix JRN-GAJI.
        $jurnal = self::header(self::prefix('penggajian'), 'pengeluaran_manual', $gaji->id, $keterangan, $totalDebet);

        self::baris($jurnal, $akunBebanGaji->id, $bebanGaji, 0, "Beban gaji pokok & honor: {$keterangan}");
        if ($tunjanganLain > 0) {
            self::baris($jurnal, $akunBebanTunjangan->id, $tunjanganLain, 0, "Beban tunjangan: {$keterangan}");
        }
        if ($bersih > 0) {
            self::baris($jurnal, $akunKas->id, 0, $bersih, "Kas keluar gaji bersih: {$keterangan}");
        }
        if ($pph21 > 0) {
            self::baris($jurnal, $akunPajak->id, 0, $pph21, "Utang PPh 21: {$keterangan}");
        }
        if ($nonPajak > 0) {
            self::baris($jurnal, $akunPotongan->id, 0, $nonPajak, "Utang potongan: {$keterangan}");
        }

        return $jurnal;
    }

    /**
     * Transaksi pengeluaran kas kecil (petty cash):
     * Dr Beban (502.01) / Cr Kas Unit (akun COA milik UnitKas kas kecil).
     * Dipanggil saat Petugas Kas Kecil mencatat transaksi keluar.
     */
    public static function jurnalKeluarKasKecil(int $referensiId, float $nominal, string $keterangan, ?UnitKas $unitKas = null): JurnalUmum
    {
        if ($nominal <= 0) {
            throw new \RuntimeException('Nominal transaksi keluar kas kecil harus lebih dari nol.');
        }

        $akunBeban = self::akunWajib(self::AKUN_BEBAN_OPERASIONAL, 'Beban operasional');
        $akunKas = self::akunKasUnit($unitKas, self::AKUN_KAS_TUNAI);

        $jurnal = self::header(
            self::prefix('pengeluaran'),
            'pengeluaran_manual',
            $referensiId,
            $keterangan,
            $nominal
        );

        self::baris($jurnal, $akunBeban->id, $nominal, 0, "Beban kas kecil: {$keterangan}");
        self::baris($jurnal, $akunKas->id, 0, $nominal, "Kas kecil keluar: {$keterangan}");

        return $jurnal;
    }

    /**
     * Pengisian / top-up kas kecil saat pengajuan kas langsung disetujui:
     * Dr Kas Unit (akun COA milik UnitKas kas kecil) / Cr Kas Utama (101.01).
     * Membalik posisi saldo: kas kecil bertambah, kas utama berkurang.
     */
    public static function jurnalPengisianKasKecil(int $referensiId, float $nominal, string $keterangan, ?UnitKas $unitKas = null): JurnalUmum
    {
        if ($nominal <= 0) {
            throw new \RuntimeException('Nominal pengisian kas kecil harus lebih dari nol.');
        }

        $akunKasUtama = self::akunWajib(self::AKUN_KAS_TUNAI, 'Kas utama');
        $akunKasUnit = self::akunKasUnit($unitKas, self::AKUN_KAS_TUNAI);

        // Pengisian kas kecil harus memindahkan dana ANTAR akun kas yang berbeda.
        // Jika akun kas unit sama dengan kas utama, jurnal menjadi netral & tidak bermakna.
        if ($akunKasUnit->id === $akunKasUtama->id) {
            throw new \RuntimeException(
                'Unit kas kecil belum dipetakan ke akun COA kas tersendiri (mis. 101.02 Kas Unit / Petty Cash). '
                . 'Jurnal pengisian kas kecil dibatalkan.'
            );
        }

        $jurnal = self::header(
            self::prefix('pemasukan'),
            'pencairan_kas',
            $referensiId,
            $keterangan,
            $nominal
        );

        self::baris($jurnal, $akunKasUnit->id, $nominal, 0, "Pengisian kas kecil: {$keterangan}");
        self::baris($jurnal, $akunKasUtama->id, 0, $nominal, "Kas utama: {$keterangan}");

        return $jurnal;
    }

    /**
     * Jurnal otomatis beban & akumulasi penyusutan aset tetap (SINAPRA):
     * Dr. Beban Penyusutan Aset Tetap (505.01)
     * Cr. Akumulasi Penyusutan Aset Tetap (105.01)
     */
    public static function jurnalPenyusutanAset(
        int $referensiId,
        float $nominal,
        string $keterangan,
        ?string $tanggal = null
    ): JurnalUmum {
        if ($nominal <= 0) {
            throw new \InvalidArgumentException('Nominal beban penyusutan aset harus lebih dari nol.');
        }

        $akunBeban = self::akunWajib(self::AKUN_BEBAN_PENYUSUTAN, 'Beban penyusutan aset tetap');
        $akunAkumulasi = self::akunWajib(self::AKUN_AKUMULASI_PENYUSUTAN, 'Akumulasi penyusutan aset tetap');

        $jurnal = self::header(
            self::prefix('penyusutan'),
            'penyesuaian',
            $referensiId,
            $keterangan,
            $nominal
        );

        if ($tanggal) {
            $jurnal->update(['tanggal_jurnal' => $tanggal]);
        }

        self::baris($jurnal, $akunBeban->id, $nominal, 0, "Beban penyusutan: {$keterangan}");
        self::baris($jurnal, $akunAkumulasi->id, 0, $nominal, "Akumulasi penyusutan: {$keterangan}");

        return $jurnal;
    }
}
