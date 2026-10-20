<?php

namespace App\Services\Siakad;

use App\Models\Siakad\Khs;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\PaCatatan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Monitoring Bimbingan Akademik (PA): kehadiran, prestasi (IPS), skor risiko,
 * dan ringkasan "Perlu Perhatian" bagi mahasiswa bimbingan seorang Dosen PA.
 *
 * Seluruh metode beroperasi pada satu himpunan advisees (dosen_wali_id = $dosenId)
 * sehingga otomatis ter-scope per PA. Pemilihan dosen dilakukan oleh controller
 * (memakai helper isPrivileged/resolveDosen) lalu hasil metode dikirim ke response.
 *
 * Sumber data:
 * - Kehadiran : siakad_krs -> siakad_krs_detail -> siakad_pertemuan (total pertemuan)
 *               siakad_absensi_mahasiswa (status: hadir/sakit/izin/alfa).
 * - Prestasi  : siakad_khs (ips per tahun_akademik) -> siakad_tahun_akademik (kode).
 * - Risiko    : gabungan kehadiran + prestasi + status mhs + KRS + penanganan khusus.
 */
class PaMonitoringService
{
    /** Detail KRS yang masih aktif diikuti mahasiswa (nilai tetap domain, kolom `status`). */
    private const KRS_DETAIL_AKTIF = 'aktif';

    /** Status KRS yang dianggap beres (nilai tetap domain, kolom `status`). */
    private const KRS_SELESAI = ['disetujui', 'dikunci'];

    /** Status mahasiswa normal (nilai tetap domain, kolom `status`). */
    private const STATUS_AKTIF = 'aktif';

    /** Status kehadiran absensi (nilai tetap domain, kolom `status` siakad_absensi_mahasiswa). */
    private const ABSENSI_HADIR = 'hadir';
    private const ABSENSI_SAKIT = 'sakit';
    private const ABSENSI_IZIN = 'izin';
    private const ABSENSI_ALFA = 'alfa';

    /** Band skor risiko (nilai tetap domain hasil klasifikasi internal). */
    private const BAND_TINGGI = 'tinggi';
    private const BAND_SEDANG = 'sedang';
    private const BAND_RENDAH = 'rendah';

    /** Ambang persentase kehadiran minimum (konsisten dengan rekapAbsensi/eligible_uas). */
    private const KEHADIRAN_MINIMAL = 75.0;

    /** Ambang IPS & IPK minimum dianggap rendah. */
    private const IPS_MINIMAL = 2.0;
    private const IPK_MINIMAL = 2.0;

    /** Ambang skor risiko untuk penentuan band (tinggi/sedang/rendah). */
    private const RISIKO_TINGGI_MIN = 3;
    private const RISIKO_SEDANG_MIN = 1;

    /**
     * Rekap kehadiran per advisee: total pertemuan, jumlah per status kehadiran,
     * persentase hadir, kelayakan UAS, dan flag kehadiran kritis.
     */
    public function kehadiran(int $dosenId): array
    {
        $advisees = $this->advisees($dosenId);
        $ids = $advisees->pluck('id');
        $totals = $this->totalPertemuanPerMahasiswa($ids);
        $absensi = $this->absensiPerMahasiswa($ids);

        return $advisees->map(function (Mahasiswa $m) use ($totals, $absensi) {
            $total = (int) ($totals->get($m->id) ?? 0);
            $a = $absensi->get($m->id);
            $hadir = (int) ($a->hadir ?? 0);
            $pct = $total > 0 ? round(($hadir / $total) * 100, 1) : null;
            $kritis = $pct !== null && $pct < self::KEHADIRAN_MINIMAL;

            return [
                'id' => $m->id,
                'nim' => $m->nim,
                'nama_lengkap' => $m->nama_lengkap,
                'status' => $m->status,
                'total_pertemuan' => $total,
                'hadir' => $hadir,
                'sakit' => (int) ($a->sakit ?? 0),
                'izin' => (int) ($a->izin ?? 0),
                'alfa' => (int) ($a->alfa ?? 0),
                'persentase_hadir' => $pct,
                'eligible_uas' => $pct === null ? null : $pct >= self::KEHADIRAN_MINIMAL,
                'kehadiran_kritis' => $kritis,
            ];
        })->values()->all();
    }

    /**
     * Trend prestasi (IPS per semester) + deteksi IPS menurun & IPS rendah.
     */
    public function prestasi(int $dosenId): array
    {
        $advisees = $this->advisees($dosenId);
        $khs = Khs::with('tahunAkademik')
            ->whereIn('mahasiswa_id', $advisees->pluck('id'))
            ->orderBy('tahun_akademik_id')
            ->get()
            ->groupBy('mahasiswa_id');

        return $advisees->map(function (Mahasiswa $m) use ($khs) {
            $rows = $khs->get($m->id, collect());
            $trend = $rows->map(fn (Khs $k) => [
                'tahun_akademik_id' => $k->tahun_akademik_id,
                'kode' => $k->tahunAkademik?->kode,
                'ips' => round((float) $k->ips, 2),
                'total_sks_semester' => (int) $k->total_sks_semester,
            ])->values();

            $jumlah = $trend->count();
            $ipsTerakhir = $jumlah > 0 ? $trend->last()['ips'] : null;
            $ipsSebelumnya = $jumlah > 1 ? $trend[$jumlah - 2]['ips'] : null;
            $menurun = $ipsTerakhir !== null && $ipsSebelumnya !== null && $ipsTerakhir < $ipsSebelumnya;

            return [
                'id' => $m->id,
                'nim' => $m->nim,
                'nama_lengkap' => $m->nama_lengkap,
                'ipk' => $m->ipk,
                'ips_terakhir' => $ipsTerakhir,
                'ips_sebelumnya' => $ipsSebelumnya,
                'ips_trend' => $trend,
                'ips_menurun' => $menurun,
                'ips_rendah' => $ipsTerakhir !== null && $ipsTerakhir < self::IPS_MINIMAL,
            ];
        })->values()->all();
    }

    /**
     * Skor risiko "cuti/berhenti" per advisee: menggabungkan sinyal kehadiran,
     * prestasi, status akademik, KRS, dan penanganan khusus yang masih terbuka.
     */
    public function risiko(int $dosenId): array
    {
        $kehadiran = collect($this->kehadiran($dosenId))->keyBy('id');
        $prestasi = collect($this->prestasi($dosenId))->keyBy('id');
        $advisees = Mahasiswa::with(['krs' => fn ($q) => $q->latest('id')->limit(1)])
            ->where('dosen_wali_id', $dosenId)
            ->orderBy('nama_lengkap')
            ->get();

        return $advisees->map(function (Mahasiswa $m) use ($kehadiran, $prestasi, $dosenId) {
            $k = $kehadiran->get($m->id);
            $p = $prestasi->get($m->id);
            $sinyal = [];

            if ($k && ($k['kehadiran_kritis'] ?? false)) {
                $sinyal[] = ['kode' => 'kehadiran_kritis', 'label' => 'Kehadiran di bawah ' . self::KEHADIRAN_MINIMAL . '%'];
            }
            if ($p && ($p['ips_menurun'] ?? false)) {
                $sinyal[] = ['kode' => 'ips_menurun', 'label' => 'IPS semester terakhir menurun'];
            }
            if ($p && ($p['ips_rendah'] ?? false)) {
                $sinyal[] = ['kode' => 'ips_rendah', 'label' => 'IPS di bawah ' . number_format(self::IPS_MINIMAL, 2)];
            }

            $ipk = $m->ipk;
            if (is_numeric($ipk) && (float) $ipk > 0 && (float) $ipk < self::IPK_MINIMAL) {
                $sinyal[] = ['kode' => 'ipk_rendah', 'label' => 'IPK di bawah ' . number_format(self::IPK_MINIMAL, 2)];
            }
            if ($m->status !== self::STATUS_AKTIF) {
                $sinyal[] = ['kode' => 'status_tidak_aktif', 'label' => 'Status mahasiswa ' . $m->status];
            }

            $krs = $m->krs->first();
            if ($krs && !in_array($krs->status, self::KRS_SELESAI, true)) {
                $sinyal[] = ['kode' => 'krs_belum_disetujui', 'label' => 'KRS ' . ($krs->status ?? 'draft') . ' (belum disetujui)'];
            }

            $khusus = PaCatatan::where('dosen_id', $dosenId)
                ->where('mahasiswa_id', $m->id)
                ->where('butuh_penanganan_khusus', true)
                ->where('status_tindak_lanjut', '!=', 'selesai')
                ->count();
            if ($khusus > 0) {
                $sinyal[] = ['kode' => 'penanganan_khusus_terbuka', 'label' => $khusus . ' penanganan khusus terbuka'];
            }

            $skor = count($sinyal);
            $band = $skor >= self::RISIKO_TINGGI_MIN
                ? self::BAND_TINGGI
                : ($skor >= self::RISIKO_SEDANG_MIN ? self::BAND_SEDANG : self::BAND_RENDAH);

            return [
                'id' => $m->id,
                'nim' => $m->nim,
                'nama_lengkap' => $m->nama_lengkap,
                'status' => $m->status,
                'ipk' => $ipk,
                'persentase_hadir' => $k['persentase_hadir'] ?? null,
                'ips_terakhir' => $p['ips_terakhir'] ?? null,
                'ips_menurun' => $p['ips_menurun'] ?? false,
                'sinyal' => $sinyal,
                'skor' => $skor,
                'band_risiko' => $band,
            ];
        })->values()->all();
    }

    /**
     * Ringkasan "Perlu Perhatian": advisees dengan skor risiko >= sedang,
     * status tidak aktif, atau sinyal lain, diurutkan menurut skor tertinggi.
     */
    public function alerts(int $dosenId): array
    {
        $semua = collect($this->risiko($dosenId));

        $items = $semua
            ->filter(fn ($r) => ($r['skor'] ?? 0) >= self::RISIKO_SEDANG_MIN || ($r['status'] ?? self::STATUS_AKTIF) !== self::STATUS_AKTIF)
            ->sortBy([['skor', 'desc'], ['nama_lengkap', 'asc']])
            ->values();

        return [
            'summary' => [
                'total_advisees' => $semua->count(),
                'risiko_tinggi' => $semua->filter(fn ($r) => ($r['band_risiko'] ?? '') === self::BAND_TINGGI)->count(),
                'risiko_sedang' => $semua->filter(fn ($r) => ($r['band_risiko'] ?? '') === self::BAND_SEDANG)->count(),
                'kehadiran_kritis' => $semua
                    ->filter(fn ($r) => ($r['persentase_hadir'] ?? null) !== null && $r['persentase_hadir'] < self::KEHADIRAN_MINIMAL)
                    ->count(),
                'perlu_perhatian' => $items->count(),
            ],
            'items' => $items,
        ];
    }

    /** Daftar mahasiswa bimbingan seorang dosen PA (terurut nama). */
    private function advisees(int $dosenId): Collection
    {
        return Mahasiswa::where('dosen_wali_id', $dosenId)
            ->orderBy('nama_lengkap')
            ->get();
    }

    /**
     * Total pertemuan yang bisa diikuti per mahasiswa (jumlah pertemuan seluruh
     * kelas dari KRS detail yang masih aktif).
     */
    private function totalPertemuanPerMahasiswa(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table('siakad_krs as krs')
            ->join('siakad_krs_detail as kd', 'kd.krs_id', '=', 'krs.id')
            ->join('siakad_pertemuan as p', 'p.kelas_id', '=', 'kd.kelas_id')
            ->whereIn('krs.mahasiswa_id', $ids)
            ->where('kd.status', self::KRS_DETAIL_AKTIF)
            ->groupBy('krs.mahasiswa_id')
            ->selectRaw('krs.mahasiswa_id, COUNT(DISTINCT p.id) as total_pertemuan')
            ->pluck('total_pertemuan', 'mahasiswa_id');
    }

    /** Agregasi jumlah absensi per status kehadiran untuk himpunan mahasiswa. */
    private function absensiPerMahasiswa(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table('siakad_absensi_mahasiswa')
            ->whereIn('mahasiswa_id', $ids)
            ->groupBy('mahasiswa_id')
            ->selectRaw(
                "mahasiswa_id,
                 SUM(CASE WHEN status = '" . self::ABSENSI_HADIR . "' THEN 1 ELSE 0 END) as hadir,
                 SUM(CASE WHEN status = '" . self::ABSENSI_SAKIT . "' THEN 1 ELSE 0 END) as sakit,
                 SUM(CASE WHEN status = '" . self::ABSENSI_IZIN . "' THEN 1 ELSE 0 END) as izin,
                 SUM(CASE WHEN status = '" . self::ABSENSI_ALFA . "' THEN 1 ELSE 0 END) as alfa"
            )
            ->get()
            ->keyBy('mahasiswa_id');
    }
}
