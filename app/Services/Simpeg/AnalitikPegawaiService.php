<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\JabatanFungsionalAkademik;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\UnitKerja;
use App\Services\IAM\RestrictedRoleService;
use App\Services\Storage\FileStorageService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalitikPegawaiService
{
    public function __construct(
        protected FileStorageService $files,
        protected RestrictedRoleService $restrictedRole
    ) {}

    /**
     * Membangun base query untuk analitik pegawai dengan filter dinamis.
     */
    public function buildQuery(array $filters)
    {
        $query = Pegawai::query()
            ->with([
                'jabatanFungsional',
                'unitKerja',
                'riwayatJabatan' => function ($q) {
                    $q->whereNotNull('jabatan_fungsional_id')
                        ->orderBy('mulai_jabatan', 'desc')
                        ->with('jabatanFungsional');
                },
                'sertifikasiDosen',
            ]);

        // Pengecualian akun Superadmin & role terestriksi (Kebijakan SIMPEG SSO)
        $restrictedRoleIds = $this->restrictedRole->getRestrictedRoleIds();
        $query->whereDoesntHave('user.roles', function ($q) use ($restrictedRoleIds) {
            $q->where('slug', 'superadmin')
                ->when(!empty($restrictedRoleIds), fn($rq) => $rq->orWhereIn('core_roles.id', $restrictedRoleIds));
        })->whereDoesntHave('roles', function ($q) use ($restrictedRoleIds) {
            $q->where('slug', 'superadmin')
                ->when(!empty($restrictedRoleIds), fn($rq) => $rq->orWhereIn('core_roles.id', $restrictedRoleIds));
        });

        // Search text (Nama, NIP, NIDN, Nomor SK)
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('nidn', 'like', "%{$search}%")
                    ->orWhereHas('riwayatJabatan', fn($rj) => $rj->where('sk_nomor', 'like', "%{$search}%"));
            });
        }

        // Filter Jabatan Fungsional Akademik (ID)
        if (!empty($filters['jabatan_fungsional_id'])) {
            $query->where('jabatan_fungsional_id', $filters['jabatan_fungsional_id']);
        }

        // Filter Unit Kerja / Program Studi (ID)
        if (!empty($filters['unit_kerja_id'])) {
            $query->where('unit_kerja_id', $filters['unit_kerja_id']);
        }

        // Filter Jenis Pegawai (Dosen / Tendik)
        if (!empty($filters['jenis_pegawai'])) {
            $query->where('jenis_pegawai', $filters['jenis_pegawai']);
        }

        // Filter Status Kepegawaian (Tetap, Kontrak, dll.)
        if (!empty($filters['status_kepegawaian'])) {
            $query->where('status_kepegawaian', $filters['status_kepegawaian']);
        }

        // Filter Rentang Tanggal TMT JAD (mulai_jabatan di riwayat jabatan aktif atau tanggal_mulai_berlaku usulan)
        $tStart = !empty($filters['tmt_start']) ? $filters['tmt_start'] : null;
        $tEnd = !empty($filters['tmt_end']) ? $filters['tmt_end'] : null;

        if ($tStart || $tEnd) {
            $query->where(function ($q) use ($tStart, $tEnd, $filters) {
                $q->whereHas('riwayatJabatan', function ($rj) use ($tStart, $tEnd, $filters) {
                    $rj->whereNotNull('jabatan_fungsional_id');
                    if (!empty($filters['jabatan_fungsional_id'])) {
                        $rj->where('jabatan_fungsional_id', $filters['jabatan_fungsional_id']);
                    }
                    if ($tStart && $tEnd) {
                        $rj->whereBetween('mulai_jabatan', [$tStart, $tEnd]);
                    } elseif ($tStart) {
                        $rj->where('mulai_jabatan', '>=', $tStart);
                    } elseif ($tEnd) {
                        $rj->where('mulai_jabatan', '<=', $tEnd);
                    }
                });
            });
        }

        // Filter Status Serdos (Sudah Serdos / Belum)
        if (isset($filters['is_serdos']) && $filters['is_serdos'] !== '') {
            $hasSerdos = filter_var($filters['is_serdos'], FILTER_VALIDATE_BOOLEAN);
            if ($hasSerdos) {
                $query->whereHas('sertifikasiDosen');
            } else {
                $query->whereDoesntHave('sertifikasiDosen');
            }
        }

        return $query;
    }

    /**
     * Mengambil ringkasan metrik statistik agregat berdasarkan filter yang diterapkan.
     */
    public function getRingkasan(array $filters): array
    {
        $baseQuery = $this->buildQuery($filters);
        $pegawais = (clone $baseQuery)->get();

        $totalPegawai = $pegawais->count();
        $totalDosen = $pegawais->where('jenis_pegawai', 'dosen')->count();
        $totalTendik = $pegawais->where('jenis_pegawai', '!=', 'dosen')->count();

        // Hitung persebaran JAD aktif
        $masterJafung = JabatanFungsionalAkademik::orderBy('angka_kredit_min')->get();
        $jadCounts = [];
        $kategoriJad = [
            'tenaga_pengajar' => 0,
            'asisten_ahli' => 0,
            'lektor' => 0,
            'lektor_kepala' => 0,
            'guru_besar' => 0,
            'belum_ada' => 0,
        ];

        foreach ($masterJafung as $jf) {
            $count = $pegawais->where('jabatan_fungsional_id', $jf->id)->count();
            $jadCounts[] = [
                'id' => $jf->id,
                'nama' => $jf->nama,
                'angka_kredit' => $jf->angka_kredit_min,
                'total' => $count,
            ];

            // Kelompokkan ke kategori besar
            $namaLower = strtolower($jf->nama);
            if (str_contains($namaLower, 'guru besar') || str_contains($namaLower, 'profesor')) {
                $kategoriJad['guru_besar'] += $count;
            } elseif (str_contains($namaLower, 'lektor kepala')) {
                $kategoriJad['lektor_kepala'] += $count;
            } elseif (str_contains($namaLower, 'lektor')) {
                $kategoriJad['lektor'] += $count;
            } elseif (str_contains($namaLower, 'asisten ahli')) {
                $kategoriJad['asisten_ahli'] += $count;
            } else {
                $kategoriJad['tenaga_pengajar'] += $count;
            }
        }

        $kategoriJad['belum_ada'] = $pegawais->whereNull('jabatan_fungsional_id')->count();

        // Sebaran per unit kerja
        $unitKerjaCounts = [];
        $allUnits = UnitKerja::where('is_active', true)->orderBy('nama')->get();
        foreach ($allUnits as $unit) {
            $count = $pegawais->where('unit_kerja_id', $unit->id)->count();
            if ($count > 0) {
                $unitKerjaCounts[] = [
                    'id' => $unit->id,
                    'nama' => $unit->nama,
                    'kode' => $unit->kode,
                    'tipe' => $unit->tipe,
                    'total' => $count,
                ];
            }
        }
        usort($unitKerjaCounts, fn($a, $b) => $b['total'] <=> $a['total']);

        // Status Serdos
        $totalSerdos = $pegawais->filter(fn($p) => $p->sertifikasiDosen && $p->sertifikasiDosen->isNotEmpty())->count();
        $totalBelumSerdos = $totalPegawai - $totalSerdos;

        return [
            'total_pegawai' => $totalPegawai,
            'total_dosen' => $totalDosen,
            'total_tendik' => $totalTendik,
            'kategori_jad' => $kategoriJad,
            'jad_detail' => $jadCounts,
            'unit_kerja_distribusi' => $unitKerjaCounts,
            'serdos' => [
                'sudah_serdos' => $totalSerdos,
                'belum_serdos' => $totalBelumSerdos,
            ],
            'periode_tmt' => [
                'start' => $filters['tmt_start'] ?? null,
                'end' => $filters['tmt_end'] ?? null,
            ],
        ];
    }

    /**
     * Mengambil daftar pegawai terfilter dengan pagination.
     */
    public function getPaginated(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->buildQuery($filters);

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'nama') {
            $query->orderBy('nama_lengkap', $sortOrder);
        } elseif ($sortBy === 'nip') {
            $query->orderBy('nip', $sortOrder);
        } else {
            $query->orderBy('created_at', $sortOrder);
        }

        $paginator = $query->paginate($perPage);

        // Format item agar siap dikonsumsi UI
        $paginator->getCollection()->transform(function (Pegawai $p) {
            $activeRiwayat = $p->riwayatJabatan->first(function ($rj) use ($p) {
                return $rj->is_active || ($p->jabatan_fungsional_id && $rj->jabatan_fungsional_id == $p->jabatan_fungsional_id);
            }) ?: $p->riwayatJabatan->first();

            $tmt = $activeRiwayat?->mulai_jabatan;
            $skNomor = $activeRiwayat?->sk_nomor;
            $skTanggal = $activeRiwayat?->sk_tanggal;
            $fileSk = $activeRiwayat?->file_sk;

            $serdosFirst = $p->sertifikasiDosen?->first();

            return [
                'id' => $p->id,
                'nama_lengkap' => $p->nama_lengkap,
                'nama_gelar' => $p->nama_gelar,
                'nip' => $p->nip,
                'nidn' => $p->nidn,
                'jenis_pegawai' => $p->jenis_pegawai,
                'status_kepegawaian' => $p->status_kepegawaian,
                'status_aktif' => $p->status,
                'is_active' => $p->is_active,
                'unit_kerja' => $p->unitKerja ? [
                    'id' => $p->unitKerja->id,
                    'nama' => $p->unitKerja->nama,
                    'kode' => $p->unitKerja->kode,
                    'tipe' => $p->unitKerja->tipe,
                ] : null,
                'jabatan_fungsional' => $p->jabatanFungsional ? [
                    'id' => $p->jabatanFungsional->id,
                    'nama' => $p->jabatanFungsional->nama,
                    'angka_kredit' => $p->jabatanFungsional->angka_kredit_min,
                ] : null,
                'jad_aktif' => [
                    'tmt' => $tmt ? Carbon::parse($tmt)->format('Y-m-d') : null,
                    'sk_nomor' => $skNomor,
                    'sk_tanggal' => $skTanggal ? Carbon::parse($skTanggal)->format('Y-m-d') : null,
                    'file_sk_url' => $fileSk ? $this->files->url($fileSk) : null,
                ],
                'is_serdos' => !empty($serdosFirst),
                'nomor_serdos' => $serdosFirst?->nomor_registrasi ?? $serdosFirst?->nomor_peserta,
            ];
        });

        return $paginator;
    }

    /**
     * Mengekspor data analitik pegawai ke file Excel (.xlsx).
     */
    public function exportExcel(array $filters): StreamedResponse
    {
        $query = $this->buildQuery($filters);
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'nama') {
            $query->orderBy('nama_lengkap', $sortOrder);
        } elseif ($sortBy === 'nip') {
            $query->orderBy('nip', $sortOrder);
        } else {
            $query->orderBy('created_at', $sortOrder);
        }

        $pegawais = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Analitik Pegawai');

        // 1. Header Informasi Dokumen
        $sheet->setCellValue('A1', 'SISTEM INFORMASI MANAJEMEN KEPEGAWAIAN (SIMPEG)');
        $sheet->setCellValue('A2', 'REKAPITULASI DATA ANALITIK PEGAWAI & JABATAN AKADEMIK (JAD)');

        $periodeText = 'Periode TMT: Semua Tanggal';
        if (!empty($filters['tmt_start']) && !empty($filters['tmt_end'])) {
            $periodeText = "Periode TMT: {$filters['tmt_start']} s/d {$filters['tmt_end']}";
        } elseif (!empty($filters['tmt_start'])) {
            $periodeText = "Periode TMT: Mulai {$filters['tmt_start']}";
        } elseif (!empty($filters['tmt_end'])) {
            $periodeText = "Periode TMT: Hingga {$filters['tmt_end']}";
        }
        $sheet->setCellValue('A3', $periodeText);
        $sheet->setCellValue('A4', 'Tanggal Unduh: ' . Carbon::now()->translatedFormat('d F Y H:i:s') . ' WIB | Total: ' . $pegawais->count() . ' Pegawai');

        $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A3:A4')->getFont()->setSize(10)->getColor()->setRGB('4B5563');

        // 2. Baris Header Tabel (Baris 6)
        $headers = [
            'A6' => 'No',
            'B6' => 'NIP',
            'C6' => 'NIDN',
            'D6' => 'Nama Lengkap & Gelar',
            'E6' => 'Jenis Pegawai',
            'F6' => 'Unit Kerja / Program Studi',
            'G6' => 'Status Kepegawaian',
            'H6' => 'Jabatan Akademik (JAD)',
            'I6' => 'Angka Kredit (KUM)',
            'J6' => 'Nomor SK JAD',
            'K6' => 'Tanggal TMT JAD',
            'L6' => 'Tanggal SK JAD',
            'M6' => 'Status Serdos',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        // Styling Header Kolom (Warna Indigo SIMPEG)
        $sheet->getStyle('A6:M6')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'], // Primary SIMPEG
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(6)->setRowHeight(28);

        // 3. Mengisi Data Baris
        $row = 7;
        $no = 1;

        foreach ($pegawais as $p) {
            $activeRiwayat = $p->riwayatJabatan->first(function ($rj) use ($p) {
                return $rj->is_active || ($p->jabatan_fungsional_id && $rj->jabatan_fungsional_id == $p->jabatan_fungsional_id);
            }) ?: $p->riwayatJabatan->first();

            $tmt = $activeRiwayat?->mulai_jabatan ? Carbon::parse($activeRiwayat->mulai_jabatan)->format('d/m/Y') : '-';
            $skNomor = $activeRiwayat?->sk_nomor ?: '-';
            $skTanggal = $activeRiwayat?->sk_tanggal ? Carbon::parse($activeRiwayat->sk_tanggal)->format('d/m/Y') : '-';
            $isSerdos = ($p->sertifikasiDosen && $p->sertifikasiDosen->isNotEmpty()) ? 'Sudah Serdos' : 'Belum';

            $sheet->setCellValue("A{$row}", $no++);
            $sheet->setCellValueExplicit("B{$row}", $p->nip ?: '-', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$row}", $p->nidn ?: '-', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("D{$row}", $p->nama_gelar ?: $p->nama_lengkap);
            $sheet->setCellValue("E{$row}", ucfirst($p->jenis_pegawai ?: '-'));
            $sheet->setCellValue("F{$row}", $p->unitKerja?->nama ?: '-');
            $sheet->setCellValue("G{$row}", ucfirst($p->status_kepegawaian ?: '-'));
            $sheet->setCellValue("H{$row}", $p->jabatanFungsional?->nama ?: 'Belum Ada JAD');
            $sheet->setCellValue("I{$row}", $p->jabatanFungsional?->angka_kredit_min ?: 0);
            $sheet->setCellValueExplicit("J{$row}", $skNomor, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("K{$row}", $tmt);
            $sheet->setCellValue("L{$row}", $skTanggal);
            $sheet->setCellValue("M{$row}", $isSerdos);

            // Zebra striping untuk baris genap
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:M{$row}")->getFill()->applyFromArray([
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F8FAFC'],
                ]);
            }

            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$row}:C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("K{$row}:M{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row++;
        }

        $lastRow = max(7, $row - 1);

        // Border tipis ke seluruh tabel
        $sheet->getStyle("A6:M{$lastRow}")->getBorders()->getAllBorders()->applyFromArray([
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CBD5E1'],
        ]);

        // Auto size kolom
        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'Analitik_Pegawai_' . Carbon::now()->format('Ymd_His') . '.xlsx';

        $callback = function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }
}
