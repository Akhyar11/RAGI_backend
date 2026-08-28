<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\TagihanMahasiswa;
use Illuminate\Http\Request;

class PiutangMahasiswaController extends Controller
{
    /**
     * GET /api/v1/sikeu/piutang
     * Get paginated student receivables (piutang) with filters and summary totals.
     */
    public function index(Request $request)
    {
        $perPage = min(100, $request->integer('per_page', 15));
        $status = $request->query('status', 'piutang'); // 'piutang' (default: belum lunas), 'belum_bayar', 'sebagian', 'dispensasi', 'lunas', 'all'
        $angkatan = $request->query('angkatan');
        $tahunAkademikId = $request->query('tahun_akademik_id');
        $programStudiId = $request->query('program_studi_id');
        $search = $request->query('search');

        $query = TagihanMahasiswa::with([
            'mahasiswa.programStudi',
            'tipeTagihanMahasiswa',
            'details.masterBiaya',
            'dispensasis' => function ($q) {
                $q->where('status', 'approved');
            }
        ]);

        // Apply filters
        $this->applyFilters($query, $status, $tahunAkademikId, $angkatan, $programStudiId, $search);

        // Sort
        $allowedSortColumns = ['id', 'created_at', 'nomor_tagihan', 'total_tagihan', 'total_bayar', 'jatuh_tempo', 'status'];
        $sortBy = in_array($request->query('sort_by'), $allowedSortColumns) ? $request->query('sort_by') : 'id';
        $sortOrder = strtolower($request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $paginated = $query->paginate($perPage);

        // Calculate summary metrics for whole dataset matching filters (without pagination)
        $summaryQuery = TagihanMahasiswa::query();
        $this->applyFilters($summaryQuery, $status, $tahunAkademikId, $angkatan, $programStudiId, $search);

        $totalTagihan = (float)$summaryQuery->sum('total_tagihan');
        $totalBayar = (float)$summaryQuery->sum('total_bayar');
        $totalPotongan = (float)$summaryQuery->sum('total_potongan');
        $totalDenda = (float)$summaryQuery->sum('total_denda');
        $totalPiutang = max(0, ($totalTagihan + $totalDenda - $totalPotongan) - $totalBayar);
        $totalMahasiswaTunggakan = $summaryQuery->distinct('mahasiswa_id')->count('mahasiswa_id');
        $totalRecordDispensasi = (clone $summaryQuery)->where('status', 'dispensasi')->count();

        // Transform data output
        $formattedData = collect($paginated->items())->map(function ($t) {
            $mhs = $t->mahasiswa;
            $tipeMhs = $t->tipeTagihanMahasiswa;

            $nim = $mhs->nim ?? $tipeMhs->nim ?? ('NIM-' . $t->mahasiswa_id);
            $nama = $mhs->nama_lengkap ?? $tipeMhs->nama_mahasiswa ?? ('Mahasiswa #' . $t->mahasiswa_id);
            $angkatanVal = $mhs->angkatan ?? $tipeMhs->tahun_angkatan ?? 2024;
            $prodi = $mhs->programStudi->nama_prodi ?? 'Teknik Informatika';

            $totalBersih = (float)($t->total_tagihan + $t->total_denda - $t->total_potongan);
            $sisaPiutang = max(0, $totalBersih - (float)$t->total_bayar);

            return [
                'id' => $t->id,
                'nomor_tagihan' => $t->nomor_tagihan,
                'mahasiswa_id' => $t->mahasiswa_id,
                'nim' => $nim,
                'nama_mahasiswa' => $nama,
                'angkatan' => (int)$angkatanVal,
                'program_studi' => $prodi,
                'tahun_akademik_id' => $t->tahun_akademik_id,
                'tahun_akademik' => '2025/2026 Ganjil',
                'total_tagihan' => (float)$t->total_tagihan,
                'total_potongan' => (float)$t->total_potongan,
                'total_denda' => (float)$t->total_denda,
                'total_bayar' => (float)$t->total_bayar,
                'sisa_piutang' => $sisaPiutang,
                'status' => $t->status,
                'jatuh_tempo' => $t->jatuh_tempo ? $t->jatuh_tempo->format('Y-m-d') : null,
                'created_at' => $t->created_at ? $t->created_at->format('Y-m-d H:i:s') : null,
                'has_dispensasi' => $t->dispensasis->isNotEmpty(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Data piutang mahasiswa berhasil dimuat',
            'data' => $formattedData,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
            'summary' => [
                'total_tagihan' => $totalTagihan,
                'total_potongan' => $totalPotongan,
                'total_denda' => $totalDenda,
                'total_bayar' => $totalBayar,
                'total_piutang' => $totalPiutang,
                'total_mahasiswa_tunggakan' => $totalMahasiswaTunggakan,
                'total_record_dispensasi' => $totalRecordDispensasi,
            ],
            'filters' => [
                'search' => $search,
                'angkatan' => $angkatan,
                'tahun_akademik_id' => $tahunAkademikId,
                'program_studi_id' => $programStudiId,
                'status' => $status,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * GET /api/v1/sikeu/piutang/export-excel
     * Download piutang report as Excel CSV format.
     */
    public function exportExcel(Request $request)
    {
        $status = $request->query('status', 'piutang');
        $angkatan = $request->query('angkatan');
        $tahunAkademikId = $request->query('tahun_akademik_id');
        $programStudiId = $request->query('program_studi_id');
        $search = $request->query('search');

        $query = TagihanMahasiswa::with([
            'mahasiswa.programStudi',
            'tipeTagihanMahasiswa',
        ]);

        $this->applyFilters($query, $status, $tahunAkademikId, $angkatan, $programStudiId, $search);

        $tagihans = $query->orderBy('id', 'desc')->get();

        $filename = 'Laporan_Piutang_Mahasiswa_' . date('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($tagihans) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                'NO',
                'NOMOR TAGIHAN',
                'NIM',
                'NAMA MAHASISWA',
                'ANGKATAN',
                'PROGRAM STUDI',
                'PERIODE / TAHUN AKADEMIK',
                'TOTAL TAGIHAN (RP)',
                'POTONGAN (RP)',
                'DENDA (RP)',
                'TOTAL BAYAR (RP)',
                'SISA PIUTANG (RP)',
                'STATUS',
                'JATUH TEMPO',
            ]);

            $no = 1;
            foreach ($tagihans as $t) {
                $mhs = $t->mahasiswa;
                $tipeMhs = $t->tipeTagihanMahasiswa;

                $nim = $mhs->nim ?? $tipeMhs->nim ?? ('NIM-' . $t->mahasiswa_id);
                $nama = $mhs->nama_lengkap ?? $tipeMhs->nama_mahasiswa ?? ('Mahasiswa #' . $t->mahasiswa_id);
                $angkatanVal = $mhs->angkatan ?? $tipeMhs->tahun_angkatan ?? 2024;
                $prodi = $mhs->programStudi->nama_prodi ?? 'Teknik Informatika';

                $totalBersih = (float)($t->total_tagihan + $t->total_denda - $t->total_potongan);
                $sisaPiutang = max(0, $totalBersih - (float)$t->total_bayar);

                fputcsv($file, [
                    $no++,
                    $t->nomor_tagihan,
                    $nim,
                    $nama,
                    $angkatanVal,
                    $prodi,
                    '2025/2026 Ganjil',
                    number_format((float)$t->total_tagihan, 0, ',', '.'),
                    number_format((float)$t->total_potongan, 0, ',', '.'),
                    number_format((float)$t->total_denda, 0, ',', '.'),
                    number_format((float)$t->total_bayar, 0, ',', '.'),
                    number_format($sisaPiutang, 0, ',', '.'),
                    strtoupper(str_replace('_', ' ', $t->status)),
                    $t->jatuh_tempo ? $t->jatuh_tempo->format('Y-m-d') : '-',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Helper to apply common query filters.
     */
    private function applyFilters($query, $status, $tahunAkademikId, $angkatan, $programStudiId, $search)
    {
        // Filter status
        if ($status === 'piutang') {
            $query->whereIn('status', ['belum_bayar', 'sebagian', 'dispensasi']);
        } elseif ($status !== 'all' && !empty($status)) {
            $query->where('status', $status);
        }

        // Filter Tahun Akademik / Periode
        if (!empty($tahunAkademikId) && $tahunAkademikId !== 'all') {
            $query->where('tahun_akademik_id', $tahunAkademikId);
        }

        // Filter Angkatan, Program Studi, & Search Text
        if (!empty($angkatan) && $angkatan !== 'all') {
            $angkatanInt = (int)$angkatan;
            $query->where(function ($q) use ($angkatanInt, $angkatan) {
                $q->whereHas('mahasiswa', function ($m) use ($angkatanInt) {
                    $m->where('angkatan', $angkatanInt);
                })
                ->orWhereHas('tipeTagihanMahasiswa', function ($tm) use ($angkatanInt) {
                    $tm->where('tahun_angkatan', $angkatanInt);
                })
                ->orWhere('nomor_tagihan', 'like', "%-{$angkatan}-%");
            });
        }

        if (!empty($programStudiId) && $programStudiId !== 'all') {
            $query->whereHas('mahasiswa', function ($m) use ($programStudiId) {
                $m->where('program_studi_id', (int)$programStudiId);
            });
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('nomor_tagihan', 'like', "%{$search}%")
                  ->orWhere('mahasiswa_id', 'like', "%{$search}%")
                  ->orWhereHas('mahasiswa', function ($m) use ($search) {
                      $m->where('nim', 'like', "%{$search}%")
                        ->orWhere('nama_lengkap', 'like', "%{$search}%");
                  })
                  ->orWhereHas('tipeTagihanMahasiswa', function ($tm) use ($search) {
                      $tm->where('nim', 'like', "%{$search}%")
                        ->orWhere('nama_mahasiswa', 'like', "%{$search}%");
                  });
            });
        }
    }
}
