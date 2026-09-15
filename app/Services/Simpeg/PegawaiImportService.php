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
     * Menghasilkan berkas CSV template impor pegawai lengkap dengan header dan data contoh.
     */
    public function getTemplateCsv(): string
    {
        $headers = [
            'nidn',
            'nuptk',
            'nip',
            'nik',
            'nama_lengkap',
            'email',
            'telepon',
            'jenis_kelamin',
            'tempat_lahir',
            'tanggal_lahir',
            'jenis_pegawai',
            'status_kepegawaian',
            'unit_kerja',
            'jabatan',
            'tanggal_masuk',
            'alamat',
        ];

        $sampleRows = [
            [
                '0415018501',
                '3560763664230001',
                '198501152010121001',
                '3271011501850002',
                'Dr. Ahmad Fadhil, M.Kom.',
                'ahmad.fadhil@campus.ac.id',
                '081234567890',
                'L',
                'Bandung',
                '1985-01-15',
                'dosen',
                'tetap_yayasan',
                'Fakultas Ilmu Komputer',
                'Dosen Pengajar',
                '2015-08-01',
                'Jl. Merdeka No. 10, Bandung',
            ],
            [
                '',
                '',
                '199203102018042002',
                '3271011003920003',
                'Siti Nurhaliza, S.E.',
                'siti.nurhaliza@campus.ac.id',
                '082345678901',
                'P',
                'Jakarta',
                '1992-03-10',
                'tendik',
                'kontrak',
                'Biro Keuangan & Administrasi Umum',
                'Staf Administrasi Keuangan',
                '2018-04-01',
                'Jl. Sudirman No. 25, Bandung',
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
        $header = array_map(fn($h) => strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', $h))), $header);

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
     * Parser XLSX mandiri tanpa pustaka eksternal menggunakan ZipArchive & SimpleXML
     */
    private function parseXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            // Fallback: coba baca sebagai CSV jika ternyata berkas CSV yang dinamai .xlsx
            return $this->parseCsv($path);
        }

        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml) {
            $xml = new SimpleXMLElement($sharedStringsXml);
            foreach ($xml->si as $si) {
                $sharedStrings[] = (string) ($si->t ?? $si->r->t ?? '');
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (!$sheetXml) {
            return [];
        }

        $xml = new SimpleXMLElement($sheetXml);
        $rawRows = [];

        foreach ($xml->sheetData->row as $r) {
            $rowValues = [];
            foreach ($r->c as $c) {
                $type = (string) $c['t'];
                $val = (string) $c->v;

                if ($type === 's' && isset($sharedStrings[(int) $val])) {
                    $val = $sharedStrings[(int) $val];
                }
                $rowValues[] = trim($val);
            }
            if (!empty(array_filter($rowValues, fn($v) => $v !== ''))) {
                $rawRows[] = $rowValues;
            }
        }

        if (count($rawRows) < 2) {
            return [];
        }

        $header = array_map(fn($h) => strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', $h))), $rawRows[0]);
        $rows = [];

        for ($i = 1; $i < count($rawRows); $i++) {
            $row = [];
            foreach ($header as $idx => $key) {
                $row[$key] = $rawRows[$i][$idx] ?? '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Memproses baris data dan membuat akun SSO otomatis
     */
    private function processRows(array $rows): array
    {
        $successCount = 0;
        $failedCount = 0;
        $errors = [];
        $createdPegawai = [];

        $dosenRole = Role::where('slug', 'dosen')->first();
        $tendikRole = Role::where('slug', 'tendik')->first();
        $defaultUnit = UnitKerja::first();

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // Mengingat baris 1 adalah header di spreadsheet

            $namaLengkap = $row['nama_lengkap'] ?? $row['nama'] ?? '';
            if (empty($namaLengkap)) {
                $errors[] = "Baris #{$rowNumber}: Nama lengkap wajib diisi.";
                $failedCount++;
                continue;
            }

            $nip = !empty($row['nip']) ? preg_replace('/[^0-9]/', '', (string) $row['nip']) : null;
            $nik = !empty($row['nik']) ? preg_replace('/[^0-9]/', '', (string) $row['nik']) : null;

            // Validasi keunikan NIP jika ada
            if ($nip && Pegawai::where('nip', $nip)->exists()) {
                $errors[] = "Baris #{$rowNumber}: NIP '{$nip}' sudah terdaftar pada pegawai lain.";
                $failedCount++;
                continue;
            }

            // Validasi keunikan NIK jika ada
            if ($nik && Pegawai::where('nik', $nik)->exists()) {
                $errors[] = "Baris #{$rowNumber}: NIK '{$nik}' sudah terdaftar pada pegawai lain.";
                $failedCount++;
                continue;
            }

            // Jenis kelamin: null jika kosong di file
            $jk = null;
            if (!empty($row['jenis_kelamin'])) {
                $jkRaw = strtoupper(trim($row['jenis_kelamin']));
                $jk = (str_starts_with($jkRaw, 'P') || str_contains($jkRaw, 'WANITA') || str_contains($jkRaw, 'PEREMPUAN')) ? 'P' : 'L';
            }

            // Jenis pegawai: null jika kosong di file
            $jenisPegawai = null;
            if (!empty($row['jenis_pegawai'])) {
                $jenisPegawaiRaw = strtolower(trim($row['jenis_pegawai']));
                $jenisPegawai = (str_contains($jenisPegawaiRaw, 'tendik') || str_contains($jenisPegawaiRaw, 'staf') || str_contains($jenisPegawaiRaw, 'karyawan'))
                    ? 'tendik'
                    : (str_contains($jenisPegawaiRaw, 'honorer') ? 'honorer' : 'dosen');
            }

            // Status kepegawaian: null jika kosong di file
            $statusKepegawaian = null;
            if (!empty($row['status_kepegawaian'])) {
                $statusKepegawaianRaw = strtolower(trim($row['status_kepegawaian']));
                if (str_contains($statusKepegawaianRaw, 'pns')) {
                    $statusKepegawaian = 'pns';
                } elseif (str_contains($statusKepegawaianRaw, 'kontrak')) {
                    $statusKepegawaian = 'kontrak';
                } elseif (str_contains($statusKepegawaianRaw, 'non_pns')) {
                    $statusKepegawaian = 'non_pns';
                } else {
                    $statusKepegawaian = 'tetap_yayasan';
                }
            }

            // Cari Unit Kerja berdasarkan nama atau kode (null jika kosong)
            $unitKerjaId = null;
            if (!empty($row['unit_kerja'])) {
                $searchUnit = trim($row['unit_kerja']);
                $foundUnit = UnitKerja::where('nama', 'like', "%{$searchUnit}%")
                    ->orWhere('kode', $searchUnit)
                    ->first();
                if ($foundUnit) {
                    $unitKerjaId = $foundUnit->id;
                }
            }

            // Tanggal masuk: null jika kosong
            $tanggalMasuk = null;
            if (!empty($row['tanggal_masuk'])) {
                $parsedTglMasuk = strtotime($row['tanggal_masuk']);
                if ($parsedTglMasuk !== false) {
                    $tanggalMasuk = date('Y-m-d', $parsedTglMasuk);
                }
            }

            // Tanggal lahir: null jika kosong
            $tanggalLahir = null;
            if (!empty($row['tanggal_lahir'])) {
                $parsedTglLahir = strtotime($row['tanggal_lahir']);
                if ($parsedTglLahir !== false) {
                    $tanggalLahir = date('Y-m-d', $parsedTglLahir);
                }
            }

            // Agama: null jika kosong
            $agama = !empty($row['agama']) ? trim($row['agama']) : null;

            $nidn = !empty($row['nidn']) ? trim($row['nidn']) : null;
            $nuptk = !empty($row['nuptk']) ? trim($row['nuptk']) : null;
            $email = !empty($row['email']) ? trim($row['email']) : null;

            try {
                DB::beginTransaction();

                // 1. Simpan Data Pegawai di simpeg_pegawai (kolom kosong tetap null)
                $pegawai = Pegawai::create([
                    'unit_kerja_id' => $unitKerjaId,
                    'nidn' => $nidn,
                    'nuptk' => $nuptk,
                    'nip' => $nip,
                    'nik' => $nik,
                    'nama_lengkap' => $namaLengkap,
                    'jenis_kelamin' => $jk,
                    'tempat_lahir' => !empty($row['tempat_lahir']) ? trim($row['tempat_lahir']) : null,
                    'tanggal_lahir' => $tanggalLahir,
                    'agama' => $agama,
                    'jenis_pegawai' => $jenisPegawai,
                    'status_kepegawaian' => $statusKepegawaian,
                    'tanggal_masuk' => $tanggalMasuk,
                    'status' => 'aktif',
                    'telepon' => !empty($row['telepon']) ? trim($row['telepon']) : null,
                    'alamat' => !empty($row['alamat']) ? trim($row['alamat']) : null,
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
                ]);

                // 3. Tambahkan riwayat jabatan awal jika kolom jabatan terisi
                if (!empty($row['jabatan'])) {
                    $searchJabatan = trim($row['jabatan']);
                    $jabatan = Jabatan::where('nama', 'like', "%{$searchJabatan}%")->first();
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
