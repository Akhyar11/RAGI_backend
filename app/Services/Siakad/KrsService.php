<?php

namespace App\Services\Siakad;

use App\Models\Siakad\Kelas;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\HakaksesKrs;
use App\Models\Siakad\Krs;
use App\Models\Siakad\KrsDetail;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\NilaiMahasiswa;
use App\Models\Siakad\PrasyaratMk;
use Illuminate\Support\Facades\DB;

class KrsService
{
    public function maxSksByIpk(float $ipk): int
    {
        if ($ipk >= 3.00) return 24;
        if ($ipk >= 2.50) return 22;
        if ($ipk >= 2.00) return 20;
        return 18;
    }

    public function resolveMahasiswa(?int $userId, ?int $mahasiswaId = null): Mahasiswa
    {
        $mhs = null;
        if ($userId) {
            $mhs = Mahasiswa::where('user_id', $userId)->first();
        }
        if (!$mhs && $mahasiswaId) {
            $mhs = Mahasiswa::find($mahasiswaId);
        }
        if (!$mhs) {
            abort(response()->json(['status' => 'error', 'message' => 'Data mahasiswa tidak ditemukan.'], 404));
        }
        return $mhs;
    }

    public function assertMahasiswaAktif(Mahasiswa $mhs): void
    {
        if ($mhs->status !== 'aktif') {
            abort(response()->json([
                'status' => 'error',
                'message' => "KRS ditolak. Status akademik mahasiswa saat ini '{$mhs->status}'. Hanya mahasiswa aktif yang dapat mengisi KRS (herregistrasi).",
            ], 422));
        }
    }

    /**
     * Gate akses KRS ala wsia_hakakses legacy: tanpa baris = terbuka;
     * baris is_dibuka=false = dikunci BAAK.
     */
    public function assertAksesKrsDibuka(Mahasiswa $mhs, int $tahunAkademikId): void
    {
        $akses = HakaksesKrs::where('mahasiswa_id', $mhs->id)
            ->where('tahun_akademik_id', $tahunAkademikId)
            ->first();
        if ($akses && !$akses->is_dibuka) {
            abort(response()->json([
                'status' => 'error',
                'message' => 'Akses KRS Anda untuk periode ini belum dibuka/dikunci oleh BAAK. Hubungi bagian akademik.',
                'errors' => ['hak_akses' => ['Akses KRS periode ini dikunci BAAK.']],
            ], 422));
        }
    }

    protected function hurufToMutu(?string $huruf): float
    {
        return match (strtoupper(trim((string) $huruf))) {
            'A' => 4.00, 'A-' => 3.75, 'B+' => 3.25, 'B' => 3.00,
            'B-' => 2.75, 'C+' => 2.25, 'C' => 2.00, 'D' => 1.00,
            'E' => 0.00, default => 0.00,
        };
    }

    protected function isLulusHuruf(?string $huruf): bool
    {
        $h = strtoupper(trim((string) $huruf));
        return $h !== '' && $h !== 'D' && $h !== 'E';
    }

    public function hasLulusMk(int $mahasiswaId, int $mataKuliahId, ?float $nilaiMinimum = null): bool
    {
        // Cek konversi transfer yang disetujui
        $mhs = Mahasiswa::with(['konversiTransfer.details'])->find($mahasiswaId);
        if ($mhs?->konversiTransfer && $mhs->konversiTransfer->status === 'disetujui') {
            foreach ($mhs->konversiTransfer->details as $d) {
                if ((int) $d->mata_kuliah_diakui_id === (int) $mataKuliahId && $this->isLulusHuruf($d->nilai_huruf_asal)) {
                    if ($nilaiMinimum !== null && $this->hurufToMutu($d->nilai_huruf_asal) * 25 < $nilaiMinimum) {
                        continue;
                    }
                    return true;
                }
            }
        }

        $nilai = NilaiMahasiswa::whereHas('krsDetail.krs', fn($q) => $q->where('mahasiswa_id', $mahasiswaId))
            ->whereHas('krsDetail.kelas', fn($q) => $q->where('mata_kuliah_id', $mataKuliahId))
            ->where('is_final', true)
            ->orderByDesc('bobot_mutu')
            ->first();

        if (!$nilai || !$this->isLulusHuruf($nilai->nilai_huruf)) {
            return false;
        }
        if ($nilaiMinimum !== null && (float) $nilai->nilai_akhir < $nilaiMinimum) {
            return false;
        }
        return true;
    }

    public function assertPrasyaratTerpenuhi(int $mahasiswaId, int $mataKuliahId): void
    {
        $prasyarats = PrasyaratMk::where('mata_kuliah_id', $mataKuliahId)->get();
        foreach ($prasyarats as $p) {
            $ok = $p->tipe === 'pernah_ambil'
                ? $this->hasPernahAmbil($mahasiswaId, (int) $p->prasyarat_id)
                : $this->hasLulusMk($mahasiswaId, (int) $p->prasyarat_id, $p->nilai_minimum ? (float) $p->nilai_minimum : null);
            if (!$ok) {
                $nama = $p->prasyarat?->nama ?? "MK ID {$p->prasyarat_id}";
                abort(response()->json([
                    'status' => 'error',
                    'message' => "Prasyarat belum terpenuhi: wajib " . ($p->tipe === 'pernah_ambil' ? 'pernah mengambil' : 'lulus') . " {$nama} terlebih dahulu.",
                ], 422));
            }
        }
    }

    public function hasPernahAmbil(int $mahasiswaId, int $mataKuliahId): bool
    {
        return KrsDetail::whereHas('krs', fn($q) => $q->where('mahasiswa_id', $mahasiswaId))
            ->whereHas('kelas', fn($q) => $q->where('mata_kuliah_id', $mataKuliahId))
            ->exists();
    }

    protected function jamToMenit(?string $jam): ?int
    {
        if (!$jam) return null;
        $parts = explode(':', substr($jam, 0, 5));
        if (count($parts) < 2) return null;
        return ((int) $parts[0]) * 60 + ((int) $parts[1]);
    }

    /**
     * Cegah IDOR/BOLA: data milik sendiri bebas; manajerial BAAK via permission
     * resmi; PA via permission approve + relasi bimbingan/peserta kelas.
     * Tanpa string role hardcoded (aturan Zero Hardcode).
     */
    public function assertBolehLihatMahasiswa($user, int $mahasiswaId): ?\Illuminate\Http\JsonResponse
    {
        if ($user && Mahasiswa::where('id', $mahasiswaId)->where('user_id', $user->id)->exists()) {
            return null;
        }
        if ($user && ($user->isSuperAdmin() || $user->hasPermission('siakad.mahasiswa.manage'))) {
            return null;
        }
        if ($user && $user->hasPermission('siakad.krs.approve')) {
            $dosen = Dosen::where('user_id', $user->id)->first();
            if ($dosen) {
                $mhs = Mahasiswa::find($mahasiswaId);
                $isAdvisee = $mhs && (int) $mhs->dosen_wali_id === (int) $dosen->id;
                $isPeserta = KrsDetail::whereHas('krs', fn($q) => $q->where('mahasiswa_id', $mahasiswaId))
                    ->whereHas('kelas.dosenPengampu', fn($q) => $q->where('dosen_id', $dosen->id))
                    ->exists();
                if ($isAdvisee || $isPeserta) {
                    return null;
                }
            }
        }
        return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki akses ke data mahasiswa ini.'], 403);
    }

    public function assertTidakBentrok(Krs $krs, Kelas $kelasBaru): void
    {
        if (!$kelasBaru->hari || !$kelasBaru->jam_mulai || !$kelasBaru->jam_selesai) {
            return;
        }
        $nbAwal = $this->jamToMenit($kelasBaru->jam_mulai);
        $nbAkhir = $this->jamToMenit($kelasBaru->jam_selesai);
        if ($nbAwal === null || $nbAkhir === null) return;

        $details = $krs->krsDetails()->with('kelas')->get();
        foreach ($details as $d) {
            $k = $d->kelas;
            if (!$k || $k->id === $kelasBaru->id || strtolower((string) $k->hari) !== strtolower((string) $kelasBaru->hari)) {
                continue;
            }
            $a = $this->jamToMenit($k->jam_mulai);
            $b = $this->jamToMenit($k->jam_selesai);
            if ($a === null || $b === null) continue;
            if ($nbAwal < $b && $a < $nbAkhir) {
                abort(response()->json([
                    'status' => 'error',
                    'message' => "Jadwal bentrok dengan kelas {$k->nama_kelas} (" . ucfirst((string) $k->hari) . " " . substr((string) $k->jam_mulai, 0, 5) . "-" . substr((string) $k->jam_selesai, 0, 5) . ").",
                ], 422));
            }
        }
    }

    public function assertTidakDuplikatMk(Krs $krs, Kelas $kelasBaru): void
    {
        $existing = $krs->krsDetails()->with('kelas')->whereHas('kelas', fn($q) => $q->where('mata_kuliah_id', $kelasBaru->mata_kuliah_id)->where('id', '!=', $kelasBaru->id))->get();
        if ($existing->isEmpty()) {
            return;
        }

        // Aturan keluarga induk-anak: boleh maksimal 1 induk teori + 1 anak praktik per MK.
        if ($kelasBaru->parent_kelas_id) {
            $parentId = (int) $kelasBaru->parent_kelas_id;
            foreach ($existing as $d) {
                $k = $d->kelas;
                if (!$k) {
                    continue;
                }
                // Saudara praktik lain dari induk yang sama -> tolak (cukup 1 praktik)
                if ($k->parent_kelas_id && (int) $k->parent_kelas_id === $parentId && (int) $k->id !== (int) $kelasBaru->id) {
                    abort(response()->json([
                        'status' => 'error',
                        'message' => 'Anda sudah mengambil kelas praktik lain dari induk yang sama. Cukup 1 teori + 1 praktik per mata kuliah.',
                        'errors' => ['kelas' => ['Sudah mengambil praktik lain dari induk yang sama.']],
                    ], 422));
                }
            }
            // Pasangan induknya sendiri -> izinkan
            $hasParent = $existing->contains(fn($d) => (int) ($d->kelas?->id) === $parentId);
            if ($hasParent) {
                return;
            }
        } else {
            // Kelas induk/biasa baru: izinkan jika yang sudah ada hanya anaknya sendiri
            $childIds = Kelas::where('parent_kelas_id', $kelasBaru->id)->pluck('id')->map(fn($v) => (int) $v)->toArray();
            if (!empty($childIds)) {
                $allAreMyChildren = $existing->every(fn($d) => in_array((int) ($d->kelas?->id), $childIds, true));
                if ($allAreMyChildren && $existing->count() <= 1) {
                    return;
                }
            }
        }

        abort(response()->json([
            'status' => 'error',
            'message' => 'Mata kuliah yang sama sudah diambil di kelas lain pada semester ini.',
            'errors' => ['kelas' => ['Mata kuliah sudah diambil di kelas lain semester ini.']],
        ], 422));
    }

    /**
     * Dua kelas satu keluarga jika: sama persis induknya, atau salah satunya induk dari lainnya.
     */
    protected function isSatuKeluargaIndukAnak(Kelas $a, Kelas $b): bool
    {
        if ((int) $a->id === (int) $b->id) {
            return true;
        }
        if ($b->parent_kelas_id && (int) $b->parent_kelas_id === (int) $a->id) {
            return true;
        }
        if ($a->parent_kelas_id && (int) $a->parent_kelas_id === (int) $b->id) {
            return true;
        }
        return false;
    }

    public function addClass(Mahasiswa $mhs, int $tahunAkademikId, int $kelasId, float $ipk): array
    {
        return DB::transaction(function () use ($mhs, $tahunAkademikId, $kelasId, $ipk) {
            $this->assertMahasiswaAktif($mhs);
            $this->assertAksesKrsDibuka($mhs, $tahunAkademikId);

            $kelas = Kelas::with('mataKuliah')->where('id', $kelasId)->lockForUpdate()->firstOrFail();
            if ($kelas->status !== 'aktif') {
                abort(response()->json(['status' => 'error', 'message' => 'Kelas belum dibuka untuk KRS (status bukan aktif).'], 422));
            }
            if ($kelas->kuota_krs <= 0) {
                abort(response()->json(['status' => 'error', 'message' => 'Kuota kelas ini sudah penuh.'], 422));
            }

            $krs = Krs::where('mahasiswa_id', $mhs->id)
                ->where('tahun_akademik_id', $tahunAkademikId)
                ->lockForUpdate()
                ->first();
            if (!$krs) {
                $krs = Krs::create([
                    'mahasiswa_id' => $mhs->id,
                    'tahun_akademik_id' => $tahunAkademikId,
                    'status' => 'draft',
                    'total_sks_diambil' => 0,
                    'locked_by_keuangan' => false,
                ]);
            }
            if (in_array($krs->status, ['diajukan', 'disetujui', 'dikunci'], true)) {
                // Revisi setelah pengajuan mengembalikan ke draft agar PA meninjau ulang
                $krs->status = 'draft';
            }

            $this->assertPrasyaratTerpenuhi($mhs->id, (int) $kelas->mata_kuliah_id);
            $this->assertTidakDuplikatMk($krs, $kelas);
            $this->assertTidakBentrok($krs, $kelas);

            $detail = KrsDetail::firstOrCreate(['krs_id' => $krs->id, 'kelas_id' => $kelas->id]);
            NilaiMahasiswa::firstOrCreate(['krs_detail_id' => $detail->id]);

            $totalSks = $krs->krsDetails()->with('kelas.mataKuliah')->get()
                ->sum(fn($d) => $d->kelas?->mataKuliah?->total_sks ?? 0);
            $maxSks = $this->maxSksByIpk($ipk);
            if ($totalSks > $maxSks) {
                abort(response()->json([
                    'status' => 'error',
                    'message' => "Batas SKS terlampaui. IPK {$ipk} maksimal {$maxSks} SKS, pengajuan ini menjadi {$totalSks} SKS.",
                ], 422));
            }
            $krs->total_sks_diambil = $totalSks;
            $krs->save();

            $kelas->decrement('kuota_krs');

            return [$krs, $kelas];
        });
    }
}
