<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sikeu\UpdatePajakAkunAliasRequest;
use App\Http\Requests\Sikeu\UpdatePajakModeRequest;
use App\Services\Sikeu\PajakPelaporanService;
use Illuminate\Http\Request;

class PajakPelaporanController extends Controller
{
    public function index(Request $request, PajakPelaporanService $service)
    {
        $accounts = $service->listAccounts($request->only([
            'search',
            'sort_by',
            'sort_order',
            'per_page',
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar alias akun pelaporan pajak berhasil dimuat.',
            'data' => collect($accounts->items())->map(fn ($account) => [
                'id' => $account->id,
                'kode_akun' => $account->kode_akun,
                'nama_akun' => $account->nama_akun,
                'nama_akun_pajak' => $account->pajakAlias?->nama_akun_pajak,
            ]),
            'meta' => [
                'current_page' => $accounts->currentPage(),
                'per_page' => $accounts->perPage(),
                'total' => $accounts->total(),
                'last_page' => $accounts->lastPage(),
                'from' => $accounts->firstItem(),
                'to' => $accounts->lastItem(),
            ],
            'mode' => $service->currentMode(),
        ]);
    }

    public function updateAlias(
        UpdatePajakAkunAliasRequest $request,
        int $akunId,
        PajakPelaporanService $service
    ) {
        $alias = $service->updateAlias(
            $akunId,
            $request->validated('nama_akun_pajak'),
            $request->user()?->id
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Nama akun pelaporan pajak berhasil disimpan.',
            'data' => $alias,
        ]);
    }

    public function updateMode(UpdatePajakModeRequest $request, PajakPelaporanService $service)
    {
        $mode = $service->updateMode(
            $request->validated('mode'),
            $request->user()?->id
        );

        return response()->json([
            'status' => 'success',
            'message' => $mode === 'publik'
                ? 'Mode pelaporan pajak publik diaktifkan.'
                : 'Mode pelaporan pajak dinonaktifkan; nama akun asli ditampilkan kembali.',
            'data' => ['mode' => $mode],
        ]);
    }
}
