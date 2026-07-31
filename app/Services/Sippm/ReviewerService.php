<?php

namespace App\Services\Sippm;

use App\Models\Sippm\ProposalKegiatan;
use App\Models\Sippm\ReviewerKegiatan;
use App\Models\Sippm\PenilaianProposal;
use Illuminate\Support\Facades\DB;

class ReviewerService
{
    /**
     * Submit review scores and recommendation.
     */
    public function submitPenilaian(ReviewerKegiatan $reviewer, array $evalData): PenilaianProposal
    {
        return DB::transaction(function () use ($reviewer, $evalData) {
            $skorJejak = $evalData['skor_rekam_jejak'] ?? 0.00;
            $skorSubstansi = $evalData['skor_substansi'] ?? 0.00;
            $skorAnggaran = $evalData['skor_rencana_anggaran'] ?? 0.00;
            
            // Calculate weighted score (e.g. 20% jejak, 50% substansi, 30% anggaran)
            $skorTotal = ($skorJejak * 0.20) + ($skorSubstansi * 0.50) + ($skorAnggaran * 0.30);

            $penilaian = PenilaianProposal::updateOrCreate(
                ['reviewer_kegiatan_id' => $reviewer->id],
                [
                    'skor_rekam_jejak' => $skorJejak,
                    'skor_substansi' => $skorSubstansi,
                    'skor_rencana_anggaran' => $skorAnggaran,
                    'skor_total' => $evalData['skor_total'] ?? $skorTotal,
                    'rekomendasi' => $evalData['rekomendasi'],
                    'catatan_revisi' => $evalData['catatan_revisi'] ?? null,
                    'file_penilaian' => $evalData['file_penilaian'] ?? null,
                    'submitted_at' => now(),
                ]
            );

            $reviewer->update(['status_review' => 'selesai']);
            $reviewer->proposal->update(['status' => 'penilaian']);

            return $penilaian;
        });
    }

    /**
     * Finalize proposal status (LPPM Admin decision).
     */
    public function finalizeProposal(ProposalKegiatan $proposal, string $status, ?float $anggaranDisetujui = null): ProposalKegiatan
    {
        $data = ['status' => $status];
        if ($anggaranDisetujui !== null) {
            $data['anggaran_disetujui'] = $anggaranDisetujui;
        }

        $proposal->update($data);
        return $proposal;
    }
}
