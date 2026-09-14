<?php

namespace App\Http\Controllers\Simpeg;

use App\Http\Controllers\Controller;
use App\Models\Simpeg\DokumenPegawai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DokumenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.dokumen.read') && !$user->hasPermission('simpeg.dokumen.create') && !$user->hasPermission('simpeg.dokumen.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk melihat Dokumen E-File.'
            ], 403);
        }

        $query = DokumenPegawai::with('pegawai');

        if ($request->has('pegawai_id')) {
            $query->where('pegawai_id', $request->pegawai_id);
        } elseif (!$user->isAdmin() && !$user->hasPermission('simpeg.dokumen.manage')) {
            // Non-admin hanya bisa melihat dokumen miliknya sendiri
            $pegId = $user->pegawai?->id;
            if ($pegId) {
                $query->where('pegawai_id', $pegId);
            } else {
                $query->whereRaw('1 = 0');
            }
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
        $user = $request->user();
        if (!$user->hasPermission('simpeg.dokumen.create') && !$user->hasPermission('simpeg.dokumen.upload') && !$user->hasPermission('simpeg.dokumen.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk mengunggah Dokumen E-File.'
            ], 403);
        }

        $validated = $request->validate([
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'nama_dokumen' => 'required|string|max:255',
            'jenis_dokumen' => 'required|in:ktp,kk,ijazah,sk,serdos,sertifikat,lainnya',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'file_path' => 'nullable|string',
            'file_size' => 'nullable|string',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
            $path = $file->storeAs('dokumen_pegawai', $fileName, 'public');
            $validated['file_path'] = 'storage/' . $path;
            $sizeBytes = $file->getSize();
            $validated['file_size'] = round($sizeBytes / (1024 * 1024), 2) . ' MB';
        } elseif (empty($validated['file_path'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'File fisik dokumen wajib diunggah.'
            ], 422);
        }

        unset($validated['file']);
        $dokumen = DokumenPegawai::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen pegawai berhasil diunggah dan tersimpan aman di server',
            'data' => $dokumen,
        ], 201);
    }

    public function getSecureView(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $dokumen = DokumenPegawai::with('pegawai')->findOrFail($id);

        // Hak Akses Rahasia: Hanya Admin SIMPEG, Superadmin, atau Pegawai Pemilik Dokumen
        $isAdmin = $user->isAdmin() 
            || $user->hasPermission('simpeg.dokumen.manage') 
            || $user->hasPermission('simpeg.pegawai.manage');

        $isOwner = $user->pegawai && $user->pegawai->id === $dokumen->pegawai_id;

        if (!$isAdmin && !$isOwner) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses Ditolak: Dokumen ini bersifat rahasia dan hanya dapat dibuka oleh Admin SIMPEG, Superadmin, atau pegawai pemilik dokumen tersebut.'
            ], 403);
        }

        $watermarkText = \App\Services\Simpeg\FileSecurityService::getWatermarkText($dokumen);
        $relativePath = str_replace('storage/', '', $dokumen->file_path ?? '');
        $fullPath = storage_path('app/public/' . $relativePath);
        $fileExists = !empty($dokumen->file_path) && file_exists($fullPath);
        $fileUrl = $fileExists ? asset($dokumen->file_path) : null;

        return response()->json([
            'status' => 'success',
            'data' => [
                'dokumen_id' => $dokumen->id,
                'nama_dokumen' => $dokumen->nama_dokumen,
                'jenis_dokumen' => $dokumen->jenis_dokumen,
                'watermark_overlay' => $watermarkText,
                'file_url' => $fileUrl,
                'file_exists' => $fileExists,
                'security_status' => 'Confidential - Encrypted & Watermarked',
            ],
        ]);
    }

    public function downloadFile(Request $request, $id)
    {
        $user = $request->user();
        $dokumen = DokumenPegawai::with('pegawai')->findOrFail($id);

        $isAdmin = $user->isAdmin() 
            || $user->hasPermission('simpeg.dokumen.manage') 
            || $user->hasPermission('simpeg.pegawai.manage');

        $isOwner = $user->pegawai && $user->pegawai->id === $dokumen->pegawai_id;

        if (!$isAdmin && !$isOwner) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses Ditolak: Dokumen ini bersifat rahasia dan hanya dapat dibuka oleh Admin SIMPEG, Superadmin, atau pegawai pemilik dokumen tersebut.'
            ], 403);
        }

        if (empty($dokumen->file_path)) {
            return response()->json(['status' => 'error', 'message' => 'Berkas fisik dokumen tidak ditemukan di server.'], 404);
        }

        $relativePath = str_replace('storage/', '', $dokumen->file_path);
        $fullPath = storage_path('app/public/' . $relativePath);

        if (!file_exists($fullPath)) {
            return response()->json(['status' => 'error', 'message' => 'File fisik tidak ditemukan pada lokasi storage server.'], 404);
        }

        return response()->download($fullPath, $dokumen->nama_dokumen . '.' . pathinfo($fullPath, PATHINFO_EXTENSION));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasPermission('simpeg.dokumen.delete') && !$user->hasPermission('simpeg.dokumen.manage') && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses (permission) untuk menghapus Dokumen E-File.'
            ], 403);
        }

        $dokumen = DokumenPegawai::findOrFail($id);

        // Delete physical file if exists
        if (!empty($dokumen->file_path)) {
            $relativePath = str_replace('storage/', '', $dokumen->file_path);
            if (Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->delete($relativePath);
            }
        }

        $dokumen->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen beserta file fisiknya berhasil dihapus',
        ]);
    }
}
