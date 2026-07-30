<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\Simpeg\GajiPegawai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('simpeg.payroll.read') && !$request->user()->hasPermission('simpeg.payroll.view') && !$request->user()->hasPermission('simpeg.payroll.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk melihat Slip Gaji / Payroll.'
            ], 403);
        }

        $query = GajiPegawai::with('pegawai');

        if ($request->has('pegawai_id')) {
            $query->where('pegawai_id', $request->pegawai_id);
        }

        if ($request->has('periode')) {
            $query->where('periode_bulan_tahun', $request->periode);
        }

        $payroll = $query->latest('periode_bulan_tahun')->get();

        return response()->json([
            'status' => 'success',
            'data' => $payroll,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('simpeg.payroll.create') && !$request->user()->hasPermission('simpeg.payroll.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk menerbitkan Slip Gaji / Payroll.'
            ], 403);
        }

        $validated = $request->validate([
            'pegawai_id' => 'required|exists:pegawai,id',
            'periode_bulan_tahun' => 'required|string',
            'gaji_pokok' => 'required|numeric',
            'total_tunjangan' => 'nullable|numeric',
            'total_potongan' => 'nullable|numeric',
            'gaji_bersih' => 'required|numeric',
            'status_transfer' => 'required|in:draft,paid,cancelled',
            'nomor_rekening' => 'nullable|string',
            'bank_nama' => 'nullable|string',
        ]);

        $gaji = GajiPegawai::create($validated);

        // Auto Post Accounting Journal to SIKEU Subsystem
        $sikeuJournal = \App\Services\Simpeg\SikeuIntegrationService::postPayrollJournal($gaji);

        return response()->json([
            'status' => 'success',
            'message' => 'Slip gaji berhasil diterbitkan dan terposting otomatis ke Jurnal SIKEU!',
            'data' => $gaji,
            'sikeu_journal' => $sikeuJournal,
        ], 201);
    }
}
