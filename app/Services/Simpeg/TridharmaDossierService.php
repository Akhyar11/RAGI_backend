<?php

namespace App\Services\Simpeg;

use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PenilaianKinerja;
use App\Models\Simpeg\SkPegawai;
use App\Models\Simpeg\SuratTugas;
use App\Models\Simpeg\SertifikasiDosen;
use App\Models\Simpeg\RiwayatPelatihan;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\DosenPengampu;
use App\Models\Siakad\Mahasiswa;
use App\Models\Sippm\ProposalKegiatan;
use App\Models\Sippm\AnggotaKegiatan;
use App\Models\Sippm\PublikasiIlmiah;
use App\Models\Sippm\HkiDanBuku;

class TridharmaDossierService
{
    /**
     * Himpun data komprehensif portofolio Tridharma dosen terintegrasi
     */
    public function getDossier(int $pegawaiId): array
    {
        $pegawai = Pegawai::with([
            'unitKerja',
            'user',
            'dosen.programStudi',
        ])->findOrFail($pegawaiId);

        // 1. Pengajaran (SIAKAD)
        $dosen = $pegawai->dosen;
        $pengajaran = [];
        $mahasiswaWali = [];
        $totalSksAjar = 0;

        if ($dosen) {
            $kelasDiampu = DosenPengampu::with([
                'kelas.mataKuliah',
                'kelas.tahunAkademik',
            ])
            ->where('dosen_id', $dosen->id)
            ->get();

            foreach ($kelasDiampu as $p) {
                $mk = $p->kelas?->mataKuliah;
                $ta = $p->kelas?->tahunAkademik;
                $sks = $mk?->total_sks ?? ($mk?->sks ?? 0);
                $totalSksAjar += $sks;

                $pengajaran[] = [
                    'id' => $p->id,
                    'kelas_id' => $p->kelas_id,
                    'kode_mk' => $mk?->kode_mk ?? '-',
                    'nama_mk' => $mk?->nama ?? ($mk?->nama_mk ?? 'Mata Kuliah'),
                    'sks' => $sks,
                    'kode_kelas' => $p->kelas?->kode_kelas ?? '-',
                    'nama_kelas' => $p->kelas?->nama_kelas ?? '-',
                    'tahun_akademik' => $ta?->nama ?? ($ta?->nama_tahun ?? '-'),
                    'semester' => $ta?->semester ?? '-',
                    'peran' => $p->peran ?? 'pengampu_utama',
                ];
            }

            $mahasiswaWali = Mahasiswa::with(['programStudi'])
                ->where('dosen_wali_id', $dosen->id)
                ->get()
                ->map(function ($m) {
                    return [
                        'id' => $m->id,
                        'nim' => $m->nim,
                        'nama_lengkap' => $m->nama_lengkap,
                        'program_studi' => $m->programStudi?->nama,
                        'angkatan' => $m->angkatan,
                        'status' => $m->status_mahasiswa ?? 'aktif',
                    ];
                })
                ->toArray();
        }

        // 2. Penelitian (SIPPM)
        $penelitianKetua = ProposalKegiatan::with(['periode', 'skema'])
            ->where('ketua_pegawai_id', $pegawai->id)
            ->whereHas('skema', fn($q) => $q->where('tipe', 'penelitian'))
            ->latest()
            ->get();

        $penelitianAnggota = AnggotaKegiatan::with(['proposal.periode', 'proposal.skema', 'proposal.ketuaPegawai'])
            ->where('pegawai_id', $pegawai->id)
            ->whereHas('proposal.skema', fn($q) => $q->where('tipe', 'penelitian'))
            ->latest()
            ->get();

        $publikasiList = PublikasiIlmiah::where('pegawai_id', $pegawai->id)
            ->latest()
            ->get();

        $hkiList = HkiDanBuku::where('pegawai_id', $pegawai->id)
            ->latest()
            ->get();

        // 3. Pengabdian Kepada Masyarakat (SIPPM)
        $pengabdianKetua = ProposalKegiatan::with(['periode', 'skema'])
            ->where('ketua_pegawai_id', $pegawai->id)
            ->whereHas('skema', fn($q) => $q->where('tipe', 'pengabdian'))
            ->latest()
            ->get();

        $pengabdianAnggota = AnggotaKegiatan::with(['proposal.periode', 'proposal.skema', 'proposal.ketuaPegawai'])
            ->where('pegawai_id', $pegawai->id)
            ->whereHas('proposal.skema', fn($q) => $q->where('tipe', 'pengabdian'))
            ->latest()
            ->get();

        // 4. Unsur Penunjang Tridharma (SIMPEG)
        $suratTugasList = SuratTugas::with(['kategoriKegiatan', 'jenisTransportasi'])
            ->where(function ($q) use ($pegawai) {
                $q->where('pegawai_id', $pegawai->id)
                  ->orWhereHas('anggota', fn($sq) => $sq->where('pegawai_id', $pegawai->id));
            })
            ->latest()
            ->get();

        $skList = SkPegawai::with(['kategori', 'kategoriSk'])
            ->where('pegawai_id', $pegawai->id)
            ->latest()
            ->get();

        $sertifikasiList = SertifikasiDosen::with(['jenisSertifikasi'])
            ->where('pegawai_id', $pegawai->id)
            ->latest()
            ->get();

        $pelatihanList = RiwayatPelatihan::with(['peran'])
            ->where('pegawai_id', $pegawai->id)
            ->latest()
            ->get();

        $kinerjaList = PenilaianKinerja::with(['pejabatPenilai', 'evaluator'])
            ->where('pegawai_id', $pegawai->id)
            ->latest('tahun')
            ->get();

        // 5. Metrik Portofolio Kumulatif (Summary KPI)
        $totalDanaPenelitian = $penelitianKetua->sum('anggaran_disetujui');
        $totalDanaPengabdian = $pengabdianKetua->sum('anggaran_disetujui');

        $publikasiScopus = $publikasiList->filter(function ($p) {
            return str_contains(strtolower($p->indexing ?? ''), 'scopus') ||
                   str_contains(strtolower($p->nama_jurnal_prosiding ?? ''), 'scopus');
        })->count();

        $publikasiSinta = $publikasiList->filter(function ($p) {
            return str_contains(strtolower($p->indexing ?? ''), 'sinta');
        })->count();

        $rerataSkp = $kinerjaList->where('status', 'dinilai')->avg('nilai_skp') ?? 0;
        $rerataBkd = $kinerjaList->where('status', 'dinilai')->whereNotNull('nilai_bkd')->avg('nilai_bkd') ?? 0;

        return [
            'pegawai' => [
                'id' => $pegawai->id,
                'nama_lengkap' => $pegawai->nama_lengkap,
                'nama_gelar' => $pegawai->nama_gelar,
                'nip' => $pegawai->nip,
                'nidn' => $pegawai->nidn ?: $dosen?->nidn,
                'nuptk' => $pegawai->nuptk ?: $dosen?->nuptk,
                'unit_kerja' => $pegawai->unitKerja?->nama,
                'program_studi' => $dosen?->programStudi?->nama,
                'jabatan_fungsional' => $dosen?->jabatan_akademik ?? $pegawai->jabatan_terakhir ?? '-',
                'status_kepegawaian' => $pegawai->status_kepegawaian,
                'sinta_id' => $pegawai->sinta_id,
                'scopus_id' => $pegawai->scopus_id,
                'google_scholar_id' => $pegawai->google_scholar_id,
                'orcid_id' => $pegawai->orcid_id,
            ],
            'metrics' => [
                'total_kelas_ajar' => count($pengajaran),
                'total_sks_ajar' => (int) $totalSksAjar,
                'total_mhs_wali' => count($mahasiswaWali),
                'total_penelitian' => $penelitianKetua->count() + $penelitianAnggota->count(),
                'total_dana_penelitian' => (float) $totalDanaPenelitian,
                'total_pengabdian' => $pengabdianKetua->count() + $pengabdianAnggota->count(),
                'total_dana_pengabdian' => (float) $totalDanaPengabdian,
                'total_publikasi' => $publikasiList->count(),
                'total_publikasi_scopus' => $publikasiScopus,
                'total_publikasi_sinta' => $publikasiSinta,
                'total_hki_buku' => $hkiList->count(),
                'total_surat_tugas' => $suratTugasList->count(),
                'total_sk_penugasan' => $skList->count(),
                'total_sertifikasi' => $sertifikasiList->count(),
                'total_pelatihan' => $pelatihanList->count(),
                'rerata_nilai_skp' => round((float) $rerataSkp, 2),
                'rerata_nilai_bkd' => round((float) $rerataBkd, 2),
            ],
            'pengajaran' => [
                'kelas' => $pengajaran,
                'mahasiswa_wali' => $mahasiswaWali,
            ],
            'penelitian' => [
                'hibah_ketua' => $penelitianKetua,
                'hibah_anggota' => $penelitianAnggota,
                'publikasi' => $publikasiList,
                'hki_buku' => $hkiList,
            ],
            'pengabdian' => [
                'hibah_ketua' => $pengabdianKetua,
                'hibah_anggota' => $pengabdianAnggota,
            ],
            'penunjang' => [
                'surat_tugas' => $suratTugasList,
                'sk_pegawai' => $skList,
                'sertifikasi' => $sertifikasiList,
                'pelatihan' => $pelatihanList,
                'kinerja_skp' => $kinerjaList,
            ],
        ];
    }
}
