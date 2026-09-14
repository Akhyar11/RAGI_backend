<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\Simpeg\GajiPegawai;
use App\Models\Simpeg\Pegawai;
use App\Models\Simpeg\PresensiPegawai;
use App\Models\Sikeu\MasterGajiPegawai;
use App\Services\Simpeg\SikeuIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.payroll.read') && !$user->hasPermission('simpeg.payroll.view') && !$user->hasPermission('simpeg.payroll.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk melihat Slip Gaji / Payroll.'
            ], 403);
        }

        $query = GajiPegawai::with('pegawai');

        if ($request->has('pegawai_id')) {
            $query->where('pegawai_id', $request->pegawai_id);
        } elseif (!$user->isAdmin() && !$user->hasPermission('simpeg.payroll.manage')) {
            $pegId = $user->pegawai?->id;
            if ($pegId) {
                $query->where('pegawai_id', $pegId);
            }
        }

        if ($request->has('periode')) {
            $query->where('periode_bulan_tahun', $request->periode);
        }

        if ($request->has('status_transfer')) {
            $query->where('status_transfer', $request->status_transfer);
        }

        $payroll = $query->latest('periode_bulan_tahun')->get();

        return response()->json([
            'status' => 'success',
            'data' => $payroll,
        ]);
    }

    public function generatePayroll(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.payroll.create') && !$user->hasPermission('simpeg.payroll.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk membuat kalkulasi payroll.'
            ], 403);
        }

        $validated = $request->validate([
            'periode' => 'required|string', // e.g. "2026-08"
        ]);

        $periode = $validated['periode'];
        $pegawaiList = Pegawai::all();

        if ($pegawaiList->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak ada data pegawai yang terdaftar di database.',
            ], 400);
        }

        $masters = MasterGajiPegawai::all()->keyBy('pegawai_id');
        $generatedCount = 0;

        foreach ($pegawaiList as $pegawai) {
            $master = $masters->get($pegawai->id);
            $gajiPokok = $master ? (float)$master->gaji_pokok : 5000000;
            $tunjanganTetap = $master ? (float)$master->tunjangan_tetap : 1500000;
            $potonganTetap = $master ? (float)$master->potongan_tetap : 200000;
            $tarifTransport = $master ? (float)$master->tarif_transport_harian : 50000;

            // Hitung Presensi Tepat Waktu (status: 'hadir' DAN jam_masuk <= 08:15:00)
            $presensiLogs = PresensiPegawai::where('pegawai_id', $pegawai->id)
                ->where('tanggal', 'LIKE', "{$periode}%")
                ->get();

            $hariTepatWaktu = 0;
            foreach ($presensiLogs as $log) {
                if ($log->status_kehadiran === 'hadir' && $log->jam_masuk) {
                    $jamMasukClean = substr($log->jam_masuk, 0, 8);
                    if ($jamMasukClean <= '08:15:00') {
                        $hariTepatWaktu++;
                    }
                }
            }

            $totalTransport = $hariTepatWaktu * $tarifTransport;
            $totalTunjangan = $tunjanganTetap + $totalTransport;
            $gajiBersih = $gajiPokok + $totalTunjangan - $potonganTetap;

            GajiPegawai::updateOrCreate(
                [
                    'pegawai_id' => $pegawai->id,
                    'periode_bulan_tahun' => $periode,
                ],
                [
                    'gaji_pokok' => $gajiPokok,
                    'tunjangan_tetap' => $tunjanganTetap,
                    'total_biaya_transport' => $totalTransport,
                    'jumlah_hari_hadir_tepat_waktu' => $hariTepatWaktu,
                    'total_tunjangan' => $totalTunjangan,
                    'total_potongan' => $potonganTetap,
                    'gaji_bersih' => $gajiBersih,
                    'status_transfer' => 'draft',
                    'catatan' => "Presensi Tepat Waktu: {$hariTepatWaktu} Hari (Tarif @ Rp " . number_format($tarifTransport, 0, ',', '.') . ")",
                ]
            );

            $generatedCount++;
        }

        return response()->json([
            'status' => 'success',
            'message' => "Payroll periode {$periode} berhasil dikalkulasi dari data presensi! Total {$generatedCount} pegawai terproses.",
        ]);
    }

    public function submitToSikeu(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.payroll.create') && !$user->hasPermission('simpeg.payroll.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk mengajukan payroll ke SIKEU.'
            ], 403);
        }

        $validated = $request->validate([
            'periode' => 'required|string',
        ]);

        $updatedCount = GajiPegawai::where('periode_bulan_tahun', $validated['periode'])
            ->whereIn('status_transfer', ['draft', 'cancelled'])
            ->update([
                'status_transfer' => 'submitted_to_sikeu',
                'submitted_at' => now(),
            ]);

        return response()->json([
            'status' => 'success',
            'message' => "Pengajuan payroll periode {$validated['periode']} ({$updatedCount} pegawai) berhasil dikirimkan ke modul SIKEU untuk proses pembayaran!",
        ]);
    }

    public function processPayment(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.payroll.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk memproses pembayaran gaji di SIKEU.'
            ], 403);
        }

        $gaji = GajiPegawai::findOrFail($id);
        
        $gaji->update([
            'status_transfer' => 'paid',
            'tanggal_transfer' => now(),
        ]);

        $sikeuJournal = SikeuIntegrationService::postPayrollJournal($gaji);

        return response()->json([
            'status' => 'success',
            'message' => "Pembayaran gaji pegawai ID #{$gaji->pegawai_id} ({$gaji->periode_bulan_tahun}) berhasil diproses & diterbitkan!",
            'data' => $gaji,
            'sikeu_journal' => $sikeuJournal,
        ]);
    }
}
