<?php

namespace App\Http\Controllers\API\Spmb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Spmb\PaymentGatewayOptionsRequest;
use App\Http\Requests\Spmb\SpmbCheckoutRequest;
use App\Http\Requests\Spmb\SpmbPaymentListRequest;
use App\Models\Sikeu\PaymentGatewayConfig;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Services\Sikeu\SpmbCheckoutService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class SpmbPaymentController extends Controller
{
    public function options(PaymentGatewayOptionsRequest $request): JsonResponse
    {
        $query = PaymentGatewayConfig::query()->select(['id', 'gateway_name', 'environment', 'is_active'])
            ->where('is_active', true)->whereIn('gateway_name', PaymentGatewayConfig::SPMB_CHANNELS);
        if ($request->filled('search')) {
            $query->where('gateway_name', 'like', '%'.$request->string('search')->trim().'%');
        }
        $sortOrder = strtolower((string) $request->input('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $items = $query->orderBy($request->input('sort_by', 'gateway_name'), $sortOrder)
            ->paginate(min(100, $request->integer('per_page', 15)));

        return $this->paginated($items, 'Pilihan kanal pembayaran berhasil dimuat.');
    }

    public function index(SpmbPaymentListRequest $request): JsonResponse
    {
        $applicationIds = PendaftaranCalonMhs::where('user_id', $request->user()->id)->select('id');
        $query = TagihanMahasiswa::query()->whereIn('calon_mahasiswa_id', $applicationIds)->where('source_system', 'SPMB')
            ->with(['detailTagihan', 'virtualAccount']);
        if ($request->filled('tahap')) {
            $reenrollment = $request->input('tahap') === 'daftar_ulang';
            $query->where(function ($builder) use ($reenrollment): void {
                $builder->where('tipe_referensi', $reenrollment ? TagihanMahasiswa::TIPE_SPMB_DAFTAR_ULANG : TagihanMahasiswa::TIPE_SPMB_PENDAFTARAN);
                if (! $reenrollment) {
                    $builder->orWhereNull('tipe_referensi');
                }
            });
        }
        if ($request->filled('search')) {
            $query->where('nomor_tagihan', 'like', '%'.$request->string('search')->trim().'%');
        }
        $sortOrder = strtolower((string) $request->input('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $items = $query->orderBy($request->input('sort_by', 'id'), $sortOrder)
            ->paginate(min(100, $request->integer('per_page', 15)))
            ->through(fn (TagihanMahasiswa $bill): array => [
                'id' => $bill->id, 'nomor_tagihan' => $bill->nomor_tagihan, 'status' => $bill->status,
                'tipe_referensi' => $bill->tipe_referensi, 'payment_gateway_config_id' => $bill->payment_gateway_config_id,
                'gateway_name' => $bill->payment_gateway_name, 'environment' => $bill->payment_gateway_environment,
                'total_tagihan' => (float) $bill->total_tagihan, 'total_potongan' => (float) $bill->total_potongan,
                'total_denda' => (float) $bill->total_denda, 'total_bayar' => (float) $bill->total_bayar,
                'sisa_tagihan' => max(0, (float) $bill->total_tagihan + (float) $bill->total_denda - (float) $bill->total_potongan - (float) $bill->total_bayar),
                'jatuh_tempo' => $bill->jatuh_tempo?->toDateString(),
                'details' => $bill->detailTagihan->map(fn ($detail) => ['id' => $detail->id, 'keterangan' => $detail->keterangan, 'nominal' => (float) $detail->nominal])->all(),
                'virtual_account' => $bill->virtualAccount ? ['va_number' => $bill->virtualAccount->va_number, 'bank_nama' => $bill->virtualAccount->bank_nama] : null,
            ]);

        return $this->paginated($items, 'Tagihan pembayaran SPMB berhasil dimuat.');
    }

    public function checkout(SpmbCheckoutRequest $request, int $id, SpmbCheckoutService $service): JsonResponse
    {
        return response()->json(['status' => 'success', 'message' => 'Instruksi pembayaran berhasil disiapkan.', 'data' => $service->checkout($id)]);
    }

    private function paginated(LengthAwarePaginator $items, string $message): JsonResponse
    {
        return response()->json(['status' => 'success', 'message' => $message, 'data' => $items->items(), 'meta' => [
            'current_page' => $items->currentPage(), 'per_page' => $items->perPage(), 'total' => $items->total(), 'last_page' => $items->lastPage(),
            'from' => $items->firstItem(), 'to' => $items->lastItem(),
        ]]);
    }
}
