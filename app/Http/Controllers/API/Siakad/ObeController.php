<?php

namespace App\Http\Controllers\API\Siakad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Siakad\Cpl;
use App\Models\Siakad\Cpmk;
use App\Models\Siakad\SubCpmk;
use App\Models\Siakad\ProfilLulusan;
use App\Models\Siakad\BahanKajian;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\KomponenPenilaian;
use App\Models\Siakad\NilaiKomponenMahasiswa;
use App\Models\Siakad\KetercapaianCpmkMahasiswa;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\NilaiMahasiswa;
use App\Models\Siakad\SkalaNilai;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MataKuliah;
use App\Services\Siakad\SiakadAkademikService;
use App\Http\Requests\Siakad\StoreCplRequest;
use App\Http\Requests\Siakad\StoreKelasKomponenRequest;
use Illuminate\Support\Facades\DB;

class ObeController extends Controller
{
    protected SiakadAkademikService $akademikService;

    public function __construct(SiakadAkademikService $akademikService)
    {
        $this->akademikService = $akademikService;
    }

    // --- CPL (Capaian Pembelajaran Lulusan) ---
    public function getCpl(Request $request)
    {
        $query = Cpl::with('programStudi');
        if ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->program_studi_id);
        }
        return response()->json([
            'status' => 'success',
            'data' => $query->get()
        ]);
    }

    public function storeCpl(StoreCplRequest $request)
    {
        $validated = $request->validated();

        $cpl = Cpl::updateOrCreate(
            ['program_studi_id' => $validated['program_studi_id'], 'kode_cpl' => $validated['kode_cpl']],
            ['kategori' => $validated['kategori'], 'deskripsi' => $validated['deskripsi'], 'is_active' => true]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'CPL berhasil disimpan',
            'data' => $cpl
        ]);
    }

    // --- CPMK (Capaian Pembelajaran Mata Kuliah) ---
    public function getCpmk(Request $request)
    {
        $query = Cpmk::with(['cpl', 'subCpmks']);
        if ($request->filled('mata_kuliah_id')) {
            $query->where('mata_kuliah_id', $request->mata_kuliah_id);
        }
        return response()->json([
            'status' => 'success',
            'data' => $query->get()
        ]);
    }

    public function storeCpmk(Request $request)
    {
        $request->validate([
            'mata_kuliah_id' => 'required|exists:siakad_mata_kuliah,id',
            'kode_cpmk' => 'required|string|max:50',
            'deskripsi' => 'required|string',
            'bobot_persentase' => 'nullable|numeric|min:0|max:100',
            'cpl_id' => 'nullable|exists:siakad_cpl,id',
        ]);

        $cpmk = Cpmk::updateOrCreate(
            ['mata_kuliah_id' => $request->mata_kuliah_id, 'kode_cpmk' => $request->kode_cpmk],
            [
                'cpl_id' => $request->cpl_id,
                'deskripsi' => $request->deskripsi,
                'bobot_persentase' => $request->bobot_persentase ?? 0
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'CPMK berhasil disimpan',
            'data' => $cpmk
        ]);
    }

    // --- Komponen Penilaian Kelas OBE ---
    public function getKelasKomponen(Request $request, $kelasId)
    {
        $kelas = Kelas::with(['mataKuliah.cpmks.cpl'])->findOrFail($kelasId);
        $komponen = KomponenPenilaian::with(['cpmk', 'subCpmk'])
            ->where('kelas_id', $kelasId)
            ->orderBy('urutan')
            ->get();

        // Auto-sync jika komponen masih kosong dan mata kuliah sudah memiliki CPMK
        if ($komponen->isEmpty() && $kelas->mataKuliah && $kelas->mataKuliah->cpmks->isNotEmpty()) {
            $urutan = 1;
            foreach ($kelas->mataKuliah->cpmks as $cpmk) {
                KomponenPenilaian::create([
                    'kelas_id' => $kelasId,
                    'cpmk_id' => $cpmk->id,
                    'nama_komponen' => 'Asesmen ' . $cpmk->kode_cpmk,
                    'teknik_penilaian' => 'tugas',
                    'bobot' => $cpmk->bobot_persentase > 0 ? $cpmk->bobot_persentase : 25,
                    'urutan' => $urutan++,
                    'is_aktif' => true,
                ]);
            }
            $komponen = KomponenPenilaian::with(['cpmk', 'subCpmk'])
                ->where('kelas_id', $kelasId)
                ->orderBy('urutan')
                ->get();
        }

        $totalBobot = $komponen->sum('bobot');

        return response()->json([
            'status' => 'success',
            'data' => [
                'kelas' => $kelas,
                'cpmk_options' => $kelas->mataKuliah->cpmks,
                'komponen' => $komponen,
                'total_bobot' => $totalBobot,
                'is_valid_100' => round($totalBobot, 2) === 100.00
            ]
        ]);
    }

    public function syncKelasKomponenFromObe(Request $request, $kelasId)
    {
        $kelas = Kelas::with(['mataKuliah.cpmks'])->findOrFail($kelasId);
        
        if (!$kelas->mataKuliah || $kelas->mataKuliah->cpmks->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mata kuliah belum memiliki data CPMK master OBE. Silakan konfigurasikan CPMK terlebih dahulu di menu OBE.'
            ], 422);
        }

        // Jangan hapus komponen yang sudah memiliki nilai mahasiswa
        $komponenIds = KomponenPenilaian::where('kelas_id', $kelasId)->pluck('id');
        $sudahDinilai = NilaiKomponenMahasiswa::whereIn('komponen_penilaian_id', $komponenIds)->exists();
        if ($sudahDinilai) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sinkronisasi dibatalkan: komponen kelas ini sudah memiliki nilai mahasiswa. Hapus/reset nilai terlebih dahulu atau kelola komponen secara manual agar nilai tidak hilang.'
            ], 422);
        }

        // Hapus komponen eksisting yang belum ada nilai
        KomponenPenilaian::where('kelas_id', $kelasId)->delete();

        $urutan = 1;
        foreach ($kelas->mataKuliah->cpmks as $cpmk) {
            KomponenPenilaian::create([
                'kelas_id' => $kelasId,
                'cpmk_id' => $cpmk->id,
                'nama_komponen' => 'Asesmen ' . $cpmk->kode_cpmk,
                'teknik_penilaian' => 'tugas',
                'bobot' => $cpmk->bobot_persentase > 0 ? $cpmk->bobot_persentase : round(100 / count($kelas->mataKuliah->cpmks), 2),
                'urutan' => $urutan++,
                'is_aktif' => true,
            ]);
        }

        $komponen = KomponenPenilaian::with(['cpmk', 'subCpmk'])
            ->where('kelas_id', $kelasId)
            ->orderBy('urutan')
            ->get();

        $totalBobot = $komponen->sum('bobot');

        return response()->json([
            'status' => 'success',
            'message' => 'Komponen asesmen kelas berhasil disinkronkan dari Master CPMK OBE mata kuliah.',
            'data' => [
                'komponen' => $komponen,
                'total_bobot' => $totalBobot,
                'is_valid_100' => round($totalBobot, 2) === 100.00
            ]
        ]);
    }

    public function storeKelasKomponen(StoreKelasKomponenRequest $request, $kelasId)
    {
        $kelas = Kelas::with(['mataKuliah', 'tahunAkademik'])->findOrFail($kelasId);
        $mode = $kelas->tahunAkademik?->mode_penilaian ?? 'semi_obe';

        if ($mode === 'full_obe') {
            if ($request->filled('id')) {
                $cpmk = Cpmk::findOrFail($request->id);
                $cpmk->update([
                    'kode_cpmk' => $request->nama_komponen,
                    'bobot_persentase' => $request->bobot,
                ]);
            } else {
                $count = Cpmk::where('mata_kuliah_id', $kelas->mata_kuliah_id)->count();
                $cpmk = Cpmk::create([
                    'mata_kuliah_id' => $kelas->mata_kuliah_id,
                    'kode_cpmk' => $request->nama_komponen ?: ('CPMK-' . ($count + 1)),
                    'deskripsi' => $request->nama_komponen,
                    'bobot_persentase' => $request->bobot,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'CPMK penilaian OBE berhasil disimpan',
                'data' => $cpmk
            ]);
        }

        if ($request->filled('id')) {
            $comp = KomponenPenilaian::findOrFail($request->id);
            $comp->update($request->only(['nama_komponen', 'bobot', 'teknik_penilaian', 'cpmk_id', 'sub_cpmk_id']));
        } else {
            $maxUrutan = KomponenPenilaian::where('kelas_id', $kelasId)->max('urutan') ?? 0;
            $comp = KomponenPenilaian::create([
                'kelas_id' => $kelasId,
                'cpmk_id' => $request->cpmk_id,
                'sub_cpmk_id' => $request->sub_cpmk_id,
                'nama_komponen' => $request->nama_komponen,
                'teknik_penilaian' => $request->teknik_penilaian,
                'bobot' => $request->bobot,
                'urutan' => $maxUrutan + 1,
                'is_aktif' => true,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Komponen penilaian berhasil disimpan',
            'data' => $comp
        ]);
    }

    public function deleteKelasKomponen($id)
    {
        $comp = KomponenPenilaian::find($id);
        if ($comp) {
            $comp->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Komponen penilaian berhasil dihapus'
            ]);
        }

        // If not in KomponenPenilaian, check if it's a CPMK (Pure OBE mode)
        $cpmk = Cpmk::find($id);
        if ($cpmk) {
            $cpmk->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'CPMK penilaian berhasil dihapus'
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Komponen/CPMK penilaian tidak ditemukan'
        ], 404);
    }

    // --- Matriks Penilaian OBE Kelas & Rekap Capaian ---
    public function getKelasNilaiObe(Request $request, $kelasId)
    {
        $kelas = Kelas::with(['mataKuliah.cpmks.cpl', 'programStudi', 'programStudis', 'dosenPengampu.dosen', 'tahunAkademik'])->findOrFail($kelasId);
        $mode = $kelas->tahunAkademik?->mode_penilaian ?? 'semi_obe';
        
        // 1. Define the components list based on the active mode
        if ($mode === 'full_obe') {
            // In Full OBE, components are the CPMKs themselves
            $komponenList = $kelas->mataKuliah->cpmks->map(function ($cpmk) {
                return (object) [
                    'id' => $cpmk->id,
                    'nama_komponen' => $cpmk->kode_cpmk,
                    'bobot' => $cpmk->bobot_persentase,
                    'cpmk_id' => $cpmk->id,
                ];
            });
        } else {
            // For Semi-OBE and Conventional, use traditional components
            $komponenList = KomponenPenilaian::with('cpmk')->where('kelas_id', $kelasId)->orderBy('urutan')->get();
            // Auto sync if empty
            if ($komponenList->isEmpty() && $kelas->mataKuliah && $kelas->mataKuliah->cpmks->isNotEmpty()) {
                $urutan = 1;
                foreach ($kelas->mataKuliah->cpmks as $cpmk) {
                    KomponenPenilaian::create([
                        'kelas_id' => $kelasId,
                        'cpmk_id' => $cpmk->id,
                        'nama_komponen' => 'Asesmen ' . $cpmk->kode_cpmk,
                        'teknik_penilaian' => 'tugas',
                        'bobot' => $cpmk->bobot_persentase > 0 ? $cpmk->bobot_persentase : 25,
                        'urutan' => $urutan++,
                        'is_aktif' => true,
                    ]);
                }
                $komponenList = KomponenPenilaian::with('cpmk')->where('kelas_id', $kelasId)->orderBy('urutan')->get();
            }
        }

        // Ambil semua mahasiswa yang terdaftar di kelas ini via KRS
        $krsDetails = KrsDetail::with([
            'krs.mahasiswa.programStudi',
            'nilai',
            'nilaiKomponens.komponenPenilaian',
            'ketercapaianCpmks.cpmk'
        ])
        ->where('kelas_id', $kelasId)
        ->where('status', 'aktif')
        ->get();

        $peserta = $krsDetails->map(function ($kd) use ($kelas, $komponenList, $mode) {
            $mhs = $kd->krs?->mahasiswa;
            $totalAkhir = 0;
            $scores = [];
            $cpmkAttainment = [];

            if ($mode === 'full_obe') {
                $cpmkRecords = $kd->ketercapaianCpmks->keyBy('cpmk_id');
                foreach ($kelas->mataKuliah->cpmks as $cpmk) {
                    $rec = $cpmkRecords->get($cpmk->id);
                    $skor = $rec ? (float)$rec->skor_ketercapaian : 0.0;
                    $bobot = (float)$cpmk->bobot_persentase;
                    $kontribusi = ($skor * $bobot) / 100;
                    $totalAkhir += $kontribusi;

                    $scores[$cpmk->id] = [
                        'komponen_id' => $cpmk->id,
                        'nama_komponen' => $cpmk->kode_cpmk,
                        'bobot' => $bobot,
                        'nilai_angka' => $skor,
                        'kontribusi' => round($kontribusi, 2),
                        'cpmk_id' => $cpmk->id,
                    ];
                }
            } else {
                // semi_obe or konvensional
                $nilaiRecords = $kd->nilaiKomponens->keyBy('komponen_penilaian_id');
                foreach ($komponenList as $comp) {
                    $rec = $nilaiRecords->get($comp->id);
                    $skor = $rec ? (float)$rec->nilai_angka : 0.0;
                    $bobot = (float)$comp->bobot;
                    $kontribusi = ($skor * $bobot) / 100;
                    $totalAkhir += $kontribusi;

                    $scores[$comp->id] = [
                        'komponen_id' => $comp->id,
                        'nama_komponen' => $comp->nama_komponen,
                        'bobot' => $bobot,
                        'nilai_angka' => $skor,
                        'kontribusi' => round($kontribusi, 2),
                        'cpmk_id' => $comp->cpmk_id,
                    ];
                }
            }

            // 2. Map CPMK attainment depending on the mode
            if ($mode === 'full_obe') {
                foreach ($kelas->mataKuliah->cpmks as $cpmk) {
                    $skor = isset($scores[$cpmk->id]) ? $scores[$cpmk->id]['nilai_angka'] : 0.0;
                    $cpmkAttainment[$cpmk->id] = [
                        'cpmk_id' => $cpmk->id,
                        'kode_cpmk' => $cpmk->kode_cpmk,
                        'deskripsi' => $cpmk->deskripsi,
                        'skor' => $skor,
                        'is_tercapai' => $skor >= 65.0,
                    ];
                }
            } elseif ($mode === 'semi_obe') {
                foreach ($kelas->mataKuliah->cpmks as $cpmk) {
                    $relatedComps = $komponenList->where('cpmk_id', $cpmk->id);
                    $totalCompWeight = $relatedComps->sum('bobot');
                    $cpmkScore = 0;
                    if ($totalCompWeight > 0) {
                        $weightedSum = 0;
                        foreach ($relatedComps as $rc) {
                            $s = $scores[$rc->id]['nilai_angka'] ?? 0;
                            $weightedSum += ($s * (float)$rc->bobot);
                        }
                        $cpmkScore = round($weightedSum / $totalCompWeight, 2);
                    } else {
                        $cpmkScore = round($totalAkhir, 2);
                    }

                    $cpmkAttainment[$cpmk->id] = [
                        'cpmk_id' => $cpmk->id,
                        'kode_cpmk' => $cpmk->kode_cpmk,
                        'deskripsi' => $cpmk->deskripsi,
                        'skor' => $cpmkScore,
                        'is_tercapai' => $cpmkScore >= 65.0,
                    ];
                }
            } else {
                // konvensional: CPMK attainment is empty/not measured
                foreach ($kelas->mataKuliah->cpmks as $cpmk) {
                    $cpmkAttainment[$cpmk->id] = [
                        'cpmk_id' => $cpmk->id,
                        'kode_cpmk' => $cpmk->kode_cpmk,
                        'deskripsi' => $cpmk->deskripsi,
                        'skor' => 0.0,
                        'is_tercapai' => true,
                    ];
                }
            }

            // Nilai Huruf & Mutu — sumber tunggal: master skala nilai (fallback baku bila kosong)
            [$huruf, $mutu] = $this->konversiHurufMutu((float) $totalAkhir, $kelas->mataKuliah?->kurikulum?->program_studi_id);

            return [
                'krs_detail_id' => $kd->id,
                'mahasiswa' => $mhs,
                'scores' => $scores,
                'cpmk_attainment' => $cpmkAttainment,
                'nilai_akhir' => round($totalAkhir, 2),
                'nilai_huruf' => $huruf,
                'bobot_mutu' => $mutu,
                'is_final' => (bool) ($kd->nilai?->is_final ?? false),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'kelas' => $kelas,
                'komponen' => $komponenList,
                'cpmks' => $mode === 'konvensional' ? [] : $kelas->mataKuliah->cpmks,
                'peserta' => $peserta,
                'mode_penilaian' => $mode,
                'total_bobot_komponen' => round((float) $komponenList->sum('bobot'), 2),
                'total_bobot_cpmk' => round((float) $kelas->mataKuliah->cpmks->sum('bobot_persentase'), 2),
                'is_valid_100' => $mode === 'full_obe'
                    ? abs((float) $kelas->mataKuliah->cpmks->sum('bobot_persentase') - 100.0) < 0.01
                    : abs((float) $komponenList->sum('bobot') - 100.0) < 0.01,
                'kelayakan' => $this->kelayakanInputNilai($kelas, $mode, $komponenList),
                'skala_nilai' => SkalaNilai::where('is_active', true)->orderBy('bobot_indeks', 'desc')->get(),
            ]
        ]);
    }

    /**
     * Info kelayakan input nilai kelas: RPS terisi + bobot 100%.
     *
     * @return array{boleh:bool, pesan:string|null, rps:array, bobot:array}
     */
    private function kelayakanInputNilai(Kelas $kelas, string $mode, $komponenList): array
    {
        $mkId = $kelas->mata_kuliah_id;
        $rps = \App\Models\Siakad\Rps::where('mata_kuliah_id', $mkId)
            ->withCount('mingguan')
            ->orderByDesc('id')
            ->first();

        $rpsInfo = [
            'ada' => (bool) $rps,
            'jumlah_pertemuan' => $rps ? (int) $rps->mingguan_count : 0,
            'status' => $rps?->status,
            'terisi' => (bool) $rps && (int) $rps->mingguan_count > 0,
        ];

        if (!$rpsInfo['terisi']) {
            return [
                'boleh' => false,
                'pesan' => 'Pengisian nilai dikunci: RPS mata kuliah ini belum diisi (minimal 1 dari 16 rencana pertemuan mingguan). Lengkapi RPS terlebih dahulu di menu Perkuliahan → Kelola RPS.',
                'rps' => $rpsInfo,
                'bobot' => null,
            ];
        }

        if ($mode === 'full_obe') {
            $total = round((float) $kelas->mataKuliah->cpmks->sum('bobot_persentase'), 2);
            $ok = abs($total - 100.0) < 0.01;
            return [
                'boleh' => $ok,
                'pesan' => $ok ? null : "Pengisian nilai dikunci: total bobot CPMK mata kuliah ini belum genap 100% (saat ini: {$total}%). Lengkapi pemetaan CPMK di menu OBE.",
                'rps' => $rpsInfo,
                'bobot' => ['tipe' => 'cpmk', 'total' => $total, 'valid_100' => $ok],
            ];
        }

        $total = round((float) $komponenList->sum('bobot'), 2);
        $ok = abs($total - 100.0) < 0.01;
        return [
            'boleh' => $ok,
            'pesan' => $ok ? null : "Pengisian nilai dikunci: total bobot komponen asesmen kelas ini belum genap 100% (saat ini: {$total}%). Sesuaikan komponen penilaian kelas terlebih dahulu.",
            'rps' => $rpsInfo,
            'bobot' => ['tipe' => 'komponen', 'total' => $total, 'valid_100' => $ok],
        ];
    }

    /**
     * Konversi nilai angka ke huruf & mutu via master skala nilai.
     * Fallback ke rentang baku bila master belum dikonfigurasi.
     *
     * @return array{0:string,1:float}
     */
    private function konversiHurufMutu(float $nilaiAkhir, ?int $prodiId = null): array
    {
        $skala = SkalaNilai::konversiNilai($nilaiAkhir, $prodiId);
        if ($skala) {
            return [$skala->nilai_huruf, (float) $skala->bobot_indeks];
        }

        $huruf = 'E';
        $mutu = 0.00;
        if ($nilaiAkhir >= 85) { $huruf = 'A'; $mutu = 4.00; }
        elseif ($nilaiAkhir >= 80) { $huruf = 'A-'; $mutu = 3.75; }
        elseif ($nilaiAkhir >= 75) { $huruf = 'B+'; $mutu = 3.25; }
        elseif ($nilaiAkhir >= 70) { $huruf = 'B'; $mutu = 3.00; }
        elseif ($nilaiAkhir >= 65) { $huruf = 'B-'; $mutu = 2.75; }
        elseif ($nilaiAkhir >= 60) { $huruf = 'C+'; $mutu = 2.25; }
        elseif ($nilaiAkhir >= 55) { $huruf = 'C'; $mutu = 2.00; }
        elseif ($nilaiAkhir >= 40) { $huruf = 'D'; $mutu = 1.00; }

        return [$huruf, $mutu];
    }

    public function saveBulkNilaiObe(Request $request, $kelasId)
    {
        $request->validate([
            'is_final' => 'nullable|boolean',
            'grades' => 'required|array',
            'grades.*.krs_detail_id' => 'required|exists:siakad_krs_detail,id',
            'grades.*.scores' => 'required|array',
        ]);

        $kelas = Kelas::with(['mataKuliah.cpmks', 'tahunAkademik'])->findOrFail($kelasId);
        $mode = $kelas->tahunAkademik?->mode_penilaian ?? 'semi_obe';
        $komponenList = KomponenPenilaian::where('kelas_id', $kelasId)->get();
        $isFinalInput = $request->boolean('is_final', false);

        // Kunci prasyarat: RPS terisi + bobot 100%
        $kelayakan = $this->kelayakanInputNilai($kelas, $mode, $komponenList);
        if (!$kelayakan['boleh']) {
            return response()->json([
                'status' => 'error',
                'message' => $kelayakan['pesan'],
                'data' => ['kelayakan' => $kelayakan],
            ], 422);
        }

        // Validasi Jadwal Periode Pengisian Nilai (Kecuali jika Admin)
        $user = $request->user();
        if ($user && !$user->isAdmin()) {
            $ta = $kelas->tahunAkademik;
            if ($ta && $ta->input_nilai_selesai && now()->greaterThan(\Carbon\Carbon::parse($ta->input_nilai_selesai))) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Batas waktu pengisian nilai untuk periode akademik ini telah ditutup (' . \Carbon\Carbon::parse($ta->input_nilai_selesai)->translatedFormat('d F Y') . '). Hubungi Bagian BAAK untuk dispensasi pengisian nilai.'
                ], 403);
            }
        }

        DB::transaction(function () use ($request, $kelas, $komponenList, $isFinalInput, $mode) {
            foreach ($request->grades as $g) {
                $kd = KrsDetail::with(['krs', 'kelas.mataKuliah.cpmks'])->findOrFail($g['krs_detail_id']);
                $scoresInput = $g['scores'];
                $totalAkhir = 0;

                if ($mode === 'full_obe') {
                    foreach ($kelas->mataKuliah->cpmks as $cpmk) {
                        $val = isset($scoresInput[$cpmk->id]) ? (float)$scoresInput[$cpmk->id] : 0.0;
                        $val = min(100, max(0, $val));

                        KetercapaianCpmkMahasiswa::updateOrCreate(
                            [
                                'krs_detail_id' => $kd->id,
                                'cpmk_id' => $cpmk->id,
                            ],
                            [
                                'skor_ketercapaian' => $val,
                                'status_ketercapaian' => $val >= 65.0 ? 'tercapai' : 'belum_tercapai',
                            ]
                        );

                        $totalAkhir += ($val * (float)$cpmk->bobot_persentase) / 100;
                    }
                } else {
                    // semi_obe or konvensional
                    foreach ($komponenList as $comp) {
                        $val = isset($scoresInput[$comp->id]) ? (float)$scoresInput[$comp->id] : 0.0;
                        $val = min(100, max(0, $val));

                        NilaiKomponenMahasiswa::updateOrCreate(
                            [
                                'krs_detail_id' => $kd->id,
                                'komponen_penilaian_id' => $comp->id,
                            ],
                            [
                                'nilai_angka' => $val,
                                'diinput_oleh' => $request->user()?->id,
                            ]
                        );

                        $totalAkhir += ($val * (float)$comp->bobot) / 100;
                    }

                    // For Semi-OBE, calculate and sync CPMK attainment based on components
                    if ($mode === 'semi_obe') {
                        foreach ($kd->kelas->mataKuliah->cpmks as $cpmk) {
                            $relatedComps = $komponenList->where('cpmk_id', $cpmk->id);
                            $totalW = $relatedComps->sum('bobot');
                            $cpmkScore = 0;
                            if ($totalW > 0) {
                                $wSum = 0;
                                foreach ($relatedComps as $rc) {
                                    $s = isset($scoresInput[$rc->id]) ? (float)$scoresInput[$rc->id] : 0.0;
                                    $wSum += ($s * (float)$rc->bobot);
                                }
                                $cpmkScore = round($wSum / $totalW, 2);
                            } else {
                                $cpmkScore = round($totalAkhir, 2);
                            }

                            KetercapaianCpmkMahasiswa::updateOrCreate(
                                [
                                    'krs_detail_id' => $kd->id,
                                    'cpmk_id' => $cpmk->id,
                                ],
                                [
                                    'skor_ketercapaian' => $cpmkScore,
                                    'status_ketercapaian' => $cpmkScore >= 65.0 ? 'tercapai' : 'belum_tercapai',
                                ]
                            );
                        }
                    }
                }

                // Sync ke siakad_nilai_mahasiswa — skala dari master
                [$hurufBulk, $mutuBulk] = $this->konversiHurufMutu((float) $totalAkhir, $kelas->mataKuliah?->kurikulum?->program_studi_id);

                NilaiMahasiswa::updateOrCreate(
                    ['krs_detail_id' => $kd->id],
                    [
                        'nilai_akhir' => round($totalAkhir, 2),
                        'nilai_huruf' => $hurufBulk,
                        'bobot_mutu' => $mutuBulk,
                        'is_final' => $isFinalInput,
                        'diinput_oleh' => $request->user()?->id,
                    ]
                );

                if ($isFinalInput && $kd->krs) {
                    $this->akademikService->hitungKhsDanIpk(
                        $kd->krs->mahasiswa_id,
                        $kd->krs->tahun_akademik_id
                    );
                }
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => $isFinalInput 
                ? 'Seluruh nilai mahasiswa berhasil disimpan dan dipublikasikan (Final).'
                : 'Nilai mahasiswa berhasil disimpan sebagai Draft.',
        ]);
    }

    public function saveKelasNilaiObe(Request $request, $kelasId)
    {
        $request->validate([
            'krs_detail_id' => 'required|exists:siakad_krs_detail,id',
            'scores' => 'required|array',
            'is_final' => 'nullable|boolean',
        ]);

        $kd = KrsDetail::with(['krs.mahasiswa', 'kelas.mataKuliah.cpmks', 'kelas.tahunAkademik'])->findOrFail($request->krs_detail_id);
        $mode = $kd->kelas->tahunAkademik?->mode_penilaian ?? 'semi_obe';
        $komponenList = KomponenPenilaian::where('kelas_id', $kelasId)->get();
        $isFinalInput = $request->boolean('is_final', false);

        // Kunci prasyarat: RPS terisi + bobot 100%
        $kelayakan = $this->kelayakanInputNilai($kd->kelas, $mode, $komponenList);
        if (!$kelayakan['boleh']) {
            return response()->json([
                'status' => 'error',
                'message' => $kelayakan['pesan'],
                'data' => ['kelayakan' => $kelayakan],
            ], 422);
        }

        // Validasi Jadwal Periode Pengisian Nilai (Kecuali jika Admin)
        $user = $request->user();
        if ($user && !$user->isAdmin()) {
            $ta = $kd->kelas->tahunAkademik;
            if ($ta && $ta->input_nilai_selesai && now()->greaterThan(\Carbon\Carbon::parse($ta->input_nilai_selesai))) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Batas waktu pengisian nilai untuk periode akademik ini telah ditutup (' . \Carbon\Carbon::parse($ta->input_nilai_selesai)->translatedFormat('d F Y') . '). Hubungi Bagian BAAK untuk dispensasi pengisian nilai.'
                ], 403);
            }
        }

        $totalAkhir = 0;
        $scoresInput = $request->scores;

        DB::transaction(function () use ($kd, $scoresInput, $request, $isFinalInput, $komponenList, $mode, &$totalAkhir) {
            if ($mode === 'full_obe') {
                foreach ($kd->kelas->mataKuliah->cpmks as $cpmk) {
                    $val = isset($scoresInput[$cpmk->id]) ? (float)$scoresInput[$cpmk->id] : 0.0;
                    $val = min(100, max(0, $val));

                    KetercapaianCpmkMahasiswa::updateOrCreate(
                        [
                            'krs_detail_id' => $kd->id,
                            'cpmk_id' => $cpmk->id,
                        ],
                        [
                            'skor_ketercapaian' => $val,
                            'status_ketercapaian' => $val >= 65.0 ? 'tercapai' : 'belum_tercapai',
                        ]
                    );

                    $totalAkhir += ($val * (float)$cpmk->bobot_persentase) / 100;
                }
            } else {
                // semi_obe or konvensional
                foreach ($komponenList as $comp) {
                    $val = isset($scoresInput[$comp->id]) ? (float)$scoresInput[$comp->id] : 0.0;
                    $val = min(100, max(0, $val));

                    NilaiKomponenMahasiswa::updateOrCreate(
                        [
                            'krs_detail_id' => $kd->id,
                            'komponen_penilaian_id' => $comp->id,
                        ],
                        [
                            'nilai_angka' => $val,
                            'diinput_oleh' => $request->user()?->id,
                        ]
                    );

                    $totalAkhir += ($val * (float)$comp->bobot) / 100;
                }

                if ($mode === 'semi_obe') {
                    foreach ($kd->kelas->mataKuliah->cpmks as $cpmk) {
                        $relatedComps = $komponenList->where('cpmk_id', $cpmk->id);
                        $totalW = $relatedComps->sum('bobot');
                        $cpmkScore = 0;
                        if ($totalW > 0) {
                            $wSum = 0;
                            foreach ($relatedComps as $rc) {
                                $s = isset($scoresInput[$rc->id]) ? (float)$scoresInput[$rc->id] : 0.0;
                                $wSum += ($s * (float)$rc->bobot);
                            }
                            $cpmkScore = round($wSum / $totalW, 2);
                        } else {
                            $cpmkScore = round($totalAkhir, 2);
                        }

                        KetercapaianCpmkMahasiswa::updateOrCreate(
                            [
                                'krs_detail_id' => $kd->id,
                                'cpmk_id' => $cpmk->id,
                            ],
                            [
                                'skor_ketercapaian' => $cpmkScore,
                                'status_ketercapaian' => $cpmkScore >= 65.0 ? 'tercapai' : 'belum_tercapai',
                            ]
                        );
                    }
                }
            }

            // Sync ke siakad_nilai_mahasiswa — skala dari master
            [$hurufSingle, $mutuSingle] = $this->konversiHurufMutu((float) $totalAkhir, $kd->kelas?->mataKuliah?->kurikulum?->program_studi_id);

            NilaiMahasiswa::updateOrCreate(
                ['krs_detail_id' => $kd->id],
                [
                    'nilai_akhir' => round($totalAkhir, 2),
                    'nilai_huruf' => $hurufSingle,
                    'bobot_mutu' => $mutuSingle,
                    'is_final' => $isFinalInput,
                    'diinput_oleh' => $request->user()?->id,
                ]
            );

            if ($isFinalInput && $kd->krs) {
                $this->akademikService->hitungKhsDanIpk(
                    $kd->krs->mahasiswa_id,
                    $kd->krs->tahun_akademik_id
                );
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Nilai OBE dan Ketercapaian CPMK berhasil diperbarui.',
            'data' => [
                'nilai_akhir' => round($totalAkhir, 2),
            ]
        ]);
    }

    // --- RPS & Alur Approval Prodi ---
    public function listRps(Request $request)
    {
        $query = \App\Models\Siakad\Rps::with(['mataKuliah.kurikulum.programStudi', 'dosenPengembang', 'koordinatorRmk', 'kaprodi']);

        if ($request->filled('program_studi_id')) {
            $query->whereHas('mataKuliah.kurikulum', fn($q) => $q->where('program_studi_id', $request->program_studi_id));
        }

        if ($request->filled('mata_kuliah_id')) {
            $query->where('mata_kuliah_id', $request->mata_kuliah_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('mataKuliah', fn($q) => $q->where('nama', 'like', "%{$s}%")->orWhere('kode_mk', 'like', "%{$s}%"));
        }

        $rpsList = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $rpsList
        ]);
    }

    public function showRps($id)
    {
        $rps = \App\Models\Siakad\Rps::with([
            'mataKuliah.cpmks.cpl',
            'mataKuliah.kurikulum.programStudi.fakultas',
            'dosenPengembang',
            'koordinatorRmk',
            'kaprodi',
            'mingguan'
        ])->findOrFail($id);

        // Kelas yang memakai RPS ini (MK sama) beserta jadwal & pengampu — read-only
        $kelasPemakai = \App\Models\Siakad\Kelas::with(['tahunAkademik', 'ruangan.gedung', 'programStudi', 'dosenPengampu.dosen'])
            ->where('mata_kuliah_id', $rps->mata_kuliah_id)
            ->orderByDesc('tahun_akademik_id')
            ->get(['id', 'mata_kuliah_id', 'tahun_akademik_id', 'program_studi_id', 'ruangan_id', 'kode_kelas', 'nama_kelas', 'hari', 'jam_mulai', 'jam_selesai', 'kapasitas', 'status']);

        return response()->json([
            'status' => 'success',
            'data' => array_merge($rps->toArray(), ['kelas_pemakai' => $kelasPemakai])
        ]);
    }

    public function storeRps(Request $request)
    {
        $request->validate([
            'mata_kuliah_id' => 'required|exists:siakad_mata_kuliah,id',
            'tahun_ajaran' => 'required|string',
            'semester' => 'required|integer',
            'deskripsi_singkat' => 'required|string',
            'pustaka_utama' => 'nullable|string',
            'pustaka_pendukung' => 'nullable|string',
            'dosen_pengembang_id' => 'nullable|exists:siakad_dosen,id',
            'koordinator_rmk_id' => 'nullable|exists:siakad_dosen,id',
            'kaprodi_id' => 'nullable|exists:siakad_dosen,id',
            'mingguan' => 'nullable|array',
        ]);

        $rps = \App\Models\Siakad\Rps::updateOrCreate(
            ['id' => $request->id],
            $request->except(['mingguan'])
        );

        if ($request->has('mingguan') && is_array($request->mingguan)) {
            foreach ($request->mingguan as $m) {
                if (isset($m['minggu_ke'])) {
                    \App\Models\Siakad\RpsMingguan::updateOrCreate(
                        [
                            'rps_id' => $rps->id,
                            'minggu_ke' => $m['minggu_ke'],
                        ],
                        [
                            'kemampuan_akhir' => $m['kemampuan_akhir'] ?? "Sub-CPMK {$m['minggu_ke']}",
                            'bahan_kajian' => $m['bahan_kajian'] ?? "Bahan Kajian Minggu {$m['minggu_ke']}",
                            'bentuk_metode' => $m['bentuk_metode'] ?? 'Kuliah, Diskusi, & Problem-Based Learning',
                            'estimasi_waktu' => $m['estimasi_waktu'] ?? '2 x 50 Menit',
                            'pengalaman_belajar' => $m['pengalaman_belajar'] ?? 'Menganalisis studi kasus dan tugas terstruktur.',
                            'indikator_penilaian' => $m['indikator_penilaian'] ?? 'Ketepatan analisis dan pemahaman materi.',
                            'bobot_penilaian' => isset($m['bobot_penilaian']) ? (float)$m['bobot_penilaian'] : ($m['minggu_ke'] == 8 ? 25.0 : ($m['minggu_ke'] == 16 ? 30.0 : 3.0)),
                        ]
                    );
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen RPS beserta 16 rencana pertemuan mingguan berhasil disimpan',
            'data' => $rps->load(['mingguan', 'dosenPengembang', 'kaprodi'])
        ]);
    }

    public function getMahasiswaPortofolioObe(Request $request, $mahasiswaId = null)
    {
        $user = $request->user();
        $mahasiswa = null;

        // 1. Jika diberikan ID numerik
        if ($mahasiswaId && is_numeric($mahasiswaId) && (int)$mahasiswaId > 0) {
            $mahasiswa = Mahasiswa::with(['programStudi.fakultas'])->find($mahasiswaId);
        }

        // 2. Jika dipanggil oleh mahasiswa yang sedang login
        if (!$mahasiswa && $user) {
            $mahasiswa = Mahasiswa::with(['programStudi.fakultas'])->where('user_id', $user->id)->first();
            if (!$mahasiswa && !empty($user->username)) {
                $mahasiswa = Mahasiswa::with(['programStudi.fakultas'])->where('nim', $user->username)->first();
            }
            if (!$mahasiswa && !empty($user->email)) {
                $mahasiswa = Mahasiswa::with(['programStudi.fakultas'])->where('email', $user->email)->first();
            }
        }

        // 3. Admin/dosen wajib menyertakan ID spesifik, tanpa fallback ke mahasiswa pertama
        if (!$mahasiswa) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data mahasiswa tidak ditemukan. Sertakan mahasiswa_id yang valid.',
                'data' => null,
            ], 404);
        }

        if (!$mahasiswa) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data mahasiswa belum terdaftar di sistem.',
                'data' => null,
            ], 404);
        }
        
        // Ambil semua CPL yang terdefinisi di program studi mahasiswa
        $cpls = Cpl::where('program_studi_id', $mahasiswa->program_studi_id)
            ->with(['cpmks.mataKuliah'])
            ->get();

        if ($cpls->isEmpty()) {
            $cpls = Cpl::with(['cpmks.mataKuliah'])->get();
        }

        // Ambil data ketercapaian CPMK dari KRS mahasiswa
        $krsDetails = KrsDetail::whereHas('krs', fn($q) => $q->where('mahasiswa_id', $mahasiswa->id))
            ->with(['ketercapaianCpmk.cpmk.cpl', 'kelas.mataKuliah', 'krs.tahunAkademik', 'nilai'])
            ->get();

        $cplSummary = [];
        $kategoriScores = [
            'sikap' => [],
            'pengetahuan' => [],
            'keterampilan_umum' => [],
            'keterampilan_khusus' => [],
        ];

        foreach ($cpls as $cpl) {
            $cpmkIds = $cpl->cpmks ? $cpl->cpmks->pluck('id')->toArray() : [];
            $attainedList = [];

            foreach ($krsDetails as $kd) {
                if ($kd->ketercapaianCpmk) {
                    foreach ($kd->ketercapaianCpmk as $kc) {
                        if (in_array($kc->cpmk_id, $cpmkIds)) {
                            $attainedList[] = (float) $kc->skor_ketercapaian;
                        }
                    }
                }
            }

            $hasAssessment = count($attainedList) > 0;
            $avgScore = $hasAssessment ? round(array_sum($attainedList) / count($attainedList), 1) : 0.0;

            $cplSummary[] = [
                'cpl_id' => $cpl->id,
                'kode_cpl' => $cpl->kode_cpl,
                'kategori' => $cpl->kategori,
                'deskripsi' => $cpl->deskripsi,
                'skor_rata_rata' => $avgScore,
                'status' => $hasAssessment ? ($avgScore >= 65.0 ? 'Memenuhi Standar' : 'Belum Memenuhi') : 'Belum Dinilai (0%)',
                'total_mata_kuliah_diukur' => $hasAssessment ? count($attainedList) : 0,
            ];

            if (isset($kategoriScores[$cpl->kategori])) {
                $kategoriScores[$cpl->kategori][] = $avgScore;
            }
        }

        $radarKategori = [];
        foreach ($kategoriScores as $kat => $scores) {
            $radarKategori[$kat] = count($scores) > 0 
                ? round(array_sum($scores) / count($scores), 1)
                : 0.0;
        }

        // Rincian per-MK per-semester: MK apa saja yang diambil + capaian CPMK-nya
        $mkDetails = $krsDetails->map(function ($kd) {
            $mk = $kd->kelas?->mataKuliah;
            $cpmkScores = ($kd->ketercapaianCpmk ?? collect())->map(fn($kc) => [
                'cpmk_id' => $kc->cpmk_id,
                'kode_cpmk' => $kc->cpmk?->kode_cpmk,
                'skor' => (float) $kc->skor_ketercapaian,
                'is_tercapai' => ($kc->status_ketercapaian ?? '') === 'tercapai' || (float) $kc->skor_ketercapaian >= 65.0,
            ])->values();
            return [
                'krs_detail_id' => $kd->id,
                'tahun_akademik_id' => $kd->krs?->tahun_akademik_id,
                'semester_label' => $kd->krs?->tahunAkademik?->nama ?? 'Semester',
                'kode_mk' => $mk?->kode_mk ?? '-',
                'nama_mk' => $mk?->nama ?? 'Mata Kuliah',
                'sks' => $mk?->total_sks ?? 0,
                'nilai_akhir' => $kd->nilai ? (float) $kd->nilai->nilai_akhir : null,
                'nilai_huruf' => $kd->nilai?->nilai_huruf,
                'is_final' => (bool) ($kd->nilai?->is_final ?? false),
                'cpmk_scores' => $cpmkScores,
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'mahasiswa' => $mahasiswa,
                'cpl_summary' => $cplSummary,
                'radar_kategori' => $radarKategori,
                'total_cpl' => count($cplSummary),
                'total_cpl_tercapai' => count(array_filter($cplSummary, fn($c) => $c['skor_rata_rata'] >= 65.0 && $c['total_mata_kuliah_diukur'] > 0)),
                'mk_details' => $mkDetails,
            ]
        ]);
    }

    public function duplicateRps(Request $request, $id)
    {
        $request->validate([
            'tahun_ajaran' => 'required|string|max:20',
            'semester' => 'nullable|integer|min:1|max:14',
        ]);

        $source = \App\Models\Siakad\Rps::with('mingguan')->findOrFail($id);

        $copy = DB::transaction(function () use ($source, $request) {
            $new = \App\Models\Siakad\Rps::create([
                'mata_kuliah_id' => $source->mata_kuliah_id,
                'tahun_ajaran' => $request->tahun_ajaran,
                'semester' => $request->input('semester', $source->semester),
                'deskripsi_singkat' => $source->deskripsi_singkat,
                'pustaka_utama' => $source->pustaka_utama,
                'pustaka_pendukung' => $source->pustaka_pendukung,
                'dosen_pengembang_id' => $source->dosen_pengembang_id,
                'koordinator_rmk_id' => $source->koordinator_rmk_id,
                'kaprodi_id' => $source->kaprodi_id,
                'status' => 'draft',
                'catatan_revisi' => null,
                'disetujui_at' => null,
            ]);

            foreach ($source->mingguan as $m) {
                \App\Models\Siakad\RpsMingguan::create([
                    'rps_id' => $new->id,
                    'minggu_ke' => $m->minggu_ke,
                    'kemampuan_akhir' => $m->kemampuan_akhir,
                    'bahan_kajian' => $m->bahan_kajian,
                    'bentuk_metode' => $m->bentuk_metode,
                    'estimasi_waktu' => $m->estimasi_waktu,
                    'pengalaman_belajar' => $m->pengalaman_belajar,
                    'indikator_penilaian' => $m->indikator_penilaian,
                    'bobot_penilaian' => $m->bobot_penilaian,
                ]);
            }

            return $new;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'RPS berhasil diimpor dari periode ' . $source->tahun_ajaran . ' sebagai draft. Silakan sesuaikan perubahannya.',
            'data' => $copy->load(['mingguan', 'mataKuliah']),
        ], 201);
    }

    public function submitRps($id)
    {
        $rps = \App\Models\Siakad\Rps::findOrFail($id);
        $rps->update([
            'status' => 'diajukan',
            'catatan_revisi' => null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen RPS berhasil diajukan ke Ketua Program Studi (Kaprodi) untuk diverifikasi.',
            'data' => $rps
        ]);
    }

    public function approveRps(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:disetujui,revisi',
            'catatan_revisi' => 'nullable|string',
        ]);

        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin() && !$user->hasRole('kaprodi') && !$user->hasRole('wakil_prodi')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (Kaprodi / Wakil Kaprodi) untuk melakukan verifikasi RPS.'
            ], 403);
        }

        $rps = \App\Models\Siakad\Rps::findOrFail($id);
        $dosen = \App\Models\Siakad\Dosen::where('user_id', $user?->id)->first();

        $rps->update([
            'status' => $request->status,
            'catatan_revisi' => $request->catatan_revisi,
            'kaprodi_id' => $dosen ? $dosen->id : $rps->kaprodi_id,
            'disetujui_at' => $request->status === 'disetujui' ? now() : null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => $request->status === 'disetujui'
                ? 'RPS berhasil diverifikasi dan disetujui oleh Kaprodi.'
                : 'RPS dikembalikan ke Dosen Pengembang dengan catatan revisi.',
            'data' => $rps
        ]);
    }

    // --- Dashboard Monitoring OBE ---
    public function getObeDashboard(Request $request)
    {
        $prodiId = $request->query('program_studi_id');

        $cplQuery = Cpl::query();
        $cpmkQuery = Cpmk::query();
        $rpsQuery = \App\Models\Siakad\Rps::query();
        $mkQuery = MataKuliah::query();

        if ($prodiId) {
            $cplQuery->where('program_studi_id', $prodiId);
            $mkQuery->whereHas('kurikulum', fn($q) => $q->where('program_studi_id', $prodiId));
            $rpsQuery->whereHas('mataKuliah.kurikulum', fn($q) => $q->where('program_studi_id', $prodiId));
        }

        $totalCpl = $cplQuery->count();
        $totalCpmk = $cpmkQuery->count();
        $totalMk = $mkQuery->count();
        $totalRps = $rpsQuery->count();
        $approvedRps = (clone $rpsQuery)->where('status', 'disetujui')->count();
        $submittedRps = (clone $rpsQuery)->where('status', 'diajukan')->count();
        $draftRps = (clone $rpsQuery)->where('status', 'draft')->count();

        // Rata-rata ketercapaian CPL per kategori (dihitung dari data riil, 0 bila belum dinilai)
        $cpls = $cplQuery->get();
        $cplStats = [
            'sikap' => 0.0,
            'pengetahuan' => 0.0,
            'keterampilan_umum' => 0.0,
            'keterampilan_khusus' => 0.0,
        ];
        if ($cpls->isNotEmpty()) {
            $scores = \App\Models\Siakad\KetercapaianCpmkMahasiswa::with('cpmk.cpl')
                ->when($prodiId, fn($q) => $q->whereHas('cpmk.cpl', fn($cq) => $cq->where('program_studi_id', $prodiId)))
                ->get()
                ->groupBy(fn($r) => $r->cpmk?->cpl?->kategori);
            foreach ($cplStats as $kat => $val) {
                if (isset($scores[$kat]) && $scores[$kat]->isNotEmpty()) {
                    $cplStats[$kat] = round($scores[$kat]->avg(fn($r) => (float) $r->skor_ketercapaian), 1);
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => [
                    'total_cpl' => $totalCpl,
                    'total_cpmk' => $totalCpmk,
                    'total_matakuliah' => $totalMk,
                    'total_rps' => $totalRps,
                    'rps_disetujui' => $approvedRps,
                    'rps_diajukan' => $submittedRps,
                    'rps_draft' => $draftRps,
                    'persentase_rps_approved' => $totalMk > 0 ? round(($approvedRps / $totalMk) * 100, 1) : 100,
                ],
                'cpl_kategori_stats' => $cplStats,
                'cpl_list' => $cpls,
            ]
        ]);
    }

    // --- Profil Lulusan (PL) ---
    public function getProfilLulusan(Request $request)
    {
        $query = ProfilLulusan::with(['programStudi', 'cpls']);
        if ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->program_studi_id);
        }
        return response()->json([
            'status' => 'success',
            'data' => $query->get()
        ]);
    }

    public function storeProfilLulusan(Request $request)
    {
        $request->validate([
            'program_studi_id' => 'required|exists:siakad_program_studi,id',
            'kode_pl' => 'required|string|max:50',
            'nama' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'urutan' => 'nullable|integer',
        ]);

        $pl = ProfilLulusan::updateOrCreate(
            [
                'program_studi_id' => $request->program_studi_id,
                'kode_pl' => $request->kode_pl
            ],
            [
                'nama' => $request->nama,
                'deskripsi' => $request->deskripsi,
                'urutan' => $request->urutan ?? 1,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Profil Lulusan berhasil disimpan',
            'data' => $pl
        ]);
    }

    public function deleteProfilLulusan($id)
    {
        $pl = ProfilLulusan::findOrFail($id);
        $pl->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Profil Lulusan berhasil dihapus'
        ]);
    }

    public function mapProfilLulusanCpl(Request $request)
    {
        $request->validate([
            'profil_lulusan_id' => 'required|exists:siakad_profil_lulusan,id',
            'cpl_ids' => 'required|array',
            'cpl_ids.*' => 'exists:siakad_cpl,id',
        ]);

        $pl = ProfilLulusan::findOrFail($request->profil_lulusan_id);
        $pl->cpls()->sync($request->cpl_ids);

        return response()->json([
            'status' => 'success',
            'message' => 'Pemetaan Profil Lulusan ke CPL berhasil disimpan',
            'data' => $pl->load('cpls')
        ]);
    }

    // --- Bahan Kajian (BK) ---
    public function getBahanKajian(Request $request)
    {
        $query = BahanKajian::with(['programStudi', 'mataKuliahs']);
        if ($request->filled('program_studi_id')) {
            $query->where('program_studi_id', $request->program_studi_id);
        }
        return response()->json([
            'status' => 'success',
            'data' => $query->get()
        ]);
    }

    public function storeBahanKajian(Request $request)
    {
        $request->validate([
            'program_studi_id' => 'required|exists:siakad_program_studi,id',
            'kode_bk' => 'required|string|max:50',
            'nama_bk' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
        ]);

        $bk = BahanKajian::updateOrCreate(
            [
                'program_studi_id' => $request->program_studi_id,
                'kode_bk' => $request->kode_bk
            ],
            [
                'nama_bk' => $request->nama_bk,
                'deskripsi' => $request->deskripsi,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Bahan Kajian berhasil disimpan',
            'data' => $bk
        ]);
    }

    public function deleteBahanKajian($id)
    {
        $bk = BahanKajian::findOrFail($id);
        $bk->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Bahan Kajian berhasil dihapus'
        ]);
    }

    public function mapMataKuliahBahanKajian(Request $request)
    {
        $request->validate([
            'mata_kuliah_id' => 'required|exists:siakad_mata_kuliah,id',
            'bahan_kajian_ids' => 'required|array',
            'bahan_kajian_ids.*' => 'exists:siakad_bahan_kajian,id',
        ]);

        $mk = MataKuliah::findOrFail($request->mata_kuliah_id);
        $mk->bahanKajians()->sync($request->bahan_kajian_ids);

        return response()->json([
            'status' => 'success',
            'message' => 'Pemetaan Mata Kuliah ke Bahan Kajian berhasil disimpan',
            'data' => $mk->load('bahanKajians')
        ]);
    }

    // --- Matriks Korelasi CPL ↔ Mata Kuliah (Checklist Matrix) ---
    public function getMatrixCplMk(Request $request)
    {
        $prodiId = $request->input('program_studi_id');
        
        $cpls = Cpl::where('is_active', true)
            ->when($prodiId, fn($q) => $q->where('program_studi_id', $prodiId))
            ->orderBy('kode_cpl')
            ->get();

        $matakuliahs = MataKuliah::with(['cpls', 'cpmks', 'kurikulum'])
            ->where('is_active', true)
            ->when($prodiId, function($q) use ($prodiId) {
                $q->whereHas('kurikulum', fn($k) => $k->where('program_studi_id', $prodiId));
            })
            ->orderBy('semester_anjuran')
            ->orderBy('kode_mk')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Data matriks CPL dan Mata Kuliah berhasil dimuat',
            'data' => [
                'cpls' => $cpls,
                'matakuliahs' => $matakuliahs,
            ]
        ]);
    }

    public function toggleMatrixCplMk(Request $request)
    {
        $request->validate([
            'mata_kuliah_id' => 'required|exists:siakad_mata_kuliah,id',
            'cpl_id' => 'required|exists:siakad_cpl,id',
            'is_checked' => 'required|boolean',
        ]);

        $mk = MataKuliah::findOrFail($request->mata_kuliah_id);

        if ($request->boolean('is_checked')) {
            $mk->cpls()->syncWithoutDetaching([$request->cpl_id]);
        } else {
            $mk->cpls()->detach($request->cpl_id);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Korelasi CPL terhadap mata kuliah berhasil diperbarui',
            'data' => [
                'mata_kuliah_id' => $mk->id,
                'cpl_id' => (int) $request->cpl_id,
                'is_checked' => $request->boolean('is_checked'),
            ]
        ]);
    }

    // --- Pemantauan & Audit Kelengkapan Pemetaan OBE per Mata Kuliah ---
    public function getAuditPemetaan(Request $request)
    {
        $prodiId = $request->input('program_studi_id');
        $taId = $request->input('tahun_akademik_id') ?? \App\Models\Spmb\MasterTahunAkademik::where('is_active', true)->value('id');

        $matakuliahs = MataKuliah::with(['cpls', 'cpmks', 'kurikulum.programStudi', 'kelas' => function($k) use ($taId) {
                if ($taId) $k->where('tahun_akademik_id', $taId);
                $k->with(['dosenPengampu.dosen', 'krsDetails']);
            }])
            ->where('is_active', true)
            ->when($prodiId, function($q) use ($prodiId) {
                $q->whereHas('kurikulum', fn($k) => $k->where('program_studi_id', $prodiId));
            })
            ->orderBy('semester_default')
            ->orderBy('kode_mk')
            ->get();

        $auditList = $matakuliahs->map(function ($mk) {
            $totalBobotCpmk = (float) $mk->cpmks->sum('bobot_persentase');
            $cpmkCount = $mk->cpmks->count();
            $cplCount = $mk->cpls->count();

            // Status Bobot
            $isBobot100 = abs($totalBobotCpmk - 100.0) < 0.01;
            $statusBobot = $cpmkCount === 0
                ? 'belum_ada_cpmk'
                : ($isBobot100 ? 'lengkap_100' : ($totalBobotCpmk < 100 ? 'kurang_100' : 'lebih_100'));

            // Kelas & Dosen Pengampu Aktif
            $totalKelas = $mk->kelas->count();
            $totalMahasiswa = $mk->kelas->sum(fn($k) => $k->krsDetails->where('status', 'aktif')->count());
            $dosenPengampus = $mk->kelas->flatMap(function($k) {
                return $k->dosenPengampu->map(fn($dp) => [
                    'id' => $dp->dosen?->id,
                    'nama_lengkap' => $dp->dosen?->nama_lengkap,
                    'peran' => $dp->peran,
                    'kelas' => $k->nama_kelas,
                ]);
            })->filter(fn($d) => !empty($d['nama_lengkap']))->unique('id')->values();

            // Status Kelayakan Penilaian Dosen
            $siapDinilai = $isBobot100 && $cpmkCount > 0;

            return [
                'id' => $mk->id,
                'kode_mk' => $mk->kode_mk,
                'nama' => $mk->nama,
                'total_sks' => $mk->total_sks,
                'semester_default' => $mk->semester_default,
                'program_studi' => [
                    'id' => $mk->kurikulum?->programStudi?->id,
                    'nama' => $mk->kurikulum?->programStudi?->nama,
                ],
                'cpl_count' => $cplCount,
                'cpmk_count' => $cpmkCount,
                'total_bobot_cpmk' => $totalBobotCpmk,
                'status_bobot' => $statusBobot,
                'siap_dinilai' => $siapDinilai,
                'total_kelas' => $totalKelas,
                'total_mahasiswa_krs' => $totalMahasiswa,
                'dosen_pengampu' => $dosenPengampus,
            ];
        });

        // Ringkasan Dashboard Audit
        $totalMk = $auditList->count();
        $mkSiapDinilai = $auditList->where('siap_dinilai', true)->count();
        $mkBelum100 = $auditList->where('siap_dinilai', false)->count();
        $mkTanpaCpmk = $auditList->where('cpmk_count', 0)->count();

        return response()->json([
            'status' => 'success',
            'message' => 'Data audit pemetaan OBE berhasil dimuat',
            'data' => [
                'summary' => [
                    'total_matakuliah' => $totalMk,
                    'siap_dinilai' => $mkSiapDinilai,
                    'belum_lengkap' => $mkBelum100,
                    'tanpa_cpmk' => $mkTanpaCpmk,
                    'persentase_kesiapan' => $totalMk > 0 ? round(($mkSiapDinilai / $totalMk) * 100, 1) : 0,
                ],
                'audit_items' => $auditList,
            ]
        ]);
    }

    // --- Pemantauan Ketertiban Dosen Menginput Nilai (Integrasi SIMPEG Kinerja) ---
    public function getDosenKepatuhanNilai(Request $request)
    {
        $taId = $request->input('tahun_akademik_id') ?? \App\Models\Spmb\MasterTahunAkademik::where('is_active', true)->value('id');
        $ta = \App\Models\Spmb\MasterTahunAkademik::find($taId);

        $now = now();
        $batasNilaiMulai = $ta?->input_nilai_mulai;
        $batasNilaiSelesai = $ta?->input_nilai_selesai;

        // Ambil seluruh dosen yang mengampu kelas pada tahun akademik ini
        $kelasQuery = Kelas::with(['dosenPengampu.dosen.pegawai', 'krsDetails.nilai', 'mataKuliah'])
            ->where('tahun_akademik_id', $taId);

        $kelases = $kelasQuery->get();

        $dosenStats = [];

        foreach ($kelases as $k) {
            foreach ($k->dosenPengampu as $dp) {
                if (!$dp->dosen) continue;
                $dId = $dp->dosen->id;

                if (!isset($dosenStats[$dId])) {
                    $dosenStats[$dId] = [
                        'dosen_id' => $dId,
                        'nama_lengkap' => $dp->dosen->nama_lengkap,
                        'nidn' => $dp->dosen->nidn,
                        'nip' => $dp->dosen->nip,
                        'pegawai_id' => $dp->dosen->pegawai?->id ?? null,
                        'total_kelas' => 0,
                        'total_mahasiswa' => 0,
                        'mahasiswa_dinilai' => 0,
                        'mahasiswa_final' => 0,
                        'kelas_selesai' => 0,
                        'status_kepatuhan' => 'tepat_waktu', // tepat_waktu, dalam_proses, terlambat
                    ];
                }

                $dosenStats[$dId]['total_kelas']++;
                $krsAktif = $k->krsDetails->where('status', 'aktif');
                $dosenStats[$dId]['total_mahasiswa'] += $krsAktif->count();

                $graded = $krsAktif->filter(fn($kd) => $kd->nilai && $kd->nilai->nilai_angka > 0 || ($kd->nilai && $kd->nilai->nilai_huruf));
                $finalized = $krsAktif->filter(fn($kd) => $kd->nilai && $kd->nilai->is_final);

                $dosenStats[$dId]['mahasiswa_dinilai'] += $graded->count();
                $dosenStats[$dId]['mahasiswa_final'] += $finalized->count();

                if ($krsAktif->count() > 0 && $finalized->count() >= $krsAktif->count()) {
                    $dosenStats[$dId]['kelas_selesai']++;
                }
            }
        }

        // Hitung persentase ketertiban & skor kinerja
        $result = collect($dosenStats)->values()->map(function ($d) use ($batasNilaiSelesai, $now) {
            $totalMhs = $d['total_mahasiswa'];
            $pctFinal = $totalMhs > 0 ? round(($d['mahasiswa_final'] / $totalMhs) * 100, 1) : 100.0;
            $pctInput = $totalMhs > 0 ? round(($d['mahasiswa_dinilai'] / $totalMhs) * 100, 1) : 100.0;

            // Evaluasi kepatuhan deadline
            $isDeadlinePassed = $batasNilaiSelesai && $now->greaterThan(\Carbon\Carbon::parse($batasNilaiSelesai));
            
            if ($pctFinal >= 100.0) {
                $statusKepatuhan = 'lengkap_final';
                $skorKepatuhan = 100.0;
            } elseif ($isDeadlinePassed) {
                $statusKepatuhan = 'terlambat';
                $skorKepatuhan = max(30.0, round($pctFinal * 0.7, 1));
            } else {
                $statusKepatuhan = 'sedang_berjalan';
                $skorKepatuhan = max(50.0, round($pctFinal, 1));
            }

            return array_merge($d, [
                'persentase_input' => $pctInput,
                'persentase_final' => $pctFinal,
                'status_kepatuhan' => $statusKepatuhan,
                'skor_kinerja_akademik' => $skorKepatuhan,
            ]);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Data ketertiban & kepatuhan pengisian nilai dosen berhasil dimuat',
            'data' => [
                'tahun_akademik' => $ta,
                'periode_nilai' => [
                    'mulai' => $batasNilaiMulai,
                    'selesai' => $batasNilaiSelesai,
                    'is_expired' => $batasNilaiSelesai ? $now->greaterThan(\Carbon\Carbon::parse($batasNilaiSelesai)) : false,
                ],
                'summary' => [
                    'total_dosen_mengajar' => $result->count(),
                    'dosen_selesai_100' => $result->where('status_kepatuhan', 'lengkap_final')->count(),
                    'dosen_terlambat' => $result->where('status_kepatuhan', 'terlambat')->count(),
                    'dosen_sedang_berjalan' => $result->where('status_kepatuhan', 'sedang_berjalan')->count(),
                ],
                'dosen_kepatuhan' => $result,
            ]
        ]);
    }
}
