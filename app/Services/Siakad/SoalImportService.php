<?php

namespace App\Services\Siakad;

use App\Models\Siakad\BankSoal;
use App\Models\Siakad\BankSoalOpsi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Parser + generator template import bank soal (XLSX/CSV).
 *
 * Format kolom (baris 1 = header, case-insensitive, spasi/_/-/. diabaikan):
 *   tipe | pertanyaan | opsi_a..opsi_e | kunci | poin | tingkat_kesulitan | pembahasan
 *
 * - tipe: pg/mc/pilihan_ganda | isian/isian_singkat | uraian/essay
 * - kunci (pilihan_ganda): huruf A-E | kunci (isian/uraian): teks jawaban benar
 * - poin: angka >= 1 (default 10)
 *
 * Referensi: Project/lms `app/Services/SoalImportService.php` (disesuaikan ke
 * skema RAG: `siakad_bank_soal` + `siakad_bank_soal_opsi`).
 */
class SoalImportService
{
    /** Batas baris data per file import. */
    /**
     * Batas keras keamanan (memory). Nilai efektif per import dapat lebih kecil
     * melalui pengaturan modul `lms_max_soal_import`.
     */
    public const MAX_ROWS = 1000;

    /** Bobot default bila kolom `poin` kosong. */
    public const DEFAULT_BOBOT = 10.0;

    /** Huruf opsi jawaban yang didukung (A-E). */
    public const OPSI_HURUF = ['A', 'B', 'C', 'D', 'E'];

    /**
     * Alias header kolom yang dikenali (sudah dinormalisasi).
     *
     * @var array<string, array<int, string>>
     */
    private const HEADER_ALIAS = [
        'tipe' => ['tipe', 'tipe_soal', 'jenis', 'jenis_soal', 'type'],
        'pertanyaan' => ['pertanyaan', 'soal', 'question'],
        'kunci' => ['kunci', 'kunci_jawaban', 'jawaban_benar', 'correct_answer', 'answer'],
        'poin' => ['poin', 'bobot', 'point', 'score'],
        'tingkat' => ['tingkat_kesulitan', 'kesulitan', 'tingkat', 'difficulty'],
        'pembahasan' => ['pembahasan', 'explanation', 'alasan'],
    ];

    /**
     * Alias kolom opsi jawaban (huruf A-E).
     *
     * @var array<string, array<int, string>>
     */
    private const HEADER_ALIAS_OPSI = [
        'A' => ['opsi_a', 'jawaban_a', 'pilihan_a', 'a'],
        'B' => ['opsi_b', 'jawaban_b', 'pilihan_b', 'b'],
        'C' => ['opsi_c', 'jawaban_c', 'pilihan_c', 'c'],
        'D' => ['opsi_d', 'jawaban_d', 'pilihan_d', 'd'],
        'E' => ['opsi_e', 'jawaban_e', 'pilihan_e', 'e'],
    ];

    /**
     * Alias nilai `tipe` di file → tipe soal canonical RAG.
     *
     * @var array<string, string>
     */
    private const TIPE_ALIAS = [
        'pg' => BankSoal::TIPE_PILIHAN_GANDA,
        'mc' => BankSoal::TIPE_PILIHAN_GANDA,
        'multiple_choice' => BankSoal::TIPE_PILIHAN_GANDA,
        'pilihanganda' => BankSoal::TIPE_PILIHAN_GANDA,
        'pilihan_ganda' => BankSoal::TIPE_PILIHAN_GANDA,
        'abcd' => BankSoal::TIPE_PILIHAN_GANDA,
        'isian' => BankSoal::TIPE_ISIAN_SINGKAT,
        'short' => BankSoal::TIPE_ISIAN_SINGKAT,
        'short_answer' => BankSoal::TIPE_ISIAN_SINGKAT,
        'isian_singkat' => BankSoal::TIPE_ISIAN_SINGKAT,
        'uraian' => BankSoal::TIPE_URAIAN,
        'essay' => BankSoal::TIPE_URAIAN,
        'esai' => BankSoal::TIPE_URAIAN,
        'essai' => BankSoal::TIPE_URAIAN,
    ];

    /**
     * Parse file import menjadi baris soal + daftar error validasi per baris.
     *
     * @return array{rows: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    public static function parseFile(string $path, int $maxRows = self::MAX_ROWS): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $raw = $sheet->toArray(null, true, true, false);

        if (empty($raw)) {
            return ['rows' => [], 'errors' => ['File kosong.']];
        }

        $index = self::buildHeaderIndex($raw[0]);

        $iTanya = self::col($index, self::HEADER_ALIAS['pertanyaan']);
        if ($iTanya === null) {
            return ['rows' => [], 'errors' => ['Kolom "pertanyaan" tidak ditemukan. Unduh template terbaru.']];
        }

        $iTipe = self::col($index, self::HEADER_ALIAS['tipe']);
        $iKunci = self::col($index, self::HEADER_ALIAS['kunci']);
        $iPoin = self::col($index, self::HEADER_ALIAS['poin']);
        $iTingkat = self::col($index, self::HEADER_ALIAS['tingkat']);
        $iPembahasan = self::col($index, self::HEADER_ALIAS['pembahasan']);

        $iOpsi = [];
        foreach (self::HEADER_ALIAS_OPSI as $huruf => $aliases) {
            $iOpsi[$huruf] = self::col($index, $aliases);
        }

        $errors = [];
        $rows = [];

        $lineCount = count($raw) - 1;
        if ($lineCount > $maxRows) {
            return ['rows' => [], 'errors' => ["Jumlah baris ({$lineCount}) melebihi batas {$maxRows}. Pecah menjadi beberapa file."]];
        }

        foreach (array_slice($raw, 1) as $n => $line) {
            $excelRow = $n + 2;
            $pertanyaan = trim((string) ($line[$iTanya] ?? ''));

            // Lewati baris yang kosong total.
            $allEmpty = $pertanyaan === '';
            foreach ($iOpsi as $c) {
                if ($c !== null && trim((string) ($line[$c] ?? '')) !== '') {
                    $allEmpty = false;
                }
            }
            if ($allEmpty) {
                continue;
            }
            if ($pertanyaan === '') {
                $errors[] = "Baris {$excelRow}: pertanyaan wajib diisi.";
                continue;
            }

            $tipeRaw = strtolower(trim((string) ($iTipe !== null ? ($line[$iTipe] ?? '') : '')));
            if ($tipeRaw === '') {
                $tipe = BankSoal::TIPE_PILIHAN_GANDA;
            } else {
                $tipe = self::TIPE_ALIAS[$tipeRaw] ?? null;
                if ($tipe === null) {
                    $errors[] = "Baris {$excelRow}: tipe '{$tipeRaw}' tidak dikenal (gunakan pg/isian/uraian).";
                    continue;
                }
            }

            $bobot = self::toFloat($iPoin !== null ? ($line[$iPoin] ?? null) : null, self::DEFAULT_BOBOT);
            if ($bobot < 1) {
                $bobot = self::DEFAULT_BOBOT;
            }
            if ($bobot > 100) {
                $errors[] = "Baris {$excelRow}: poin maksimal 100.";
                continue;
            }

            $tingkat = null;
            $tingkatRaw = strtolower(trim((string) ($iTingkat !== null ? ($line[$iTingkat] ?? '') : '')));
            if ($tingkatRaw !== '') {
                if (! in_array($tingkatRaw, BankSoal::TINGKAT, true)) {
                    $errors[] = "Baris {$excelRow}: tingkat kesulitan '{$tingkatRaw}' tidak dikenal (gunakan mudah/sedang/sukar).";
                    continue;
                }
                $tingkat = $tingkatRaw;
            }

            $pembahasan = trim((string) ($iPembahasan !== null ? ($line[$iPembahasan] ?? '') : ''));
            $pembahasan = $pembahasan === '' ? null : $pembahasan;

            $kunci = trim((string) ($iKunci !== null ? ($line[$iKunci] ?? '') : ''));

            if ($tipe === BankSoal::TIPE_PILIHAN_GANDA) {
                $opsi = [];
                foreach (self::OPSI_HURUF as $k) {
                    $c = $iOpsi[$k];
                    $t = $c !== null ? trim((string) ($line[$c] ?? '')) : '';
                    if ($t !== '') {
                        $opsi[$k] = $t;
                    }
                }
                if (count($opsi) < 2) {
                    $errors[] = "Baris {$excelRow}: soal pilihan ganda minimal 2 opsi (isi opsi_a..opsi_d).";
                    continue;
                }

                $kunciHuruf = strtoupper($kunci);
                if ($kunciHuruf === '' || ! isset($opsi[$kunciHuruf])) {
                    $errors[] = "Baris {$excelRow}: kunci '{$kunci}' tidak cocok dengan opsi yang terisi (".implode(',', array_keys($opsi)).').';
                    continue;
                }

                $opsiRows = [];
                $urutan = 1;
                foreach ($opsi as $k => $teks) {
                    $opsiRows[] = [
                        'teks' => $teks,
                        'is_benar' => $k === $kunciHuruf,
                        'urutan' => $urutan++,
                    ];
                }

                $rows[] = [
                    'tipe_soal' => $tipe,
                    'pertanyaan' => $pertanyaan,
                    'bobot' => $bobot,
                    'kunci_jawaban' => null,
                    'pembahasan' => $pembahasan,
                    'tingkat_kesulitan' => $tingkat,
                    'opsi' => $opsiRows,
                ];

                continue;
            }

            // isian_singkat / uraian → kunci_jawaban berupa teks jawaban benar.
            if ($tipe === BankSoal::TIPE_ISIAN_SINGKAT && $kunci === '') {
                $errors[] = "Baris {$excelRow}: kunci jawaban isian wajib diisi.";
                continue;
            }

            $rows[] = [
                'tipe_soal' => $tipe,
                'pertanyaan' => $pertanyaan,
                'bobot' => $bobot,
                'kunci_jawaban' => $kunci === '' ? null : $kunci,
                'pembahasan' => $pembahasan,
                'tingkat_kesulitan' => $tingkat,
                'opsi' => [],
            ];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Simpan hasil parse ke `siakad_bank_soal` (+ opsi jawaban) dalam satu transaksi.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $context  rps_id, rps_mingguan_id, sub_cpmk_id, kategori_id, tingkat_kesulitan, dibuat_oleh
     * @return array{imported: int, ids: array<int, int>, errors: array<int, string>}
     */
    public function importRows(array $rows, array $context): array
    {
        $ids = [];
        $errors = [];

        DB::transaction(function () use ($rows, $context, &$ids, &$errors): void {
            foreach ($rows as $row) {
                try {
                    $attributes = [
                        'rps_id' => $context['rps_id'],
                        'rps_mingguan_id' => $context['rps_mingguan_id'] ?? null,
                        'sub_cpmk_id' => $context['sub_cpmk_id'] ?? null,
                        'kategori_id' => $context['kategori_id'] ?? null,
                        'tipe_soal' => $row['tipe_soal'],
                        'pertanyaan' => $row['pertanyaan'],
                        'bobot' => $row['bobot'],
                        'kunci_jawaban' => $row['kunci_jawaban'] ?? null,
                        'pembahasan' => $row['pembahasan'] ?? null,
                        'dibuat_oleh' => $context['dibuat_oleh'] ?? null,
                    ];

                    // Kolom `tingkat_kesulitan` NOT NULL di DB — biarkan default DB bila kosong.
                    $tingkat = $row['tingkat_kesulitan'] ?? $context['tingkat_kesulitan'] ?? null;
                    if ($tingkat !== null) {
                        $attributes['tingkat_kesulitan'] = $tingkat;
                    }

                    $soal = BankSoal::create($attributes);

                    foreach ($row['opsi'] ?? [] as $opsi) {
                        BankSoalOpsi::create([
                            'bank_soal_id' => $soal->id,
                            'teks' => $opsi['teks'],
                            'is_benar' => $opsi['is_benar'],
                            'urutan' => $opsi['urutan'],
                        ]);
                    }

                    $ids[] = $soal->id;
                } catch (\Throwable $e) {
                    Log::error('Gagal import baris bank soal: ' . $e->getMessage());
                    $errors[] = 'Gagal menyimpan soal: ' . $e->getMessage();
                }
            }
        });

        return ['imported' => count($ids), 'ids' => $ids, 'errors' => $errors];
    }

    /**
     * Susun spreadsheet template import (2 sheet: Soal + Panduan).
     */
    public static function buildTemplate(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Soal');

        $headers = [
            'tipe', 'pertanyaan', 'opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e',
            'kunci', 'poin', 'tingkat_kesulitan', 'pembahasan',
        ];
        $sheet->fromArray([$headers], null, 'A1');

        $contoh = [
            ['pg', 'Ibukota Indonesia adalah …', 'Jakarta', 'Bandung', 'Surabaya', 'Medan', '', 'A', 10, 'mudah', 'Jakarta adalah ibu kota RI.'],
            ['pg', '2 + 2 × 2 = …', '6', '8', '4', '10', '', 'A', 10, 'sedang', 'Operasi perkalian didahulukan.'],
            ['isian', 'Sebutkan kepanjangan dari HTTP.', '', '', '', '', '', 'HyperText Transfer Protocol', 10, 'sedang', ''],
            ['uraian', 'Jelaskan perbedaan OOP dan pemrograman prosedural.', '', '', '', '', '', '', 20, 'sukar', ''],
        ];
        $sheet->fromArray($contoh, null, 'A2');

        foreach (range('A', 'K') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        $help = $spreadsheet->createSheet();
        $help->setTitle('Panduan');
        $help->fromArray([
            ['Panduan Import Bank Soal'],
            [''],
            ['Kolom', 'Keterangan'],
            ['tipe', 'pg (pilihan ganda) | isian (isian singkat) | uraian'],
            ['pertanyaan', 'Teks soal (wajib)'],
            ['opsi_a .. opsi_e', 'Teks pilihan (pilihan ganda minimal 2 opsi)'],
            ['kunci', 'Pilihan ganda: huruf A-E | Isian/Uraian: teks jawaban benar'],
            ['poin', 'Bobot nilai (angka >= 1, default 10, maksimal 100)'],
            ['tingkat_kesulitan', 'mudah | sedang | sukar (opsional)'],
            ['pembahasan', 'Pembahasan / kunci diskusi (opsional)'],
            [''],
            ['Baris 2-5 adalah contoh dan AKAN ikut terimport bila tidak dihapus.'],
            ['Hapus baris contoh bila tidak diperlukan, lalu upload file ke halaman Bank Soal.'],
        ], null, 'A1');
        $help->getColumnDimension('A')->setAutoSize(true);
        $help->getColumnDimension('B')->setAutoSize(true);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Simpan template sebagai file XLSX sementara (untuk stream unduhan).
     */
    public static function saveTemplate(string $path): void
    {
        $writer = new Xlsx(self::buildTemplate());
        $writer->save($path);
    }

    /**
     * Bangun peta index kolom dari baris header.
     *
     * @param  array<int, mixed>  $header
     * @return array<string, int>
     */
    private static function buildHeaderIndex(array $header): array
    {
        $index = [];
        foreach ($header as $i => $h) {
            $norm = self::norm((string) $h);
            if ($norm === '') {
                continue;
            }
            $index[$norm] ??= $i;
            $index[self::stripUnderscore($norm)] ??= $i;
        }

        return $index;
    }

    /**
     * Cari index kolom berdasarkan daftar alias.
     *
     * @param  array<string, int>  $index
     * @param  array<int, string>  $aliases
     */
    private static function col(array $index, array $aliases): ?int
    {
        foreach ($aliases as $alias) {
            $norm = self::norm($alias);
            if (isset($index[$norm])) {
                return $index[$norm];
            }
            $alt = self::stripUnderscore($norm);
            if (isset($index[$alt])) {
                return $index[$alt];
            }
        }

        return null;
    }

    /**
     * Normalisasi nama header: lowercase, trim, spasi/tanda hubung/titik → underscore.
     */
    private static function norm(string $h): string
    {
        $h = strtolower(trim($h));
        $h = str_replace([' ', '-', '.'], '_', $h);
        $h = (string) preg_replace('/_+/', '_', $h);

        return trim($h, '_');
    }

    private static function stripUnderscore(string $h): string
    {
        return str_replace('_', '', $h);
    }

    private static function toFloat(mixed $value, float $default): float
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (! is_numeric($value)) {
            return $default;
        }

        return (float) $value;
    }
}
