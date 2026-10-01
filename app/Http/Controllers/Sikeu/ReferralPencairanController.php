<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sikeu\ApproveReferralPayoutRequest;
use App\Http\Requests\Sikeu\CairkanReferralPayoutRequest;
use App\Models\Spmb\PayoutReferral;
use App\Services\Sikeu\SikeuReferralPencairanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralPencairanController extends Controller
{
    public function __construct(private SikeuReferralPencairanService $service) {}

    /**
     * Daftar invoice payout referral masuk (tab SPMB pada Pengajuan Operasional).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, $request->integer('per_page', 15));

        $query = PayoutReferral::query()
            ->with(['referrer:id,name,username,email'])
            ->withCount('usages');

        $allowedStatuses = [
            PayoutReferral::STATUS_PENDING_KEUANGAN,
            PayoutReferral::STATUS_PENDING_DIREKTUR,
            PayoutReferral::STATUS_DISETUJUI,
            PayoutReferral::STATUS_DICAIRKAN,
            PayoutReferral::STATUS_DITOLAK,
        ];

        if ($request->filled('status') && in_array($request->input('status'), $allowedStatuses, true)) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nomor_bukti', 'like', "%{$search}%")
                    ->orWhere('sikeu_reference', 'like', "%{$search}%")
                    ->orWhereHas('referrer', function ($rq) use ($search) {
                        $rq->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $allowedSort = ['created_at', 'updated_at', 'total_nominal', 'nomor_bukti'];
        $sortBy = in_array($request->input('sort_by'), $allowedSort, true) ? $request->input('sort_by') : 'created_at';
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortOrder);
        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar invoice referral berhasil dimuat.',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
            'filters' => [
                'search' => $request->input('search'),
                'status' => $request->input('status'),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * Detail invoice payout referral: data referrer + riwayat usage referral.
     */
    public function show($id): JsonResponse
    {
        $payout = PayoutReferral::query()
            ->with([
                'referrer:id,name,username,email',
                'approverKeuangan:id,name,username',
                'approverDirektur:id,name,username',
                'usages.pendaftaran:id,no_pendaftaran,nama_lengkap,status,gelombang_id',
                'usages.pendaftaran.gelombangPenerimaan:id,nama',
            ])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail invoice referral berhasil dimuat.',
            'data' => $payout,
        ]);
    }

    /**
     * Approval bertahap (keuangan -> direktur) atau penolakan.
     * Body: { aksi: approve|reject, catatan?: string }
     */
    public function approve(ApproveReferralPayoutRequest $request, $id): JsonResponse
    {
        $payout = PayoutReferral::findOrFail($id);
        $result = $this->service->approve(
            $payout,
            $request->validated('aksi'),
            $request->validated('catatan'),
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'message' => $request->validated('aksi') === 'approve'
                ? 'Invoice referral disetujui ke tahap berikutnya.'
                : 'Invoice referral ditolak.',
            'data' => $result,
        ]);
    }

    /**
     * Pencairan: pilih unit kas + nominal manual (maks = total bukti).
     */
    public function cairkan(CairkanReferralPayoutRequest $request, $id): JsonResponse
    {
        $payout = PayoutReferral::findOrFail($id);
        $result = $this->service->cairkan($payout, $request->user(), $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Payout referral berhasil dicairkan dan dicatat sebagai pengeluaran.',
            'data' => $result,
        ]);
    }
}
