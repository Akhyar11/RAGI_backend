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
        $cutoffDate = $request->query('cutoff_date');
        $search = $request->query('search');

        $query = TagihanMahasiswa::with([
            'mahasiswa.programStudi',
            'tipeTagihanMahasiswa',
            'details.masterBiaya',
            'pembayarans',
            'dispensasis' => function ($q) {
                $q->where('status', 'approved');
            }
        ]);

        // Apply filters
        $this->applyFilters($query, $status, $tahunAkademikId, $angkatan, $programStudiId, $search, $cutoffDate);

        // Sort
        $allowedSortColumns = ['id', 'created_at', 'nomor_tagihan', 'total_tagihan', 'total_bayar', 'jatuh_tempo', 'status'];
        $sortBy = in_array($request->query('sort_by'), $allowedSortColumns) ? $request->query('sort_by') : 'id';
        $sortOrder = strtolower($request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $paginated = $query->paginate($perPage);

        // Calculate summary metrics for whole dataset matching filters (without pagination)
        $summaryQuery = TagihanMahasiswa::query();
        $this->applyFilters($summaryQuery, $status, $tahunAkademikId, $angkatan, $programStudiId, $search, $cutoffDate);

        $allTagihans = $summaryQuery->with(['pembayarans'])->get();
        $totalTagihan = (float)$allTagihans->sum('total_tagihan');
        $totalPotongan = (float)$allTagihans->sum('total_potongan');
        $totalDenda = (float)$allTagihans->sum('total_denda');

        $totalBayar = (float)$allTagihans->sum(function ($t) use ($cutoffDate) {
            if ($cutoffDate) {
                return (float)$t->pembayarans->where('status', 'success')->filter(function ($p) use ($cutoffDate) {
                    $tgl = $p->waktu_bayar ? $p->waktu_bayar->toDateString() : ($p->created_at ? $p->created_at->toDateString() : null);
                    return $tgl && $tgl <= $cutoffDate;
                })->sum('jumlah_bayar');
            }
            return (float)$t->total_bayar;
        });

        $totalPiutang = max(0, ($totalTagihan + $totalDenda - $totalPotongan) - $totalBayar);
        $totalMahasiswaTunggakan = $allTagihans->pluck('mahasiswa_id')->unique()->count();
        $totalRecordDispensasi = $allTagihans->where('status', 'dispensasi')->count();

        // Transform data output
        $formattedData = collect($paginated->items())->map(function ($t) use ($cutoffDate) {
            $mhs = $t->mahasiswa;
            $tipeMhs = $t->tipeTagihanMahasiswa;

            $nim = $mhs?->nim ?? $tipeMhs?->nim ?? ('2024' . str_pad($t->mahasiswa_id, 4, '0', STR_PAD_LEFT));
            $nama = $mhs?->nama_lengkap ?? $tipeMhs?->nama_mahasiswa ?? ('Mahasiswa #' . $t->mahasiswa_id);
            $angkatanVal = $mhs?->angkatan ?? $tipeMhs?->tahun_angkatan ?? 2025;
            $prodi = $mhs?->programStudi?->nama ?? $mhs?->programStudi?->nama_prodi ?? 'Teknik Informatika';

            $totalBayarRow = $cutoffDate
                ? (float)$t->pembayarans->where('status', 'success')->filter(function ($p) use ($cutoffDate) {
                    $tgl = $p->waktu_bayar ? $p->waktu_bayar->toDateString() : ($p->created_at ? $p->created_at->toDateString() : null);
                    return $tgl && $tgl <= $cutoffDate;
                })->sum('jumlah_bayar')
                : (float)$t->total_bayar;

            $totalBersih = (float)($t->total_tagihan + $t->total_denda - $t->total_potongan);
            $sisaPiutang = max(0, $totalBersih - $totalBayarRow);
            $statusRow = $cutoffDate ? ($sisaPiutang <= 0 ? 'lunas' : ($totalBayarRow > 0 ? 'sebagian' : 'belum_bayar')) : $t->status;

            return [
                'id' => $t->id,
                'nomor_tagihan' => $t->nomor_tagihan,
                'mahasiswa_id' => $t->mahasiswa_id,
                'nim' => $nim,
                'nama_mahasiswa' => $nama,
                'angkatan' => (int)$angkatanVal,
                'program_studi' => $prodi,
                'program_studi_id' => $mhs?->program_studi_id,
                'tahun_akademik_id' => $t->tahun_akademik_id,
                'tahun_akademik' => '2025/2026 Ganjil',
                'total_tagihan' => (float)$t->total_tagihan,
                'total_potongan' => (float)$t->total_potongan,
                'total_denda' => (float)$t->total_denda,
                'total_bayar' => $totalBayarRow,
                'sisa_piutang' => $sisaPiutang,
                'status' => $statusRow,
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
                'cutoff_date' => $cutoffDate,
                'status' => $status,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * GET /api/v1/sikeu/piutang/export-excel
     * Download piutang report as rich formatted Excel (.xls) spreadsheet.
     */
    public function exportExcel(Request $request)
    {
        $status = $request->query('status', 'piutang');
        $angkatan = $request->query('angkatan');
        $tahunAkademikId = $request->query('tahun_akademik_id');
        $programStudiId = $request->query('program_studi_id');
        $cutoffDate = $request->query('cutoff_date');
        $search = $request->query('search');

        $query = TagihanMahasiswa::with([
            'mahasiswa.programStudi',
            'tipeTagihanMahasiswa',
            'pembayarans',
        ]);

        $this->applyFilters($query, $status, $tahunAkademikId, $angkatan, $programStudiId, $search, $cutoffDate);

        $tagihans = $query->orderBy('id', 'desc')->get();

        $filename = 'Laporan_Piutang_Mahasiswa_' . ($cutoffDate ? 'Cutoff_' . str_replace('-', '', $cutoffDate) . '_' : '') . date('Ymd_His') . '.xls';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($tagihans, $cutoffDate, $status, $angkatan) {
            $output = fopen('php://output', 'w');
            
            // Output UTF-8 BOM
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            $filterInfo = 'Status: ' . strtoupper($status) . ' | Angkatan: ' . ($angkatan ?: 'Semua') . ($cutoffDate ? ' | Cutoff Date: ' . $cutoffDate : '') . ' | Dicetak: ' . date('d-m-Y H:i:s');

            $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
                <!--[if gte mso 9]>
                <xml>
                    <x:ExcelWorkbook>
                        <x:ExcelWorksheets>
                            <x:ExcelWorksheet>
                                <x:Name>Rekapitulasi Piutang</x:Name>
                                <x:WorksheetOptions>
                                    <x:DisplayGridlines/>
                                </x:WorksheetOptions>
                            </x:ExcelWorksheet>
                        </x:ExcelWorksheets>
                    </x:ExcelWorkbook>
                </xml>
                <![endif]-->
                <style>
                    body { font-family: Segoe UI, Arial, sans-serif; font-size: 11px; }
                    table { border-collapse: collapse; width: 100%; }
                    .header-title { font-size: 14px; font-weight: bold; text-align: center; color: #1e3a8a; }
                    .header-sub { font-size: 12px; font-weight: bold; text-align: center; color: #334155; }
                    .header-info { font-size: 10px; font-style: italic; text-align: center; color: #64748b; }
                    th { background-color: #1e40af; color: #ffffff; font-weight: bold; text-align: center; border: 1px solid #1e3a8a; padding: 8px 6px; }
                    td { border: 1px solid #cbd5e1; padding: 6px; font-size: 11px; }
                    .text-center { text-align: center; }
                    .text-right { text-align: right; }
                    .num-format { mso-number-format:"\#\,\#\#0"; text-align: right; }
                    .str-format { mso-number-format:"\@"; }
                    .summary-row { background-color: #e2e8f0; font-weight: bold; border-top: 2px solid #0f172a; }
                    .badge-lunas { color: #166534; font-weight: bold; }
                    .badge-belum { color: #991b1b; font-weight: bold; }
                    .badge-sebagian { color: #854d0e; font-weight: bold; }
                </style>
            </head>
            <body>
                <table>
                    <tr><td colspan="14" class="header-title">UNIVERSITAS SSO CAMPUS</td></tr>
                    <tr><td colspan="14" class="header-sub">DIREKTORAT KEUANGAN & AKUNTANSI — LAPORAN POSISI PIUTANG MAHASISWA</td></tr>
                    <tr><td colspan="14" class="header-info">' . htmlspecialchars($filterInfo) . '</td></tr>
                    <tr><td colspan="14"></td></tr>
                    <thead>
                        <tr>
                            <th>NO</th>
                            <th>NOMOR TAGIHAN</th>
                            <th>NIM</th>
                            <th>NAMA MAHASISWA</th>
                            <th>ANGKATAN</th>
                            <th>PROGRAM STUDI</th>
                            <th>PERIODE</th>
                            <th>TOTAL TAGIHAN (RP)</th>
                            <th>POTONGAN (RP)</th>
                            <th>DENDA (RP)</th>
                            <th>TOTAL BAYAR (RP)</th>
                            <th>SISA PIUTANG (RP)</th>
                            <th>STATUS</th>
                            <th>JATUH TEMPO</th>
                        </tr>
                    </thead>
                    <tbody>';

            fwrite($output, $html);

            $no = 1;
            $sumTagihan = 0;
            $sumPotongan = 0;
            $sumDenda = 0;
            $sumBayar = 0;
            $sumPiutang = 0;

            foreach ($tagihans as $t) {
                $mhs = $t->mahasiswa;
                $tipeMhs = $t->tipeTagihanMahasiswa;

                $nim = $mhs?->nim ?? $tipeMhs?->nim ?? ('2024' . str_pad($t->mahasiswa_id, 4, '0', STR_PAD_LEFT));
                $nama = $mhs?->nama_lengkap ?? $tipeMhs?->nama_mahasiswa ?? ('Mahasiswa #' . $t->mahasiswa_id);
                $angkatanVal = $mhs?->angkatan ?? $tipeMhs?->tahun_angkatan ?? 2025;
                $prodi = $mhs?->programStudi?->nama ?? $mhs?->programStudi?->nama_prodi ?? 'Teknik Informatika';

                $totalBayarRow = $cutoffDate
                    ? (float)$t->pembayarans->where('status', 'success')->filter(function ($p) use ($cutoffDate) {
                        $tgl = $p->waktu_bayar ? $p->waktu_bayar->toDateString() : ($p->created_at ? $p->created_at->toDateString() : null);
                        return $tgl && $tgl <= $cutoffDate;
                    })->sum('jumlah_bayar')
                    : (float)$t->total_bayar;

                $totalBersih = (float)($t->total_tagihan + $t->total_denda - $t->total_potongan);
                $sisaPiutang = max(0, $totalBersih - $totalBayarRow);
                $statusRow = $cutoffDate ? ($sisaPiutang <= 0 ? 'LUNAS' : ($totalBayarRow > 0 ? 'SEBAGIAN' : 'BELUM BAYAR')) : strtoupper(str_replace('_', ' ', $t->status));

                $sumTagihan += (float)$t->total_tagihan;
                $sumPotongan += (float)$t->total_potongan;
                $sumDenda += (float)$t->total_denda;
                $sumBayar += $totalBayarRow;
                $sumPiutang += $sisaPiutang;

                $bgClass = $no % 2 === 0 ? 'style="background-color:#f8fafc;"' : '';
                $statusClass = $statusRow === 'LUNAS' ? 'badge-lunas' : ($statusRow === 'SEBAGIAN' ? 'badge-sebagian' : 'badge-belum');

                $rowHtml = "<tr {$bgClass}>
                    <td class=\"text-center\">{$no}</td>
                    <td class=\"str-format\">{$t->nomor_tagihan}</td>
                    <td class=\"str-format text-center\">{$nim}</td>
                    <td>" . htmlspecialchars($nama) . "</td>
                    <td class=\"text-center\">{$angkatanVal}</td>
                    <td>" . htmlspecialchars($prodi) . "</td>
                    <td class=\"text-center\">2025/2026 Ganjil</td>
                    <td class=\"num-format\">" . (float)$t->total_tagihan . "</td>
                    <td class=\"num-format\">" . (float)$t->total_potongan . "</td>
                    <td class=\"num-format\">" . (float)$t->total_denda . "</td>
                    <td class=\"num-format\">{$totalBayarRow}</td>
                    <td class=\"num-format\" style=\"font-weight:bold; color:#991b1b;\">{$sisaPiutang}</td>
                    <td class=\"text-center {$statusClass}\">{$statusRow}</td>
                    <td class=\"text-center\">" . ($t->jatuh_tempo ? $t->jatuh_tempo->format('Y-m-d') : '-') . "</td>
                </tr>";

                fwrite($output, $rowHtml);
                $no++;
            }

            $footerHtml = "<tr class=\"summary-row\">
                <td colspan=\"7\" class=\"text-right\">TOTAL REKAPITULASI KESELURUHAN (RP):</td>
                <td class=\"num-format\">{$sumTagihan}</td>
                <td class=\"num-format\">{$sumPotongan}</td>
                <td class=\"num-format\">{$sumDenda}</td>
                <td class=\"num-format\">{$sumBayar}</td>
                <td class=\"num-format\" style=\"color:#991b1b;\">{$sumPiutang}</td>
                <td colspan=\"2\" class=\"text-center\">" . ($no - 1) . " Data</td>
            </tr>
            </tbody>
            </table>
            </body>
            </html>";

            fwrite($output, $footerHtml);
            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Helper to apply common query filters.
     */
    private function applyFilters($query, $status, $tahunAkademikId, $angkatan, $programStudiId, $search, $cutoffDate = null)
    {
        // Filter Cutoff Tanggal: tagihan yang terbit/dibuat pada atau sebelum cutoff date
        if (!empty($cutoffDate)) {
            $query->where(function ($q) use ($cutoffDate) {
                $q->whereDate('jatuh_tempo', '<=', $cutoffDate)
                  ->orWhereDate('created_at', '<=', $cutoffDate);
            });
        }

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
