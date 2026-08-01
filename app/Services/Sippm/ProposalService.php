<?php

namespace App\Services\Sippm;

use App\Models\Sippm\ProposalKegiatan;
use App\Models\Sippm\AnggotaKegiatan;
use App\Models\Sippm\ReviewerKegiatan;
use App\Models\Simpeg\Pegawai;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ProposalService
{
    /**
     * Create a new proposal with initial team members and SIAKAD course integration.
     */
    public function createProposal(array $data): ProposalKegiatan
    {
        return DB::transaction(function () use ($data) {
            $year = date('Y');
            $count = ProposalKegiatan::whereYear('created_at', $year)->count() + 1;
            $kodeProposal = 'PRP-' . $year . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

            $proposal = ProposalKegiatan::create([
                'periode_id' => $data['periode_id'],
                'skema_id' => $data['skema_id'],
                'ketua_pegawai_id' => $data['ketua_pegawai_id'],
                'mitra_kerjasama_id' => $data['mitra_kerjasama_id'] ?? null,
                'mata_kuliah_id' => $data['mata_kuliah_id'] ?? null,
                'kode_proposal' => $kodeProposal,
                'judul' => $data['judul'],
                'abstrak' => $data['abstrak'],
                'rumpun_ilmu' => $data['rumpun_ilmu'],
                'target_tkt' => $data['target_tkt'] ?? 1,
                'anggaran_diajukan' => $data['anggaran_diajukan'],
                'file_proposal' => $data['file_proposal'],
                'status' => 'draft',
            ]);

            // Add Ketua as Anggota record with jenis_tim = 'dosen'
            AnggotaKegiatan::create([
                'proposal_id' => $proposal->id,
                'jenis_tim' => 'dosen',
                'pegawai_id' => $data['ketua_pegawai_id'],
                'peran_dalam_tim' => 'Ketua Pengusul',
                'tugas_kegiatan' => 'Ketua Pengusul & Penanggung Jawab Riset',
            ]);

            // Add additional team members if provided
            if (!empty($data['anggota']) && is_array($data['anggota'])) {
                foreach ($data['anggota'] as $anggota) {
                    // Normalize jenis_tim format
                    $jenisTim = $anggota['jenis_tim'] ?? ($anggota['jenis_anggota'] ?? 'dosen');

                    AnggotaKegiatan::create([
                        'proposal_id' => $proposal->id,
                        'jenis_tim' => $jenisTim,
                        'pegawai_id' => $anggota['pegawai_id'] ?? null,
                        'mahasiswa_id' => $anggota['mahasiswa_id'] ?? null,
                        'mata_kuliah_id' => $anggota['mata_kuliah_id'] ?? null,
                        'nama_eksternal' => $anggota['nama_eksternal'] ?? null,
                        'instansi_eksternal' => $anggota['instansi_eksternal'] ?? null,
                        'nidn_eksternal' => $anggota['nidn_eksternal'] ?? null,
                        'peran_dalam_tim' => $anggota['peran_dalam_tim'] ?? ($anggota['peran'] ?? 'Anggota'),
                        'tugas_kegiatan' => $anggota['tugas_kegiatan'] ?? null,
                    ]);
                }
            }

            return $proposal->load(['skema', 'periode', 'ketuaPegawai', 'anggota.pegawai']);
        });
    }

    /**
     * Update existing proposal details.
     */
    public function updateProposal(ProposalKegiatan $proposal, array $data): ProposalKegiatan
    {
        return DB::transaction(function () use ($proposal, $data) {
            $proposal->update(array_filter([
                'judul' => $data['judul'] ?? null,
                'abstrak' => $data['abstrak'] ?? null,
                'rumpun_ilmu' => $data['rumpun_ilmu'] ?? null,
                'target_tkt' => $data['target_tkt'] ?? null,
                'anggaran_diajukan' => $data['anggaran_diajukan'] ?? null,
                'file_proposal' => $data['file_proposal'] ?? null,
                'mata_kuliah_id' => $data['mata_kuliah_id'] ?? null,
            ]));

            return $proposal->fresh(['skema', 'periode', 'ketuaPegawai', 'anggota']);
        });
    }

    /**
     * Submit proposal from draft to diajukan.
     */
    public function submitProposal(ProposalKegiatan $proposal): ProposalKegiatan
    {
        $proposal->update(['status' => 'diajukan']);
        return $proposal;
    }

    /**
     * Assign reviewer to proposal (LPPM Admin).
     */
    public function assignReviewer(ProposalKegiatan $proposal, int $reviewerPegawaiId): ReviewerKegiatan
    {
        return DB::transaction(function () use ($proposal, $reviewerPegawaiId) {
            $reviewer = ReviewerKegiatan::create([
                'proposal_id' => $proposal->id,
                'reviewer_pegawai_id' => $reviewerPegawaiId,
                'tgl_penugasan' => now(),
                'status_review' => 'pending',
            ]);

            $proposal->update(['status' => 'plot_reviewer']);

            return $reviewer->load(['proposal', 'reviewerPegawai']);
        });
    }

    /**
     * Fetch active courses (Mata Kuliah) for a student from SIAKAD for Grade Conversion integration.
     */
    public function getActiveMataKuliahForMahasiswa(int $mahasiswaId): array
    {
        // Try fetching active KRS & course details from SIAKAD if tables exist
        if (DB::getSchemaBuilder()->hasTable('krs') && DB::getSchemaBuilder()->hasTable('krs_detail') && DB::getSchemaBuilder()->hasTable('mata_kuliah')) {
            $courses = DB::table('krs_detail')
                ->join('krs', 'krs_detail.krs_id', '=', 'krs.id')
                ->join('kelas', 'krs_detail.kelas_id', '=', 'kelas.id')
                ->join('mata_kuliah', 'kelas.mata_kuliah_id', '=', 'mata_kuliah.id')
                ->where('krs.mahasiswa_id', $mahasiswaId)
                ->where('krs_detail.status', 'aktif')
                ->select(
                    'mata_kuliah.id as mata_kuliah_id',
                    'mata_kuliah.kode_mk',
                    'mata_kuliah.nama as nama_mk',
                    'mata_kuliah.total_sks',
                    'kelas.nama_kelas'
                )
                ->get();

            if ($courses->isNotEmpty()) {
                return $courses->toArray();
            }
        }

        // Standard fallback mock active courses list for SIAKAD integration
        return [
            [
                'mata_kuliah_id' => 101,
                'kode_mk' => 'MK-PML-01',
                'nama_mk' => 'Metodologi Penelitian & Pengabdian Masyarakat',
                'total_sks' => 3,
                'nama_kelas' => 'Kelas A - Genap 2025/2026',
            ],
            [
                'mata_kuliah_id' => 102,
                'kode_mk' => 'MK-MBKM-02',
                'nama_mk' => 'Proyek Kemanusiaan & Pengabdian Desa',
                'total_sks' => 4,
                'nama_kelas' => 'Kelas MBKM - Genap 2025/2026',
            ],
            [
                'mata_kuliah_id' => 103,
                'kode_mk' => 'MK-SKR-03',
                'nama_mk' => 'Tugas Akhir / Skripsi',
                'total_sks' => 6,
                'nama_kelas' => 'Kelas Riset - Genap 2025/2026',
            ],
        ];
    }

    /**
     * Get reference list for Tendik (SIMPEG).
     */
    public function getTendikReference(): array
    {
        return Pegawai::where('jenis_pegawai', 'tendik')
            ->where('status', 'aktif')
            ->select('id', 'nip', 'nama_lengkap', 'jenis_pegawai', 'unit_kerja_id')
            ->get()
            ->toArray();
    }

    /**
     * Get reference list for Dosen (SIMPEG).
     */
    public function getDosenReference(): array
    {
        return Pegawai::with('unitKerja')
            ->where('jenis_pegawai', 'dosen')
            ->where('status', 'aktif')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'nip' => $p->nip,
                    'nama_lengkap' => $p->nama_lengkap,
                    'jenis_pegawai' => $p->jenis_pegawai,
                    'prodi' => $p->unitKerja->nama_unit ?? $p->unitKerja->nama ?? 'S1 Teknik Informatika',
                ];
            })
            ->toArray();
    }
}
