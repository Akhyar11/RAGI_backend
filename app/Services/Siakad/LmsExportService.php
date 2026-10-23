<?php

namespace App\Services\Siakad;

use App\Models\Lms\IzinAbsensi;
use App\Models\Lms\PengumpulanTugas;
use App\Models\Lms\Quiz;
use App\Models\Lms\QuizAttempt;
use App\Models\Lms\Tugas;
use App\Models\Siakad\AbsensiMahasiswa;
use App\Models\Siakad\Kelas;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\Pertemuan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service terpusat untuk export laporan LMS (CSV & XLSX).
 *
 * Semua output CSV memakai **BOM UTF-8** + line ending `\r\n` agar Excel di
 * Windows membaca karakter Indonesia (mis. nama mahasiswa berkarakter
 *UTF-8/emoji) dengan benar. XLSX tidak memerlukan BOM.
 *
 * Service ini hanya menyiapkan data (header + baris) dan menulis response
 * download. Otorisasi & scoping akses kelas tetap menjadi tanggung jawab
 * controller/policy.
 */
class LmsExportService
{
    /** Format export yang didukung. */
    public const FORMAT_CSV = 'csv';
    public const FORMAT_XLSX = 'xlsx';

    /**
     * Daftar format yang didukung.
     *
     * @var array<int, string>
     */
    public const FORMATS = [self::FORMAT_CSV, self::FORMAT_XLSX];

    /**
     * Label status kehadiran absensi (closed-set domain, bukan entitas master).
     *
     * @var array<string, string>
     */
    private const STATUS_ABSENSI = [
        'hadir'  => 'Hadir',
        'sakit'  => 'Sakit',
        'izin'   => 'Izin',
        'alfa'   => 'Alfa',
        'hilang' => 'Hilang',
    ];

    /**
     * Label status proses pengajuan izin (closed-set domain).
     *
     * @var array<string, string>
     */
    private const STATUS_IZIN = [
        'pending'  => 'Menunggu',
        'disetujui' => 'Disetujui',
        'ditolak'  => 'Ditolak',
        'diproses' => 'Diproses',
    ];

    public function __construct(protected LmsService $lmsService) {}

    /* ---------------------------------------------------------------------
     |  Writer (CSV / XLSX)
     | ------------------------------------------------------------------ */

    /**
     * Buat response download sesuai format yang diminta.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function download(
        string $filenameBase,
        array $headers,
        array $rows,
        string $format = self::FORMAT_CSV
    ): StreamedResponse {
        $format = in_array($format, self::FORMATS, true) ? $format : self::FORMAT_CSV;

        return $format === self::FORMAT_XLSX
            ? $this->xlsxResponse($filenameBase, $headers, $rows)
            : $this->csvResponse($filenameBase, $headers, $rows);
    }

    /**
     * Response CSV streaming dengan BOM UTF-8.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function csvResponse(string $filenameBase, array $headers, array $rows): StreamedResponse
    {
        $filename = $this->sanitizeFilename($filenameBase) . '.csv';

        $callback = function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            // BOM UTF-8 agar Excel di Windows membaca karakter Indonesia dengan benar.
            fwrite($handle, "\xEF\xBB\xBF");

            // `fputcsv` memakai `\n`; ditulis eksplisit `\r\n` agar sesuai RFC 4180.
            fputcsv($handle, $headers, ',', '"', '\\', "\r\n");

            foreach ($rows as $row) {
                $cells = array_map(
                    fn (mixed $value) => (string) $this->normalizeCell($value),
                    $row
                );

                fputcsv($handle, $cells, ',', '"', '\\', "\r\n");
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=' . $filename,
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ]);
    }

    /**
     * Response XLSX streaming.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function xlsxResponse(string $filenameBase, array $headers, array $rows): StreamedResponse
    {
        $filename = $this->sanitizeFilename($filenameBase) . '.xlsx';

        $callback = function () use ($headers, $rows): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Laporan');

            $column = 1;
            foreach ($headers as $header) {
                $sheet->setCellValueExplicit(
                    \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column) . '1',
                    (string) $header,
                    DataType::TYPE_STRING
                );
                $column++;
            }

            $rowNumber = 2;
            foreach ($rows as $row) {
                $column = 1;
                foreach ($row as $value) {
                    $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column) . $rowNumber;
                    $normalized = $this->normalizeCell($value);

                    // Angka tetap ditulis sebagai angka (bukan teks) agar bisa
                    // dihitung & disortir di Excel.
                    $dataType = is_int($normalized) || is_float($normalized)
                        ? DataType::TYPE_NUMERIC
                        : DataType::TYPE_STRING;

                    $sheet->setCellValueExplicit($cell, $normalized, $dataType);
                    $column++;
                }
                $rowNumber++;
            }

            if ($headers !== []) {
                $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')
                    ->getFont()->setBold(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');

            $spreadsheet->disconnectWorksheets();
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename=' . $filename,
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ]);
    }

    /* ---------------------------------------------------------------------
     |  Data Builder — Rekap Nilai Kelas
     | ------------------------------------------------------------------ */

    /**
     * Rekap nilai (tugas + quiz) per mahasiswa pada satu kelas.
     *
     * Struktur kolom bersifat dinamis: satu kolom per tugas dan per quiz,
     * mengikuti data aktual kelas — bukan daftar statis.
     *
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    public function rekapNilaiKelas(int $kelasId): array
    {
        $kelas = Kelas::with(['mataKuliah', 'programStudi', 'pertemuans'])->findOrFail($kelasId);

        $tugas = Tugas::with('pertemuan')
            ->whereIn('pertemuan_id', $kelas->pertemuans->pluck('id'))
            ->orderBy('pertemuan_id')
            ->orderBy('id')
            ->get();

        $quiz = Quiz::where('kelas_id', $kelasId)
            ->where('tipe', Quiz::TIPE_KUIS)
            ->orderBy('id')
            ->get();

        $mahasiswa = $this->mahasiswaKelas($kelasId);

        // Nilai tugas per mahasiswa.
        $nilaiTugas = [];
        if ($tugas->isNotEmpty()) {
            $pengumpulan = PengumpulanTugas::whereIn('tugas_id', $tugas->pluck('id'))->get();
            foreach ($pengumpulan as $p) {
                $nilaiTugas[$p->mahasiswa_id][$p->tugas_id] = $p->nilai;
            }
        }

        // Nilai quiz (attempt terakhir) per mahasiswa.
        $nilaiQuiz = [];
        if ($quiz->isNotEmpty()) {
            $attempts = QuizAttempt::whereIn('quiz_id', $quiz->pluck('id'))
                ->whereNotNull('nilai_akhir')
                ->orderBy('attempt_ke')
                ->get();
            foreach ($attempts as $a) {
                $nilaiQuiz[$a->mahasiswa_id][$a->quiz_id] = $a->nilai_akhir;
            }
        }

        $headers = ['No', 'NIM', 'Nama Mahasiswa', 'Program Studi'];
        foreach ($tugas as $t) {
            $headers[] = 'Tugas: ' . $t->judul;
        }
        foreach ($quiz as $q) {
            $headers[] = 'Quiz: ' . $q->judul;
        }
        $headers[] = 'Rata-rata';
        $headers[] = 'Tugas Dinilai';
        $headers[] = 'Quiz Dinilai';

        $rows = [];
        $no = 0;

        foreach ($mahasiswa as $m) {
            $no++;
            $row = [
                $no,
                $m->nim,
                $m->nama_lengkap,
                $m->programStudi?->nama ?? '-',
            ];

            $terilai = [];

            foreach ($tugas as $t) {
                $nilai = $nilaiTugas[$m->id][$t->id] ?? null;
                $row[] = $nilai === null ? '-' : (float) $nilai;
                if ($nilai !== null) {
                    $terilai[] = (float) $nilai;
                }
            }

            foreach ($quiz as $q) {
                $nilai = $nilaiQuiz[$m->id][$q->id] ?? null;
                $row[] = $nilai === null ? '-' : (float) $nilai;
                if ($nilai !== null) {
                    $terilai[] = (float) $nilai;
                }
            }

            $row[] = $terilai === [] ? '-' : round(array_sum($terilai) / count($terilai), 2);
            $row[] = count($nilaiTugas[$m->id] ?? []);
            $row[] = count($nilaiQuiz[$m->id] ?? []);

            $rows[] = $row;
        }

        return [
            'filename' => 'Rekap_Nilai_' . $this->kelasSlug($kelas) . '_' . now()->format('Ymd_His'),
            'headers'  => $headers,
            'rows'     => $rows,
        ];
    }

    /* ---------------------------------------------------------------------
     |  Data Builder — Rekap Presensi
     | ------------------------------------------------------------------ */

    /**
     * Rekap absensi per pertemuan × mahasiswa (format panjang).
     *
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    public function rekapPresensiKelas(int $kelasId): array
    {
        $kelas = Kelas::with(['mataKuliah', 'programStudi'])->findOrFail($kelasId);

        $pertemuan = Pertemuan::where('kelas_id', $kelasId)
            ->orderBy('pertemuan_ke')
            ->orderBy('id')
            ->get();

        $mahasiswa = $this->mahasiswaKelas($kelasId);

        $absensi = [];
        if ($pertemuan->isNotEmpty()) {
            $list = AbsensiMahasiswa::whereIn('pertemuan_id', $pertemuan->pluck('id'))->get();
            foreach ($list as $a) {
                $absensi[$a->pertemuan_id][$a->mahasiswa_id] = $a;
            }
        }

        $headers = [
            'Kode Kelas',
            'Nama Kelas',
            'Mata Kuliah',
            'Pertemuan Ke',
            'Tanggal',
            'Topik Pertemuan',
            'NIM',
            'Nama Mahasiswa',
            'Status Kehadiran',
            'Catatan',
        ];

        $rows = [];

        foreach ($pertemuan as $p) {
            foreach ($mahasiswa as $m) {
                $a = $absensi[$p->id][$m->id] ?? null;

                $rows[] = [
                    $kelas->kode_kelas ?? '-',
                    $kelas->nama_kelas ?? '-',
                    $kelas->mataKuliah?->nama ?? '-',
                    $p->pertemuan_ke ?? '-',
                    $p->tanggal ? Carbon::parse($p->tanggal)->toDateString() : '-',
                    $p->materi ?? '-',
                    $m->nim,
                    $m->nama_lengkap,
                    $a ? (self::STATUS_ABSENSI[$a->status] ?? $a->status) : 'Belum Absen',
                    $a?->catatan ?? '-',
                ];
            }
        }

        return [
            'filename' => 'Rekap_Absensi_' . $this->kelasSlug($kelas) . '_' . now()->format('Ymd_His'),
            'headers'  => $headers,
            'rows'     => $rows,
        ];
    }

    /* ---------------------------------------------------------------------
     |  Data Builder — Rekap Izin Absensi
     | ------------------------------------------------------------------ */

    /**
     * Rekap pengajuan izin absensi pada satu kelas.
     *
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    public function rekapIzinKelas(int $kelasId): array
    {
        $kelas = Kelas::with(['mataKuliah', 'programStudi'])->findOrFail($kelasId);

        $pertemuanIds = Pertemuan::where('kelas_id', $kelasId)->pluck('id');

        $izin = IzinAbsensi::with(['mahasiswa', 'pertemuan', 'tipeIzin'])
            ->whereIn('pertemuan_id', $pertemuanIds)
            ->orderBy('pertemuan_id')
            ->orderBy('id')
            ->get();

        $headers = [
            'Kode Kelas',
            'Nama Kelas',
            'Pertemuan Ke',
            'Tanggal',
            'NIM',
            'Nama Mahasiswa',
            'Tipe Izin',
            'Alasan',
            'Status',
            'Catatan Dosen',
            'Diajukan Pada',
            'Diproses Pada',
        ];

        $rows = [];

        foreach ($izin as $i) {
            $rows[] = [
                $kelas->kode_kelas ?? '-',
                $kelas->nama_kelas ?? '-',
                $i->pertemuan?->pertemuan_ke ?? '-',
                $i->pertemuan?->tanggal ? Carbon::parse($i->pertemuan->tanggal)->toDateString() : '-',
                $i->mahasiswa?->nim ?? '-',
                $i->mahasiswa?->nama_lengkap ?? '-',
                $i->tipeIzin?->nama ?? ($i->tipe_izin ?? '-'),
                $i->alasan ?? '-',
                self::STATUS_IZIN[$i->status] ?? $i->status,
                $i->catatan_dosen ?? '-',
                $i->created_at ? Carbon::parse($i->created_at)->format('Y-m-d H:i') : '-',
                $i->diproses_at ? Carbon::parse($i->diproses_at)->format('Y-m-d H:i') : '-',
            ];
        }

        return [
            'filename' => 'Rekap_Izin_Absensi_' . $this->kelasSlug($kelas) . '_' . now()->format('Ymd_His'),
            'headers'  => $headers,
            'rows'     => $rows,
        ];
    }

    /* ---------------------------------------------------------------------
     |  Data Builder — Hasil Quiz / Tryout
     | ------------------------------------------------------------------ */

    /**
     * Hasil pengerjaan quiz per mahasiswa.
     *
     * Untuk tryout, peserta yang belum pernah mengerjakan tetap ditampilkan
     * dengan nilai kosong agar rekap tetap lengkap.
     *
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    public function hasilQuiz(int $quizId): array
    {
        $quiz = Quiz::with(['kelas.mataKuliah', 'kelas.programStudi'])->findOrFail($quizId);

        $attempts = QuizAttempt::with('mahasiswa')
            ->where('quiz_id', $quizId)
            ->orderBy('mahasiswa_id')
            ->orderBy('attempt_ke')
            ->get();

        $headers = [
            'Judul Quiz',
            'Kode Kelas',
            'Nama Kelas',
            'Mata Kuliah',
            'NIM',
            'Nama Mahasiswa',
            'Percobaan Ke',
            'Status',
            'Nilai Akhir',
            'Mulai',
            'Submit',
            'Durasi (menit)',
            'Perlu Penilaian Manual',
        ];

        $rows = [];

        foreach ($attempts as $a) {
            $rows[] = [
                $quiz->judul,
                $quiz->kelas?->kode_kelas ?? '-',
                $quiz->kelas?->nama_kelas ?? '-',
                $quiz->kelas?->mataKuliah?->nama_mk ?? '-',
                $a->mahasiswa?->nim ?? '-',
                $a->mahasiswa?->nama_lengkap ?? '-',
                $a->attempt_ke,
                $a->status,
                $a->nilai_akhir === null ? '-' : (float) $a->nilai_akhir,
                $a->dimulai_at ? Carbon::parse($a->dimulai_at)->format('Y-m-d H:i') : '-',
                $a->disubmit_at ? Carbon::parse($a->disubmit_at)->format('Y-m-d H:i') : '-',
                $this->durasiMenit($a),
                $a->butuh_penilaian_manual ? 'Ya' : 'Tidak',
            ];
        }

        // Tryout: peserta yang belum mengerjakan tetap ditampilkan.
        if ($quiz->tipe === Quiz::TIPE_TRYOUT) {
            $sudahAda = $attempts->pluck('mahasiswa_id')->unique()->all();

            $peserta = $quiz->tryoutPeserta()->with('mahasiswa')->get();

            foreach ($peserta as $p) {
                if (in_array($p->mahasiswa_id, $sudahAda, true)) {
                    continue;
                }

                $rows[] = [
                    $quiz->judul,
                    $quiz->kelas?->kode_kelas ?? '-',
                    $quiz->kelas?->nama_kelas ?? '-',
                    $quiz->kelas?->mataKuliah?->nama_mk ?? '-',
                    $p->mahasiswa?->nim ?? '-',
                    $p->mahasiswa?->nama_lengkap ?? '-',
                    0,
                    'belum_mengerjakan',
                    '-',
                    '-',
                    '-',
                    '-',
                    'Tidak',
                ];
            }
        }

        return [
            'filename' => 'Hasil_Quiz_' . $this->kelasSlug($quiz->kelas) . '_' . now()->format('Ymd_His'),
            'headers'  => $headers,
            'rows'     => $rows,
        ];
    }

    /* ---------------------------------------------------------------------
     |  Data Builder — Pengumpulan Tugas
     | ------------------------------------------------------------------ */

    /**
     * Status & nilai pengumpulan tugas per mahasiswa.
     *
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    public function pengumpulanTugas(int $tugasId): array
    {
        $tugas = Tugas::with(['pertemuan.kelas.mataKuliah', 'pertemuan.kelas.programStudi'])
            ->findOrFail($tugasId);

        $kelasId = $tugas->pertemuan?->kelas_id;

        $mahasiswa = $kelasId ? $this->mahasiswaKelas($kelasId) : collect();

        $pengumpulan = PengumpulanTugas::with('mahasiswa')
            ->where('tugas_id', $tugasId)
            ->get()
            ->keyBy('mahasiswa_id');

        $headers = [
            'Judul Tugas',
            'NIM',
            'Nama Mahasiswa',
            'Status Pengumpulan',
            'Catatan Mahasiswa',
            'Nama Berkas',
            'Terlambat',
            'Nilai',
            'Feedback Dosen',
            'Waktu Kumpul',
            'Waktu Dinilai',
        ];

        $rows = [];
        $no = 0;

        foreach ($mahasiswa as $m) {
            $no++;
            $p = $pengumpulan[$m->id] ?? null;

            $rows[] = [
                $tugas->judul,
                $m->nim,
                $m->nama_lengkap,
                $p ? 'Terkumpul' : 'Belum Mengumpulkan',
                $p?->catatan_mahasiswa ?? '-',
                $p?->nama_file_asli ?? '-',
                $p ? ($p->is_late ? 'Ya' : 'Tidak') : '-',
                $p?->nilai === null ? '-' : (float) $p->nilai,
                $p?->feedback_dosen ?? '-',
                $p?->created_at ? Carbon::parse($p->created_at)->format('Y-m-d H:i') : '-',
                $p?->dinilai_at ? Carbon::parse($p->dinilai_at)->format('Y-m-d H:i') : '-',
            ];
        }

        return [
            'filename' => 'Pengumpulan_Tugas_' . $this->kelasSlug($tugas->pertemuan?->kelas) . '_' . now()->format('Ymd_His'),
            'headers'  => $headers,
            'rows'     => $rows,
        ];
    }

    /* ---------------------------------------------------------------------
     |  Helper
     | ------------------------------------------------------------------ */

    /**
     * Pastikan user berhak mengakses kelas tersebut.
     *
     * @throws ValidationException bila user tidak punya akses ke kelas.
     */
    public function assertKelasAccess(int $kelasId, int $userId): void
    {
        $kelasIds = $this->lmsService->resolveAccessibleKelasIds($userId);

        // null = user privileged (superadmin/admin/kaprodi/wakil_prodi) → semua kelas.
        if ($kelasIds === null) {
            return;
        }

        if (!in_array((int) $kelasId, array_map('intval', $kelasIds), true)) {
            throw ValidationException::withMessages([
                'kelas' => ['Anda tidak berhak mengakses data kelas ini.'],
            ]);
        }
    }

    /**
     * Pastikan user berhak mengakses kelas induk dari sebuah tugas.
     *
     * @throws ValidationException bila user tidak punya akses ke kelas tugas.
     */
    public function assertTugasAccess(int $tugasId, int $userId): void
    {
        $kelasId = Tugas::with('pertemuan')
            ->findOrFail($tugasId)
            ->pertemuan?->kelas_id;

        if (!$kelasId) {
            return;
        }

        $this->assertKelasAccess((int) $kelasId, $userId);
    }

    /**
     * Daftar mahasiswa yang mengambil kelas (via KRS Detail) beserta prodi.
     *
     * @return Collection<int, Mahasiswa>
     */
    protected function mahasiswaKelas(int $kelasId): Collection
    {
        $krsDetails = KrsDetail::with('krs.mahasiswa.programStudi')
            ->where('kelas_id', $kelasId)
            ->get();

        return $krsDetails
            ->map(fn (KrsDetail $kd) => $kd->krs?->mahasiswa)
            ->filter()
            ->unique('id')
            ->sortBy('nim')
            ->values();
    }

    /**
     * Durasi pengerjaan attempt dalam menit (menyisakan attempt yang belum submit).
     */
    protected function durasiMenit(QuizAttempt $attempt): string
    {
        if (!$attempt->dimulai_at) {
            return '-';
        }

        $selesai = $attempt->disubmit_at ? Carbon::parse($attempt->disubmit_at) : Carbon::now();

        return (string) round(Carbon::parse($attempt->dimulai_at)->diffInSeconds($selesai) / 60, 1);
    }

    /**
     * Normalisasi nilai sel agar aman ditulis ke CSV/XLSX.
     *
     * Angka tetap dipertahankan sebagai tipe numerik supaya pada XLSX bisa
     * dihitung/diurutkan di Excel; pada CSV dikonversi ke string.
     */
    protected function normalizeCell(mixed $value): mixed
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d H:i');
        }

        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        return $value;
    }

    /**
     * Sanitasi nama berkas agar aman dipakai pada header Content-Disposition.
     */
    protected function sanitizeFilename(string $filename): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $filename) ?? 'lms_export';
        $clean = trim($clean, '_');

        return $clean === '' ? 'lms_export' : $clean;
    }

    /**
     * Slug kelas untuk menyusun nama berkas (kode kelas, fallback nama kelas).
     */
    protected function kelasSlug(?Kelas $kelas): string
    {
        if (!$kelas) {
            return 'Kelas';
        }

        $slug = $kelas->kode_kelas ?: $kelas->nama_kelas ?: ('Kelas_' . $kelas->id);
        $slug = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $slug) ?? '';

        return trim($slug, '_') === '' ? ('Kelas_' . $kelas->id) : trim($slug, '_');
    }
}
