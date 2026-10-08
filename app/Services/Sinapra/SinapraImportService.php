<?php

namespace App\Services\Sinapra;

use App\Models\Sinapra\Gedung;
use App\Models\Sinapra\Ruangan;
use App\Models\Sinapra\Aset;
use App\Models\Sinapra\KategoriAset;
use App\Models\Sinapra\MasterTipeRuangan;
use App\Models\Sinapra\MasterKategoriBhp;
use App\Models\Sinapra\MasterSatuan;
use App\Models\Sinapra\MasterVendor;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class SinapraImportService
{
    /**
     * Map sheet pattern per entity
     */
    protected array $sheetPatterns = [
        'gedung' => ['1_gedung', 'gedung'],
        'tipe-ruangan' => ['2_tipe_ruangan', 'tipe_ruangan', 'tipe-ruangan'],
        'ruangan' => ['3_ruangan', 'ruangan'],
        'kategori-aset' => ['4_kategori_aset', 'kategori_aset', 'kategori-aset'],
        'kategori-bhp' => ['5_kategori_bhp', 'kategori_bhp', 'kategori-bhp'],
        'satuan' => ['6_satuan', 'satuan'],
        'vendor' => ['7_vendor_rekanan', 'vendor_rekanan', 'vendor'],
        'aset' => ['11_inventaris_aset', 'inventaris_aset', 'aset'],
    ];

    /**
     * Import file spreadsheet untuk entity tertentu
     */
    public function import(string $entity, UploadedFile $file, $user = null): array
    {
        $filePath = $file->getRealPath();
        $spreadsheet = IOFactory::load($filePath);

        // Pilih worksheet yang paling sesuai
        $sheet = $this->resolveWorksheet($spreadsheet, $entity);
        $rows = $sheet->toArray(null, true, true, false);

        if (empty($rows) || count($rows) < 2) {
            return [
                'status' => 'error',
                'message' => 'Berkas Excel kosong atau tidak memiliki baris data setelah header.',
                'created' => 0,
                'updated' => 0,
                'failed' => 0,
                'errors' => [],
            ];
        }

        // Ambil header dan bersihkan
        $rawHeaders = array_shift($rows);
        $headers = $this->normalizeHeaders($rawHeaders);

        $created = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];
        $rowNumber = 1; // baris 1 adalah header

        foreach ($rows as $row) {
            $rowNumber++;

            // Abaikan jika seluruh baris kosong
            if ($this->isRowEmpty($row)) {
                continue;
            }

            $mapped = $this->combineRowWithHeaders($headers, $row);

            try {
                $result = DB::transaction(function () use ($entity, $mapped, $rowNumber) {
                    return $this->processEntityRow($entity, $mapped, $rowNumber);
                });

                if ($result === 'created') {
                    $created++;
                } elseif ($result === 'updated') {
                    $updated++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = [
                    'row' => $rowNumber,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Catat Audit Log
        if ($created > 0 || $updated > 0) {
            try {
                AuditLogService::record(
                    'SINAPRA',
                    'import_excel',
                    "sinapra_{$entity}",
                    null,
                    null,
                    [
                        'entity' => $entity,
                        'file_name' => $file->getClientOriginalName(),
                        'created_count' => $created,
                        'updated_count' => $updated,
                        'failed_count' => $failed,
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log import SINAPRA: ' . $e->getMessage());
            }
        }

        return [
            'status' => 'success',
            'message' => "Proses import selesai. Berhasil dibuat: {$created}, diperbarui: {$updated}, gagal: {$failed}.",
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
            'total_processed' => $created + $updated + $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Memproses baris entitas
     */
    protected function processEntityRow(string $entity, array $data, int $rowNumber): string
    {
        return match ($entity) {
            'gedung' => $this->processGedung($data, $rowNumber),
            'tipe-ruangan' => $this->processTipeRuangan($data, $rowNumber),
            'ruangan' => $this->processRuangan($data, $rowNumber),
            'kategori-aset' => $this->processKategoriAset($data, $rowNumber),
            'kategori-bhp' => $this->processKategoriBhp($data, $rowNumber),
            'satuan' => $this->processSatuan($data, $rowNumber),
            'vendor' => $this->processVendor($data, $rowNumber),
            'aset' => $this->processAset($data, $rowNumber),
            default => throw new \InvalidArgumentException("Entitas {$entity} tidak didukung."),
        };
    }

    /**
     * Helper untuk save / update model dengan audit log terstandarisasi (old & new values)
     */
    protected function saveOrUpdateWithAudit(Model $model, array $payload, string $table, bool $isNew): string
    {
        if ($isNew) {
            $model->fill($payload);
            $model->save();

            try {
                AuditLogService::record(
                    'SINAPRA',
                    'create',
                    $table,
                    $model->id,
                    null,
                    $model->toArray()
                );
            } catch (\Throwable $e) {
                Log::warning("Gagal mencatat audit log create {$table}: " . $e->getMessage());
            }

            return 'created';
        }

        $model->fill($payload);
        $dirty = $model->getDirty();
        $oldValues = array_intersect_key($model->getOriginal(), $dirty);
        $model->save();
        $newValues = $model->getChanges();

        if (!empty($newValues)) {
            try {
                AuditLogService::record(
                    'SINAPRA',
                    'update',
                    $table,
                    $model->id,
                    $oldValues,
                    $newValues
                );
            } catch (\Throwable $e) {
                Log::warning("Gagal mencatat audit log update {$table}: " . $e->getMessage());
            }
        }

        return 'updated';
    }

    /**
     * GEDUNG
     */
    protected function processGedung(array $data, int $rowNumber): string
    {
        $kode = trim((string)($data['kode'] ?? $data['kode_gedung'] ?? ''));
        $nama = trim((string)($data['nama'] ?? $data['nama_gedung'] ?? ''));

        if (empty($kode) || empty($nama)) {
            throw new \Exception("Kolom Kode Gedung dan Nama Gedung wajib diisi.");
        }

        $statusRaw = strtolower(trim((string)($data['status'] ?? 'aktif')));
        $status = ($statusRaw === 'nonaktif' || $statusRaw === 'tidak_aktif' || $statusRaw === '0') ? 'tidak_aktif' : (($statusRaw === 'renovasi') ? 'renovasi' : 'aktif');

        $payload = [
            'nama' => $nama,
            'jumlah_lantai' => !empty($data['jumlah_lantai']) ? (int)$data['jumlah_lantai'] : 1,
            'alamat' => $data['alamat'] ?? $data['alamat_kampus'] ?? null,
            'tahun_bangun' => !empty($data['tahun_bangun']) ? (int)$data['tahun_bangun'] : (int)date('Y'),
            'luas_m2' => !empty($data['luas_m2']) ? (float)$data['luas_m2'] : null,
            'status' => $status,
        ];

        $gedung = Gedung::where('kode', $kode)->first();
        if ($gedung) {
            return $this->saveOrUpdateWithAudit($gedung, $payload, 'sinapra_gedung', false);
        }

        $payload['kode'] = $kode;
        $gedung = new Gedung();
        return $this->saveOrUpdateWithAudit($gedung, $payload, 'sinapra_gedung', true);
    }

    /**
     * MASTER TIPE RUANGAN
     */
    protected function processTipeRuangan(array $data, int $rowNumber): string
    {
        $kode = trim((string)($data['kode'] ?? $data['kode_tipe'] ?? ''));
        $nama = trim((string)($data['nama'] ?? $data['nama_tipe_ruangan'] ?? ''));

        if (empty($kode) || empty($nama)) {
            throw new \Exception("Kolom Kode Tipe dan Nama Tipe Ruangan wajib diisi.");
        }

        $isActive = $this->parseBoolean($data['is_active'] ?? $data['status_aktif'] ?? 1);

        $payload = [
            'nama' => $nama,
            'deskripsi' => $data['deskripsi'] ?? $data['deskripsi_fasilitas'] ?? null,
            'urutan' => !empty($data['urutan']) ? (int)$data['urutan'] : 1,
            'is_active' => $isActive,
        ];

        $tipe = MasterTipeRuangan::where('kode', $kode)->first();
        if ($tipe) {
            return $this->saveOrUpdateWithAudit($tipe, $payload, 'sinapra_master_tipe_ruangan', false);
        }

        $payload['kode'] = $kode;
        $tipe = new MasterTipeRuangan();
        return $this->saveOrUpdateWithAudit($tipe, $payload, 'sinapra_master_tipe_ruangan', true);
    }

    /**
     * RUANGAN
     */
    protected function processRuangan(array $data, int $rowNumber): string
    {
        $kode = trim((string)($data['kode'] ?? $data['kode_ruangan'] ?? ''));
        $nama = trim((string)($data['nama'] ?? $data['nama_ruangan'] ?? ''));
        $kodeGedung = trim((string)($data['kode_gedung'] ?? $data['gedung'] ?? ''));
        $kodeTipe = trim((string)($data['kode_tipe_ruangan'] ?? $data['kode_tipe'] ?? $data['tipe_ruangan'] ?? ''));

        if (empty($kode) || empty($nama)) {
            throw new \Exception("Kolom Kode Ruangan dan Nama Ruangan wajib diisi.");
        }

        if (empty($kodeGedung)) {
            throw new \Exception("Kolom Kode Gedung wajib diisi untuk menentukan lokasi ruangan.");
        }

        // Cari Gedung
        $gedung = Gedung::where('kode', $kodeGedung)->orWhere('nama', $kodeGedung)->first();
        if (!$gedung) {
            throw new \Exception("Gedung dengan kode/nama '{$kodeGedung}' tidak ditemukan di database.");
        }

        // Cari Tipe Ruangan (optional)
        $tipeRuanganId = null;
        $tipeObj = null;
        if (!empty($kodeTipe)) {
            $tipeObj = MasterTipeRuangan::where('kode', $kodeTipe)->orWhere('nama', $kodeTipe)->first();
            if ($tipeObj) {
                $tipeRuanganId = $tipeObj->id;
            } elseif (is_numeric($kodeTipe)) {
                $tipeObj = MasterTipeRuangan::find((int)$kodeTipe);
                if ($tipeObj) {
                    $tipeRuanganId = $tipeObj->id;
                }
            }
        } elseif (!empty($data['tipe_ruangan_id'])) {
            $tipeObj = MasterTipeRuangan::find((int)$data['tipe_ruangan_id']);
            if ($tipeObj) {
                $tipeRuanganId = $tipeObj->id;
            }
        }

        // Cari Program Studi (optional)
        $prodiId = null;
        $kodeProdi = trim((string)($data['kode_prodi'] ?? $data['prodi'] ?? $data['program_studi'] ?? ''));
        if (!empty($kodeProdi)) {
            $prodiObj = DB::table('siakad_program_studi')
                ->where('kode_prodi', $kodeProdi)
                ->orWhere('nama', $kodeProdi)
                ->first();
            if ($prodiObj) {
                $prodiId = $prodiObj->id;
            }
        }

        $statusRaw = strtolower(trim((string)($data['status'] ?? 'aktif')));
        $status = in_array($statusRaw, ['nonaktif', 'tidak_aktif', '0']) ? 'tidak_aktif' : ($statusRaw === 'maintenance' ? 'maintenance' : 'aktif');

        $adaAc = $this->parseBoolean($data['ada_ac'] ?? 0);
        $adaProyektor = $this->parseBoolean($data['ada_proyektor'] ?? 0);
        $adaWifi = $this->parseBoolean($data['ada_wifi'] ?? 0);

        // Resolusi nilai kolom tipe legacy (menjamin nilai sah pada MySQL enum maupun kolom string)
        $rawTipe = $data['tipe'] ?? $data['jenis_ruangan'] ?? $data['jenis'] ?? null;
        $tipeValue = $this->resolveLegacyRuanganTipe($rawTipe, $tipeObj, $nama);

        $payload = [
            'gedung_id' => $gedung->id,
            'tipe_ruangan_id' => $tipeRuanganId,
            'program_studi_id' => $prodiId,
            'nama' => $nama,
            'lantai' => !empty($data['lantai']) ? (int)$data['lantai'] : 1,
            'tipe' => $tipeValue,
            'kapasitas' => !empty($data['kapasitas']) ? (int)$data['kapasitas'] : 30,
            'ada_ac' => $adaAc,
            'jumlah_ac' => $adaAc ? (!empty($data['jumlah_ac']) ? (int)$data['jumlah_ac'] : 1) : 0,
            'ada_proyektor' => $adaProyektor,
            'jumlah_proyektor' => $adaProyektor ? (!empty($data['jumlah_proyektor']) ? (int)$data['jumlah_proyektor'] : 1) : 0,
            'ada_wifi' => $adaWifi,
            'jumlah_wifi' => $adaWifi ? (!empty($data['jumlah_wifi']) ? (int)$data['jumlah_wifi'] : 1) : 0,
            'status' => $status,
        ];

        $ruangan = Ruangan::where('kode', $kode)->first();
        if ($ruangan) {
            return $this->saveOrUpdateWithAudit($ruangan, $payload, 'sinapra_ruangan', false);
        }

        $payload['kode'] = $kode;
        $ruangan = new Ruangan();
        return $this->saveOrUpdateWithAudit($ruangan, $payload, 'sinapra_ruangan', true);
    }

    /**
     * Resolusi nilai kolom legacy 'tipe' pada sinapra_ruangan agar kompatibel dengan MySQL ENUM:
     * ('kelas', 'lab', 'aula', 'kantor', 'gudang', 'toilet', 'lainnya')
     */
    protected function resolveLegacyRuanganTipe(?string $rawInput, ?MasterTipeRuangan $tipeObj, string $namaRuangan): string
    {
        $validEnums = ['kelas', 'lab', 'aula', 'kantor', 'gudang', 'toilet', 'lainnya'];
        $cleanInput = strtolower(trim((string)$rawInput));

        if (in_array($cleanInput, $validEnums)) {
            return $cleanInput;
        }

        // Kumpulkan teks konteks dari input, master tipe ruangan, dan nama ruangan
        $context = strtolower(
            $cleanInput . ' ' .
            ($tipeObj ? ($tipeObj->kode . ' ' . $tipeObj->nama) : '') . ' ' .
            $namaRuangan
        );

        if (str_contains($context, 'lab')) {
            return 'lab';
        }

        if (str_contains($context, 'kelas') || str_contains($context, 'kuliah') || str_contains($context, 'teori') || str_contains($context, 'seminar')) {
            return 'kelas';
        }

        if (
            str_contains($context, 'kantor') ||
            str_contains($context, 'office') ||
            str_contains($context, 'biro') ||
            str_contains($context, 'administrasi') ||
            str_contains($context, 'tu') ||
            str_contains($context, 'tata usaha') ||
            str_contains($context, 'rektor') ||
            str_contains($context, 'dekan') ||
            str_contains($context, 'dosen') ||
            str_contains($context, 'pimpinan') ||
            str_contains($context, 'sarpras')
        ) {
            return 'kantor';
        }

        if (str_contains($context, 'aula') || str_contains($context, 'hall') || str_contains($context, 'auditorium') || str_contains($context, 'serbaguna')) {
            return 'aula';
        }

        if (str_contains($context, 'gudang') || str_contains($context, 'storage') || str_contains($context, 'arsip')) {
            return 'gudang';
        }

        if (str_contains($context, 'toilet') || str_contains($context, 'wc') || str_contains($context, 'kamar mandi')) {
            return 'toilet';
        }

        return 'lainnya';
    }

    /**
     * KATEGORI ASET
     */
    protected function processKategoriAset(array $data, int $rowNumber): string
    {
        $kode = trim((string)($data['kode'] ?? $data['kode_kategori'] ?? ''));
        $nama = trim((string)($data['nama'] ?? $data['nama_kategori_aset'] ?? ''));

        if (empty($kode) || empty($nama)) {
            throw new \Exception("Kolom Kode Kategori dan Nama Kategori Aset wajib diisi.");
        }

        $indukId = null;
        $kodeInduk = trim((string)($data['kode_induk'] ?? $data['induk'] ?? ''));
        if (!empty($kodeInduk)) {
            $induk = KategoriAset::where('kode', $kodeInduk)->first();
            if ($induk) {
                $indukId = $induk->id;
            }
        }

        $payload = [
            'induk_id' => $indukId,
            'nama' => $nama,
            'masa_manfaat_tahun' => !empty($data['masa_manfaat_tahun']) ? (int)$data['masa_manfaat_tahun'] : 4,
            'tarif_penyusutan_persen' => !empty($data['tarif_penyusutan_persen']) ? (float)$data['tarif_penyusutan_persen'] : 25.00,
        ];

        $kategori = KategoriAset::where('kode', $kode)->first();
        if ($kategori) {
            return $this->saveOrUpdateWithAudit($kategori, $payload, 'sinapra_kategori_aset', false);
        }

        $payload['kode'] = $kode;
        $kategori = new KategoriAset();
        return $this->saveOrUpdateWithAudit($kategori, $payload, 'sinapra_kategori_aset', true);
    }

    /**
     * MASTER KATEGORI BHP
     */
    protected function processKategoriBhp(array $data, int $rowNumber): string
    {
        $kode = trim((string)($data['kode'] ?? $data['kode_kategori'] ?? ''));
        $nama = trim((string)($data['nama'] ?? $data['nama_kategori_bhp'] ?? ''));

        if (empty($kode) || empty($nama)) {
            throw new \Exception("Kolom Kode Kategori dan Nama Kategori BHP wajib diisi.");
        }

        $isActive = $this->parseBoolean($data['is_active'] ?? $data['status_aktif'] ?? 1);

        $payload = [
            'nama' => $nama,
            'deskripsi' => $data['deskripsi'] ?? $data['deskripsi_kategori'] ?? null,
            'urutan' => !empty($data['urutan']) ? (int)$data['urutan'] : 1,
            'is_active' => $isActive,
        ];

        $kategori = MasterKategoriBhp::where('kode', $kode)->first();
        if ($kategori) {
            return $this->saveOrUpdateWithAudit($kategori, $payload, 'sinapra_master_kategori_bhp', false);
        }

        $payload['kode'] = $kode;
        $kategori = new MasterKategoriBhp();
        return $this->saveOrUpdateWithAudit($kategori, $payload, 'sinapra_master_kategori_bhp', true);
    }

    /**
     * MASTER SATUAN
     */
    protected function processSatuan(array $data, int $rowNumber): string
    {
        $kode = trim((string)($data['kode'] ?? $data['kode_satuan'] ?? ''));
        $nama = trim((string)($data['nama'] ?? $data['nama_satuan'] ?? ''));

        if (empty($kode) || empty($nama)) {
            throw new \Exception("Kolom Kode Satuan dan Nama Satuan wajib diisi.");
        }

        $isActive = $this->parseBoolean($data['is_active'] ?? $data['status_aktif'] ?? 1);

        $payload = [
            'nama' => $nama,
            'keterangan' => $data['keterangan'] ?? $data['keterangan_penggunaan'] ?? null,
            'urutan' => !empty($data['urutan']) ? (int)$data['urutan'] : 1,
            'is_active' => $isActive,
        ];

        $satuan = MasterSatuan::where('kode', $kode)->first();
        if ($satuan) {
            return $this->saveOrUpdateWithAudit($satuan, $payload, 'sinapra_master_satuan', false);
        }

        $payload['kode'] = $kode;
        $satuan = new MasterSatuan();
        return $this->saveOrUpdateWithAudit($satuan, $payload, 'sinapra_master_satuan', true);
    }

    /**
     * MASTER VENDOR
     */
    protected function processVendor(array $data, int $rowNumber): string
    {
        $kode = trim((string)($data['kode'] ?? $data['kode_vendor'] ?? ''));
        $nama = trim((string)($data['nama'] ?? $data['nama_vendor'] ?? $data['nama_vendor_rekanan'] ?? ''));

        if (empty($kode) || empty($nama)) {
            throw new \Exception("Kolom Kode Vendor dan Nama Vendor wajib diisi.");
        }

        $isActive = $this->parseBoolean($data['is_active'] ?? $data['status_aktif'] ?? 1);

        $payload = [
            'nama' => $nama,
            'jenis_rekanan' => $data['jenis_rekanan'] ?? null,
            'alamat' => $data['alamat'] ?? $data['alamat_kantor'] ?? null,
            'telepon' => $data['telepon'] ?? null,
            'email' => $data['email'] ?? null,
            'pic_nama' => $data['pic_nama'] ?? $data['nama_pic'] ?? null,
            'pic_kontak' => $data['pic_kontak'] ?? $data['kontak_pic'] ?? null,
            'nomor_npwp' => $data['nomor_npwp'] ?? null,
            'urutan' => !empty($data['urutan']) ? (int)$data['urutan'] : 1,
            'is_active' => $isActive,
        ];

        $vendor = MasterVendor::where('kode', $kode)->first();
        if ($vendor) {
            return $this->saveOrUpdateWithAudit($vendor, $payload, 'sinapra_master_vendor', false);
        }

        $payload['kode'] = $kode;
        $vendor = new MasterVendor();
        return $this->saveOrUpdateWithAudit($vendor, $payload, 'sinapra_master_vendor', true);
    }

    /**
     * INVENTARIS ASET
     */
    protected function processAset(array $data, int $rowNumber): string
    {
        $kodeAset = trim((string)($data['kode_aset'] ?? $data['kode'] ?? ''));
        $nama = trim((string)($data['nama'] ?? $data['nama_aset'] ?? $data['nama_barang'] ?? ''));
        $kodeKategori = trim((string)($data['kode_kategori'] ?? $data['kategori'] ?? ''));
        $kodeRuangan = trim((string)($data['kode_ruangan'] ?? $data['ruangan'] ?? ''));

        if (empty($kodeAset) || empty($nama)) {
            throw new \Exception("Kolom Kode Aset dan Nama Aset wajib diisi.");
        }

        // Resolusi Kategori
        $kategoriId = null;
        if (!empty($kodeKategori)) {
            $kat = KategoriAset::where('kode', $kodeKategori)->orWhere('nama', $kodeKategori)->first();
            if ($kat) {
                $kategoriId = $kat->id;
            }
        }

        if (!$kategoriId) {
            throw new \Exception("Kategori Aset dengan kode/nama '{$kodeKategori}' tidak ditemukan di database.");
        }

        // Resolusi Ruangan (Lokasi) - opsional jika aset baru belum ditempatkan
        $ruanganId = null;
        $ruang = null;
        if (!empty($kodeRuangan)) {
            $ruang = Ruangan::where('kode', $kodeRuangan)->orWhere('nama', $kodeRuangan)->first();
            if ($ruang) {
                $ruanganId = $ruang->id;
            } else {
                throw new \Exception("Ruangan dengan kode/nama '{$kodeRuangan}' tidak ditemukan di database.");
            }
        }

        $hargaPerolehan = !empty($data['harga_perolehan']) ? (float)$data['harga_perolehan'] : 0.0;
        $nilaiBuku = !empty($data['nilai_buku']) ? (float)$data['nilai_buku'] : $hargaPerolehan;

        $tglPerolehan = null;
        if (!empty($data['tanggal_perolehan'])) {
            $rawDate = (string)$data['tanggal_perolehan'];
            if (is_numeric($rawDate) && (int)$rawDate > 30000) {
                try {
                    $tglPerolehan = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($rawDate)->format('Y-m-d');
                } catch (\Throwable) {
                    $tglPerolehan = date('Y-m-d');
                }
            } else {
                $parsed = strtotime($rawDate);
                $tglPerolehan = ($parsed !== false) ? date('Y-m-d', $parsed) : date('Y-m-d');
            }
        } else {
            $tglPerolehan = date('Y-m-d');
        }

        // Resolusi Kondisi Aset (enum: 'baik', 'rusak_ringan', 'rusak_berat', 'hilang')
        $kondisiRaw = strtolower(trim((string)($data['kondisi'] ?? 'baik')));
        $kondisi = match ($kondisiRaw) {
            'baik', 'bagus', 'good', '1' => 'baik',
            'rusak_ringan', 'ringan' => 'rusak_ringan',
            'rusak_berat', 'berat' => 'rusak_berat',
            'hilang' => 'hilang',
            default => 'baik',
        };

        // Resolusi Status Aset (enum: 'tersedia', 'dipinjam', 'maintenance', 'dihapus')
        $statusRaw = strtolower(trim((string)($data['status'] ?? 'tersedia')));
        $status = match ($statusRaw) {
            'tersedia', 'aktif', 'ready', '1' => 'tersedia',
            'dipinjam', 'pinjam' => 'dipinjam',
            'maintenance', 'dalam_perbaikan', 'rusak', 'perbaikan' => 'maintenance',
            'dihapus', 'dihapuskan', 'disposal', 'nonaktif', 'hilang' => 'dihapus',
            default => 'tersedia',
        };

        $isBorrowable = $this->parseBoolean($data['is_borrowable'] ?? $data['dapat_dipinjam'] ?? 1);
        $isLabAsset = $this->parseBoolean($data['is_lab_asset'] ?? $data['aset_lab'] ?? 0);

        // Resolusi Program Studi (jika diisi atau inherit dari ruangan)
        $prodiId = null;
        $kodeProdi = trim((string)($data['kode_prodi'] ?? $data['prodi'] ?? $data['program_studi'] ?? ''));
        if (!empty($kodeProdi)) {
            $prodiObj = DB::table('siakad_program_studi')
                ->where('kode_prodi', $kodeProdi)
                ->orWhere('nama', $kodeProdi)
                ->first();
            if ($prodiObj) {
                $prodiId = $prodiObj->id;
            }
        } elseif ($ruang && !empty($ruang->program_studi_id)) {
            $prodiId = $ruang->program_studi_id;
        }

        // Resolusi Penanggung Jawab Pegawai (opsional)
        $pegawaiId = null;
        $picRaw = trim((string)($data['penanggung_jawab'] ?? $data['nip_penanggung_jawab'] ?? $data['pic'] ?? ''));
        if (!empty($picRaw)) {
            $pegawai = DB::table('simpeg_pegawai')
                ->where('nip', $picRaw)
                ->orWhere('nama_lengkap', 'like', "%{$picRaw}%")
                ->first();
            if ($pegawai) {
                $pegawaiId = $pegawai->id;
            }
        }

        $payload = [
            'kategori_id' => $kategoriId,
            'ruangan_id' => $ruanganId,
            'program_studi_id' => $prodiId,
            'penanggung_jawab_pegawai_id' => $pegawaiId,
            'nama' => $nama,
            'merk' => $data['merk'] ?? null,
            'model' => $data['model'] ?? null,
            'serial_number' => $data['serial_number'] ?? $data['nomor_seri'] ?? null,
            'tanggal_perolehan' => $tglPerolehan,
            'harga_perolehan' => $hargaPerolehan,
            'nilai_buku' => $nilaiBuku,
            'kondisi' => $kondisi,
            'status' => $status,
            'is_borrowable' => $isBorrowable,
            'is_lab_asset' => $isLabAsset,
        ];

        $aset = Aset::where('kode_aset', $kodeAset)->first();
        if ($aset) {
            return $this->saveOrUpdateWithAudit($aset, $payload, 'sinapra_aset', false);
        }

        $payload['kode_aset'] = $kodeAset;
        $aset = new Aset();
        return $this->saveOrUpdateWithAudit($aset, $payload, 'sinapra_aset', true);
    }

    /**
     * Cari sheet yang paling sesuai
     */
    protected function resolveWorksheet(Spreadsheet $spreadsheet, string $entity)
    {
        $patterns = $this->sheetPatterns[$entity] ?? [$entity];
        $allSheetNames = $spreadsheet->getSheetNames();

        // 1. Coba cari yang cocok persis dengan pattern utama (termasuk nomor urut sheet)
        foreach ($allSheetNames as $sheetName) {
            $normalizedSheet = strtolower(trim($sheetName));
            foreach ($patterns as $pattern) {
                if ($pattern === 'ruangan' && str_contains($normalizedSheet, 'tipe')) {
                    continue;
                }
                if ($pattern === 'aset' && (str_contains($normalizedSheet, 'kategori') || str_contains($normalizedSheet, 'mutasi') || str_contains($normalizedSheet, 'disposal'))) {
                    continue;
                }
                if (str_contains($normalizedSheet, $pattern)) {
                    return $spreadsheet->getSheetByName($sheetName);
                }
            }
        }

        // Jika tidak ada nama sheet yang cocok, kembalikan active sheet (misal saat upload file tunggal/template per modul)
        return $spreadsheet->getActiveSheet();
    }

    /**
     * Normalisasi header kolom
     */
    protected function normalizeHeaders(array $rawHeaders): array
    {
        $normalized = [];
        foreach ($rawHeaders as $idx => $header) {
            $str = trim((string)$header);
            // Hapus isi tanda kurung misalnya (M2), (TAHUN), (ORANG), (1/0), (%)
            $cleaned = preg_replace('/\s*\([^)]*\)/', '', $str);
            // Ubah tanda baca dan spasi jadi underscore (lowercase)
            $key = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $cleaned), '_'));
            $normalized[$idx] = $key;
        }
        return $normalized;
    }

    /**
     * Gabungkan baris data dengan header
     */
    protected function combineRowWithHeaders(array $headers, array $row): array
    {
        $mapped = [];
        foreach ($headers as $idx => $key) {
            if (!empty($key)) {
                $mapped[$key] = isset($row[$idx]) ? trim((string)$row[$idx]) : null;
            }
        }
        return $mapped;
    }

    /**
     * Cek apakah baris kosong semua
     */
    protected function isRowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string)$cell) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Parse nilai boolean fleksibel (1, '1', 'ya', 'true', 'aktif')
     */
    protected function parseBoolean($val): bool
    {
        if (is_bool($val)) {
            return $val;
        }
        $str = strtolower(trim((string)$val));
        return in_array($str, ['1', 'true', 'ya', 'yes', 'aktif', 'y']);
    }

    /**
     * Generate template berkas Excel (.xlsx) per entitas
     */
    public function generateTemplate(string $entity): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(Str::headline($entity));

        $config = $this->getTemplateConfig($entity);
        $headers = $config['headers'];
        $exampleRows = $config['rows'];

        // Tulis Header
        $colIndex = 1;
        foreach ($headers as $header) {
            $sheet->setCellValue([$colIndex, 1], $header);
            $colIndex++;
        }

        // Tulis Contoh Baris
        $rowIndex = 2;
        foreach ($exampleRows as $row) {
            $colIndex = 1;
            foreach ($row as $cell) {
                $sheet->setCellValue([$colIndex, $rowIndex], $cell);
                $colIndex++;
            }
            $rowIndex++;
        }

        // Style Header
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = "A1:{$lastColLetter}1";

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '1E293B'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Auto width kolom
        foreach (range(1, count($headers)) as $col) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * Konfigurasi kolom dan sample rows per entitas template
     */
    protected function getTemplateConfig(string $entity): array
    {
        return match ($entity) {
            'gedung' => [
                'headers' => ['KODE GEDUNG', 'NAMA GEDUNG', 'JUMLAH LANTAI', 'ALAMAT KAMPUS', 'TAHUN BANGUN', 'LUAS (M2)', 'STATUS'],
                'rows' => [
                    ['GDG-A', 'Gedung Rektorat & Akademik', 4, 'Jl. Kampus Terpadu No. 1, Blok A', 2018, 2500.50, 'aktif'],
                    ['GDG-B', 'Gedung Laboratorium Komputer', 3, 'Jl. Kampus Terpadu No. 1, Blok B', 2020, 1800.00, 'aktif'],
                ],
            ],
            'tipe-ruangan' => [
                'headers' => ['KODE TIPE', 'NAMA TIPE RUANGAN', 'DESKRIPSI FASILITAS', 'URUTAN', 'STATUS AKTIF (1/0)'],
                'rows' => [
                    ['LAB_KOMP', 'Laboratorium Komputer', 'Ruang praktikum komputer & multimedia', 1, 1],
                    ['R_KULIAH', 'Ruang Kelas Teori', 'Ruang perkuliahan tatap muka reguler', 2, 1],
                ],
            ],
            'ruangan' => [
                'headers' => ['KODE GEDUNG', 'KODE RUANGAN', 'NAMA RUANGAN', 'LANTAI', 'KODE TIPE RUANGAN', 'KAPASITAS (ORANG)', 'KODE PRODI', 'ADA AC (1/0)', 'JUMLAH AC', 'ADA PROYEKTOR (1/0)', 'JUMLAH PROYEKTOR', 'ADA WIFI (1/0)', 'JUMLAH WIFI', 'STATUS'],
                'rows' => [
                    ['GDG-B', 'R-LAB-01', 'Lab Pemrograman Komputer 1', 1, 'LAB_KOMP', 35, 'TRPL', 1, 2, 1, 1, 1, 2, 'aktif'],
                    ['GDG-A', 'R-KUL-101', 'Ruang Kuliah Teori 101', 1, 'R_KULIAH', 45, '', 1, 2, 1, 1, 1, 1, 'aktif'],
                ],
            ],
            'kategori-aset' => [
                'headers' => ['KODE INDUK', 'KODE KATEGORI', 'NAMA KATEGORI ASET', 'MASA MANFAAT (TAHUN)', 'TARIF PENYUSUTAN (%)'],
                'rows' => [
                    ['', 'KAT-IT', 'Peralatan Komputer & IT', 4, 25.00],
                    ['KAT-IT', 'KAT-PC', 'Personal Computer & Laptop', 4, 25.00],
                    ['', 'KAT-MEBEL', 'Mebel & Perabot Kantor', 8, 12.50],
                ],
            ],
            'kategori-bhp' => [
                'headers' => ['KODE KATEGORI', 'NAMA KATEGORI BHP', 'DESKRIPSI KATEGORI', 'URUTAN', 'STATUS AKTIF (1/0)'],
                'rows' => [
                    ['BHP_KOMP', 'Komponen Komputer & Jaringan', 'Kabel LAN, konektor RJ45, tinta printer, mouse', 1, 1],
                    ['BHP_MEDIS', 'Bahan Habis Pakai Medis', 'Sarung tangan lateks, masker, alkohol 70%', 2, 1],
                ],
            ],
            'satuan' => [
                'headers' => ['KODE SATUAN', 'NAMA SATUAN', 'KETERANGAN PENGGUNAAN', 'URUTAN', 'STATUS AKTIF (1/0)'],
                'rows' => [
                    ['UNIT', 'Unit', 'Peralatan tunggal, komputer, proyektor', 1, 1],
                    ['SET', 'Set', 'Perangkat lengkap meja-kursi, kit alat', 2, 1],
                    ['BOX', 'Box / Kotak', 'Kemasan bahan habis pakai / kertas', 3, 1],
                ],
            ],
            'vendor' => [
                'headers' => ['KODE VENDOR', 'NAMA VENDOR / REKANAN', 'JENIS REKANAN', 'ALAMAT KANTOR', 'TELEPON', 'EMAIL', 'NAMA PIC', 'KONTAK PIC (HP/WA)', 'NOMOR NPWP', 'URUTAN', 'STATUS AKTIF (1/0)'],
                'rows' => [
                    ['VND-001', 'PT Sinergi Teknologi Nusantara', 'Hardware IT & Komputer', 'Jl. Sudirman No. 45, Jakarta', '021-5551234', 'sales@sinergitek.co.id', 'Budi Santoso', '081234567890', '01.234.567.8-012.000', 1, 1],
                ],
            ],
            'aset' => [
                'headers' => ['KODE ASET', 'NAMA ASET', 'KODE KATEGORI', 'KODE RUANGAN', 'KODE PRODI', 'MERK', 'MODEL', 'SERIAL NUMBER', 'TANGGAL PEROLEHAN', 'HARGA PEROLEHAN', 'NILAI BUKU', 'KONDISI', 'STATUS', 'DAPAT DIPINJAM (1/0)', 'ASET LAB (1/0)'],
                'rows' => [
                    ['AST-PC-001', 'PC Workstation Lab Core i7', 'KAT-PC', 'R-LAB-01', 'TRPL', 'Dell', 'OptiPlex 7090', 'SN-DELL-88912', '2025-01-15', 15000000, 15000000, 'baik', 'aktif', 0, 1],
                    ['AST-PRJ-002', 'Proyektor LCD Epson 4000 Lumens', 'KAT-IT', 'R-KUL-101', '', 'Epson', 'EB-X51', 'SN-EPS-3341', '2025-02-10', 7500000, 7500000, 'baik', 'aktif', 1, 0],
                ],
            ],
            default => [
                'headers' => ['KODE', 'NAMA', 'KETERANGAN'],
                'rows' => [],
            ],
        };
    }
}
