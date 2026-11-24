<?php

namespace App\Services\Siakad;

use App\Models\Siakad\Kelas;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Laporan cetak LMS dalam bentuk PDF (Dompdf).
 *
 * Terpisah dari `LmsExportService` yang hanya menangani CSV/XLSX: PDF memakai
 * route cetak tersendiri, di-inline ke browser (`Content-Disposition: inline`)
 * dan tidak dimasukkan ke `LmsExportService::FORMATS`.
 *
 * Otorisasi & scope diterapkan di sini lewat `resolveAccessibleKelasIds()`:
 * null = semua kelas (admin/kaprodi), array kosong = tanpa akses.
 */
class LmsPdfReportService
{
    private const STATUS_HADIR = 'hadir';

    public function __construct(protected LmsService $lmsService) {}

    /**
     * Cetak rekap kehadiran satu kelas (ringkasan mahasiswa + ringkasan pertemuan).
     *
     * @return array{pdf: string, filename: string, jumlah_baris: int}
     */
    public function cetakKehadiranKelas(int $kelasId): array
    {
        $kelas = Kelas::with(['mataKuliah', 'programStudi', 'tahunAkademik', 'pertemuans'])
            ->findOrFail($kelasId);

        $matrix = $this->lmsService->getRekapMatrix($kelasId);
        $rekapitulasi = $matrix['rekapitulasi'];
        $detail = $matrix['detail_pertemuan'] ?? [];

        $pertemuans = Collection::make($matrix['pertemuan_list'] ?? [])
            ->map(function (array $pertemuan) use ($detail): array {
                $statuses = Collection::make($detail)->pluck($pertemuan['pertemuan_ke']);

                return [
                    'pertemuan_ke' => $pertemuan['pertemuan_ke'],
                    'tanggal'      => $pertemuan['tanggal'],
                    'hadir'        => $statuses->filter(fn ($status) => $status === self::STATUS_HADIR)->count(),
                    'sakit'        => $statuses->filter(fn ($status) => $status === 'sakit')->count(),
                    'izin'         => $statuses->filter(fn ($status) => $status === 'izin')->count(),
                    'alfa'         => $statuses->filter(fn ($status) => $status === 'alfa')->count(),
                ];
            })
            ->values()
            ->all();

        $kodeKelas = (string) ($kelas->kode_kelas ?? $kelasId);

        return [
            'pdf' => $this->render('lms.pdf.kehadiran-kelas', [
                'judul'                => 'Rekapitulasi Kehadiran Kelas',
                'kelas'                => $kelas,
                'rekapitulasi'         => $rekapitulasi,
                'pertemuans'           => $pertemuans,
                'totalPertemuan'       => (int) $matrix['total_pertemuan'],
                'batasMinHadir'        => (float) $matrix['batas_min_hadir_persen'],
                'tanggalCetak'         => Carbon::now()->format('d-m-Y H:i'),
            ]),
            'filename'     => $this->filename('Kehadiran_Kelas_' . $kodeKelas),
            'jumlah_baris' => count($rekapitulasi),
        ];
    }

    /**
     * Cetak rekap kehadiran per program studi untuk satu tahun akademik.
     *
     * @return array{pdf: string, filename: string, jumlah_baris: int}
     */
    public function cetakRekapProdi(
        ?int $programStudiId,
        int $tahunAkademikId,
        int $userId
    ): array {
        $tahunAkademik = TahunAkademik::findOrFail($tahunAkademikId);
        $prodi = $programStudiId ? ProgramStudi::findOrFail($programStudiId) : null;

        $kelasQuery = Kelas::query()
            ->with(['mataKuliah', 'programStudi'])
            ->where('tahun_akademik_id', $tahunAkademikId)
            ->when($prodi, function ($query) use ($programStudiId): void {
                $query->where(function ($q) use ($programStudiId): void {
                    $q->where('program_studi_id', $programStudiId)
                        ->orWhereHas('programStudis', fn ($ps) => $ps->whereKey($programStudiId));
                });
            });

        $accessibleIds = $this->lmsService->resolveAccessibleKelasIds($userId);
        if ($accessibleIds === []) {
            $kelasList = collect();
        } elseif ($accessibleIds === null) {
            $kelasList = $kelasQuery->orderBy('kode_kelas')->get();
        } else {
            $kelasList = $kelasQuery->whereIn('id', $accessibleIds)->orderBy('kode_kelas')->get();
        }

        $grupProdi = [];
        $jumlahBaris = 0;

        foreach ($kelasList as $kelas) {
            $rekap = $this->lmsService->getRekapAbsensiKelas((int) $kelas->id);
            $baris = $rekap['rekapitulasi'];
            $jumlahBaris += count($baris);

            $prodiKelas = $kelas->programStudi;
            $prodiKunci = $prodiKelas ? (int) $prodiKelas->id : 0;

            if (! isset($grupProdi[$prodiKunci])) {
                $grupProdi[$prodiKunci] = [
                    'kode' => $prodiKelas?->kode_prodi ?? '-',
                    'nama' => $prodiKelas?->nama ?? 'Tanpa Program Studi',
                    'kelas' => [],
                ];
            }

            $persen = Collection::make($baris)->avg('persen_kehadiran') ?? 0;
            $grupProdi[$prodiKunci]['kelas'][] = [
                'kode_kelas'        => $kelas->kode_kelas,
                'nama_kelas'        => $kelas->nama_kelas,
                'mata_kuliah'       => $kelas->mataKuliah?->nama ?? '-',
                'total_pertemuan'   => (int) $rekap['total_pertemuan'],
                'total_mahasiswa'   => count($baris),
                'rata_persen'       => round((float) $persen, 1),
                'memenuhi'          => Collection::make($baris)->filter(fn ($item) => $item['memenuhi_syarat'])->count(),
                'tidak_memenuhi'    => Collection::make($baris)->reject(fn ($item) => $item['memenuhi_syarat'])->count(),
            ];
        }

        ksort($grupProdi);

        return [
            'pdf' => $this->render('lms.pdf.rekap-prodi', [
                'judul'         => 'Rekap Kehadiran per Program Studi',
                'prodi'         => $prodi,
                'tahunAkademik' => $tahunAkademik,
                'grupProdi'     => array_values($grupProdi),
                'jumlahKelas'   => $kelasList->count(),
                'jumlahBaris'   => $jumlahBaris,
                'tanggalCetak'  => Carbon::now()->format('d-m-Y H:i'),
            ]),
            'filename' => $this->filename(
                'Rekap_Kehadiran_Prodi_' . ($prodi?->kode_prodi ?? 'Semua')
            ),
            'jumlah_baris' => $jumlahBaris,
        ];
    }

    /**
     * Render Blade ke PDF A4 portrait tanpa akses resource eksternal.
     */
    protected function render(string $view, array $data): string
    {
        $html = view($view, $data)->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Nama berkas aman untuk header Content-Disposition.
     */
    protected function filename(string $base): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $base) ?: 'Laporan';

        return $safe . '_' . now()->format('Ymd_His') . '.pdf';
    }
}
