<?php

namespace App\Services\Simpeg;

use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPengampu;
use App\Models\Simpeg\GajiDetail;
use App\Models\Simpeg\GajiPegawai;
use App\Models\Simpeg\MasterKomponenGaji;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PegawaiKomponenGaji;
use App\Models\Simpeg\PresensiPegawai;
use App\Models\Simpeg\RiwayatJabatan;
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
        // 1. Ambil kustomisasi komponen pegawai
        $customKomponens = PegawaiKomponenGaji::where('pegawai_id', $pegawai->id)
            ->where('is_active', true)
            ->get()
            ->keyBy('komponen_gaji_id');

        // 2. Hitung Presensi Tepat Waktu (status: hadir & jam_masuk <= 08:15)
        $hariTepatWaktu = PresensiPegawai::where('pegawai_id', $pegawai->id)
            ->where('tanggal', 'LIKE', "{$periode}%")
            ->where('status_kehadiran', 'hadir')
            ->whereNotNull('jam_masuk')
            ->get()
            ->filter(function ($log) {
                $jam = substr((string) $log->jam_masuk, 0, 8);
                return $jam <= '08:15:00';
            })
            ->count();

        // 3. Hitung Beban Mengajar Dosen dari SIAKAD (jika dosen)
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

        // 4. Hitung Tunjangan Jabatan Fungsional Akademik
        $tunjanganFungsionalNominal = 0.0;
        $riwayatJafung = RiwayatJabatan::with('jabatanFungsional')
            ->where('pegawai_id', $pegawai->id)
            ->where('is_active', true)
            ->whereNotNull('jabatan_fungsional_id')
            ->latest('mulai_jabatan')
            ->first();

        if ($riwayatJafung && $riwayatJafung->jabatanFungsional) {
            $jafungNama = strtolower($riwayatJafung->jabatanFungsional->nama);
            if (str_contains($jafungNama, 'guru besar') || str_contains($jafungNama, 'profesor')) {
                $tunjanganFungsionalNominal = 2500000;
            } elseif (str_contains($jafungNama, 'lektor kepala')) {
                $tunjanganFungsionalNominal = 1750000;
            } elseif (str_contains($jafungNama, 'lektor')) {
                $tunjanganFungsionalNominal = 1250000;
            } elseif (str_contains($jafungNama, 'asisten ahli')) {
                $tunjanganFungsionalNominal = 750000;
            } else {
                $tunjanganFungsionalNominal = 500000;
            }
        }

        // 5. Iterasi Komponen Gaji & Hitung Butir-per-Butir
        $detailItems = [];
        $gajiPokok = 0.0;
        $totalTunjangan = 0.0;
        $totalPotongan = 0.0;
        $totalBiayaTransport = 0.0;
        $totalHonorSks = 0.0;
        $totalTunjFungsional = 0.0;
        $penghasilanBrutoTaxable = 0.0;

        foreach ($masterKomponens as $komp) {
            $nominal = (float) $komp->nilai_default;
            $keterangan = $komp->keterangan;

            // Cek apakah ada override kustom untuk pegawai ini
            $kustom = $customKomponens->get($komp->id);
            if ($kustom && $kustom->nominal_kustom !== null) {
                $nominal = (float) $kustom->nominal_kustom;
            }

            // Hitung nilai dinamis sesuai tipe_nilai
            if ($komp->tipe_nilai === 'rumus_kehadiran') {
                $tarifHarian = $nominal;
                $nominal = $hariTepatWaktu * $tarifHarian;
                $totalBiayaTransport = $nominal;
                $keterangan = "Presensi Tepat Waktu: {$hariTepatWaktu} Hari @ Rp " . number_format($tarifHarian, 0, ',', '.');
            } elseif ($komp->tipe_nilai === 'rumus_sks') {
                $tarifPerSks = $nominal;
                $nominal = $totalSksDiampu * $tarifPerSks;
                $totalHonorSks = $nominal;
                $keterangan = "Honor Mengajar: {$totalSksDiampu} SKS @ Rp " . number_format($tarifPerSks, 0, ',', '.');
            } elseif ($komp->kode === 'TUNJ_FUNGSIONAL' && $tunjanganFungsionalNominal > 0 && (!$kustom || $kustom->nominal_kustom === null)) {
                $nominal = $tunjanganFungsionalNominal;
                $totalTunjFungsional = $nominal;
                $keterangan = "Tunjangan Jafung: " . ($riwayatJafung->jabatanFungsional->nama ?? 'Akademik');
            } elseif ($komp->kode === 'GAJI_POKOK') {
                $gajiPokok = $nominal;
            }

            // Skip PPh21 dulu karena butuh total bruto
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

        // 6. Hitung PPh 21 Otomatis (Estimasi Tarif Efektif Rata-Rata / TER Bulanan)
        $totalPenghasilanBruto = $gajiPokok + $totalTunjangan;
        $tarifPphPersen = 0.0;
        if ($totalPenghasilanBruto > 15000000) {
            $tarifPphPersen = 0.05; // 5%
        } elseif ($totalPenghasilanBruto > 7000000) {
            $tarifPphPersen = 0.015; // 1.5%
        } elseif ($totalPenghasilanBruto > 5400000) {
            $tarifPphPersen = 0.005; // 0.5%
        } else {
            $tarifPphPersen = 0.0; // PTKP
        }

        $totalPph21 = round($totalPenghasilanBruto * $tarifPphPersen, 2);
        $totalPotongan += $totalPph21;

        // Tambahkan item PPh21 ke detail
        $kompPph = $masterKomponens->firstWhere('kode', 'POT_PPH21');
        $detailItems[] = [
            'komponen_gaji_id' => $kompPph?->id,
            'nama_komponen' => $kompPph?->nama ?? 'Potongan Pajak Penghasilan (PPh 21)',
            'jenis' => 'potongan',
            'nominal' => $totalPph21,
            'keterangan' => "Estimasi PPh21 Bulanan (" . ($tarifPphPersen * 100) . "% dari Bruto Rp " . number_format($totalPenghasilanBruto, 0, ',', '.') . ")",
        ];

        // Total BPJS
        $totalBpjs = 0.0;
        foreach ($detailItems as $d) {
            if (str_contains($d['nama_komponen'], 'BPJS')) {
                $totalBpjs += $d['nominal'];
            }
        }

        // 7. Kalkulasi Take Home Pay (Gaji Bersih)
        $gajiBersih = $gajiPokok + $totalTunjangan - $totalPotongan;

        // 8. Simpan atau Perbarui ke simpeg_gaji_pegawai
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
                'catatan' => "Honor SKS: {$totalSksDiampu} SKS | Presensi: {$hariTepatWaktu} Hari | PPh21: Rp " . number_format($totalPph21, 0, ',', '.'),
            ]
        );

        // 9. Sinkronkan rincian butir ke simpeg_gaji_detail
        GajiDetail::where('gaji_pegawai_id', $gajiPegawai->id)->delete();
        foreach ($detailItems as $item) {
            $item['gaji_pegawai_id'] = $gajiPegawai->id;
            GajiDetail::create($item);
        }

        return $gajiPegawai->fresh(['details', 'pegawai']);
    }
}
