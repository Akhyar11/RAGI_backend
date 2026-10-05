<?php

namespace App\Services\Spmb;

use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\HasilSeleksi;
use App\Models\Spmb\BerkasRequirement;
use App\Models\Spmb\DokumenPendaftaran;
use App\Models\Sikeu\TagihanMahasiswa;
use Illuminate\Support\Facades\DB;
use App\Events\Spmb\MahasiswaDiterima;
use Illuminate\Validation\ValidationException;

use App\Models\Spmb\PendaftaranAlur;
use App\Models\Spmb\MasterTipeJalurAlur;
use App\Models\Siakad\TahunAkademik;
use App\Models\Spmb\TemplateSuratSpmb;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Log;
use Dompdf\Dompdf;
use Dompdf\Options;

class SpmbPendaftaranService
{
    public function __construct(private SpmbReferralService $referralService) {}

    /**
     * Submit pendaftaran dari draft ke submitted
     */
    public function submitPendaftaran(PendaftaranCalonMhs $pendaftaran): void
    {
        if ($pendaftaran->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Pendaftaran tidak dalam status draft.'
            ]);
        }

        $this->validateBerkasWajib($pendaftaran);

        $pendaftaran->update(['status' => 'submitted']);
    }

    /**
     * Pastikan seluruh berkas wajib (sesuai spmb_berkas_requirement untuk jalur
     * masuk calon) sudah diunggah sebelum pendaftaran disubmit.
     */
    private function validateBerkasWajib(PendaftaranCalonMhs $pendaftaran): void
    {
        $jalurMasukId = $pendaftaran->gelombangPenerimaan?->jalur_masuk_id;

        if (! $jalurMasukId) {
            return;
        }

        $wajib = BerkasRequirement::where('jalur_masuk_id', $jalurMasukId)
            ->where('is_active', true)
            ->where('wajib', true)
            ->get();

        if ($wajib->isEmpty()) {
            return;
        }

        $uploadedJenis = DokumenPendaftaran::where('pendaftaran_id', $pendaftaran->id)
            ->whereNotNull('file_path')
            ->pluck('jenis_dokumen')
            ->filter()
            ->unique()
            ->all();

        $missing = $wajib
            ->filter(fn ($req) => ! in_array($req->jenis_dokumen, $uploadedJenis, true))
            ->pluck('label')
            ->all();

        if (! empty($missing)) {
            throw ValidationException::withMessages([
                'berkas' => 'Berkas wajib belum lengkap: ' . implode(', ', $missing) . '. Silakan unggah terlebih dahulu.',
            ]);
        }
    }

    /**
     * Verifikasi administrasi oleh Admin
     */
    public function verifikasiAdministrasi(PendaftaranCalonMhs $pendaftaran, bool $isLulus, ?string $catatan, int $adminId): void
    {
        if ($pendaftaran->status !== 'submitted') {
            throw ValidationException::withMessages([
                'status' => 'Pendaftaran belum disubmit oleh calon mahasiswa.'
            ]);
        }

        $pendaftaran->update([
            'status' => $isLulus ? 'lulus_administrasi' : 'gagal_administrasi',
            'catatan_verifikasi' => $catatan,
            'diverifikasi_oleh' => $adminId,
            'diverifikasi_at' => now(),
        ]);

        // Sinkronkan status referral: qualified bila lolos, dibatalkan bila gagal.
        if ($isLulus) {
            $this->referralService->qualify($pendaftaran);
        } else {
            $this->referralService->cancel($pendaftaran);
        }
    }

    public function generateProgressAlur(PendaftaranCalonMhs $pendaftaran): void
    {
        // Pastikan tidak menduplikasi jika sudah ada
        if (PendaftaranAlur::where('pendaftaran_id', $pendaftaran->id)->exists()) {
            return;
        }

        if (!$pendaftaran->master_tipe_jalur_id) {
            return;
        }

        $masterAlurs = MasterTipeJalurAlur::where('master_tipe_jalur_id', $pendaftaran->master_tipe_jalur_id)
            ->orderBy('urutan', 'asc')
            ->get();

        $alursData = [];
        foreach ($masterAlurs as $index => $masterAlur) {
            $alursData[] = [
                'pendaftaran_id' => $pendaftaran->id,
                'master_tipe_jalur_alur_id' => $masterAlur->id,
                'status' => $index === 0 ? PendaftaranAlur::STATUS_IN_PROGRESS : PendaftaranAlur::STATUS_PENDING,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($alursData)) {
            PendaftaranAlur::insert($alursData);
        }
    }

    /**
     * Menetapkan hasil seleksi kelulusan
     */
    public function tetapkanKelulusan(PendaftaranCalonMhs $pendaftaran, array $dataSeleksi): HasilSeleksi
    {
        return DB::transaction(function () use ($pendaftaran, $dataSeleksi) {
            
            // Cek Kuota jika meluluskan
            if ($dataSeleksi['status'] === 'lulus' && !empty($dataSeleksi['program_studi_diterima_id'])) {
                $gelombang = $pendaftaran->gelombangPenerimaan;
                $tahunAkademikId = \App\Models\Siakad\TahunAkademik::query()
                    ->where('is_active', true)
                    ->orderByDesc('kode')
                    ->value('id') ?? 1;
                $kuotaProdi = \App\Models\Spmb\SpmbKuotaProdi::where('tahun_akademik_id', $tahunAkademikId)
                    ->where('program_studi_id', $dataSeleksi['program_studi_diterima_id'])
                    ->lockForUpdate()
                    ->first();
                
                if ($kuotaProdi && $kuotaProdi->kuota_terisi >= $kuotaProdi->kuota_total) {
                    throw ValidationException::withMessages([
                        'status' => 'Kuota untuk Program Studi ini sudah penuh (' . $kuotaProdi->kuota_total . ').'
                    ]);
                }

                if ($kuotaProdi) {
                    $kuotaProdi->increment('kuota_terisi');
                }
            }

            $hasil = HasilSeleksi::updateOrCreate(
                ['pendaftaran_id' => $pendaftaran->id],
                [
                    'program_studi_diterima_id' => $dataSeleksi['program_studi_diterima_id'] ?? null,
                    'nilai_total' => $dataSeleksi['nilai_total'] ?? 0,
                    'peringkat' => $dataSeleksi['peringkat'] ?? null,
                    'status' => $dataSeleksi['status'], // 'lulus', 'tidak_lulus', 'cadangan'
                    'status_daftar_ulang' => $dataSeleksi['status'] === 'lulus' ? 'belum' : 'belum',
                    'catatan' => $dataSeleksi['catatan'] ?? null,
                    'diumumkan_at' => $dataSeleksi['diumumkan_at'] ?? now(),
                ]
            );

            return $hasil;
        });
    }

    /**
     * Simpan biodata pendaftaran (draft) beserta kode referral.
     */
    public function saveBiodata(\App\Models\User $user, array $validated): PendaftaranCalonMhs
    {
        return DB::transaction(function () use ($user, $validated) {
            // Kode referral ditangani service (validasi exists + anti self-referral),
            // jangan ikut di-mass-assign agar tidak menyimpan kode tak valid.
            $referralCode = $validated['used_referral_code'] ?? null;
            unset($validated['used_referral_code']);

            $existing = PendaftaranCalonMhs::where('user_id', $user->id)->first();
            $noPendaftaran = ($existing && ! empty($existing->no_pendaftaran))
                ? $existing->no_pendaftaran
                : ('REG-'.date('Ymd').'-'.rand(1000, 9999));

            // GELOMBANG IMMUTABILITY PROTECTION:
            // If candidate already registered/paid in a gelombang, lock gelombang_id so future admin gelombang changes won't affect them.
            if ($existing && ! empty($existing->gelombang_id)) {
                $validated['gelombang_id'] = $existing->gelombang_id;
            }

            // Sanitasi input: jika nik kosong string, jadikan null agar tidak memicu duplikat unique key
            if (array_key_exists('nik', $validated)) {
                if (empty(trim((string) $validated['nik']))) {
                    $validated['nik'] = $existing ? $existing->nik : null;
                }
            }

            // Pastikan nama_lengkap memiliki nilai representatif meskipun pada Step 1 awal
            $namaLengkap = ! empty($validated['nama_lengkap'])
                ? $validated['nama_lengkap']
                : ($existing->nama_lengkap ?? $user->name ?? $user->username ?? 'Calon Mahasiswa');

            $pendaftaran = PendaftaranCalonMhs::updateOrCreate(
                ['user_id' => $user->id],
                array_merge($validated, [
                    'nama_lengkap' => $namaLengkap,
                    'no_pendaftaran' => $noPendaftaran,
                    'kewarganegaraan' => $validated['kewarganegaraan'] ?? $existing->kewarganegaraan ?? 'WNI',
                    'status' => $existing ? $existing->status : 'draft',
                    'status_pembayaran' => $existing ? $existing->status_pembayaran : 'belum_bayar',
                ])
            );

            // Terapkan / perbarui kode referral bila dikirim dari wizard.
            if ($referralCode !== null && trim($referralCode) !== '') {
                $this->referralService->attachToPendaftaran($pendaftaran, $referralCode);
                $pendaftaran->refresh();
            }

            return $pendaftaran;
        });
    }

    /**
     * Perbarui status pendaftaran sekaligus sinkronkan status referral.
     */
    public function updateStatusWithReferral(PendaftaranCalonMhs $pendaftaran, array $validated, int $adminId): PendaftaranCalonMhs
    {
        return DB::transaction(function () use ($pendaftaran, $validated, $adminId) {
            $pendaftaran->status = $validated['status'];
            if (isset($validated['catatan_verifikasi'])) {
                $pendaftaran->catatan_verifikasi = $validated['catatan_verifikasi'];
            }
            $pendaftaran->diverifikasi_oleh = $adminId;
            $pendaftaran->diverifikasi_at = now();
            $pendaftaran->save();

            // Sinkronkan status referral mengikuti keputusan verifikasi.
            if ($validated['status'] === PendaftaranCalonMhs::STATUS_LULUS_ADMINISTRASI) {
                $this->referralService->qualify($pendaftaran);
            } elseif ($validated['status'] === PendaftaranCalonMhs::STATUS_GAGAL_ADMINISTRASI) {
                $this->referralService->cancel($pendaftaran);
            }

            return $pendaftaran->refresh();
        });
    }

    /**
     * Ringkasan pembayaran daftar ulang calon mahasiswa.
     *
     * Tagihan daftar ulang diterbitkan & dibayar di modul SIKEU
     * (sikeu_tagihan_mahasiswa, tipe_referensi spmb_daftar_ulang).
     * Method ini hanya membaca status terkini untuk kebutuhan detail admin SPMB.
     */
    public function daftarUlangSummary(PendaftaranCalonMhs $pendaftaran): array
    {
        $tagihan = TagihanMahasiswa::with(['virtualAccount', 'pembayarans'])
            ->where('calon_mahasiswa_id', $pendaftaran->id)
            ->where('source_system', 'SPMB')
            ->where('tipe_referensi', TagihanMahasiswa::TIPE_SPMB_DAFTAR_ULANG)
            ->latest('id')
            ->first();

        $statusDaftarUlang = $pendaftaran->hasilSeleksi?->status_daftar_ulang;

        if (! $tagihan) {
            return [
                'has_tagihan' => false,
                'status_daftar_ulang' => $statusDaftarUlang,
                'tagihan' => null,
            ];
        }

        $totalBersih = (float) $tagihan->total_tagihan
            + (float) $tagihan->total_denda
            - (float) $tagihan->total_potongan;
        $sudahDibayar = (float) $tagihan->total_bayar;
        $sisaKurang = max(0, $totalBersih - $sudahDibayar);
        $persenTerbayar = $totalBersih > 0
            ? min(100, round(($sudahDibayar / $totalBersih) * 100, 2))
            : 0;

        $virtualAccount = $tagihan->virtualAccount;

        return [
            'has_tagihan' => true,
            'status_daftar_ulang' => $statusDaftarUlang,
            'tagihan' => [
                'id' => $tagihan->id,
                'nomor_tagihan' => $tagihan->nomor_tagihan,
                'status' => $tagihan->status,
                'due_date' => $tagihan->jatuh_tempo?->toDateString(),
                'total_tagihan' => (float) $tagihan->total_tagihan,
                'total_potongan' => (float) $tagihan->total_potongan,
                'total_denda' => (float) $tagihan->total_denda,
                'total_bersih' => $totalBersih,
                'sudah_dibayar' => $sudahDibayar,
                'sisa_kurang' => $sisaKurang,
                'persen_terbayar' => $persenTerbayar,
                'virtual_account' => $virtualAccount ? [
                    'va_number' => $virtualAccount->va_number,
                    'bank_kode' => $virtualAccount->bank_kode,
                    'bank_nama' => $virtualAccount->bank_nama,
                    'nominal' => (float) $virtualAccount->nominal,
                    'status' => $virtualAccount->status,
                    'expired_at' => $virtualAccount->expired_at,
                ] : null,
                'riwayat_pembayaran' => $tagihan->pembayarans
                    ->sortByDesc('waktu_bayar')
                    ->values()
                    ->map(fn ($bayar) => [
                        'id' => $bayar->id,
                        'kode_transaksi' => $bayar->kode_transaksi,
                        'jumlah_bayar' => (float) $bayar->jumlah_bayar,
                        'channel_bayar' => $bayar->channel_bayar,
                        'status' => $bayar->status,
                        'paid_at' => $bayar->waktu_bayar,
                    ])->all(),
            ],
        ];
    }

    /**
     * Cari template surat aktif yang paling spesifik untuk pendaftaran
     */
    public function resolveTemplateSurat(?int $jalurMasukId = null, ?int $gelombangId = null, string $jenisSurat = 'sk_lulus'): ?TemplateSuratSpmb
    {
        // 1. Template spesifik Jalur + Gelombang
        if ($jalurMasukId && $gelombangId) {
            $t = TemplateSuratSpmb::query()
                ->where('jenis_surat', $jenisSurat)
                ->where('is_active', true)
                ->where('jalur_masuk_id', $jalurMasukId)
                ->where('gelombang_id', $gelombangId)
                ->first();
            if ($t) {
                return $t;
            }
        }

        // 2. Template spesifik Jalur
        if ($jalurMasukId) {
            $t = TemplateSuratSpmb::query()
                ->where('jenis_surat', $jenisSurat)
                ->where('is_active', true)
                ->where('jalur_masuk_id', $jalurMasukId)
                ->whereNull('gelombang_id')
                ->first();
            if ($t) {
                return $t;
            }
        }

        // 3. Template spesifik Gelombang
        if ($gelombangId) {
            $t = TemplateSuratSpmb::query()
                ->where('jenis_surat', $jenisSurat)
                ->where('is_active', true)
                ->whereNull('jalur_masuk_id')
                ->where('gelombang_id', $gelombangId)
                ->first();
            if ($t) {
                return $t;
            }
        }

        // 4. Template default (tanpa batasan jalur & gelombang)
        $t = TemplateSuratSpmb::query()
            ->where('jenis_surat', $jenisSurat)
            ->where('is_active', true)
            ->whereNull('jalur_masuk_id')
            ->whereNull('gelombang_id')
            ->first();
        if ($t) {
            return $t;
        }

        // 5. Fallback ke sembarang template aktif jenis ini
        return TemplateSuratSpmb::query()
            ->where('jenis_surat', $jenisSurat)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Konversi angka bulan ke format romawi
     */
    private function getRomawiBulan(int $month): string
    {
        $map = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];
        return $map[$month] ?? 'I';
    }

    /**
     * Ganti token/placeholder pada teks
     */
    public function replacePlaceholders(?string $text, array $variables): string
    {
        if (empty($text)) {
            return '';
        }

        $search = [];
        $replace = [];
        foreach ($variables as $key => $val) {
            $search[] = '{' . $key . '}';
            $replace[] = (string) $val;
        }

        return str_replace($search, $replace, $text);
    }

    /**
     * Generate PDF Surat Keterangan Tanda Lulus (SK Tanda Lulus)
     */
    public function generateSkLulusPdf(PendaftaranCalonMhs $pendaftaran, ?TemplateSuratSpmb $template = null): string
    {
        $pendaftaran->loadMissing([
            'gelombangPenerimaan.jalurMasuk',
            'programStudi',
            'programStudiPilihan2',
            'hasilSeleksi.programStudiDiterima',
            'verifikator',
            'user',
        ]);

        $jalurId = $pendaftaran->gelombangPenerimaan?->jalur_masuk_id;
        $gelombangId = $pendaftaran->gelombang_id;

        if (!$template) {
            $template = $this->resolveTemplateSurat($jalurId, $gelombangId, 'sk_lulus');
        }

        $tahunAkademik = TahunAkademik::query()
            ->where('is_active', true)
            ->orderByDesc('kode')
            ->first();

        $tahunAkademikNama = $tahunAkademik->nama ?? (date('Y') . '/' . (date('Y') + 1));
        $prodiDiterimaNama = $pendaftaran->hasilSeleksi?->programStudiDiterima?->nama 
            ?? $pendaftaran->programStudi?->nama 
            ?? 'Program Studi Terpilih';
        $jenjangDiterima = $pendaftaran->hasilSeleksi?->programStudiDiterima?->jenjang 
            ?? $pendaftaran->programStudi?->jenjang 
            ?? '';

        $tanggalPenetapan = $pendaftaran->diverifikasi_at 
            ? $pendaftaran->diverifikasi_at->translatedFormat('d F Y') 
            : now()->translatedFormat('d F Y');

        $romawiBulan = $this->getRomawiBulan((int) date('n'));
        $tahun = date('Y');

        $placeholders = [
            'nama' => strtoupper($pendaftaran->nama_lengkap ?? '-'),
            'no_pendaftaran' => $pendaftaran->no_pendaftaran ?? '-',
            'nik' => $pendaftaran->nik ?? '-',
            'tempat_lahir' => $pendaftaran->tempat_lahir ?? '-',
            'tanggal_lahir' => $pendaftaran->tanggal_lahir ? $pendaftaran->tanggal_lahir->translatedFormat('d F Y') : '-',
            'asal_sekolah' => $pendaftaran->asal_sekolah ?? $pendaftaran->asal_pt ?? '-',
            'prodi_diterima' => $prodiDiterimaNama,
            'jenjang' => $jenjangDiterima,
            'jalur' => $pendaftaran->gelombangPenerimaan?->jalurMasuk?->nama ?? 'Reguler',
            'gelombang' => $pendaftaran->gelombangPenerimaan?->nama ?? 'Gelombang Utama',
            'tahun_akademik' => $tahunAkademikNama,
            'tanggal_penetapan' => $tanggalPenetapan,
            'tahun' => $tahun,
            'romawi_bulan' => $romawiBulan,
            'kota' => $template?->kota_penetapan ?? 'Surakarta',
        ];

        // Format nomor surat
        $nomorSuratFormat = $template?->format_nomor_surat ?: 'SKL/SPMB/{tahun}/{romawi_bulan}/{no_pendaftaran}';
        $nomorSurat = $this->replacePlaceholders($nomorSuratFormat, $placeholders);

        // Kop
        $kopInstitusi = $template?->kop_nama_institusi ?: config('app.institution_name', config('app.name', 'UNIVERSITAS INDONUSA'));
        $kopSub = $template?->kop_nama_sub ?: 'PANITIA PENERIMAAN MAHASISWA BARU (SPMB)';
        $kopKontak = $this->replacePlaceholders($template?->kop_alamat_kontak ?: "Sekretariat SPMB Kampus Terpadu • Email: spmb@kampus.ac.id • Website: spmb.kampus.ac.id\nTahun Akademik {tahun_akademik}", $placeholders);

        // Judul, Teks, Petunjuk
        $judulSurat = $template?->judul_surat ?: 'SURAT KETERANGAN TANDA LULUS SELEKSI';
        $teksPembuka = $this->replacePlaceholders($template?->teks_pembuka ?: 'Berdasarkan hasil evaluasi verifikasi kelengkapan berkas administrasi dan pemenuhan syarat seleksi penerimaan mahasiswa baru Tahun Akademik {tahun_akademik}, Panitia Penerimaan Mahasiswa Baru menyatakan bahwa:', $placeholders);
        $teksKeputusan = $this->replacePlaceholders($template?->teks_keputusan ?: 'DINYATAKAN LULUS / DITERIMA', $placeholders);
        $petunjukDaftarUlang = $this->replacePlaceholders($template?->petunjuk_daftar_ulang ?: "1. Calon mahasiswa yang dinyatakan lulus wajib melakukan Daftar Ulang melalui portal resmi SPMB pada menu Daftar Ulang.\n2. Selesaikan pembayaran biaya registrasi/UKT menggunakan nomor Virtual Account resmi yang tertera pada invoice tagihan Anda sebelum batas waktu yang ditentukan.\n3. Setelah pembayaran daftar ulang terkonfirmasi lunas, sistem akan menerbitkan Nomor Induk Mahasiswa (NIM) resmi dan akun akademik mahasiswa baru.\n4. Surat keterangan ini sah dan dihasilkan secara otomatis oleh Sistem Informasi Penerimaan Mahasiswa Baru terintegrasi.", $placeholders);

        // Pejabat & Tanda Tangan
        $kotaPenetapan = $template?->kota_penetapan ?: 'Surakarta';
        $namaPenandatangan = $template?->nama_penandatangan ?: ($pendaftaran->verifikator?->name ?? 'Panitia Seleksi SPMB');
        $jabatanPenandatangan = $template?->jabatan_penandatangan ?: 'Ketua Panitia SPMB / Direktur Admisi';
        $nipPenandatangan = $template?->nip_penandatangan ?: null;
        $catatanKaki = $this->replacePlaceholders($template?->catatan_kaki ?: 'Dokumen ini merupakan bukti kelulusan seleksi SPMB yang sah. Keabsahan dokumen dapat diverifikasi langsung melalui database induk kampus terintegrasi.', $placeholders);

        $html = view('spmb.sk-tanda-lulus', [
            'pendaftaran' => $pendaftaran,
            'tahunAkademik' => $tahunAkademik,
            'generatedAt' => now(),
            'template' => $template,
            'nomorSurat' => $nomorSurat,
            'kopInstitusi' => $kopInstitusi,
            'kopSub' => $kopSub,
            'kopKontak' => $kopKontak,
            'judulSurat' => $judulSurat,
            'teksPembuka' => $teksPembuka,
            'teksKeputusan' => $teksKeputusan,
            'petunjukDaftarUlang' => $petunjukDaftarUlang,
            'kotaPenetapan' => $kotaPenetapan,
            'namaPenandatangan' => $namaPenandatangan,
            'jabatanPenandatangan' => $jabatanPenandatangan,
            'nipPenandatangan' => $nipPenandatangan,
            'catatanKaki' => $catatanKaki,
            'prodiDiterimaNama' => $prodiDiterimaNama,
            'jenjangDiterima' => $jenjangDiterima,
            'tanggalPenetapan' => $tanggalPenetapan,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Preview template surat dengan data dummy
     */
    public function previewTemplateSuratPdf(TemplateSuratSpmb $template): string
    {
        $mock = new PendaftaranCalonMhs();
        $mock->id = 9999;
        $mock->no_pendaftaran = 'SPMB-' . date('Y') . '-0001';
        $mock->nama_lengkap = 'AHMAD FAUZI PRATAMA';
        $mock->nik = '3371012345670001';
        $mock->tempat_lahir = 'Surakarta';
        $mock->tanggal_lahir = now()->subYears(18);
        $mock->asal_sekolah = 'SMA Negeri 1 Surakarta';
        $mock->diverifikasi_at = now();

        return $this->generateSkLulusPdf($mock, $template);
    }

    /**
     * Simpan template surat baru
     */
    public function storeTemplateSurat(array $data): TemplateSuratSpmb
    {
        if (!isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        $template = TemplateSuratSpmb::create($data);
        $template->load(['jalurMasuk', 'gelombang']);

        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'create',
                tableName: $template->getTable(),
                recordId: (int) $template->id,
                oldValues: null,
                newValues: $template->toArray(),
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log create template surat: ' . $e->getMessage());
        }

        return $template;
    }

    /**
     * Perbarui template surat
     */
    public function updateTemplateSurat(TemplateSuratSpmb $template, array $data): TemplateSuratSpmb
    {
        $oldValues = $template->getOriginal();
        $template->update($data);
        $template->load(['jalurMasuk', 'gelombang']);
        $newValues = $template->getChanges();

        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'update',
                tableName: $template->getTable(),
                recordId: (int) $template->id,
                oldValues: $oldValues,
                newValues: $newValues,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log update template surat: ' . $e->getMessage());
        }

        return $template;
    }

    /**
     * Hapus template surat
     */
    public function deleteTemplateSurat(TemplateSuratSpmb $template): void
    {
        $oldValues = $template->getOriginal();
        $tableName = $template->getTable();
        $id = (int) $template->id;
        $template->delete();

        try {
            AuditLogService::record(
                module: 'SPMB',
                action: 'delete',
                tableName: $tableName,
                recordId: $id,
                oldValues: $oldValues,
                newValues: null,
                request: request()
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log delete template surat: ' . $e->getMessage());
        }
    }
}
