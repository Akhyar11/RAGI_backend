<?php

namespace App\Services\Mobile;

use App\Models\Simpeg\DokumenPegawai;
use App\Models\Simpeg\GajiPegawai;
use App\Models\Simpeg\IzinJamKerja;
use App\Models\Simpeg\MasterJenisCuti;
use App\Models\Simpeg\PengajuanCuti;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\RiwayatPelatihan;
use App\Models\Simpeg\RiwayatTes;
use App\Models\Simpeg\SertifikasiDosen;
use App\Models\Simpeg\SkPegawai;
use App\Models\Simpeg\SuratTugas;
use App\Models\User;
use App\Services\Storage\FileStorageService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Self-service SIMPEG, detail payroll, dan e-file ARSIP untuk myInU-Staff.
 * Dipisahkan dari MobileStaffService agar gateway lama tetap ringkas.
 */
class MobileStaffSimpegService
{
    public function __construct(
        protected ?FileStorageService $fileStorageService = null
    ) {
        $this->fileStorageService = $fileStorageService ?? app(FileStorageService::class);
    }

    /** Format meta paginator, identik dengan MobileStaffService::formatMeta(). */
    private function formatMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    // =========================================================================
    // GATEWAY SIMPEG SELF-SERVICE, PAYROLL DETAIL & ARSIP E-FILE (myInU-Staff)
    // =========================================================================

    /**
     * Resolve Pegawai milik akun login. Tidak ada fallback ke record arbitrer.
     */
    protected function resolvePegawai(User $user): ?Pegawai
    {
        return $user->pegawai ?? Pegawai::where('user_id', $user->id)->first();
    }

    /**
     * Meta paginator kosong untuk akun yang belum terhubung dengan data pegawai.
     */
    protected function emptyMobilePaginator(int $perPage): array
    {
        return [
            'items' => [],
            'meta' => [
                'current_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'last_page' => 1,
                'from' => null,
                'to' => null,
            ],
        ];
    }

    /** URL berkas privat selalu lewat signed URL terpusat. */
    protected function mobileFileUrl(?string $path): ?string
    {
        return $path ? $this->fileStorageService->signedUrl($path) : null;
    }

    /**
     * Susun metadata audit untuk dicatat controller (satu jejak per mutasi).
     */
    private function mobileAudit(string $tableName, int $recordId, ?array $newValues, ?array $oldValues = null, string $action = 'create'): array
    {
        return [
            'module' => 'SIMPEG',
            'action' => $action,
            'table_name' => $tableName,
            'record_id' => $recordId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ];
    }

    /** Format ukuran berkas yang dibaca manusia (mis. "1.23 MB"). */
    protected function formatMobileFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, '.', '') . ' MB';
        }

        return number_format(max($bytes, 0) / 1024, 2, '.', '') . ' KB';
    }

    // SIMPEG: Cuti

    /** Turunkan enum legacy dari `kode` master; kode tanpa padanan ditolak (bukan default asumtif). */
    protected function resolveJenisCutiEnum(MasterJenisCuti $master): string
    {
        $kode = strtolower(str_replace(['-', ' '], '_', trim((string) $master->kode)));
        $legacyValue = preg_replace('/^(cuti|izin)_/', '', $kode);

        if (!in_array($legacyValue, PengajuanCuti::JENIS_CUTI, true)) {
            throw new \DomainException(
                'Jenis cuti pada master belum memiliki padanan kode enum legacy yang valid.'
            );
        }

        return $legacyValue;
    }

    public function getMobileCuti(User $user, ?Request $request = null): array
    {
        $pegawai = $this->resolvePegawai($user);
        $perPage = min(100, max(1, (int) ($request?->integer('per_page', 15) ?? 15)));
        $sortOrder = strtolower($request?->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (!$pegawai) {
            return $this->emptyMobilePaginator($perPage);
        }

        $query = PengajuanCuti::with(['masterJenisCuti', 'approver'])
            ->where('pegawai_id', $pegawai->id);

        if ($request?->filled('status')) {
            $query->where('status_approval', $request->status);
        }
        if ($request?->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('alasan', 'like', "%{$search}%")
                  ->orWhereHas('masterJenisCuti', fn($sub) => $sub->where('nama', 'like', "%{$search}%"));
            });
        }

        $paginator = $query->orderBy('created_at', $sortOrder)->paginate($perPage);

        $items = $paginator->getCollection()->map(function ($c) {
            return [
                'id' => (string) $c->id,
                'title' => $c->masterJenisCuti?->nama ?: ($c->jenis_cuti ?: 'Pengajuan Cuti'),
                'subtitle' => $c->tanggal_mulai?->format('Y-m-d') . ' s/d ' . $c->tanggal_selesai?->format('Y-m-d'),
                'status' => $c->status_approval ?: 'pending',
                'date' => $c->created_at?->format('Y-m-d') ?? '',
                'amount' => '',
                'jenis_cuti' => $c->masterJenisCuti?->nama ?: $c->jenis_cuti,
                'tanggal_mulai' => $c->tanggal_mulai?->format('Y-m-d') ?? '',
                'tanggal_selesai' => $c->tanggal_selesai?->format('Y-m-d') ?? '',
                'jumlah_hari' => $c->jumlah_hari,
                'alasan' => $c->alasan,
                'catatan_approval' => $c->catatan_approval,
                'file_url' => $this->mobileFileUrl($c->file_pendukung),
                'raw' => $c->toArray(),
            ];
        })->values()->all();

        return ['items' => $items, 'meta' => $this->formatMeta($paginator)];
    }

    public function storeMobileCuti(User $user, array $data, $file = null): array
    {
        $pegawai = $this->resolvePegawai($user);
        if (!$pegawai) {
            throw new \DomainException('Akun Anda belum terhubung dengan data pegawai.');
        }

        $master = MasterJenisCuti::findOrFail((int) $data['master_jenis_cuti_id']);
        $payload = [
            'pegawai_id' => $pegawai->id,
            'master_jenis_cuti_id' => $master->id,
            // Kolom legacy required; turunkan dari master.
            'jenis_cuti' => $this->resolveJenisCutiEnum($master),
            'tanggal_mulai' => $data['tanggal_mulai'],
            'tanggal_selesai' => $data['tanggal_selesai'],
            'jumlah_hari' => (int) ($data['jumlah_hari'] ?? (Carbon::parse($data['tanggal_mulai'])->diffInDays(Carbon::parse($data['tanggal_selesai'])) + 1)),
            'alasan' => $data['alasan'],
            'status_approval' => 'pending',
        ];

        if ($file instanceof UploadedFile) {
            $payload['file_pendukung'] = $this->fileStorageService->store($file, 'simpeg/cuti', private: true);
        }

        $cuti = new PengajuanCuti($payload);
        $cuti->save();

        return [
            'id' => (string) $cuti->id,
            'title' => $cuti->masterJenisCuti?->nama ?: 'Pengajuan Cuti',
            'subtitle' => $cuti->tanggal_mulai?->format('Y-m-d') . ' s/d ' . $cuti->tanggal_selesai?->format('Y-m-d'),
            'status' => $cuti->status_approval,
            'date' => $cuti->created_at?->format('Y-m-d') ?? now()->toDateString(),
            'amount' => '',
            'file_url' => $this->mobileFileUrl($cuti->file_pendukung),
            'raw' => $cuti->load('masterJenisCuti')->toArray(),
            'audit' => $this->mobileAudit('simpeg_pengajuan_cuti', $cuti->id, $cuti->only([
                'pegawai_id', 'master_jenis_cuti_id', 'tanggal_mulai', 'tanggal_selesai',
                'jumlah_hari', 'alasan', 'status_approval'
            ])),
        ];
    }

    // SIMPEG: Izin Jam Kerja

    public function getMobileIzinKerja(User $user, ?Request $request = null): array
    {
        $pegawai = $this->resolvePegawai($user);
        $perPage = min(100, max(1, (int) ($request?->integer('per_page', 15) ?? 15)));
        $sortOrder = strtolower($request?->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (!$pegawai) {
            return $this->emptyMobilePaginator($perPage);
        }

        $query = IzinJamKerja::with(['jenisIzin', 'approver'])
            ->where('pegawai_id', $pegawai->id);

        if ($request?->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request?->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('alasan', 'like', "%{$search}%")
                  ->orWhereHas('jenisIzin', fn($sub) => $sub->where('nama', 'like', "%{$search}%"));
            });
        }

        $paginator = $query->orderBy('created_at', $sortOrder)->paginate($perPage);

        $items = $paginator->getCollection()->map(function ($i) {
            return [
                'id' => (string) $i->id,
                'title' => $i->jenisIzin?->nama ?: 'Izin Jam Kerja',
                'subtitle' => $i->tanggal?->format('Y-m-d') . ' (' . $i->jam_mulai . ' - ' . $i->jam_selesai . ')',
                'status' => $i->status ?: 'menunggu',
                'date' => $i->created_at?->format('Y-m-d') ?? '',
                'amount' => '',
                'alasan' => $i->alasan,
                'catatan_approval' => $i->catatan_approval,
                'file_url' => $this->mobileFileUrl($i->file_bukti),
                'raw' => $i->toArray(),
            ];
        })->values()->all();

        return ['items' => $items, 'meta' => $this->formatMeta($paginator)];
    }

    public function storeMobileIzinKerja(User $user, array $data, $file = null): array
    {
        $pegawai = $this->resolvePegawai($user);
        if (!$pegawai) {
            throw new \DomainException('Akun Anda belum terhubung dengan data pegawai.');
        }

        $payload = [
            'pegawai_id' => $pegawai->id,
            'master_jenis_izin_id' => (int) $data['master_jenis_izin_id'],
            'tanggal' => $data['tanggal'],
            'jam_mulai' => $data['jam_mulai'],
            'jam_selesai' => $data['jam_selesai'],
            'alasan' => $data['alasan'],
            'status' => 'menunggu',
        ];

        if ($file instanceof UploadedFile) {
            $payload['file_bukti'] = $this->fileStorageService->store($file, 'simpeg/izin-kerja', private: true);
        }

        $izin = new IzinJamKerja($payload);
        $izin->save();

        return [
            'id' => (string) $izin->id,
            'title' => $izin->jenisIzin?->nama ?: 'Izin Jam Kerja',
            'subtitle' => $izin->tanggal?->format('Y-m-d') . ' (' . $izin->jam_mulai . ' - ' . $izin->jam_selesai . ')',
            'status' => $izin->status,
            'date' => $izin->created_at?->format('Y-m-d') ?? now()->toDateString(),
            'amount' => '',
            'file_url' => $this->mobileFileUrl($izin->file_bukti),
            'raw' => $izin->load('jenisIzin')->toArray(),
            'audit' => $this->mobileAudit('simpeg_izin_jam_kerja', $izin->id, $izin->only([
                'pegawai_id', 'master_jenis_izin_id', 'tanggal', 'jam_mulai', 'jam_selesai', 'alasan', 'status'
            ])),
        ];
    }

    // SIMPEG: Surat Tugas & LPJ

    public function getMobileSuratTugas(User $user, ?Request $request = null): array
    {
        $pegawai = $this->resolvePegawai($user);
        $perPage = min(100, max(1, (int) ($request?->integer('per_page', 15) ?? 15)));
        $sortOrder = strtolower($request?->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (!$pegawai) {
            return $this->emptyMobilePaginator($perPage);
        }

        $query = SuratTugas::where('pegawai_id', $pegawai->id);

        if ($request?->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request?->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_kegiatan', 'like', "%{$search}%")
                  ->orWhere('nomor_surat', 'like', "%{$search}%")
                  ->orWhere('lokasi_tujuan', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderBy('created_at', $sortOrder)->paginate($perPage);

        $items = $paginator->getCollection()->map(function ($t) {
            return [
                'id' => (string) $t->id,
                'title' => $t->nama_kegiatan ?: 'Surat Tugas',
                'subtitle' => ($t->nomor_surat ?: 'Draft') . ($t->lokasi_tujuan ? ' | ' . $t->lokasi_tujuan : ''),
                'status' => $t->status ?: 'diajukan',
                'date' => $t->tanggal_mulai?->format('Y-m-d') ?? ($t->created_at?->format('Y-m-d') ?? ''),
                'amount' => (float) $t->estimasi_biaya > 0 ? 'Rp ' . number_format((float) $t->estimasi_biaya, 0, ',', '.') : '',
                'nomor_surat' => $t->nomor_surat,
                'file_surat_tugas_url' => $this->mobileFileUrl($t->file_surat_tugas),
                'file_lpj_url' => $this->mobileFileUrl($t->file_lpj),
                'raw' => collect($t->toArray())->except(['file_surat_tugas', 'file_lpj'])->all(),
            ];
        })->values()->all();

        return ['items' => $items, 'meta' => $this->formatMeta($paginator)];
    }

    public function storeMobileSuratTugas(User $user, array $data, $file = null): array
    {
        $pegawai = $this->resolvePegawai($user);
        if (!$pegawai) {
            throw new \DomainException('Akun Anda belum terhubung dengan data pegawai.');
        }

        $payload = [
            'pegawai_id' => $pegawai->id,
            'kategori_kegiatan_id' => (int) $data['kategori_kegiatan_id'],
            'jenis_transportasi_id' => (int) $data['jenis_transportasi_id'],
            'nama_kegiatan' => $data['nama_kegiatan'],
            'jam_pelaksanaan' => $data['jam_pelaksanaan'] ?? null,
            'penyelenggara' => $data['penyelenggara'] ?? null,
            'tempat_berangkat' => $data['tempat_berangkat'],
            'lokasi_tujuan' => $data['lokasi_tujuan'],
            'tanggal_berangkat' => $data['tanggal_berangkat'],
            'tanggal_kembali' => $data['tanggal_kembali'],
            'tanggal_mulai' => $data['tanggal_mulai'],
            'tanggal_selesai' => $data['tanggal_selesai'],
            'maksud_tujuan' => $data['maksud_tujuan'],
            'estimasi_biaya' => $data['estimasi_biaya'] ?? null,
            'nama_bank' => $pegawai->nama_bank,
            'nomor_rekening' => $pegawai->nomor_rekening,
            'nama_rekening' => $pegawai->nama_rekening ?: $pegawai->nama_lengkap,
            'keterangan' => $data['keterangan'] ?? null,
            'status' => 'diajukan',
        ];

        if ($file instanceof UploadedFile) {
            $payload['file_surat_tugas'] = $this->fileStorageService->store($file, 'simpeg/surat-tugas', private: true);
        }

        $surat = new SuratTugas($payload);
        $surat->save();

        return [
            'id' => (string) $surat->id,
            'title' => $surat->nama_kegiatan,
            'subtitle' => ($surat->nomor_surat ?: 'Draft') . ' | ' . $surat->lokasi_tujuan,
            'status' => $surat->status,
            'date' => $surat->created_at?->format('Y-m-d') ?? now()->toDateString(),
            'amount' => (float) $surat->estimasi_biaya > 0 ? 'Rp ' . number_format((float) $surat->estimasi_biaya, 0, ',', '.') : '',
            'file_surat_tugas_url' => $this->mobileFileUrl($surat->file_surat_tugas),
            'raw' => $surat->toArray(),
            'audit' => $this->mobileAudit('simpeg_surat_tugas', $surat->id, $surat->only([
                'pegawai_id', 'kategori_kegiatan_id', 'jenis_transportasi_id', 'nama_kegiatan',
                'tanggal_mulai', 'tanggal_selesai', 'estimasi_biaya', 'status'
            ])),
        ];
    }

    /** Unggah LPJ: hanya penanggung jawab dan hanya surat tugas disetujui/selesai. */
    public function uploadMobileLpj(User $user, int $suratTugasId, array $data, $file): array
    {
        $pegawai = $this->resolvePegawai($user);
        $surat = SuratTugas::findOrFail($suratTugasId);

        // Hak akses endpoint sudah diverifikasi via Gate; di sini tegakkan
        // kepemilikan baris: LPJ hanya untuk penanggung jawab surat tugas ini.
        if (!$pegawai || $surat->pegawai_id !== $pegawai->id) {
            throw new AuthorizationException('Hanya penanggung jawab kegiatan yang berhak mengunggah berkas LPJ.');
        }

        if (!in_array($surat->status, ['disetujui', 'selesai'], true)) {
            throw new \DomainException('LPJ hanya dapat diunggah untuk surat tugas yang telah disetujui.');
        }

        $filePath = $this->fileStorageService->store($file, 'simpeg/surat-tugas/lpj', private: true);

        $lpjFields = [
            'file_lpj',
            'laporan_kegiatan',
            'biaya_realisasi',
            'tanggal_upload_lpj',
            'status_pencairan',
        ];
        // Snapshot nilai lama SEBELUM update.
        $oldValues = array_intersect_key($surat->getOriginal(), array_flip($lpjFields));
        $surat->update([
            'file_lpj' => $filePath,
            'laporan_kegiatan' => $data['laporan_kegiatan'] ?? $surat->laporan_kegiatan,
            'biaya_realisasi' => array_key_exists('biaya_realisasi', $data) && $data['biaya_realisasi'] !== ''
                ? $data['biaya_realisasi']
                : $surat->biaya_realisasi,
            'tanggal_upload_lpj' => now(),
            'status_pencairan' => 'lpj_diunggah',
        ]);

         return [
            'id' => (string) $surat->id,
            'title' => $surat->nama_kegiatan,
            'subtitle' => ($surat->nomor_surat ?: 'Surat Tugas') . ' - LPJ',
            'status' => $surat->status,
            'date' => $surat->created_at?->format('Y-m-d') ?? '',
            'amount' => (float) $surat->biaya_realisasi > 0 ? 'Rp ' . number_format((float) $surat->biaya_realisasi, 0, ',', '.') : '',
            'file_lpj_url' => $this->mobileFileUrl($surat->file_lpj),
            'raw' => $surat->toArray(),
            'audit' => $this->mobileAudit(
                'simpeg_surat_tugas',
                $surat->id,
                $surat->getChanges(),
                $oldValues,
                'upload_lpj'
            ),
        ];
    }

    // SIMPEG: SK Pegawai & Kompetensi

    public function getMobileSkPegawai(User $user, ?Request $request = null): array
    {
        $pegawai = $this->resolvePegawai($user);
        $perPage = min(100, max(1, (int) ($request?->integer('per_page', 15) ?? 15)));
        $sortOrder = strtolower($request?->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (!$pegawai) {
            return $this->emptyMobilePaginator($perPage);
        }

        $query = SkPegawai::with('kategoriSk')->where('pegawai_id', $pegawai->id);

        if ($request?->filled('status')) {
            $query->where('status_verifikasi', $request->status);
        }
        if ($request?->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('judul_sk', 'like', "%{$search}%")
                  ->orWhere('nomor_sk', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderBy('created_at', $sortOrder)->paginate($perPage);

        $items = $paginator->getCollection()->map(function ($s) {
            return [
                'id' => (string) $s->id,
                'title' => $s->judul_sk,
                'subtitle' => ($s->nomor_sk ?: 'SK') . ($s->kategoriSk?->nama ? ' | ' . $s->kategoriSk->nama : ''),
                'status' => $s->status_verifikasi ?: 'pending',
                'date' => $s->tanggal_sk?->format('Y-m-d') ?? ($s->created_at?->format('Y-m-d') ?? ''),
                'amount' => '',
                'file_url' => $this->mobileFileUrl($s->file_sk),
                'raw' => $s->toArray(),
            ];
        })->values()->all();

        return ['items' => $items, 'meta' => $this->formatMeta($paginator)];
    }

    public function storeMobileSkPegawai(User $user, array $data, $file): array
    {
        $pegawai = $this->resolvePegawai($user);
        if (!$pegawai) {
            throw new \DomainException('Akun Anda belum terhubung dengan data pegawai.');
        }

        $sk = new SkPegawai([
            'pegawai_id' => $pegawai->id,
            'kategori_sk_id' => (int) $data['kategori_sk_id'],
            'nomor_sk' => $data['nomor_sk'],
            'judul_sk' => $data['judul_sk'],
            'tanggal_sk' => $data['tanggal_sk'],
            'tmt_sk' => $data['tmt_sk'],
            'tmt_selesai' => $data['tmt_selesai'] ?? null,
            'pejabat_penetap' => $data['pejabat_penetap'],
            'file_sk' => $this->fileStorageService->store($file, 'simpeg/sk-pegawai', private: true),
            'keterangan' => $data['keterangan'] ?? null,
            'status_verifikasi' => 'pending',
        ]);
        $sk->save();

        return [
            'id' => (string) $sk->id,
            'title' => $sk->judul_sk,
            'subtitle' => ($sk->nomor_sk ?: 'SK') . ' | ' . ($sk->kategoriSk?->nama ?? 'SK Pegawai'),
            'status' => $sk->status_verifikasi,
            'date' => $sk->tanggal_sk?->format('Y-m-d') ?? now()->toDateString(),
            'amount' => '',
            'file_url' => $this->mobileFileUrl($sk->file_sk),
            'raw' => $sk->toArray(),
            'audit' => $this->mobileAudit('simpeg_sk_pegawai', $sk->id, $sk->only([
                'pegawai_id', 'kategori_sk_id', 'nomor_sk', 'judul_sk', 'tanggal_sk',
                'tmt_sk', 'tmt_selesai', 'pejabat_penetap', 'status_verifikasi'
            ])),
        ];
    }

    /** Kompetensi pegawai: sertifikasi / tes / pelatihan milik akun login. */
    public function getMobileKompetensi(User $user, ?Request $request = null): array
    {
        $pegawai = $this->resolvePegawai($user);
        $perPage = min(100, max(1, (int) ($request?->integer('per_page', 15) ?? 15)));
        $sortOrder = strtolower($request?->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $jenis = strtolower((string) $request?->query('jenis', 'sertifikasi'));

        if (!$pegawai) {
            return $this->emptyMobilePaginator($perPage);
        }

        if ($jenis === 'tes') {
            $query = RiwayatTes::with('jenisTes')->where('pegawai_id', $pegawai->id);
            if ($request?->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nama_tes', 'like', "%{$search}%")
                      ->orWhere('penyelenggara', 'like', "%{$search}%");
                });
            }
            $paginator = $query->orderBy('created_at', $sortOrder)->paginate($perPage);
            $items = $paginator->getCollection()->map(function ($t) {
                return [
                    'id' => 'TES-' . $t->id,
                    'title' => $t->nama_tes,
                    'subtitle' => ($t->jenisTes?->nama ?: 'Tes') . ($t->skor !== null ? ' | Skor: ' . $t->skor : ''),
                    'status' => $t->masa_berlaku && $t->masa_berlaku->isPast() ? 'kedaluwarsa' : 'aktif',
                    'date' => $t->tahun ? (string) $t->tahun : ($t->created_at?->format('Y-m-d') ?? ''),
                    'amount' => '',
                    'file_url' => $this->mobileFileUrl($t->file_path),
                    'raw' => $t->toArray(),
                ];
            })->values()->all();

            return ['items' => $items, 'meta' => $this->formatMeta($paginator)];
        }

        if ($jenis === 'pelatihan') {
            $query = RiwayatPelatihan::with(['jenisPelatihan', 'peran', 'tingkat'])
                ->where('pegawai_id', $pegawai->id);
            if ($request?->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nama_kegiatan', 'like', "%{$search}%")
                      ->orWhere('penyelenggara', 'like', "%{$search}%")
                      ->orWhere('nomor_sertifikat', 'like', "%{$search}%");
                });
            }
            $paginator = $query->orderBy('created_at', $sortOrder)->paginate($perPage);
            $items = $paginator->getCollection()->map(function ($p) {
                return [
                    'id' => 'PEL-' . $p->id,
                    'title' => $p->nama_kegiatan,
                    'subtitle' => ($p->jenisPelatihan?->nama ?: 'Pelatihan') . ($p->tingkat?->nama ? ' | ' . $p->tingkat->nama : ''),
                    'status' => $p->nomor_sertifikat ? 'tersertifikasi' : 'diikuti',
                    'date' => $p->tanggal_mulai?->format('Y-m-d') ?? ($p->created_at?->format('Y-m-d') ?? ''),
                    'amount' => '',
                    'file_url' => $this->mobileFileUrl($p->file_path),
                    'raw' => $p->toArray(),
                ];
            })->values()->all();

            return ['items' => $items, 'meta' => $this->formatMeta($paginator)];
        }

        $query = SertifikasiDosen::with('jenisSertifikasi')->where('pegawai_id', $pegawai->id);
        if ($request?->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_sertifikat', 'like', "%{$search}%")
                  ->orWhere('bidang_studi', 'like', "%{$search}%")
                  ->orWhere('nomor_registrasi', 'like', "%{$search}%")
                  ->orWhere('penyelenggara', 'like', "%{$search}%");
            });
        }
        $paginator = $query->orderBy('created_at', $sortOrder)->paginate($perPage);
        $items = $paginator->getCollection()->map(function ($s) {
            return [
                'id' => 'SER-' . $s->id,
                'title' => $s->nama_sertifikat,
                'subtitle' => ($s->jenisSertifikasi?->nama ?: 'Sertifikasi') . ($s->bidang_studi ? ' | ' . $s->bidang_studi : ''),
                'status' => $s->file_path || $s->tautan ? 'terdokumentasi' : 'tercatat',
                'date' => $s->tahun_sertifikasi ? (string) $s->tahun_sertifikasi : ($s->created_at?->format('Y-m-d') ?? ''),
                'amount' => '',
                'file_url' => $this->mobileFileUrl($s->file_path),
                'raw' => $s->toArray(),
            ];
        })->values()->all();

        return ['items' => $items, 'meta' => $this->formatMeta($paginator)];
    }

    // SIKEU: Detail & slip payroll (PDF dihasilkan on demand)

    /** Ambil data gaji milik akun login; 404 bila bukan milik pegawai tersebut. */
    protected function resolvePayrollForUser(User $user, int $id): GajiPegawai
    {
        $pegawai = $this->resolvePegawai($user);
        if (!$pegawai) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())
                ->setModel(GajiPegawai::class, [$id]);
        }

        return GajiPegawai::with(['details', 'pegawai'])
            ->where('pegawai_id', $pegawai->id)
            ->findOrFail($id);
    }

    public function getSikeuPayrollDetail(User $user, int $id): array
    {
        $gaji = $this->resolvePayrollForUser($user, $id);
        $details = $gaji->details;

        return [
            'id' => (string) $gaji->id,
            'title' => 'Slip Gaji Periode ' . $gaji->periode_bulan_tahun,
            'subtitle' => 'Gaji Pokok: Rp ' . number_format((float) $gaji->gaji_pokok, 0, ',', '.')
                . ' | Potongan: Rp ' . number_format((float) $gaji->total_potongan, 0, ',', '.'),
            'status' => $gaji->status_transfer ?: 'terbit',
            'date' => $gaji->tanggal_transfer ? Carbon::parse($gaji->tanggal_transfer)->format('Y-m-d') : ($gaji->created_at?->format('Y-m-d') ?? ''),
            'amount' => 'Rp ' . number_format((float) $gaji->gaji_bersih, 0, ',', '.'),
            'periode' => $gaji->periode_bulan_tahun,
            'gaji_pokok' => (string) $gaji->gaji_pokok,
            'total_tunjangan' => (string) $gaji->total_tunjangan,
            'total_potongan' => (string) $gaji->total_potongan,
            'gaji_bersih' => (string) $gaji->gaji_bersih,
            'pendapatan' => $details->where('jenis', 'pendapatan')->map(fn($d) => [
                'komponen' => $d->nama_komponen ?: 'Pendapatan',
                'nominal' => (string) $d->nominal,
                'keterangan' => $d->keterangan,
            ])->values()->all(),
            'potongan' => $details->where('jenis', 'potongan')->map(fn($d) => [
                'komponen' => $d->nama_komponen ?: 'Potongan',
                'nominal' => (string) $d->nominal,
                'keterangan' => $d->keterangan,
            ])->values()->all(),
            'raw' => $gaji->toArray(),
        ];
    }

    /** PDF slip gaji dihasilkan on demand (GajiPegawai tidak menyimpan path berkas). */
    public function generateSikeuPayrollSlipPdf(User $user, int $id): string
    {
        $gaji = $this->resolvePayrollForUser($user, $id);
        $detail = $this->getSikeuPayrollDetail($user, $id);
        $pegawai = $gaji->pegawai;

        $rows = '';
        foreach ($detail['pendapatan'] as $item) {
            $rows .= '<tr><td>' . e($item['komponen']) . '</td><td style="text-align:right">'
                . 'Rp ' . number_format((float) $item['nominal'], 0, ',', '.') . '</td></tr>';
        }
        foreach ($detail['potongan'] as $item) {
            $rows .= '<tr><td>' . e($item['komponen']) . ' (potongan)</td><td style="text-align:right">-'
                . 'Rp ' . number_format((float) $item['nominal'], 0, ',', '.') . '</td></tr>';
        }

        $html = '<html><body style="font-family:DejaVu Sans,sans-serif;font-size:11px;">'
            . '<h2 style="text-align:center;">SLIP GAJI PEGAWAI</h2>'
            . '<p>Periode: <strong>' . e((string) $gaji->periode_bulan_tahun) . '</strong></p>'
            . '<p>Nama: <strong>' . e($pegawai?->nama_lengkap ?? '-') . '</strong><br>'
            . 'NIP: ' . e($pegawai?->nip ?? '-') . '</p>'
            . '<table border="1" cellpadding="5" cellspacing="0" width="100%">'
            . '<thead><tr><th>Komponen</th><th>Nominal</th></tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<p style="text-align:right;margin-top:12px;">Gaji Bersih: <strong>'
            . e($detail['amount']) . '</strong></p>'
            . '<p style="margin-top:20px;color:#666;">Dokumen ini dihasilkan otomatis dari sistem pada '
            . e(now()->format('Y-m-d H:i:s')) . '.</p>'
            . '</body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    // ARSIP: Detail, signed download URL & unggah e-file pribadi

    /** Ambil e-file milik akun login; 404 bila bukan dokumen pegawai bersangkutan. */
    protected function resolveEfileForUser(User $user, int $id): DokumenPegawai
    {
        $pegawai = $this->resolvePegawai($user);
        $query = DokumenPegawai::query();
        if ($pegawai) {
            $query->where('pegawai_id', $pegawai->id);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query->findOrFail($id);
    }

    public function getArsipEfileDetail(User $user, int $id): array
    {
        $d = $this->resolveEfileForUser($user, $id);

        return [
            'id' => (string) $d->id,
            'title' => $d->nama_dokumen,
            'subtitle' => $d->jenis_dokumen . ($d->file_size ? ' (' . $d->file_size . ')' : ''),
            'status' => $d->status_verifikasi ?: 'pending',
            'date' => $d->created_at?->format('Y-m-d') ?? '',
            'amount' => '',
            'jenis_dokumen' => $d->jenis_dokumen,
            'file_size' => $d->file_size,
            'catatan_verifikasi' => $d->catatan_verifikasi,
            'file_url' => $this->mobileFileUrl($d->file_path),
            'raw' => $d->toArray(),
        ];
    }

    public function getArsipEfileDownload(User $user, int $id): array
    {
        $d = $this->resolveEfileForUser($user, $id);
        if (!$d->file_path) {
            throw new \DomainException('Berkas dokumen belum tersedia.');
        }

        $url = $this->mobileFileUrl($d->file_path);
        if (!$url) {
            throw new \DomainException('Berkas dokumen tidak dapat diakses.');
        }

        return [
            'id' => (string) $d->id,
            'nama_dokumen' => $d->nama_dokumen,
            'file_url' => $url,
            'expires_in_seconds' => 900,
        ];
    }

    public function storeArsipEfile(User $user, array $data, $file): array
    {
        $pegawai = $this->resolvePegawai($user);
        if (!$pegawai) {
            throw new \DomainException('Akun Anda belum terhubung dengan data pegawai.');
        }

        $dokumen = new DokumenPegawai([
            'pegawai_id' => $pegawai->id,
            'nama_dokumen' => $data['nama_dokumen'],
            'jenis_dokumen' => $data['jenis_dokumen'],
            'file_path' => $this->fileStorageService->store($file, 'simpeg/e-file', private: true),
            'file_size' => $this->formatMobileFileSize((int) $file->getSize()),
            'status_verifikasi' => 'pending',
        ]);
        $dokumen->save();

        $detail = $this->getArsipEfileDetail($user, $dokumen->id);
        $detail['audit'] = $this->mobileAudit('simpeg_dokumen_pegawai', $dokumen->id, $dokumen->only([
            'pegawai_id', 'nama_dokumen', 'jenis_dokumen', 'file_path', 'file_size', 'status_verifikasi'
        ]));

        return $detail;
    }
}
