<?php

namespace App\Services\Spmb;

use App\Models\Role;
use App\Models\Spmb\MasterProgramStudi;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

/**
 * Import pendaftar dari Excel sistem lama (legacy).
 *
 * Alur dua langkah tanpa penyimpanan file: preview() hanya memvalidasi,
 * import() menyimpan baris yang valid. Gelombang/jalur sengaja dikosongkan.
 */
class SpmbPendaftaranImportService
{
    /** Nilai tetap domain (selaras enum kolom asal_lulusan). */
    private const ASAL_LULUSAN_SEKOLAH = 'sekolah';

    private const SUMBER_IMPORT = 'import';

    private const LABEL_SUDAH_MEMBAYAR = 'sudah membayar';

    /** header ter-normalisasi => kunci internal */
    private const HEADER_MAP = [
        'nodaftar' => 'no_daftar',
        'nama' => 'nama',
        'tempatlahir' => 'tempat_lahir',
        'tgllahir' => 'tanggal_lahir',
        'jeniskelamin' => 'jenis_kelamin',
        'alamat' => 'alamat',
        'telphp' => 'no_hp',
        'email' => 'email',
        'namaorgtua' => 'nama_ortu',
        'alamatorgtua' => 'alamat_ortu',
        'telporgtua' => 'telp_ortu',
        'pekerjaanorgtua' => 'pekerjaan_ortu',
        'sekolah' => 'asal_sekolah',
        'alamatsekolah' => 'alamat_sekolah',
        'jurusansekolah' => 'jurusan_sekolah',
        'tahunlulus' => 'tahun_lulus',
        'programstudi' => 'program_studi',
        'kelas' => 'kelas',
        'infodaftar' => 'info_daftar',
        'ketinfodaftar' => 'ket_info_daftar',
        'onoffline' => 'mode_daftar',
        'tgldaftar' => 'tanggal_daftar',
        'bayarpendaftaran' => 'bayar_pendaftaran',
        'keteranganseleksi' => 'keterangan_seleksi',
        'validasipembayaran' => 'validasi_pembayaran',
        'beasiswa' => 'beasiswa',
    ];

    /** Urutan dan label kolom template (sama dengan ekspor sistem lama). */
    public const TEMPLATE_HEADERS = [
        'No', 'No.Daftar', 'Nama', 'Tempat Lahir', 'Tgl.Lahir', 'Jenis Kelamin', 'Alamat', 'Telp/HP', 'Email',
        'Nama Org Tua', 'Alamat Org Tua', 'Telp. Org Tua', 'Pekerjaan OrgTua', 'Sekolah', 'Alamat Sekolah',
        'Jurusan Sekolah', 'Tahun Lulus', 'Program Studi', 'Kelas', 'Info Daftar', 'Ket. Info Daftar',
        'On/Off-Line', 'Tgl. Daftar', 'Bayar Pendaftaran', 'Keterangan Seleksi', 'Validasi Pembayaran', 'Beasiswa',
    ];

    private const REQUIRED_HEADERS = ['no_daftar', 'nama', 'email', 'program_studi'];

    public function __construct(
        private readonly SpmbPendaftaranManualService $manualService,
    ) {}

    /** Membuat file template kosong; mengembalikan path file sementara (.xlsx). */
    public function buildTemplate(): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Data Pendaftar');
        $sheet->setCellValue('A1', 'DATA PENDAFTARAN');
        $sheet->fromArray(self::TEMPLATE_HEADERS, null, 'A3');
        $sheet->getStyle('A3:AA3')->getFont()->setBold(true);
        foreach (range('A', 'Z') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getColumnDimension('AA')->setAutoSize(true);
        $sheet->freezePane('A4');

        $guide = $book->createSheet()->setTitle('Petunjuk');
        $guide->fromArray([
            ['Petunjuk pengisian'],
            ['Isi data mulai baris ke-4 pada sheet "Data Pendaftar". Jangan mengubah nama kolom.'],
            ['Kolom wajib: No.Daftar, Nama, Email, Program Studi.'],
            ['No.Daftar dan Email harus unik dan belum terdaftar di sistem.'],
            ['Program Studi ditulis "<jenjang>-<nama prodi>", contoh: D3-Farmasi atau D4/S1 Terapan-Produksi Media.'],
            ['Tgl.Lahir dan Tgl. Daftar: format tanggal Excel atau dd-mm-yyyy. Jenis Kelamin: L atau P.'],
            ['Validasi Pembayaran "Sudah Membayar" ditandai lunas; selain itu belum bayar.'],
            ['Gelombang dan Jalur Masuk tidak diisi saat import; tetapkan setelahnya.'],
        ], null, 'A1');
        $guide->getStyle('A1')->getFont()->setBold(true);
        $guide->getColumnDimension('A')->setWidth(110);
        $book->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    /** @return array{summary: array<string,int>, warnings: list<string>, rows: list<array<string,mixed>>} */
    public function preview(string $path): array
    {
        $analysis = $this->analyze($path);

        $rows = array_map(fn (array $row) => [
            'row' => $row['row'],
            'no_daftar' => $row['data']['no_daftar'],
            'nama' => $row['data']['nama'],
            'email' => $row['data']['email'],
            'program_studi' => $row['data']['program_studi'],
            'status' => $row['errors'] === [] ? 'valid' : 'error',
            'errors' => $row['errors'],
        ], $analysis['rows']);

        $errorCount = count(array_filter($rows, fn ($r) => $r['status'] === 'error'));

        return [
            'summary' => [
                'total' => count($rows),
                'valid' => count($rows) - $errorCount,
                'error' => $errorCount,
            ],
            'warnings' => $analysis['warnings'],
            'rows' => $rows,
        ];
    }

    /** @return array{summary: array<string,int>, failed: list<array<string,mixed>>, default_password_used: bool} */
    public function import(string $path, int $adminId, Request $request): array
    {
        $analysis = $this->analyze($path);
        $calonRole = Role::where('slug', Role::SLUG_CALON_MAHASISWA)->first();
        if (! $calonRole) {
            throw ValidationException::withMessages([
                'file' => 'Role calon mahasiswa belum tersedia. Jalankan seeder role sebelum import.',
            ]);
        }

        $passwordHash = Hash::make((string) config('spmb.import.default_password'));
        $importedIds = [];
        $failed = [];

        foreach ($analysis['rows'] as $row) {
            if ($row['errors'] !== []) {
                $failed[] = $this->failedEntry($row, $row['errors']);

                continue;
            }

            try {
                $importedIds[] = DB::transaction(fn () => $this->persistRow($row['data'], $calonRole, $passwordHash, $adminId));
            } catch (Throwable $exception) {
                Log::error('Import pendaftar SPMB gagal pada baris '.$row['row'].': '.$exception->getMessage());
                $failed[] = $this->failedEntry($row, ['Gagal menyimpan baris ini (kemungkinan data duplikat).']);
            }
        }

        $imported = count($importedIds);
        $summary = ['total' => count($analysis['rows']), 'imported' => $imported, 'failed' => count($failed)];

        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'pendaftaran_import',
                tableName: (new PendaftaranCalonMhs)->getTable(),
                newValues: $summary + ['diinput_oleh' => $adminId, 'imported_ids' => $importedIds],
                request: $request,
            );
        } catch (Throwable $exception) {
            Log::warning('Gagal mencatat audit log import pendaftar: '.$exception->getMessage());
        }

        return [
            'summary' => $summary,
            'failed' => $failed,
            'default_password_used' => $imported > 0,
        ];
    }

    /** @param array<string,mixed> $d */
    private function persistRow(array $d, Role $calonRole, string $passwordHash, int $adminId): int
    {
        $user = new User([
            'username' => $this->manualService->generateUsername($d['email']),
            'name' => $d['nama'],
            'email' => $d['email'],
            'password' => $passwordHash,
            'phone' => $d['no_hp'],
            'is_active' => true,
            'is_verified' => true,
        ]);
        $user->save();
        $user->email_verified_at = now();
        $user->save();
        $user->roles()->syncWithoutDetaching([$calonRole->id]);

        $pendaftaran = new PendaftaranCalonMhs([
            'user_id' => $user->id,
            'gelombang_id' => null,
            'program_studi_id' => $d['program_studi_id'],
            'no_pendaftaran' => $d['no_daftar'],
            'nama_lengkap' => $d['nama'],
            'tempat_lahir' => $d['tempat_lahir'],
            'tanggal_lahir' => $d['tanggal_lahir'],
            'jenis_kelamin' => $d['jenis_kelamin'],
            'alamat' => $d['alamat'],
            'no_hp' => $d['no_hp'],
            'nama_ortu' => $d['nama_ortu'],
            'alamat_ortu' => $d['alamat_ortu'],
            'telp_ortu' => $d['telp_ortu'],
            'pekerjaan_ortu' => $d['pekerjaan_ortu'],
            'asal_sekolah' => $d['asal_sekolah'],
            'alamat_sekolah' => $d['alamat_sekolah'],
            'jurusan_sekolah' => $d['jurusan_sekolah'],
            'tahun_lulus' => $d['tahun_lulus'],
            'info_daftar' => $d['info_daftar'],
            'ket_info_daftar' => $d['ket_info_daftar'],
            'status' => PendaftaranCalonMhs::STATUS_SUBMITTED,
            'status_pembayaran' => $d['status_pembayaran'],
            'sumber_pendaftaran' => self::SUMBER_IMPORT,
            'diinput_oleh' => $adminId,
            'data_import' => $d['data_import'],
        ]);
        // asal_lulusan wajib di skema, tetapi tidak ada di Excel; tidak ada di $fillable.
        $pendaftaran->asal_lulusan = self::ASAL_LULUSAN_SEKOLAH;
        if ($d['tanggal_daftar']) {
            $pendaftaran->created_at = $d['tanggal_daftar'];
            $pendaftaran->updated_at = $d['tanggal_daftar'];
        }
        $pendaftaran->save();

        return (int) $pendaftaran->id;
    }

    /** @return array{rows: list<array{row:int,data:array<string,mixed>,errors:list<string>}>, warnings: list<string>} */
    private function analyze(string $path): array
    {
        $sheet = $this->readSheet($path);
        [$headerIndex, $columns] = $this->locateHeader($sheet);

        $prodiMap = $this->prodiMap();
        $rows = [];
        $seenNoDaftar = [];
        $seenEmail = [];

        foreach (array_slice($sheet, $headerIndex + 1, null, true) as $index => $cells) {
            $raw = [];
            foreach ($columns as $key => $col) {
                $raw[$key] = $cells[$col] ?? null;
            }
            if ($this->isBlank($raw)) {
                continue;
            }

            $data = $this->normalizeRow($raw, $prodiMap);
            $errors = $this->validateRow($data, $raw);

            if ($data['no_daftar'] !== '') {
                if (isset($seenNoDaftar[$data['no_daftar']])) {
                    $errors[] = 'No.Daftar duplikat dengan baris '.$seenNoDaftar[$data['no_daftar']].' di file.';
                }
                $seenNoDaftar[$data['no_daftar']] ??= $index + 1;
            }
            if ($data['email'] !== '') {
                if (isset($seenEmail[$data['email']])) {
                    $errors[] = 'Email duplikat dengan baris '.$seenEmail[$data['email']].' di file.';
                }
                $seenEmail[$data['email']] ??= $index + 1;
            }

            $rows[] = ['row' => $index + 1, 'data' => $data, 'errors' => $errors];
        }

        $this->flagExistingRecords($rows);

        return [
            'rows' => $rows,
            'warnings' => [
                'Gelombang dan Jalur Masuk akan dikosongkan untuk seluruh data import. Tetapkan secara manual setelah import.',
                'Akun login dibuat otomatis untuk setiap pendaftar dengan password default. Minta pendaftar segera menggantinya.',
            ],
        ];
    }

    /** @return list<list<mixed>> baris Excel (indeks 0-based) */
    private function readSheet(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);

            return array_values($reader->load($path)->getActiveSheet()->toArray(null, true, false, false));
        } catch (Throwable $exception) {
            Log::warning('File import pendaftar tidak dapat dibaca: '.$exception->getMessage());
            throw ValidationException::withMessages(['file' => 'File Excel tidak dapat dibaca. Pastikan formatnya benar.']);
        }
    }

    /** @return array{0:int, 1:array<string,int>} */
    private function locateHeader(array $sheet): array
    {
        foreach (array_slice($sheet, 0, 20, true) as $index => $cells) {
            $columns = [];
            foreach ($cells as $col => $label) {
                $key = self::HEADER_MAP[$this->normalizeHeader($label)] ?? null;
                if ($key !== null && ! isset($columns[$key])) {
                    $columns[$key] = $col;
                }
            }
            if (array_diff(self::REQUIRED_HEADERS, array_keys($columns)) === []) {
                return [$index, $columns];
            }
        }

        throw ValidationException::withMessages([
            'file' => 'Header tidak dikenali. Kolom wajib: No.Daftar, Nama, Email, Program Studi.',
        ]);
    }

    private function normalizeHeader(mixed $label): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $label));
    }

    /** @param array<string,mixed> $raw @param array<string,int> $prodiMap @return array<string,mixed> */
    private function normalizeRow(array $raw, array $prodiMap): array
    {
        $prodiText = $this->text($raw['program_studi'] ?? null, 255);
        [$jenjang, $namaProdi] = $this->splitProdi($prodiText);
        $tanggalLahir = $this->date($raw['tanggal_lahir'] ?? null);
        $tanggalDaftar = $this->date($raw['tanggal_daftar'] ?? null);
        $jk = strtoupper($this->text($raw['jenis_kelamin'] ?? null, 2));
        $tahunLulus = $this->text($raw['tahun_lulus'] ?? null, 4);

        return [
            'no_daftar' => $this->text($raw['no_daftar'] ?? null, 255),
            'nama' => $this->text($raw['nama'] ?? null, 255),
            'email' => strtolower($this->text($raw['email'] ?? null, 255)),
            'tempat_lahir' => $this->nullable($raw['tempat_lahir'] ?? null, 255),
            'tanggal_lahir' => $tanggalLahir,
            'jenis_kelamin' => $jk !== '' ? $jk : null,
            'alamat' => $this->nullable($raw['alamat'] ?? null, 2000),
            'no_hp' => $this->nullable($raw['no_hp'] ?? null, 30),
            'nama_ortu' => $this->nullable($raw['nama_ortu'] ?? null, 255),
            'alamat_ortu' => $this->nullable($raw['alamat_ortu'] ?? null, 1000),
            'telp_ortu' => $this->nullable($raw['telp_ortu'] ?? null, 30),
            'pekerjaan_ortu' => $this->nullable($raw['pekerjaan_ortu'] ?? null, 100),
            'asal_sekolah' => $this->nullable($raw['asal_sekolah'] ?? null, 255),
            'alamat_sekolah' => $this->nullable($raw['alamat_sekolah'] ?? null, 1000),
            'jurusan_sekolah' => $this->nullable($raw['jurusan_sekolah'] ?? null, 255),
            'tahun_lulus' => ($tahunLulus === '' || $tahunLulus === '0') ? null : $tahunLulus,
            'program_studi' => $prodiText,
            'program_studi_id' => $prodiMap[$this->prodiKey($jenjang, $namaProdi)] ?? null,
            'info_daftar' => $this->nullable($raw['info_daftar'] ?? null, 100),
            'ket_info_daftar' => $this->nullable($raw['ket_info_daftar'] ?? null, 255),
            'status_pembayaran' => mb_strtolower($this->text($raw['validasi_pembayaran'] ?? null, 100)) === self::LABEL_SUDAH_MEMBAYAR
                ? PendaftaranCalonMhs::STATUS_PEMBAYARAN_LUNAS
                : PendaftaranCalonMhs::STATUS_PEMBAYARAN_BELUM,
            'tanggal_daftar' => $tanggalDaftar,
            'data_import' => array_filter([
                'kelas' => $this->nullable($raw['kelas'] ?? null, 100),
                'beasiswa' => $this->nullable($raw['beasiswa'] ?? null, 100),
                'keterangan_seleksi' => $this->nullable($raw['keterangan_seleksi'] ?? null, 100),
                'bayar_pendaftaran' => $this->nullable($raw['bayar_pendaftaran'] ?? null, 100),
                'validasi_pembayaran' => $this->nullable($raw['validasi_pembayaran'] ?? null, 100),
                'mode_daftar' => $this->nullable($raw['mode_daftar'] ?? null, 50),
                'tanggal_daftar' => $tanggalDaftar?->toDateTimeString(),
            ], fn ($v) => $v !== null),
        ];
    }

    /** @param array<string,mixed> $d @param array<string,mixed> $raw @return list<string> */
    private function validateRow(array $d, array $raw): array
    {
        $errors = [];
        if ($d['no_daftar'] === '') {
            $errors[] = 'No.Daftar wajib diisi.';
        }
        if ($d['nama'] === '') {
            $errors[] = 'Nama wajib diisi.';
        }
        if ($d['email'] === '') {
            $errors[] = 'Email wajib diisi.';
        } elseif (! filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        }
        if ($d['program_studi_id'] === null) {
            $errors[] = $d['program_studi'] === ''
                ? 'Program Studi wajib diisi.'
                : 'Program Studi "'.$d['program_studi'].'" tidak ditemukan atau tidak aktif.';
        }
        if ($d['jenis_kelamin'] !== null && ! in_array($d['jenis_kelamin'], ['L', 'P'], true)) {
            $errors[] = 'Jenis Kelamin harus L atau P.';
        }
        if ($d['tanggal_lahir'] === null && $this->text($raw['tanggal_lahir'] ?? null, 50) !== '') {
            $errors[] = 'Format Tgl.Lahir tidak dikenali.';
        }
        if ($d['tanggal_lahir'] !== null && $d['tanggal_lahir']->isFuture()) {
            $errors[] = 'Tgl.Lahir tidak boleh di masa depan.';
        }

        return $errors;
    }

    /** Tandai baris yang sudah ada di database (satu query per jenis). @param list<array<string,mixed>> $rows */
    private function flagExistingRecords(array &$rows): void
    {
        $noDaftar = array_values(array_filter(array_map(fn ($r) => $r['data']['no_daftar'], $rows)));
        $emails = array_values(array_filter(array_map(fn ($r) => $r['data']['email'], $rows)));

        $existingNo = PendaftaranCalonMhs::withTrashed()->whereIn('no_pendaftaran', $noDaftar)
            ->pluck('no_pendaftaran')->flip();
        $existingEmail = User::withTrashed()->whereIn('email', $emails)->pluck('email')
            ->map(fn ($e) => strtolower($e))->flip();

        foreach ($rows as &$row) {
            if ($existingNo->has($row['data']['no_daftar'])) {
                $row['errors'][] = 'No.Daftar sudah terdaftar di sistem.';
            }
            if ($existingEmail->has($row['data']['email'])) {
                $row['errors'][] = 'Email sudah digunakan akun lain.';
            }
        }
    }

    /** @return array<string,int> kunci "jenjang|nama" => id prodi aktif */
    private function prodiMap(): array
    {
        $map = [];
        foreach (MasterProgramStudi::query()->where('is_active', true)->get(['id', 'nama', 'jenjang']) as $prodi) {
            $map[$this->prodiKey((string) $prodi->jenjang, (string) $prodi->nama)] = (int) $prodi->id;
        }

        return $map;
    }

    /** "D4/S1 Terapan-Produksi Media" => ["D4", "Produksi Media"] */
    private function splitProdi(string $text): array
    {
        if (! preg_match('/^\s*([^-]+?)\s*-\s*(.+)$/u', $text, $m)) {
            return ['', $text];
        }

        return [preg_split('/[\/\s]/', trim($m[1]))[0] ?? '', $m[2]];
    }

    private function prodiKey(string $jenjang, string $nama): string
    {
        return mb_strtolower(trim($jenjang)).'|'.mb_strtolower(preg_replace('/\s+/', ' ', trim($nama)));
    }

    private function text(mixed $value, int $max): string
    {
        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        return mb_substr(trim((string) $value), 0, $max);
    }

    private function nullable(mixed $value, int $max): ?string
    {
        $text = $this->text($value, $max);

        return $text === '' ? null : $text;
    }

    private function date(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
            }

            return Carbon::parse(str_replace('/', '-', trim((string) $value)));
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string,mixed> $raw */
    private function isBlank(array $raw): bool
    {
        foreach ($raw as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @param array{row:int,data:array<string,mixed>} $row @param list<string> $errors @return array<string,mixed> */
    private function failedEntry(array $row, array $errors): array
    {
        return [
            'row' => $row['row'],
            'no_daftar' => $row['data']['no_daftar'],
            'nama' => $row['data']['nama'],
            'errors' => $errors,
        ];
    }
}
