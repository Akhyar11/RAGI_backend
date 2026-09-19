<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\DispensasiTagihan;
use App\Models\Sikeu\VirtualAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TagihanApprovalController extends Controller
{
    /**
     * GET /api/v1/sikeu/approvals
     * List all pending external bills & dispensations requiring leadership approval.
     */
    public function index()
    {
        $pendingTagihan = TagihanMahasiswa::where('status_approval', 'pending')
            ->orWhere('status', 'pending_approval')
            ->orderBy('created_at', 'desc')
            ->get();

        $pendingDispensasi = DispensasiTagihan::with(['tagihan'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'tagihan_pending' => $pendingTagihan,
                'dispensasi_pending' => $pendingDispensasi,
            ]
        ]);
    }

    /**
     * POST /api/v1/sikeu/approvals/tagihan/{id}/approve
     * Approve external bill.
     */
    public function approveTagihan(Request $request, $id)
    {
        $tagihan = TagihanMahasiswa::findOrFail($id);

        if ($tagihan->status_approval === 'approved') {
            return response()->json([
                'status' => 'error',
                'message' => 'Tagihan sudah disetujui sebelumnya.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $tagihan->update([
                'status' => 'belum_bayar',
                'status_approval' => 'approved',
                'disetujui_oleh' => auth()->id() ?? 1,
                'tanggal_approval' => now(),
                'catatan_approval' => $request->input('catatan', 'Disetujui oleh pimpinan'),
            ]);

            // Generate VA if not created
            if ($tagihan->virtualAccounts()->count() === 0) {
                $mhsNim = $tagihan->mahasiswa?->nim ?? $tagihan->tipeTagihanMahasiswa?->nim ?? $tagihan->mahasiswa_id;
                $cleanId = preg_replace('/[^0-9]/', '', (string)$mhsNim);
                if (empty($cleanId)) {
                    $cleanId = str_pad($tagihan->id, 8, '0', STR_PAD_LEFT);
                }
                $vaNumber = '88012' . $cleanId;
                $sisaTagihan = max(0, (float) $tagihan->total_tagihan + (float) $tagihan->total_denda - (float) $tagihan->total_potongan - (float) $tagihan->total_bayar);
                VirtualAccount::create([
                    'tagihan_id' => $tagihan->id,
                    'va_number' => $vaNumber,
                    'bank_kode' => 'BNI',
                    'bank_nama' => 'Bank BNI',
                    'nominal' => $sisaTagihan,
                    'expired_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                    'status' => 'aktif',
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Tagihan eksternal berhasil disetujui oleh pimpinan dan VA telah aktif.',
                'data' => $tagihan->fresh(['virtualAccounts'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal meng-approve tagihan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/v1/sikeu/approvals/tagihan/{id}/reject
     * Reject external bill.
     */
    public function rejectTagihan(Request $request, $id)
    {
        $tagihan = TagihanMahasiswa::findOrFail($id);

        if ($tagihan->status_approval === 'rejected') {
            return response()->json([
                'status' => 'error',
                'message' => 'Tagihan sudah ditolak sebelumnya.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $tagihan->update([
                'status' => 'batal',
                'status_approval' => 'rejected',
                'disetujui_oleh' => auth()->id() ?? 1,
                'tanggal_approval' => now(),
                'catatan_approval' => $request->input('catatan', 'Ditolak oleh pimpinan'),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Tagihan eksternal ditolak oleh pimpinan.',
                'data' => $tagihan
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menolak tagihan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/v1/sikeu/approvals/dispensasi/{id}/approve
     * Approve payment dispensation.
     */
    public function approveDispensasi(Request $request, $id)
    {
        $dispensasi = DispensasiTagihan::with('tagihan')->findOrFail($id);

        if ($dispensasi->status === 'approved') {
            return response()->json([
                'status' => 'error',
                'message' => 'Dispensasi pembayaran sudah disetujui sebelumnya.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $dispensasi->update([
                'status' => 'approved',
                'disetujui_oleh' => auth()->id() ?? 1,
                'tanggal_persetujuan' => now(),
                'catatan_pimpinan' => $request->input('catatan', 'Dispensasi pembayaran disetujui oleh pimpinan.'),
            ]);

            // Update tagihan status and due date
            $tagihan = $dispensasi->tagihan;
            $tagihan->update([
                'status' => 'dispensasi',
                'jatuh_tempo' => $dispensasi->jatuh_tempo_baru ?? $tagihan->jatuh_tempo,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Permohonan dispensasi pembayaran disetujui oleh pimpinan. Status tagihan kini dispensasi (Unlock KRS).',
                'data' => $dispensasi->fresh(['tagihan'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal meng-approve dispensasi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/v1/sikeu/approvals/dispensasi/{id}/reject
     * Reject payment dispensation.
     */
    public function rejectDispensasi(Request $request, $id)
    {
        $dispensasi = DispensasiTagihan::with('tagihan')->findOrFail($id);

        if ($dispensasi->status === 'rejected') {
            return response()->json([
                'status' => 'error',
                'message' => 'Dispensasi pembayaran sudah ditolak sebelumnya.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $dispensasi->update([
                'status' => 'rejected',
                'disetujui_oleh' => auth()->id() ?? 1,
                'tanggal_persetujuan' => now(),
                'catatan_pimpinan' => $request->input('catatan', 'Dispensasi pembayaran ditolak oleh pimpinan.'),
            ]);

            // Revert status tagihan kembali ke belum_bayar / sebagian jika tagihan berstatus dispensasi
            $tagihan = $dispensasi->tagihan;
            if ($tagihan && $tagihan->status === 'dispensasi') {
                $totalBersih = (float)($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan);
                $newStatus = (float)$tagihan->total_bayar >= $totalBersih ? 'lunas' : ((float)$tagihan->total_bayar > 0 ? 'sebagian' : 'belum_bayar');
                $tagihan->update(['status' => $newStatus]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Permohonan dispensasi pembayaran ditolak oleh pimpinan.',
                'data' => $dispensasi
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menolak dispensasi: ' . $e->getMessage()
            ], 500);
        }
    }
}
