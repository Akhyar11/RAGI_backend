<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\Simpeg\DokumenPegawai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DokumenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('simpeg.dokumen.read') && !$request->user()->hasPermission('simpeg.dokumen.create') && !$request->user()->hasPermission('simpeg.dokumen.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk melihat Dokumen E-File.'
            ], 403);
        }

        $query = DokumenPegawai::with('pegawai');

        if ($request->has('pegawai_id')) {
            $query->where('pegawai_id', $request->pegawai_id);
        }

        if ($request->has('jenis_dokumen')) {
            $query->where('jenis_dokumen', $request->jenis_dokumen);
        }

        $dokumen = $query->latest()->get();

        return response()->json([
            'status' => 'success',
            'data' => $dokumen,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('simpeg.dokumen.create') && !$request->user()->hasPermission('simpeg.dokumen.upload') && !$request->user()->hasPermission('simpeg.dokumen.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk mengunggah Dokumen E-File.'
            ], 403);
        }

        $validated = $request->validate([
            'pegawai_id' => 'required|exists:pegawai,id',
            'nama_dokumen' => 'required|string|max:255',
            'jenis_dokumen' => 'required|in:ktp,kk,ijazah,sk,serdos,sertifikat,lainnya',
            'file_path' => 'required|string',
            'file_size' => 'nullable|string',
        ]);

        $dokumen = DokumenPegawai::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen pegawai berhasil diunggah',
            'data' => $dokumen,
        ], 201);
    }

    public function getSecureView(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasPermission('simpeg.dokumen.read') && !$request->user()->hasPermission('simpeg.dokumen.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk membuka pratinjau dokumen ini.'
            ], 403);
        }

        $dokumen = DokumenPegawai::with('pegawai')->findOrFail($id);
        $watermarkText = \App\Services\Simpeg\FileSecurityService::getWatermarkText($dokumen);

        return response()->json([
            'status' => 'success',
            'data' => [
                'dokumen_id' => $dokumen->id,
                'nama_dokumen' => $dokumen->nama_dokumen,
                'jenis_dokumen' => $dokumen->jenis_dokumen,
                'watermark_overlay' => $watermarkText,
                'signed_url' => "https://storage.campus.ac.id/secure-file/{$dokumen->id}?token=" . md5($watermarkText),
                'expires_at' => now()->addMinutes(15)->toIso8601String(),
                'security_status' => 'Encrypted & Watermarked',
            ],
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasPermission('simpeg.dokumen.delete') && !$request->user()->hasPermission('simpeg.dokumen.manage')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk menghapus Dokumen E-File.'
            ], 403);
        }

        $dokumen = DokumenPegawai::findOrFail($id);
        $dokumen->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen berhasil dihapus',
        ]);
    }
}
