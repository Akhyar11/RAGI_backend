<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sikeu\PayReferralPayoutRequest;
use App\Http\Requests\Sikeu\RejectReferralPayoutRequest;
use App\Http\Requests\Sikeu\VerifyReferralPayoutRequest;
use App\Models\Spmb\PayoutReferral;
use App\Services\Sikeu\SikeuReferralPencairanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReferralPencairanController extends Controller
{
    public function __construct(private SikeuReferralPencairanService $service) {}

    /**
     * Daftar invoice payout referral masuk (untuk admin keuangan).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, $request->integer('per_page', 15));

        $query = PayoutReferral::query()
            ->with(['referrer:id,name,username,email'])
            ->withCount('usages');

        $allowedStatuses = [
            PayoutReferral::STATUS_MENUNGGU_VERIFIKASI,
            PayoutReferral::STATUS_TERVERIFIKASI,
            PayoutReferral::STATUS_DIBAYAR,
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
                            ->orWhere('username', 'like', "%{$search}%");
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
     * Detail invoice payout referral beserta bukti dan usages.
     */
    public function show($id): JsonResponse
    {
        $payout = PayoutReferral::query()
            ->with([
                'referrer:id,name,username,email',
                'usages.pendaftaran:id,no_pendaftaran,nama_lengkap,status',
            ])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail invoice referral berhasil dimuat.',
            'data' => $payout,
        ]);
    }

    /**
     * Verifikasi bukti payout referral oleh admin keuangan.
     */
    public function verify(VerifyReferralPayoutRequest $request, $id): JsonResponse
    {
        $payout = PayoutReferral::findOrFail($id);

        if (! in_array($payout->status, [
            PayoutReferral::STATUS_MENUNGGU_VERIFIKASI,
            PayoutReferral::STATUS_DITOLAK,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => ['Payout referral pada status ini tidak dapat diverifikasi.'],
            ]);
        }

        $payout->update([
            'status' => PayoutReferral::STATUS_TERVERIFIKASI,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'catatan_penolakan' => null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Invoice referral berhasil diverifikasi.',
            'data' => $payout->fresh(['referrer:id,name,username,email']),
        ]);
    }

    /**
     * Bayar invoice payout referral: catat pengeluaran + jurnal, tandai dibayar.
     */
    public function pay(PayReferralPayoutRequest $request, $id): JsonResponse
    {
        $payout = PayoutReferral::findOrFail($id);

        $paid = $this->service->pay($payout, $request->user(), $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Payout referral berhasil dibayar dan dicatat sebagai pengeluaran.',
            'data' => $paid,
        ]);
    }

    /**
     * Tolak invoice payout referral (usages tetap ditandai agar tidak diklaim ulang).
     */
    public function reject(RejectReferralPayoutRequest $request, $id): JsonResponse
    {
        $payout = PayoutReferral::findOrFail($id);

        if (! in_array($payout->status, [
            PayoutReferral::STATUS_MENUNGGU_VERIFIKASI,
            PayoutReferral::STATUS_TERVERIFIKASI,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => ['Payout referral pada status ini tidak dapat ditolak.'],
            ]);
        }

        $payout->update([
            'status' => PayoutReferral::STATUS_DITOLAK,
            'catatan_penolakan' => $request->input('catatan'),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Invoice referral ditolak.',
            'data' => $payout->fresh(['referrer:id,name,username,email']),
        ]);
    }
}
