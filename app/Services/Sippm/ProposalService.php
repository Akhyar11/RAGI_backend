<?php

namespace App\Services\Sippm;

use App\Models\Sippm\ProposalKegiatan;
use App\Models\Sippm\AnggotaKegiatan;
use App\Models\Sippm\ReviewerKegiatan;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ProposalService
{
    /**
     * Create a new proposal with initial team members.
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
                'kode_proposal' => $kodeProposal,
                'judul' => $data['judul'],
                'abstrak' => $data['abstrak'],
                'rumpun_ilmu' => $data['rumpun_ilmu'],
                'target_tkt' => $data['target_tkt'] ?? 1,
                'anggaran_diajukan' => $data['anggaran_diajukan'],
                'file_proposal' => $data['file_proposal'],
                'status' => 'draft',
            ]);

            // Add Ketua as Anggota record
            AnggotaKegiatan::create([
                'proposal_id' => $proposal->id,
                'jenis_anggota' => 'dosen',
                'pegawai_id' => $data['ketua_pegawai_id'],
                'peran' => 'ketua',
                'tugas_kegiatan' => 'Ketua Pengusul & Penanggung Jawab Riset',
            ]);

            // Add additional team members if provided
            if (!empty($data['anggota']) && is_array($data['anggota'])) {
                foreach ($data['anggota'] as $anggota) {
                    AnggotaKegiatan::create([
                        'proposal_id' => $proposal->id,
                        'jenis_anggota' => $anggota['jenis_anggota'],
                        'pegawai_id' => $anggota['pegawai_id'] ?? null,
                        'mahasiswa_id' => $anggota['mahasiswa_id'] ?? null,
                        'nama_eksternal' => $anggota['nama_eksternal'] ?? null,
                        'instansi_eksternal' => $anggota['instansi_eksternal'] ?? null,
                        'peran' => $anggota['peran'] ?? 'anggota',
                        'tugas_kegiatan' => $anggota['tugas_kegiatan'] ?? null,
                    ]);
                }
            }

            return $proposal->load(['skema', 'periode', 'ketuaPegawai', 'anggota']);
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
}
