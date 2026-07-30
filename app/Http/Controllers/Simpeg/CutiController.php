<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\Simpeg\PengajuanCuti;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CutiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('simpeg.cuti.read') && !$request->user()->hasPermission('simpeg.cuti.request') && !$request->user()->hasPermission('simpeg.cuti.approve')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk melihat Pengajuan Cuti.'
            ], 403);
        }

        $query = PengajuanCuti::with(['pegawai', 'approver']);

        if ($request->has('pegawai_id')) {
            $query->where('pegawai_id', $request->pegawai_id);
        }

        if ($request->has('status_approval')) {
            $query->where('status_approval', $request->status_approval);
        }

        $cuti = $query->latest()->get();

        return response()->json([
            'status' => 'success',
            'data' => $cuti,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('simpeg.cuti.create') && !$request->user()->hasPermission('simpeg.cuti.request') && !$request->user()->hasPermission('simpeg.cuti.approve')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk mengajukan Cuti.'
            ], 403);
        }

        $validated = $request->validate([
            'pegawai_id' => 'required|exists:pegawai,id',
            'jenis_cuti' => 'required|in:tahunan,sakit,melahirkan,alasan_penting,besar',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'jumlah_hari' => 'required|integer|min:1',
            'alasan' => 'required|string',
            'file_pendukung' => 'nullable|string',
        ]);

        $cuti = PengajuanCuti::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan cuti berhasil dibuat',
            'data' => $cuti,
        ], 201);
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasPermission('simpeg.cuti.update') && !$request->user()->hasPermission('simpeg.cuti.approve')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk menyetujui / menolak Cuti.'
            ], 403);
        }

        $validated = $request->validate([
            'status_approval' => 'required|in:pending,approved,rejected',
            'catatan_approval' => 'nullable|string',
        ]);

        $cuti = PengajuanCuti::with('pegawai')->findOrFail($id);
        $cuti->update([
            'status_approval' => $validated['status_approval'],
            'catatan_approval' => $validated['catatan_approval'] ?? null,
            'approved_by' => $request->user()?->id,
        ]);

        // Send WhatsApp & Email Notification
        $pegawaiNama = $cuti->pegawai ? $cuti->pegawai->nama_lengkap : 'Pegawai';
        $phone = $cuti->pegawai->telepon ?? '08123456789';
        $statusUpper = strtoupper($cuti->status_approval);
        $msg = "Halo {$pegawaiNama}, pengajuan cuti Anda tanggal {$cuti->tanggal_mulai} s/d {$cuti->tanggal_selesai} telah DI-{$statusUpper} oleh SDM Kampus.";

        $waLog = \App\Services\Notification\CampusNotificationService::sendWhatsApp($phone, $msg);
        $mailLog = \App\Services\Notification\CampusNotificationService::sendEmail("{$pegawaiNama}@campus.ac.id", "Status Pengajuan Cuti {$statusUpper}", $msg);

        return response()->json([
            'status' => 'success',
            'message' => 'Status pengajuan cuti berhasil diperbarui',
            'data' => $cuti,
            'notifications' => [
                'whatsapp' => $waLog,
                'email' => $mailLog,
            ],
        ]);
    }
}
