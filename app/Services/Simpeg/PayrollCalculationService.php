<?php

namespace App\Services\Simpeg;

use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPengampu;
use App\Models\Simpeg\GajiDetail;
use App\Models\Simpeg\GajiPegawai;
use App\Models\Simpeg\MasterBracketPph21;
use App\Models\Simpeg\MasterKomponenGaji;
use App\Models\Simpeg\MasterSkalaGajiPokok;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PegawaiKomponenGaji;
use App\Models\Simpeg\PresensiPegawai;
use App\Models\Simpeg\RiwayatJabatan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollCalculationService
{
    /**
     * Hitung kalkulasi payroll seluruh atau satu pegawai untuk periode tertentu (YYYY-MM).
     */
    public function calculatePayroll(string $periode, ?int $pegawaiId = null): array
    {
        $query = Pegawai::query()->where('is_active', true);
        if ($pegawaiId) {
            $query->where('id', $pegawaiId);
        }
        $pegawaiList = $query->get();

        $masterKomponens = MasterKomponenGaji::where('is_active', true)
            ->orderBy('urutan', 'asc')
            ->get();

        $processedList = [];

        DB::transaction(function () use ($pegawaiList, $masterKomponens, $periode, &$processedList) {
            foreach ($pegawaiList as $pegawai) {
                $gaji = $this->calculateSinglePegawaiPayroll($pegawai, $masterKomponens, $periode);
                $processedList[] = $gaji;
            }
        });

        return [
            'periode' => $periode,
            'total_pegawai' => count($processedList),
            'payrolls' => $processedList,
        ];
    }

    /**
     * Kalkulasi penggajian untuk satu pegawai.
     */
    public function calculateSinglePegawaiPayroll(Pegawai $pegawai, $masterKomponens, string $periode): GajiPegawai
    {
        // 1. Ambil kustomisasi komponen khusus pegawai (jika ada override)
        $customKomponens = PegawaiKomponenGaji::where('pegawai_id', $pegawai->id)
            ->where('is_active', true)
            ->get()
            ->keyBy('komponen_gaji_id');

        // 2. Hitung Masa Kerja (Lama Bekerja) Pegawai
        $masaKerjaTahun = 0;
        if ($pegawai->tanggal_masuk) {
            $refDate = Carbon::createFromFormat('Y-m', $periode)->endOfMonth();
            $masaKerjaTahun = (int) Carbon::parse($pegawai->tanggal_masuk)->diffInYears($refDate);
        }

        // 3. Hitung Log Presensi (Tepat Waktu, Terlambat, Alpha)
        $presensiLogs = PresensiPegawai::where('pegawai_id', $pegawai->id)
            ->where('tanggal', 'LIKE', "{$periode}%")
            ->get();

        $hariTepatWaktu = $presensiLogs->filter(function ($log) {
            if ($log->status_kehadiran !== 'hadir' || empty($log->jam_masuk)) {
                return false;
            }
            $jam = substr((string) $log->jam_masuk, 0, 8);
            return $jam <= '08:15:00';
        })->count();

        $hariTerlambat = $presensiLogs->filter(function ($log) {
            if ($log->status_kehadiran === 'terlambat') {
                return true;
            }
            if ($log->status_kehadiran === 'hadir' && !empty($log->jam_masuk)) {
                return substr((string) $log->jam_masuk, 0, 8) > '08:15:00';
            }
            return false;
        })->count();

        $hariAlpha = $presensiLogs->filter(function ($log) {
            return $log->status_kehadiran === 'alpha';
        })->count();

        // 4. Hitung Beban Mengajar Dosen dari SIAKAD (jika terdaftar sebagai dosen)
        $totalSksDiampu = 0.0;
        $dosen = Dosen::where('pegawai_id', $pegawai->id)->first();
        if ($dosen) {
            $pengampuList = DosenPengampu::with('kelas.mataKuliah')
                ->where('dosen_id', $dosen->id)
                ->get();

            foreach ($pengampuList as $p) {
                $sks = (float) ($p->sks_substansi_total ?? $p->kelas?->mataKuliah?->total_sks ?? 0);
                $totalSksDiampu += $sks;
            }
        }

        // 5. Ambil Jabatan Fungsional Akademik & Tunjangan Dinamis dari Database
        $riwayatJafung = RiwayatJabatan::with('jabatanFungsional')
            ->where('pegawai_id', $pegawai->id)
            ->where('is_active', true)
            ->whereNotNull('jabatan_fungsional_id')
            ->latest('mulai_jabatan')
            ->first();

        $golonganJafung = $riwayatJafung?->jabatanFungsional?->golongan;
        $tunjanganFungsionalNominal = (float) ($riwayatJafung?->jabatanFungsional?->tunjangan_nominal ?? 0.0);

        // 6. Tentukan Gaji Pokok Dinamis berdasarkan Matriks Skala Gaji Pokok & Masa Kerja
        $skalaGaji = null;
        if ($golonganJafung) {
            $skalaGaji = MasterSkalaGajiPokok::where('is_active', true)
                ->where('golongan', $golonganJafung)
                ->where('masa_kerja_min_tahun', '<=', $masaKerjaTahun)
                ->where('masa_kerja_max_tahun', '>=', $masaKerjaTahun)
                ->first();
        }
        if (!$skalaGaji) {
            $skalaGaji = MasterSkalaGajiPokok::where('is_active', true)
                ->where('masa_kerja_min_tahun', '<=', $masaKerjaTahun)
                ->where('masa_kerja_max_tahun', '>=', $masaKerjaTahun)
                ->first();
        }

        $kompGapok = $masterKomponens->firstWhere('kode', 'GAJI_POKOK');
        $fallbackGajiPokok = $kompGapok ? (float) $kompGapok->nilai_default : 4500000.0;
        $defaultGajiPokok = $skalaGaji ? (float) $skalaGaji->nominal_gaji : $fallbackGajiPokok;
        $gajiPokokKeterangan = $skalaGaji
            ? "Masa Kerja: {$masaKerjaTahun} Thn ({$skalaGaji->nama_skala})"
            : "Masa Kerja: {$masaKerjaTahun} Thn (Tarif Standar)";

        // 7. Iterasi Komponen Gaji & Hitung Butir-per-Butir
        $detailItems = [];
        $gajiPokok = $defaultGajiPokok;
        $totalTunjangan = 0.0;
        $totalPotongan = 0.0;
        $totalBiayaTransport = 0.0;
        $totalHonorSks = 0.0;
        $totalTunjFungsional = 0.0;
        $penghasilanBrutoTaxable = 0.0;

        foreach ($masterKomponens as $komp) {
            $nominal = (float) $komp->nilai_default;
            $keterangan = $komp->keterangan;

            // Cek apakah ada override kustom khusus pegawai ini
            $kustom = $customKomponens->get($komp->id);
            if ($kustom && $kustom->nominal_kustom !== null) {
                $nominal = (float) $kustom->nominal_kustom;
                $keterangan .= " (Kustom Pegawai)";
            }

            // Hitung nilai dinamis sesuai tipe_nilai dan kode komponen
            if ($komp->tipe_nilai === 'rumus_kehadiran') {
                $tarifHarian = $nominal;
                if ($komp->kode === 'POT_KETERLAMBATAN') {
                    $nominal = $hariTerlambat * $tarifHarian;
                    $keterangan = "Potongan Terlambat: {$hariTerlambat} Kejadian @ Rp " . number_format($tarifHarian, 0, ',', '.');
                } elseif ($komp->kode === 'POT_ALPHA') {
                    $nominal = $hariAlpha * $tarifHarian;
                    $keterangan = "Potongan Alpha: {$hariAlpha} Hari @ Rp " . number_format($tarifHarian, 0, ',', '.');
                } else {
                    // Default insentif/transport hadir tepat waktu
                    $nominal = $hariTepatWaktu * $tarifHarian;
                    $totalBiayaTransport = $nominal;
                    $keterangan = "Presensi Tepat Waktu: {$hariTepatWaktu} Hari @ Rp " . number_format($tarifHarian, 0, ',', '.');
                }
            } elseif ($komp->tipe_nilai === 'rumus_sks') {
                $tarifPerSks = $nominal;
                $nominal = $totalSksDiampu * $tarifPerSks;
                $totalHonorSks = $nominal;
                $keterangan = "Honor Mengajar: {$totalSksDiampu} SKS @ Rp " . number_format($tarifPerSks, 0, ',', '.');
            } elseif ($komp->kode === 'TUNJ_FUNGSIONAL' && (!$kustom || $kustom->nominal_kustom === null)) {
                $nominal = $tunjanganFungsionalNominal;
                $totalTunjFungsional = $nominal;
                $keterangan = "Tunjangan Jafung: " . ($riwayatJafung?->jabatanFungsional?->nama ?? 'Akademik');
            } elseif ($komp->kode === 'GAJI_POKOK') {
                if (!$kustom || $kustom->nominal_kustom === null) {
                    $nominal = $defaultGajiPokok;
                }
                $gajiPokok = $nominal;
                $keterangan = $gajiPokokKeterangan;
            }

            // Skip PPh21 terlebih dahulu karena membutuhkan total bruto
            if ($komp->tipe_nilai === 'rumus_pph21' || $komp->kode === 'POT_PPH21') {
                continue;
            }

            if ($komp->jenis === 'pendapatan') {
                if ($komp->kode !== 'GAJI_POKOK') {
                    $totalTunjangan += $nominal;
                }
                if ($komp->is_taxable) {
                    $penghasilanBrutoTaxable += $nominal;
                }
            } else {
                $totalPotongan += $nominal;
            }

            $detailItems[] = [
                'komponen_gaji_id' => $komp->id,
                'nama_komponen' => $komp->nama,
                'jenis' => $komp->jenis,
                'nominal' => $nominal,
                'keterangan' => $keterangan,
            ];
        }

        // 8. Hitung PPh 21 Otomatis berbasis Matriks Bracket TER Dinamis dari Database
        $totalPenghasilanBruto = $gajiPokok + $totalTunjangan;

        $bracket = MasterBracketPph21::where('is_active', true)
            ->where('penghasilan_bruto_min', '<=', $totalPenghasilanBruto)
            ->where(function ($q) use ($totalPenghasilanBruto) {
                $q->whereNull('penghasilan_bruto_max')
                  ->orWhere('penghasilan_bruto_max', '>=', $totalPenghasilanBruto);
            })
            ->orderBy('penghasilan_bruto_min', 'desc')
            ->first();

        $tarifPphPersen = $bracket ? (float) $bracket->tarif_persen : 0.0;
        $bracketDesc = $bracket ? $bracket->keterangan : 'Tarif Efektif Rata-Rata';

        $totalPph21 = round($totalPenghasilanBruto * $tarifPphPersen, 2);
        $totalPotongan += $totalPph21;

        // Tambahkan item PPh21 ke detail slip gaji
        $kompPph = $masterKomponens->firstWhere('kode', 'POT_PPH21');
        $detailItems[] = [
            'komponen_gaji_id' => $kompPph?->id,
            'nama_komponen' => $kompPph?->nama ?? 'Potongan Pajak Penghasilan (PPh 21)',
            'jenis' => 'potongan',
            'nominal' => $totalPph21,
            'keterangan' => "Estimasi PPh21 Bulanan (" . ($tarifPphPersen * 100) . "% dari Bruto Rp " . number_format($totalPenghasilanBruto, 0, ',', '.') . " - {$bracketDesc})",
        ];

        // Total BPJS
        $totalBpjs = 0.0;
        foreach ($detailItems as $d) {
            if (str_contains($d['nama_komponen'], 'BPJS')) {
                $totalBpjs += $d['nominal'];
            }
        }

        // 9. Kalkulasi Take Home Pay (Gaji Bersih)
        $gajiBersih = $gajiPokok + $totalTunjangan - $totalPotongan;

        // 10. Simpan atau Perbarui ke simpeg_gaji_pegawai
        $gajiPegawai = GajiPegawai::updateOrCreate(
            [
                'pegawai_id' => $pegawai->id,
                'periode_bulan_tahun' => $periode,
            ],
            [
                'gaji_pokok' => $gajiPokok,
                'tunjangan_tetap' => $totalTunjangan - $totalBiayaTransport - $totalHonorSks,
                'total_biaya_transport' => $totalBiayaTransport,
                'total_honor_sks' => $totalHonorSks,
                'total_sks_diampu' => $totalSksDiampu,
                'total_tunjangan_fungsional' => $totalTunjFungsional,
                'jumlah_hari_hadir_tepat_waktu' => $hariTepatWaktu,
                'total_tunjangan' => $totalTunjangan,
                'total_potongan' => $totalPotongan,
                'total_pph21' => $totalPph21,
                'total_bpjs' => $totalBpjs,
                'gaji_bersih' => $gajiBersih,
                'status_transfer' => 'draft',
                'catatan' => "Honor SKS: {$totalSksDiampu} SKS | Presensi Tepat Waktu: {$hariTepatWaktu} Hari | Masa Kerja: {$masaKerjaTahun} Thn | PPh21: Rp " . number_format($totalPph21, 0, ',', '.'),
            ]
        );

        // 11. Sinkronkan rincian butir ke simpeg_gaji_detail
        GajiDetail::where('gaji_pegawai_id', $gajiPegawai->id)->delete();
        foreach ($detailItems as $item) {
            $item['gaji_pegawai_id'] = $gajiPegawai->id;
            GajiDetail::create($item);
        }

        return $gajiPegawai->fresh(['details', 'pegawai']);
    }

    /**
     * Simpan kustomisasi komponen gaji seorang pegawai dalam database transaction.
     */
    public function savePegawaiKomponen(int $pegawaiId, array $data): void
    {
        $komponenList = isset($data['komponen']) && is_array($data['komponen'])
            ? $data['komponen']
            : $data;

        DB::transaction(function () use ($pegawaiId, $komponenList) {
            foreach ($komponenList as $item) {
                $nominal = $item['nominal_kustom'] ?? $item['nilai_kustom'] ?? null;
                PegawaiKomponenGaji::updateOrCreate(
                    [
                        'pegawai_id' => $pegawaiId,
                        'komponen_gaji_id' => $item['komponen_gaji_id'],
                    ],
                    [
                        'nominal_kustom' => $nominal,
                        'is_active' => $item['is_active'] ?? true,
                        'catatan' => $item['catatan'] ?? null,
                    ]
                );
            }
        });
    }
}
