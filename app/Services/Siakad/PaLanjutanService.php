<?php

namespace App\Services\Siakad;

use App\Models\Siakad\PaBooking;
use App\Models\Siakad\PaInsiden;
use App\Models\Siakad\PaJadwal;
use App\Models\Siakad\PaPkl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fase 2 Bimbingan PA — layanan lanjutan:
 *  - Jadwal pertemuan/konsultasi PA + pengajuan janji (booking) mahasiswa.
 *  - Status PKL/PL/Magang mahasiswa bimbingan.
 *  - Pelaporan insiden (kekerasan/pelecehan) + rujukan konseling + eskalasi.
 *  - Laporan monitoring bulanan (agregasi; memakai PaMonitoringService).
 *
 * Service tidak mengakses Request; data baku dioper sebagai array dari controller.
 */
class PaLanjutanService
{
    /** Status jadwal buka/tutup (nilai tetap domain, kolom `status` siakad_pa_jadwal). */
    private const JADWAL_DIBUKA = 'dibuka';

    /** Mode jadwal bawaan (nilai tetap domain, kolom `mode` siakad_pa_jadwal). */
    private const JADWAL_MODE_TATAP_MUKA = 'tatap_muka';

    /** Status booking pada siklus pengajuan (nilai tetap domain). */
    private const BOOKING_DIAJUKAN = 'diajukan';

    /** Status PKL awal & jenis bawaan (nilai tetap domain, kolom siakad_pa_pkl). */
    private const PKL_RENCANA = 'rencana';
    private const PKL_JENIS_PKL = 'pkl';

    /** Status insiden (nilai tetap domain, kolom `status` siakad_pa_insiden). */
    private const INSIDEN_DILAPORKAN = 'dilaporkan';
    private const INSIDEN_DIVERIFIKASI = 'diverifikasi';
    private const INSIDEN_DITANGANI = 'ditangani';

    /** Status booking/jadwal/insiden yang bersifat tetap domain (selaras kolom `status`). */
    private const BOOKING_MENUNGGU = [self::BOOKING_DIAJUKAN];
    private const BOOKING_AKTIF = ['disetujui', 'selesai'];
    private const PKL_AKTIF = 'berjalan';
    private const INSIDEN_SELESAI = ['selesai', 'ditolak'];
    private const INSIDEN_BELUM_DITANGANI = [self::INSIDEN_DILAPORKAN, self::INSIDEN_DIVERIFIKASI];

    /**
     * Snapshot nilai lama & delta perubahan dari operasi update terakhir,
     * untuk keperluan audit log di controller. Diambil SEBELUM dan SESUDAH
     * mutasi pada instance model yang sama (by-reference), sehingga controller
     * tidak perlu membaca ulang model yang sudah berubah.
     */
    public array $auditOld = [];
    public array $auditChanges = [];

    public function __construct(private PaMonitoringService $monitoring) {}

    // =========================================================================
    // JADWAL & KONSULTASI
    // =========================================================================

    /** Daftar jadwal seorang dosen PA (opsional terurut tanggal). */
    public function listJadwal(int $dosenId, ?string $dari = null, ?string $sampai = null): Collection
    {
        $query = PaJadwal::withCount('bookings')
            ->where('dosen_id', $dosenId);
        if ($dari) {
            $query->whereDate('tanggal', '>=', $dari);
        }
        if ($sampai) {
            $query->whereDate('tanggal', '<=', $sampai);
        }

        return $query->orderBy('tanggal')->orderBy('jam_mulai')->get();
    }

    public function createJadwal(int $dosenId, array $data): PaJadwal
    {
        return DB::transaction(function () use ($dosenId, $data) {
            return PaJadwal::create([
                'dosen_id' => $dosenId,
                'tahun_akademik_id' => $data['tahun_akademik_id'] ?? null,
                'tanggal' => $data['tanggal'],
                'jam_mulai' => $data['jam_mulai'],
                'jam_selesai' => $data['jam_selesai'],
                'mode' => $data['mode'] ?? self::JADWAL_MODE_TATAP_MUKA,
                'lokasi' => $data['lokasi'] ?? null,
                'kuota' => $data['kuota'] ?? 1,
                'catatan' => $data['catatan'] ?? null,
                'status' => $data['status'] ?? self::JADWAL_DIBUKA,
            ]);
        });
    }

    public function updateJadwal(PaJadwal $jadwal, array $data): PaJadwal
    {
        return DB::transaction(function () use ($jadwal, $data) {
            $this->auditOld = $jadwal->getOriginal();
            $jadwal->update($data);
            $this->auditChanges = $jadwal->getChanges();

            return $jadwal;
        });
    }

    public function deleteJadwal(PaJadwal $jadwal): void
    {
        $jadwal->delete();
    }

    /** Booking pada jadwal tertentu (untuk dosen pemilik jadwal). */
    public function listBookingJadwal(int $jadwalId): Collection
    {
        return PaBooking::with('mahasiswa')
            ->where('jadwal_id', $jadwalId)
            ->orderByDesc('created_at')
            ->get();
    }

    /** Daftar seluruh pengajuan janji pada semua jadwal seorang dosen. */
    public function listBookingDosen(int $dosenId, ?string $status = null): Collection
    {
        $query = PaBooking::with(['mahasiswa', 'jadwal'])
            ->whereHas('jadwal', fn ($q) => $q->where('dosen_id', $dosenId));
        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderByDesc('created_at')->get();
    }

    /** Riwayat pengajuan janji konsultasi milik seorang mahasiswa. */
    public function listBookingMahasiswa(int $mahasiswaId): Collection
    {
        return PaBooking::with('jadwal.dosen')
            ->where('mahasiswa_id', $mahasiswaId)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Mahasiswa mengajukan janji pada slot jadwal yang dibuka.
     * Menolak bila jadwal ditutup, kuota penuh, atau sudah pernah mengajukan.
     */
    public function ajukanBooking(PaJadwal $jadwal, int $mahasiswaId, array $data): array
    {
        if ($jadwal->status !== self::JADWAL_DIBUKA) {
            return ['error' => 'Jadwal sudah ditutup.'];
        }

        $sudah = PaBooking::where('jadwal_id', $jadwal->id)->where('mahasiswa_id', $mahasiswaId)->exists();
        if ($sudah) {
            return ['error' => 'Anda sudah mengajukan janji pada jadwal ini.'];
        }

        $terpakai = PaBooking::where('jadwal_id', $jadwal->id)
            ->whereIn('status', array_merge(self::BOOKING_MENUNGGU, self::BOOKING_AKTIF))
            ->count();
        if ($terpakai >= $jadwal->kuota) {
            return ['error' => 'Kuota jadwal sudah penuh.'];
        }

        $booking = DB::transaction(fn () => PaBooking::create([
            'jadwal_id' => $jadwal->id,
            'mahasiswa_id' => $mahasiswaId,
            'topik' => $data['topik'] ?? null,
            'catatan_mahasiswa' => $data['catatan_mahasiswa'] ?? null,
            'status' => self::BOOKING_DIAJUKAN,
        ]));

        return ['data' => $booking];
    }

    /** Dosen memproses booking (setujui/tolak/selesai) + catatan. */
    public function prosesBooking(PaBooking $booking, string $status, ?string $catatanDosen): PaBooking
    {
        return DB::transaction(function () use ($booking, $status, $catatanDosen) {
            $this->auditOld = $booking->getOriginal();
            $booking->update([
                'status' => $status,
                'catatan_dosen' => $catatanDosen,
                'diproses_pada' => now(),
            ]);
            $this->auditChanges = $booking->getChanges();

            return $booking;
        });
    }

    // =========================================================================
    // PKL / PL / MAGANG
    // =========================================================================

    /** Daftar status PKL/PL/Magang mahasiswa bimbingan seorang dosen PA. */
    public function listPklDosen(int $dosenId): Collection
    {
        return PaPkl::with(['mahasiswa', 'tahunAkademik'])
            ->whereHas('mahasiswa', fn ($q) => $q->where('dosen_wali_id', $dosenId))
            ->orderByDesc('created_at')
            ->get();
    }

    public function createPkl(int $dosenId, array $data): PaPkl
    {
        return DB::transaction(fn () => PaPkl::create([
            'mahasiswa_id' => $data['mahasiswa_id'],
            'tahun_akademik_id' => $data['tahun_akademik_id'] ?? null,
            'dosen_id' => $dosenId,
            'jenis' => $data['jenis'] ?? self::PKL_JENIS_PKL,
            'instansi' => $data['instansi'],
            'lokasi' => $data['lokasi'] ?? null,
            'pembimbing_lapangan' => $data['pembimbing_lapangan'] ?? null,
            'tanggal_mulai' => $data['tanggal_mulai'] ?? null,
            'tanggal_selesai' => $data['tanggal_selesai'] ?? null,
            'status' => $data['status'] ?? self::PKL_RENCANA,
            'catatan' => $data['catatan'] ?? null,
        ]));
    }

    public function updatePkl(PaPkl $pkl, array $data): PaPkl
    {
        return DB::transaction(function () use ($pkl, $data) {
            $this->auditOld = $pkl->getOriginal();
            $pkl->update($data);
            $this->auditChanges = $pkl->getChanges();

            return $pkl;
        });
    }

    public function deletePkl(PaPkl $pkl): void
    {
        $pkl->delete();
    }

    // =========================================================================
    // INSIDEN: KEKERASAN/PELECEHAN + RUJUKAN KONSELING + ESKALASI
    // =========================================================================

    /**
     * Daftar insiden. Untuk pengelola (privileged) menerima semua; untuk dosen
     * dibatasi pada insiden yang dilaporkan/ditangani oleh dirinya. Baris anonim
     * disembunyikan identitas pelapornya (mahasiswa_id/dosen_id disamarkan).
     */
    public function listInsiden(?int $dosenId, bool $privileged, ?string $status = null, ?string $jenis = null): Collection
    {
        $query = PaInsiden::with(['mahasiswa', 'dosen']);
        if (!$privileged) {
            $query->where('dosen_id', $dosenId);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($jenis) {
            $query->where('jenis', $jenis);
        }

        return $query->orderByDesc('created_at')->get()->map(fn (PaInsiden $i) => $this->maskInsiden($i));
    }

    public function createInsiden(array $data): PaInsiden
    {
        return DB::transaction(function () use ($data) {
            // Status awal selalu "dilaporkan" (nilai tetap domain), tidak
            // ditentukan oleh input controller agar konsisten.
            $data['status'] = self::INSIDEN_DILAPORKAN;

            return PaInsiden::create($data);
        });
    }

    /** Eskalasi insiden ke unit tujuan (Kaprodi/Kemahasiswaan/Satgas PPKS/dll). */
    public function eskalasiInsiden(PaInsiden $insiden, string $tujuan, ?string $catatan): PaInsiden
    {
        return DB::transaction(function () use ($insiden, $tujuan, $catatan) {
            $this->auditOld = $insiden->getOriginal();
            $insiden->update([
                'eskalasi_tujuan' => $tujuan,
                'dieskalasi_pada' => now(),
                'status' => $insiden->status === self::INSIDEN_DILAPORKAN ? self::INSIDEN_DIVERIFIKASI : $insiden->status,
                'tindak_lanjut' => $catatan ?? $insiden->tindak_lanjut,
            ]);
            $this->auditChanges = $insiden->getChanges();

            return $insiden;
        });
    }

    /** Tandai rujukan konseling untuk pelapor/korban. */
    public function rujukKonseling(PaInsiden $insiden, string $konselingKe, ?string $catatan): PaInsiden
    {
        return DB::transaction(function () use ($insiden, $konselingKe, $catatan) {
            $this->auditOld = $insiden->getOriginal();
            $insiden->update([
                'rujukan_konseling' => true,
                'konseling_ke' => $konselingKe,
                'status' => in_array($insiden->status, self::INSIDEN_BELUM_DITANGANI, true) ? self::INSIDEN_DITANGANI : $insiden->status,
                'tindak_lanjut' => $catatan ?? $insiden->tindak_lanjut,
            ]);
            $this->auditChanges = $insiden->getChanges();

            return $insiden;
        });
    }

    /** Perbarui status/tindak lanjut insiden. */
    public function updateInsiden(PaInsiden $insiden, array $data): PaInsiden
    {
        return DB::transaction(function () use ($insiden, $data) {
            $this->auditOld = $insiden->getOriginal();
            $insiden->update($data);
            $this->auditChanges = $insiden->getChanges();

            return $insiden;
        });
    }

    // =========================================================================
    // LAPORAN MONITORING BULANAN
    // =========================================================================

    /**
     * Ringkasan monitoring bulanan seorang dosen PA: komposisi risiko, kehadiran
     * kritis, status PKL, aktivitas bimbingan pada bulan tersebut, dan rekap insiden.
     * Agregasi memakai PaMonitoringService agar konsisten dengan Fase 1.
     *
     * @param  string  $bulan  Format Y-m (mis. "2026-10").
     */
    public function laporanBulanan(int $dosenId, string $bulan): array
    {
        $awal = $bulan . '-01';
        $akhir = date('Y-m-t', strtotime($awal));

        $risiko = collect($this->monitoring->risiko($dosenId));
        $kehadiran = collect($this->monitoring->kehadiran($dosenId));

        $bookings = PaBooking::whereHas('jadwal', fn ($q) => $q->where('dosen_id', $dosenId))
            ->whereBetween('created_at', [$awal . ' 00:00:00', $akhir . ' 23:59:59'])
            ->get();

        $insidenBulan = PaInsiden::where('dosen_id', $dosenId)
            ->whereBetween('created_at', [$awal . ' 00:00:00', $akhir . ' 23:59:59'])
            ->get();

        $pkl = $this->listPklDosen($dosenId);

        return [
            'periode' => ['bulan' => $bulan, 'dari' => $awal, 'sampai' => $akhir],
            'dosen_id' => $dosenId,
            'kehadiran' => [
                'total_advisees' => $kehadiran->count(),
                'kehadiran_kritis' => $kehadiran->filter(fn ($k) => $k['kehadiran_kritis'] ?? false)->count(),
                'rata_persentase_hadir' => $kehadiran->whereNotNull('persentase_hadir')->avg('persentase_hadir'),
            ],
            'risiko' => [
                'tinggi' => $risiko->filter(fn ($r) => ($r['band_risiko'] ?? '') === 'tinggi')->count(),
                'sedang' => $risiko->filter(fn ($r) => ($r['band_risiko'] ?? '') === 'sedang')->count(),
                'rendah' => $risiko->filter(fn ($r) => ($r['band_risiko'] ?? '') === 'rendah')->count(),
                'daftar_perlu_perhatian' => $risiko->filter(fn ($r) => ($r['skor'] ?? 0) >= 1)
                    ->map(fn ($r) => [
                        'mahasiswa_id' => $r['id'],
                        'nim' => $r['nim'],
                        'nama_lengkap' => $r['nama_lengkap'],
                        'skor' => $r['skor'],
                        'band_risiko' => $r['band_risiko'],
                        'sinyal' => $r['sinyal'],
                    ])->values()->all(),
            ],
            'konsultasi' => [
                'total_pengajuan' => $bookings->count(),
                'disetujui' => $bookings->whereIn('status', self::BOOKING_AKTIF)->count(),
                'menunggu' => $bookings->whereIn('status', self::BOOKING_MENUNGGU)->count(),
            ],
            'pkl' => [
                'total' => $pkl->count(),
                'berjalan' => $pkl->where('status', self::PKL_AKTIF)->count(),
            ],
            'insiden' => [
                'total' => $insidenBulan->count(),
                'keluar_eskalasi' => $insidenBulan->whereNotNull('eskalasi_tujuan')->count(),
                'rujukan_konseling' => $insidenBulan->where('rujukan_konseling', true)->count(),
                'belum_selesai' => $insidenBulan->whereNotIn('status', self::INSIDEN_SELESAI)->count(),
            ],
        ];
    }

    /** Samarkan identitas pelapor pada baris anonim (perlindungan korban/pelapor). */
    private function maskInsiden(PaInsiden $insiden): array
    {
        $data = $insiden->toArray();
        if ($insiden->anonim) {
            $data['mahasiswa_id'] = null;
            $data['dosen_id'] = null;
            $data['pelapor_user_id'] = null;
            $data['mahasiswa'] = null;
            $data['dosen'] = null;
        }

        return $data;
    }
}
