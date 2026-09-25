<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Spmb\ValidateReferralCodeRequest;
use App\Models\Spmb\ReferralUsage;
use App\Services\Spmb\SpmbReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReferralController extends Controller
{
    public function __construct(private SpmbReferralService $referralService) {}

    /**
     * Validasi kode referral (untuk konfirmasi di form).
     */
    public function validate(ValidateReferralCodeRequest $request): JsonResponse
    {
        $summary = $this->referralService->validate($request->validated('code'), $request->user()?->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Kode referral valid.',
            'data' => $summary,
        ]);
    }

    /**
     * Kode referral milik pengguna yang sedang login + statistiknya.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        // Pastikan kode referral selalu tersedia (generate lazy bila belum ada).
        $code = $user->referral_code;
        if ($code) {
            $user->referral_code = $code;
        }

        $stats = $this->referralService->statsForUser($user);

        return response()->json([
            'status' => 'success',
            'message' => 'Data referral berhasil diambil.',
            'data' => [
                'summary' => $stats,
            ],
        ]);
    }

    /**
     * Laporan rekap referral (Admin/Panitia SPMB).
     * Otorisasi ditangani middleware route `can:spmb.manage`.
     */
    public function report(Request $request): JsonResponse
    {
        $perPage = min(100, $request->integer('per_page', 15));
        $allowedSort = ['created_at', 'status', 'referral_code'];
        $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'created_at';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

        $query = ReferralUsage::query()->with([
            'referrer:id,username,name,email,referral_code',
            'pendaftaran:id,no_pendaftaran,nama_lengkap,status,status_pembayaran,gelombang_id',
            'pendaftaran.gelombangPenerimaan:id,nama',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('referral_code')) {
            $query->where('referral_code', 'like', '%'.$request->input('referral_code').'%');
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('referral_code', 'like', "%{$search}%")
                    ->orWhereHas('referrer', function ($rq) use ($search) {
                        $rq->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('pendaftaran', function ($pq) use ($search) {
                        $pq->where('nama_lengkap', 'like', "%{$search}%")
                            ->orWhere('no_pendaftaran', 'like', "%{$search}%");
                    });
            });
        }

        $query->orderBy($sortBy, $sortOrder);

        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data retrieved successfully',
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
                'referral_code' => $request->input('referral_code'),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * Ringkasan agregat referral per status (Admin/Panitia SPMB).
     * Otorisasi ditangani middleware route `can:spmb.manage`.
     */
    public function summary(Request $request): JsonResponse
    {
        $summary = DB::table('spmb_referral_usages')
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'status' => 'success',
            'message' => 'Data retrieved successfully',
            'data' => [
                'claimed' => (int) ($summary[ReferralUsage::STATUS_CLAIMED] ?? 0),
                'qualified' => (int) ($summary[ReferralUsage::STATUS_QUALIFIED] ?? 0),
                'rewarded' => (int) ($summary[ReferralUsage::STATUS_REWARDED] ?? 0),
                'cancelled' => (int) ($summary[ReferralUsage::STATUS_CANCELLED] ?? 0),
            ],
        ]);
    }
}
