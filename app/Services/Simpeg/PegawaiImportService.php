<?php

namespace App\Services\Simpeg;

use App\Models\Role;
use App\Models\Simpeg\Jabatan;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\RiwayatJabatan;
use App\Models\Simpeg\UnitKerja;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use SimpleXMLElement;
use ZipArchive;

class PegawaiImportService
{
    /**
     * Daftar kolom standar impor pegawai.
     */
    public const COLUMNS = [
        'nidn',
        'nuptk',
        'nip',
        'nik',
        'gelar_depan',
        'nama_lengkap',
        'gelar_belakang',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'agama',
        'email',
        'telepon',
        'alamat',
        'jenis_pegawai',
        'status_kepegawaian',
        'unit_kerja',
        'jabatan',
        'jabatan_fungsional',
        'shift_kerja',
        'lokasi_presensi',
        'tanggal_masuk',
        'nama_bank',
        'nomor_rekening',
        'nama_rekening',
    ];

    /**
     * Menghasilkan berkas Excel (.xlsx) template impor pegawai lengkap dengan Data Validation (dropdown) dinamis dari tabel master.
     */
    public function getTemplateXlsx(): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Sistem Informasi Terpadu Kampus (SIMPEG)')
            ->setTitle('Template Impor Data Pegawai')
            ->setSubject('Master Data Pegawai');

        // 1. Ambil data master riil dari sistem (dinamis)
        $units = UnitKerja::where('is_active', true)->orderBy('nama')->pluck('nama')->toArray();
        if (empty($units)) {
            $units = ['Fakultas Ilmu Komputer', 'Biro Kepegawaian & Administrasi Umum'];
        }

        $jabatans = Jabatan::where('is_active', true)->orderBy('nama')->pluck('nama')->toArray();
        if (empty($jabatans)) {
            $jabatans = ['Dosen Pengajar', 'Staf Administrasi'];
        }

        $jadList = \App\Models\Simpeg\JabatanFungsionalAkademik::orderBy('id')->pluck('nama')->toArray();
        if (empty($jadList)) {
            $jadList = ['Asisten Ahli', 'Lektor', 'Lektor Kepala', 'Guru Besar'];
        }

        $shiftTemplates = \App\Models\ShiftTemplate::where('is_active', true)->orderBy('name')->pluck('name')->toArray();
        if (empty($shiftTemplates)) {
            $shiftTemplates = ['Shift Reguler (08:00 - 16:00)', 'Shift Pagi Dosen'];
        }

        $officeLocations = \App\Models\OfficeLocation::where('is_active', true)->orderBy('name')->pluck('name')->toArray();
        if (empty($officeLocations)) {
            $officeLocations = ['Kampus Utama (Pusat)', 'Kampus II'];
        }

        // Domain closed values
        $genders = ['L', 'P'];
        $religions = ['Islam', 'Kristen Protestan', 'Katolik', 'Hindu', 'Buddha', 'Khonghucu'];
        $employeeTypes = ['dosen', 'tendik'];
        $employmentStatuses = ['tetap_yayasan', 'kontrak', 'pns', 'non_pns'];

        // 2. Buat Sheet 2: 'Referensi Data Master'
        $refSheet = $spreadsheet->createSheet(1);
        $refSheet->setTitle('Referensi Data Master');

        $refHeaders = [
            'A1' => 'Jenis Kelamin',
            'B1' => 'Agama',
            'C1' => 'Jenis Pegawai',
            'D1' => 'Status Kepegawaian',
            'E1' => 'Unit Kerja (Aktif)',
            'F1' => 'Jabatan Struktural',
            'G1' => 'Jabatan Fungsional (JAD)',
            'H1' => 'Shift Kerja (Presensi)',
            'I1' => 'Lokasi Presensi',
        ];

        foreach ($refHeaders as $cell => $text) {
            $refSheet->setCellValue($cell, $text);
        }

        // Style header referensi
        $refSheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '334155'], // Slate 700
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);
        $refSheet->getRowDimension(1)->setRowHeight(24);

        $populateRefColumn = function ($sheet, string $colLetter, array $items): int {
            $row = 2;
            foreach ($items as $item) {
                $sheet->setCellValue("{$colLetter}{$row}", (string) $item);
                $row++;
            }
            return max(2, $row - 1);
        };

        $lastRowGender = $populateRefColumn($refSheet, 'A', $genders);
        $lastRowReligion = $populateRefColumn($refSheet, 'B', $religions);
        $lastRowEmpType = $populateRefColumn($refSheet, 'C', $employeeTypes);
        $lastRowEmpStatus = $populateRefColumn($refSheet, 'D', $employmentStatuses);
        $lastRowUnit = $populateRefColumn($refSheet, 'E', $units);
        $lastRowJabatan = $populateRefColumn($refSheet, 'F', $jabatans);
        $lastRowJad = $populateRefColumn($refSheet, 'G', $jadList);
        $lastRowShift = $populateRefColumn($refSheet, 'H', $shiftTemplates);
        $lastRowLocation = $populateRefColumn($refSheet, 'I', $officeLocations);

        foreach (range('A', 'I') as $col) {
            $refSheet->getColumnDimension($col)->setAutoSize(true);
        }

        // 3. Buat Sheet 1: 'Template Impor Pegawai'
        $mainSheet = $spreadsheet->setActiveSheetIndex(0);
        $mainSheet->setTitle('Template Impor Pegawai');

        // Tulis Baris 1: Header
        foreach (self::COLUMNS as $colIdx => $colKey) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
            $mainSheet->setCellValue("{$colLetter}1", $colKey);
        }

        // Styling Header Utama (Modern Blue Theme SIMPEG)
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count(self::COLUMNS));
        $mainSheet->getStyle("A1:{$lastColLetter}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E40AF'], // Blue 800
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '1E3A8A'],
                ],
            ],
        ]);
        $mainSheet->getRowDimension(1)->setRowHeight(28);

        // Data Contoh
        $sampleData = [
            [
                'nidn' => '0415018501',
                'nuptk' => '3560763664230001',
                'nip' => '198501152010121001',
                'nik' => '3271011501850002',
                'gelar_depan' => 'Dr.',
                'nama_lengkap' => 'Ahmad Fadhil',
                'gelar_belakang' => 'M.Kom.',
                'jenis_kelamin' => 'L',
                'tempat_lahir' => 'Bandung',
                'tanggal_lahir' => '1985-01-15',
                'agama' => 'Islam',
                'email' => 'ahmad.fadhil@campus.ac.id',
                'telepon' => '081234567890',
                'alamat' => 'Jl. Merdeka No. 10, Bandung',
                'jenis_pegawai' => 'dosen',
                'status_kepegawaian' => 'tetap_yayasan',
                'unit_kerja' => $units[0] ?? 'Fakultas Ilmu Komputer',
                'jabatan' => $jabatans[0] ?? 'Dosen Pengajar',
                'jabatan_fungsional' => $jadList[0] ?? 'Lektor',
                'shift_kerja' => $shiftTemplates[0] ?? 'Shift Reguler',
                'lokasi_presensi' => $officeLocations[0] ?? 'Kampus Utama',
                'tanggal_masuk' => '2015-08-01',
                'nama_bank' => 'Bank Mandiri',
                'nomor_rekening' => '1310012345678',
                'nama_rekening' => 'Ahmad Fadhil',
            ],
            [
                'nidn' => '',
                'nuptk' => '',
                'nip' => '199203102018042002',
                'nik' => '3271011003920003',
                'gelar_depan' => '',
                'nama_lengkap' => 'Siti Nurhaliza',
                'gelar_belakang' => 'S.E.',
                'jenis_kelamin' => 'P',
                'tempat_lahir' => 'Jakarta',
                'tanggal_lahir' => '1992-03-10',
                'agama' => 'Islam',
                'email' => 'siti.nurhaliza@campus.ac.id',
                'telepon' => '082345678901',
                'alamat' => 'Jl. Sudirman No. 25, Bandung',
                'jenis_pegawai' => 'tendik',
                'status_kepegawaian' => 'kontrak',
                'unit_kerja' => $units[1] ?? ($units[0] ?? 'Biro Kepegawaian & Administrasi Umum'),
                'jabatan' => $jabatans[1] ?? ($jabatans[0] ?? 'Staf Administrasi'),
                'jabatan_fungsional' => '',
                'shift_kerja' => $shiftTemplates[0] ?? 'Shift Reguler',
                'lokasi_presensi' => $officeLocations[0] ?? 'Kampus Utama',
                'tanggal_masuk' => '2018-04-01',
                'nama_bank' => 'Bank BCA',
                'nomor_rekening' => '0123456789',
                'nama_rekening' => 'Siti Nurhaliza',
            ],
        ];

        foreach ($sampleData as $rowIdx => $rowValues) {
            $rowNum = $rowIdx + 2;
            foreach (self::COLUMNS as $colIdx => $colKey) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
                $mainSheet->setCellValueExplicit(
                    "{$colLetter}{$rowNum}",
                    $rowValues[$colKey] ?? '',
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
            }
            $mainSheet->getRowDimension($rowNum)->setRowHeight(20);
        }

        // Terapkan Data Validation (Dropdown) baris 2 sampai 500
        $maxDataRow = 500;
        $sheetRefTitle = "'Referensi Data Master'";

        $validations = [
            'H' => [ // jenis_kelamin
                'title' => 'Jenis Kelamin',
                'prompt' => 'Pilih L atau P',
                'formula' => "{$sheetRefTitle}!\$A\$2:\$A\${$lastRowGender}",
            ],
            'K' => [ // agama
                'title' => 'Agama',
                'prompt' => 'Pilih Agama sesuai KTP',
                'formula' => "{$sheetRefTitle}!\$B\$2:\$B\${$lastRowReligion}",
            ],
            'O' => [ // jenis_pegawai
                'title' => 'Jenis Pegawai',
                'prompt' => 'Pilih dosen atau tendik',
                'formula' => "{$sheetRefTitle}!\$C\$2:\$C\${$lastRowEmpType}",
            ],
            'P' => [ // status_kepegawaian
                'title' => 'Status Kepegawaian',
                'prompt' => 'Pilih status kepegawaian',
                'formula' => "{$sheetRefTitle}!\$D\$2:\$D\${$lastRowEmpStatus}",
            ],
            'Q' => [ // unit_kerja
                'title' => 'Unit Kerja Bertugas',
                'prompt' => 'Pilih Unit Kerja aktif dari daftar',
                'formula' => "{$sheetRefTitle}!\$E\$2:\$E\${$lastRowUnit}",
            ],
            'R' => [ // jabatan
                'title' => 'Jabatan Struktural',
                'prompt' => 'Pilih Jabatan Struktural dari daftar',
                'formula' => "{$sheetRefTitle}!\$F\$2:\$F\${$lastRowJabatan}",
            ],
            'S' => [ // jabatan_fungsional
                'title' => 'Jabatan Akademik Dosen (JAD)',
                'prompt' => 'Pilih Jabatan Fungsional Dosen dari daftar',
                'formula' => "{$sheetRefTitle}!\$G\$2:\$G\${$lastRowJad}",
            ],
            'T' => [ // shift_kerja
                'title' => 'Shift Kerja Presensi',
                'prompt' => 'Pilih Jadwal Presensi / Shift Kerja',
                'formula' => "{$sheetRefTitle}!\$H\$2:\$H\${$lastRowShift}",
            ],
            'U' => [ // lokasi_presensi
                'title' => 'Lokasi Presensi',
                'prompt' => 'Pilih Kantor / Lokasi Presensi',
                'formula' => "{$sheetRefTitle}!\$I\$2:\$I\${$lastRowLocation}",
            ],
        ];

        foreach ($validations as $colLetter => $vConfig) {
            $validation = $mainSheet->getCell("{$colLetter}2")->getDataValidation();
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Pilihan Tidak Sesuai Master');
            $validation->setError('Harap pilih nilai yang tersedia pada menu dropdown agar data selaras dengan sistem.');
            $validation->setPromptTitle($vConfig['title']);
            $validation->setPrompt($vConfig['prompt']);
            $validation->setFormula1($vConfig['formula']);
            $validation->setSqref("{$colLetter}2:{$colLetter}{$maxDataRow}");
        }

        // Format kolom teks dan auto width
        foreach (range(1, count(self::COLUMNS)) as $colIdx) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
            $mainSheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tempPath = tempnam(sys_get_temp_dir(), 'pegawai_tpl_');
        $writer->save($tempPath);

        $binary = file_get_contents($tempPath);
        @unlink($tempPath);

        return $binary;
    }

    /**
     * Menghasilkan berkas CSV template impor pegawai lengkap dengan header dan data contoh.
     */
    public function getTemplateCsv(): string
    {
        $headers = self::COLUMNS;

        $units = UnitKerja::where('is_active', true)->orderBy('nama')->pluck('nama')->toArray();
        $jabatans = Jabatan::where('is_active', true)->orderBy('nama')->pluck('nama')->toArray();
        $jadList = \App\Models\Simpeg\JabatanFungsionalAkademik::orderBy('id')->pluck('nama')->toArray();
        $shiftTemplates = \App\Models\ShiftTemplate::where('is_active', true)->orderBy('name')->pluck('name')->toArray();
        $officeLocations = \App\Models\OfficeLocation::where('is_active', true)->orderBy('name')->pluck('name')->toArray();

        $sampleRows = [
            [
                '0415018501',
                '3560763664230001',
                '198501152010121001',
                '3271011501850002',
                'Dr.',
                'Ahmad Fadhil',
                'M.Kom.',
                'L',
                'Bandung',
                '1985-01-15',
                'Islam',
                'ahmad.fadhil@campus.ac.id',
                '081234567890',
                'Jl. Merdeka No. 10, Bandung',
                'dosen',
                'tetap_yayasan',
                $units[0] ?? 'Fakultas Ilmu Komputer',
                $jabatans[0] ?? 'Dosen Pengajar',
                $jadList[0] ?? 'Lektor',
                $shiftTemplates[0] ?? 'Shift Reguler',
                $officeLocations[0] ?? 'Kampus Utama',
                '2015-08-01',
                'Bank Mandiri',
                '1310012345678',
                'Ahmad Fadhil',
            ],
            [
                '',
                '',
                '199203102018042002',
                '3271011003920003',
                '',
                'Siti Nurhaliza',
                'S.E.',
                'P',
                'Jakarta',
                '1992-03-10',
                'Islam',
                'siti.nurhaliza@campus.ac.id',
                '082345678901',
                'Jl. Sudirman No. 25, Bandung',
                'tendik',
                'kontrak',
                $units[1] ?? ($units[0] ?? 'Biro Keuangan & Administrasi Umum'),
                $jabatans[1] ?? ($jabatans[0] ?? 'Staf Administrasi Keuangan'),
                '',
                $shiftTemplates[0] ?? 'Shift Reguler',
                $officeLocations[0] ?? 'Kampus Utama',
                '2018-04-01',
                'Bank BCA',
                '0123456789',
                'Siti Nurhaliza',
            ],
        ];

        // Tambahkan UTF-8 BOM agar terbaca sempurna di Microsoft Excel
        $output = "\xEF\xBB\xBF";
        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, $headers);
        foreach ($sampleRows as $row) {
            fputcsv($fp, $row);
        }
        rewind($fp);
        $output .= stream_get_contents($fp);
        fclose($fp);

        return $output;
    }

    /**
     * Memproses berkas yang diunggah (CSV atau XLSX).
     */
    public function import(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $rows = [];

        if (in_array($extension, ['csv', 'txt'])) {
            $rows = $this->parseCsv($file->getRealPath());
        } elseif (in_array($extension, ['xlsx', 'xls'])) {
            $rows = $this->parseXlsx($file->getRealPath());
        } else {
            throw new \InvalidArgumentException('Format berkas tidak didukung. Harap unggah berkas .csv atau .xlsx.');
        }

        if (empty($rows)) {
            return [
                'total' => 0,
                'success' => 0,
                'failed' => 0,
                'errors' => ['Berkas kosong atau tidak memiliki baris data.'],
                'data' => [],
            ];
        }

        return $this->processRows($rows);
    }

    /**
     * Parser CSV dengan auto-detection delimiter (, atau ;)
     */
    private function parseCsv(string $path): array
    {
        $rows = [];
        $fp = fopen($path, 'r');
        if (!$fp) {
            return $rows;
        }

        // Baca baris pertama untuk deteksi delimiter dan BOM
        $firstLine = fgets($fp);
        if (!$firstLine) {
            fclose($fp);
            return $rows;
        }

        // Hapus UTF-8 BOM jika ada
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);

        // Deteksi delimiter
        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

        $header = str_getcsv($firstLine, $delimiter);
        $header = array_map(function ($h) {
            $cleaned = preg_replace('/[\s\-]+/', '_', trim($h));
            return strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $cleaned));
        }, $header);

        while (($data = fgetcsv($fp, 0, $delimiter)) !== false) {
            if (empty(array_filter($data, fn($v) => trim($v) !== ''))) {
                continue; // Lewati baris kosong
            }

            $row = [];
            foreach ($header as $idx => $key) {
                $row[$key] = isset($data[$idx]) ? trim($data[$idx]) : '';
            }
            $rows[] = $row;
        }

        fclose($fp);
        return $rows;
    }

    /**
     * Parser XLSX menggunakan PhpSpreadsheet dengan penanganan tanggal dan formula yang aman
     */
    private function parseXlsx(string $path): array
    {
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(false); // Baca format data agar tanggal terbaca akurat
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getSheet(0); // Ambil sheet pertama (Template Impor Pegawai)

            $highestRow = $sheet->getHighestDataRow();
            $highestColumn = $sheet->getHighestDataColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            if ($highestRow < 2) {
                return [];
            }

            // Ambil header dari baris 1
            $headers = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $val = trim((string) $sheet->getCell([$col, 1])->getValue());
                $cleaned = preg_replace('/[\s\-]+/', '_', $val);
                $headers[$col] = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $cleaned));
            }

            $rows = [];
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowData = [];
                $hasContent = false;

                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $key = $headers[$col] ?? "col_{$col}";
                    $cell = $sheet->getCell([$col, $row]);

                    // Penanganan khusus sel tanggal di Excel
                    if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                        $cellVal = $cell->getValue();
                        if (is_numeric($cellVal)) {
                            $formatted = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($cellVal)->format('Y-m-d');
                            $rowData[$key] = $formatted;
                        } else {
                            $rowData[$key] = trim((string) $cell->getFormattedValue());
                        }
                    } else {
                        $val = $cell->getValue();
                        $rowData[$key] = is_string($val) ? trim($val) : (string) ($val ?? '');
                    }

                    if (!empty($rowData[$key])) {
                        $hasContent = true;
                    }
                }

                if ($hasContent) {
                    $rows[] = $rowData;
                }
            }

            return $rows;
        } catch (\Throwable $e) {
            // Fallback: coba baca sebagai CSV jika berkas bukan zip/xlsx murni
            return $this->parseCsv($path);
        }
    }

    /**
     * Memproses baris data dan membuat akun SSO otomatis
     */
    private function processRows(array $rows): array
    {
        // Hindari timeout PHP 30 detik pada impor berkas dengan banyak baris data
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $successCount = 0;
        $failedCount = 0;
        $errors = [];
        $createdPegawai = [];

        $dosenRole = Role::where('slug', 'dosen')->first();
        $tendikRole = Role::where('slug', 'tendik')->first();

        // Optimasi: Pre-hash default password satu kali di luar perulangan
        $hashedDefaultPassword = Hash::make('indonusa');

        // Pre-load master entities ke memori untuk menghindari N+1 query
        $allUnits = UnitKerja::all();
        $allJabatans = Jabatan::all();
        $allJad = \App\Models\Simpeg\JabatanFungsionalAkademik::all();
        $allShifts = \App\Models\ShiftTemplate::all();
        $allLocations = \App\Models\OfficeLocation::all();

        // Lacak identifier yang sudah diproses dalam berkas ini untuk mendeteksi duplikasi antar-baris
        $seenNip = [];
        $seenNidn = [];
        $seenNuptk = [];
        $seenNik = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // Mengingat baris 1 adalah header di spreadsheet

            $namaLengkap = $row['nama_lengkap'] ?? $row['nama'] ?? $row['namalengkap'] ?? '';
            if (empty($namaLengkap)) {
                $errors[] = "Baris #{$rowNumber}: Nama lengkap wajib diisi.";
                $failedCount++;
                continue;
            }

            $pegawaiService = app(PegawaiService::class);

            $nipRaw = $row['nip'] ?? $row['nomor_induk_pegawai'] ?? null;
            $nip = $pegawaiService->sanitizeIdentifier(!empty($nipRaw) ? preg_replace('/[^0-9]/', '', (string) $nipRaw) : null);

            $nikRaw = $row['nik'] ?? $row['no_ktp'] ?? $row['ktp'] ?? null;
            $nik = $pegawaiService->sanitizeIdentifier(!empty($nikRaw) ? preg_replace('/[^0-9]/', '', (string) $nikRaw) : null);

            $nidnRaw = $row['nidn'] ?? $row['nomor_induk_dosen_nasional'] ?? $row['no_nidn'] ?? null;
            $nidn = $pegawaiService->sanitizeIdentifier(!empty($nidnRaw) ? trim((string) $nidnRaw) : null);

            $nuptkRaw = $row['nuptk'] ?? $row['nomor_unik_pendidik_dan_tenaga_kependidikan'] ?? $row['no_nuptk'] ?? null;
            $nuptk = $pegawaiService->sanitizeIdentifier(!empty($nuptkRaw) ? trim((string) $nuptkRaw) : null);

            // Validasi keunikan NIP jika ada
            if ($nip) {
                if (isset($seenNip[$nip])) {
                    $errors[] = "Baris #{$rowNumber}: NIP '{$nip}' duplikat dengan baris #{$seenNip[$nip]} pada berkas impor ini.";
                    $failedCount++;
                    continue;
                }
                if (Pegawai::where('nip', $nip)->exists()) {
                    $errors[] = "Baris #{$rowNumber}: NIP '{$nip}' sudah terdaftar pada pegawai lain di sistem.";
                    $failedCount++;
                    continue;
                }
                $seenNip[$nip] = $rowNumber;
            }

            // Validasi keunikan NIDN jika ada
            if ($nidn) {
                if (isset($seenNidn[$nidn])) {
                    $errors[] = "Baris #{$rowNumber}: NIDN '{$nidn}' duplikat dengan baris #{$seenNidn[$nidn]} pada berkas impor ini.";
                    $failedCount++;
                    continue;
                }
                if (Pegawai::where('nidn', $nidn)->exists()) {
                    $errors[] = "Baris #{$rowNumber}: NIDN '{$nidn}' sudah terdaftar pada pegawai lain di sistem.";
                    $failedCount++;
                    continue;
                }
                $seenNidn[$nidn] = $rowNumber;
            }

            // Validasi keunikan NUPTK jika ada
            if ($nuptk) {
                if (isset($seenNuptk[$nuptk])) {
                    $errors[] = "Baris #{$rowNumber}: NUPTK '{$nuptk}' duplikat dengan baris #{$seenNuptk[$nuptk]} pada berkas impor ini.";
                    $failedCount++;
                    continue;
                }
                if (Pegawai::where('nuptk', $nuptk)->exists()) {
                    $errors[] = "Baris #{$rowNumber}: NUPTK '{$nuptk}' sudah terdaftar pada pegawai lain di sistem.";
                    $failedCount++;
                    continue;
                }
                $seenNuptk[$nuptk] = $rowNumber;
            }

            // Validasi keunikan NIK jika ada
            if ($nik) {
                if (isset($seenNik[$nik])) {
                    $errors[] = "Baris #{$rowNumber}: NIK '{$nik}' duplikat dengan baris #{$seenNik[$nik]} pada berkas impor ini.";
                    $failedCount++;
                    continue;
                }
                if (Pegawai::where('nik', $nik)->exists()) {
                    $errors[] = "Baris #{$rowNumber}: NIK '{$nik}' sudah terdaftar pada pegawai lain di sistem.";
                    $failedCount++;
                    continue;
                }
                $seenNik[$nik] = $rowNumber;
            }

            // Jenis kelamin: null jika kosong di file
            $jk = null;
            $jkVal = $row['jenis_kelamin'] ?? $row['jeniskelamin'] ?? $row['jk'] ?? $row['gender'] ?? null;
            if (!empty($jkVal)) {
                $jkRaw = strtoupper(trim($jkVal));
                $jk = (str_starts_with($jkRaw, 'P') || str_contains($jkRaw, 'WANITA') || str_contains($jkRaw, 'PEREMPUAN')) ? 'P' : 'L';
            }

            // Jenis pegawai: null jika kosong di file
            $jenisPegawai = null;
            $jpVal = $row['jenis_pegawai'] ?? $row['jenispegawai'] ?? $row['tipe_pegawai'] ?? $row['tipepegawai'] ?? null;
            if (!empty($jpVal)) {
                $jenisPegawaiRaw = strtolower(trim($jpVal));
                if (
                    str_contains($jenisPegawaiRaw, 'tendik') ||
                    str_contains($jenisPegawaiRaw, 'pegawai') ||
                    str_contains($jenisPegawaiRaw, 'staf') ||
                    str_contains($jenisPegawaiRaw, 'staff') ||
                    str_contains($jenisPegawaiRaw, 'karyawan') ||
                    str_contains($jenisPegawaiRaw, 'kependidikan') ||
                    str_contains($jenisPegawaiRaw, 'administrasi') ||
                    str_contains($jenisPegawaiRaw, 'admin') ||
                    str_contains($jenisPegawaiRaw, 'laboran') ||
                    str_contains($jenisPegawaiRaw, 'teknisi') ||
                    str_contains($jenisPegawaiRaw, 'tu')
                ) {
                    $jenisPegawai = 'tendik';
                } elseif (str_contains($jenisPegawaiRaw, 'honorer')) {
                    $jenisPegawai = 'honorer';
                } elseif (
                    str_contains($jenisPegawaiRaw, 'dosen') ||
                    str_contains($jenisPegawaiRaw, 'pengajar') ||
                    str_contains($jenisPegawaiRaw, 'pendidik') ||
                    str_contains($jenisPegawaiRaw, 'lektor') ||
                    str_contains($jenisPegawaiRaw, 'asisten')
                ) {
                    $jenisPegawai = 'dosen';
                } else {
                    $jenisPegawai = 'tendik';
                }
            }

            // Status kepegawaian: null jika kosong di file
            $statusKepegawaian = null;
            $skVal = $row['status_kepegawaian'] ?? $row['statuskepegawaian'] ?? $row['status_pegawai'] ?? null;
            if (!empty($skVal)) {
                $statusKepegawaianRaw = strtolower(trim($skVal));
                if (str_contains($statusKepegawaianRaw, 'pns') && !str_contains($statusKepegawaianRaw, 'non')) {
                    $statusKepegawaian = 'pns';
                } elseif (str_contains($statusKepegawaianRaw, 'kontrak')) {
                    $statusKepegawaian = 'kontrak';
                } elseif (str_contains($statusKepegawaianRaw, 'non_pns') || str_contains($statusKepegawaianRaw, 'non pns')) {
                    $statusKepegawaian = 'non_pns';
                } else {
                    $statusKepegawaian = 'tetap_yayasan';
                }
            }

            // Cari Unit Kerja dari pre-loaded collection berdasarkan nama atau kode (null jika kosong)
            $unitKerjaId = null;
            $ukVal = $row['unit_kerja'] ?? $row['unitkerja'] ?? $row['unit'] ?? $row['fakultas'] ?? $row['prodi'] ?? null;
            if (!empty($ukVal)) {
                $searchUnit = trim($ukVal);
                $foundUnit = $allUnits->first(function ($u) use ($searchUnit) {
                    return stripos($u->nama, $searchUnit) !== false || strcasecmp($u->kode, $searchUnit) === 0;
                });
                if ($foundUnit) {
                    $unitKerjaId = $foundUnit->id;
                }
            }

            // Cari Jabatan Fungsional Dosen (JAD) dari pre-loaded collection
            $jadId = null;
            $jadVal = $row['jabatan_fungsional'] ?? $row['jabatanfungsional'] ?? $row['jad'] ?? null;
            if (!empty($jadVal)) {
                $searchJad = trim($jadVal);
                $foundJad = $allJad->first(function ($j) use ($searchJad) {
                    return strcasecmp($j->nama, $searchJad) === 0 || stripos($j->nama, $searchJad) !== false;
                });
                if ($foundJad) {
                    $jadId = $foundJad->id;
                }
            }

            // Cari Shift Kerja (Jadwal Presensi) dari pre-loaded collection
            $shiftId = null;
            $shiftVal = $row['shift_kerja'] ?? $row['shift'] ?? $row['jadwal_presensi'] ?? $row['shift_template'] ?? null;
            if (!empty($shiftVal)) {
                $searchShift = trim($shiftVal);
                $foundShift = $allShifts->first(function ($s) use ($searchShift) {
                    return strcasecmp($s->name, $searchShift) === 0 || stripos($s->name, $searchShift) !== false;
                });
                if ($foundShift) {
                    $shiftId = $foundShift->id;
                }
            }

            // Cari Lokasi Presensi dari pre-loaded collection
            $officeLocationId = null;
            $locVal = $row['lokasi_presensi'] ?? $row['lokasi'] ?? $row['kantor'] ?? $row['office_location'] ?? null;
            if (!empty($locVal)) {
                $searchLoc = trim($locVal);
                $foundLoc = $allLocations->first(function ($l) use ($searchLoc) {
                    return strcasecmp($l->name, $searchLoc) === 0 || stripos($l->name, $searchLoc) !== false;
                });
                if ($foundLoc) {
                    $officeLocationId = $foundLoc->id;
                }
            }

            // Tanggal masuk: null jika kosong
            $tanggalMasuk = null;
            $tglMasukRaw = $row['tanggal_masuk'] ?? $row['tanggalmasuk'] ?? $row['tgl_masuk'] ?? null;
            if (!empty($tglMasukRaw)) {
                $parsedTglMasuk = strtotime($tglMasukRaw);
                if ($parsedTglMasuk !== false) {
                    $tanggalMasuk = date('Y-m-d', $parsedTglMasuk);
                }
            }

            // Tanggal lahir: null jika kosong
            $tanggalLahir = null;
            $tglLahirRaw = $row['tanggal_lahir'] ?? $row['tanggallahir'] ?? $row['tgl_lahir'] ?? null;
            if (!empty($tglLahirRaw)) {
                $parsedTglLahir = strtotime($tglLahirRaw);
                if ($parsedTglLahir !== false) {
                    $tanggalLahir = date('Y-m-d', $parsedTglLahir);
                }
            }

            // Gelar akademik depan & belakang
            $gelarDepan = !empty($row['gelar_depan']) ? trim($row['gelar_depan']) : null;
            $gelarBelakang = !empty($row['gelar_belakang']) ? trim($row['gelar_belakang']) : null;

            // Rekening payroll
            $namaBank = !empty($row['nama_bank']) ? trim($row['nama_bank']) : ($row['bank'] ?? null);
            $nomorRekening = !empty($row['nomor_rekening']) ? trim($row['nomor_rekening']) : ($row['no_rekening'] ?? ($row['rekening'] ?? null));
            $namaRekening = !empty($row['nama_rekening']) ? trim($row['nama_rekening']) : ($row['atas_nama_rekening'] ?? null);

            // Agama: null jika kosong
            $agama = !empty($row['agama']) ? trim($row['agama']) : null;

            $tempatLahir = $row['tempat_lahir'] ?? $row['tempatlahir'] ?? null;
            $telepon = $row['telepon'] ?? $row['nohp'] ?? $row['no_hp'] ?? $row['handphone'] ?? null;
            $alamat = $row['alamat'] ?? null;
            $email = !empty($row['email']) ? trim($row['email']) : null;

            try {
                DB::beginTransaction();

                // 1. Simpan Data Pegawai di simpeg_pegawai (kolom kosong tetap null)
                $pegawai = Pegawai::create([
                    'unit_kerja_id' => $unitKerjaId,
                    'jabatan_fungsional_id' => $jadId,
                    'shift_template_id' => $shiftId,
                    'office_location_id' => $officeLocationId,
                    'nidn' => $nidn,
                    'nuptk' => $nuptk,
                    'nip' => $nip,
                    'nik' => $nik,
                    'gelar_depan' => !empty($gelarDepan) ? trim($gelarDepan) : null,
                    'nama_lengkap' => $namaLengkap,
                    'gelar_belakang' => !empty($gelarBelakang) ? trim($gelarBelakang) : null,
                    'jenis_kelamin' => $jk,
                    'tempat_lahir' => !empty($tempatLahir) ? trim($tempatLahir) : null,
                    'tanggal_lahir' => $tanggalLahir,
                    'agama' => $agama,
                    'jenis_pegawai' => $jenisPegawai,
                    'status_kepegawaian' => $statusKepegawaian,
                    'tanggal_masuk' => $tanggalMasuk,
                    'status' => 'aktif',
                    'telepon' => !empty($telepon) ? trim($telepon) : null,
                    'alamat' => !empty($alamat) ? trim($alamat) : null,
                    'nama_bank' => !empty($namaBank) ? trim($namaBank) : null,
                    'nomor_rekening' => !empty($nomorRekening) ? trim($nomorRekening) : null,
                    'nama_rekening' => !empty($namaRekening) ? trim($namaRekening) : null,
                ]);

                // 2. Buat atau hubungkan akun SSO di core_users dengan skala prioritas username: NIDN -> NUPTK -> NIP
                $roleIds = [];
                if ($jenisPegawai === 'dosen' && $dosenRole) {
                    $roleIds[] = $dosenRole->id;
                } elseif ($jenisPegawai === 'tendik' && $tendikRole) {
                    $roleIds[] = $tendikRole->id;
                }

                $user = app(PegawaiService::class)->ensureSsoUserForPegawai($pegawai, [
                    'email' => $email,
                    'role_ids' => $roleIds,
                    'hashed_password' => $hashedDefaultPassword,
                ]);

                // 3. Tambahkan riwayat jabatan awal jika kolom jabatan terisi
                $jabatanRaw = $row['jabatan'] ?? $row['posisi'] ?? null;
                if (!empty($jabatanRaw)) {
                    $searchJabatan = trim($jabatanRaw);
                    $jabatan = $allJabatans->first(function ($j) use ($searchJabatan) {
                        return stripos($j->nama, $searchJabatan) !== false;
                    });
                    if ($jabatan) {
                        RiwayatJabatan::create([
                            'pegawai_id' => $pegawai->id,
                            'unit_kerja_id' => $unitKerjaId,
                            'jabatan_id' => $jabatan->id,
                            'tanggal_mulai' => $pegawai->tanggal_masuk ?? date('Y-m-d'),
                            'is_aktif' => true,
                        ]);
                    }
                }

                // Sinkronisasi otomatis ke modul SIAKAD jika pegawai adalah Dosen
                app(PegawaiService::class)->syncDosenRecord($pegawai);

                DB::commit();
                $successCount++;
                $createdPegawai[] = [
                    'id' => $pegawai->id,
                    'nama' => $pegawai->nama_lengkap,
                    'nip' => $pegawai->nip,
                    'email' => $user->email,
                    'username' => $user->username,
                ];
            } catch (\Throwable $e) {
                DB::rollBack();
                $errors[] = "Baris #{$rowNumber} ({$namaLengkap}): " . $e->getMessage();
                $failedCount++;
            }
        }

        return [
            'total' => count($rows),
            'success' => $successCount,
            'failed' => $failedCount,
            'errors' => $errors,
            'data' => $createdPegawai,
        ];
    }
}
