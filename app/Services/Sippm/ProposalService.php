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

            $periodeId = (int) $data['periode_id'];
            $skemaId = (int) $data['skema_id'];
            $ketuaPegawaiId = (int) $data['ketua_pegawai_id'];

            $proposal = ProposalKegiatan::create([
                'periode_id' => $periodeId,
                'skema_id' => $skemaId,
                'ketua_pegawai_id' => $ketuaPegawaiId,
                'mitra_kerjasama_id' => $data['mitra_kerjasama_id'] ?? null,
                'mata_kuliah_id' => $data['mata_kuliah_id'] ?? null,
                'kode_proposal' => $kodeProposal,
                'judul' => $data['judul'],
                'abstrak' => $data['abstrak'],
                'rumpun_ilmu' => $data['rumpun_ilmu'],
                'target_tkt' => $data['target_tkt'] ?? 1,
                'anggaran_diajukan' => $data['anggaran_diajukan'],
                'file_proposal' => $data['file_proposal'] ?? 'dokumen_proposal_usulan.pdf',
                'status' => 'draft',
            ]);

            // Add Ketua as Anggota record with jenis_tim = 'dosen'
            AnggotaKegiatan::create([
                'proposal_id' => $proposal->id,
                'jenis_tim' => 'dosen',
                'pegawai_id' => $ketuaPegawaiId,
                'peran_dalam_tim' => 'Ketua Pengusul',
                'tugas_kegiatan' => 'Ketua Pengusul & Penanggung Jawab Riset',
            ]);

            // Add additional team members if provided
            if (!empty($data['anggota']) && is_array($data['anggota'])) {
                foreach ($data['anggota'] as $anggota) {
                    // Normalize jenis_tim format
                    $jenisTim = $anggota['jenis_tim'] ?? ($anggota['jenis_anggota'] ?? 'dosen');

                    $memberPegawaiId = !empty($anggota['pegawai_id']) ? (int) $anggota['pegawai_id'] : null;
                    if ($memberPegawaiId && !DB::table('simpeg_pegawai')->where('id', $memberPegawaiId)->exists()) {
                        $memberPegawaiId = null;
                    }

                    AnggotaKegiatan::create([
                        'proposal_id' => $proposal->id,
                        'jenis_tim' => $jenisTim,
                        'pegawai_id' => $memberPegawaiId,
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
            $oldValues = $proposal->getOriginal();

            $proposal->update(array_filter([
                'judul' => $data['judul'] ?? null,
                'abstrak' => $data['abstrak'] ?? null,
                'rumpun_ilmu' => $data['rumpun_ilmu'] ?? null,
                'target_tkt' => $data['target_tkt'] ?? null,
                'anggaran_diajukan' => $data['anggaran_diajukan'] ?? null,
                'anggaran_disetujui' => $data['anggaran_disetujui'] ?? null,
                'file_proposal' => $data['file_proposal'] ?? null,
                'mata_kuliah_id' => $data['mata_kuliah_id'] ?? null,
                'status' => $data['status'] ?? null,
            ]));

            $newValues = $proposal->getChanges();

            if (!empty($newValues)) {
                try {
                    \App\Services\Core\AuditLogService::record(
                        action: 'update',
                        module: 'sippm',
                        entityType: ProposalKegiatan::class,
                        entityId: $proposal->id,
                        oldValues: $oldValues,
                        newValues: $newValues,
                        description: "Memperbarui data proposal ID: {$proposal->id}"
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log updateProposal: ' . $e->getMessage());
                }
            }

            return $proposal->fresh(['skema', 'periode', 'ketuaPegawai', 'anggota']);
        });
    }

    /**
     * Add logbook entry to proposal.
     */
    public function addLogbookEntry(ProposalKegiatan $proposal, array $data, $user): \App\Models\Sippm\LogbookKegiatan
    {
        $logbook = DB::transaction(function () use ($proposal, $data) {
            return \App\Models\Sippm\LogbookKegiatan::create([
                'proposal_id' => $proposal->id,
                'tgl_kegiatan' => $data['tgl_kegiatan'],
                'persentase_capaian' => $data['persentase_capaian'],
                'uraian_kegiatan' => $data['uraian_kegiatan'],
                'hambatan' => $data['hambatan'] ?? null,
                'status_verifikasi' => 'pending',
            ]);
        });

        try {
            \App\Services\Core\AuditLogService::record(
                action: 'create',
                module: 'sippm',
                entityType: \App\Models\Sippm\LogbookKegiatan::class,
                entityId: $logbook->id,
                oldValues: null,
                newValues: $logbook->toArray(),
                description: "Menambahkan catatan logbook proposal ID: {$proposal->id}"
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit log addLogbookEntry: ' . $e->getMessage());
        }

        return $logbook;
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
        if (DB::getSchemaBuilder()->hasTable('siakad_krs') && DB::getSchemaBuilder()->hasTable('siakad_krs_detail') && DB::getSchemaBuilder()->hasTable('siakad_mata_kuliah')) {
            $courses = DB::table('siakad_krs_detail')
                ->join('siakad_krs', 'siakad_krs_detail.krs_id', '=', 'siakad_krs.id')
                ->join('siakad_kelas', 'siakad_krs_detail.kelas_id', '=', 'siakad_kelas.id')
                ->join('siakad_mata_kuliah', 'siakad_kelas.mata_kuliah_id', '=', 'siakad_mata_kuliah.id')
                ->where('siakad_krs.mahasiswa_id', $mahasiswaId)
                ->where('siakad_krs_detail.status', 'aktif')
                ->select(
                    'siakad_mata_kuliah.id as mata_kuliah_id',
                    'siakad_mata_kuliah.kode_mk',
                    'siakad_mata_kuliah.nama as nama_mk',
                    'siakad_mata_kuliah.total_sks',
                    'siakad_kelas.nama_kelas'
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
        $tendik = Pegawai::where('jenis_pegawai', 'tendik')
            ->where('status', 'aktif')
            ->select('id', 'nip', 'nama_lengkap', 'jenis_pegawai', 'unit_kerja_id')
            ->get();

        if ($tendik->isEmpty()) {
            $tendik = Pegawai::where('status', 'aktif')
                ->select('id', 'nip', 'nama_lengkap', 'jenis_pegawai', 'unit_kerja_id')
                ->take(10)
                ->get();
        }

        return $tendik->toArray();
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
