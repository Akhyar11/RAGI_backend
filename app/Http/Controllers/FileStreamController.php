<?php

namespace App\Http\Controllers;

use App\Services\Storage\FileStorageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * FileStreamController — endpoint generik untuk menampilkan berkas privat
 * secara aman (Signed URL, tanpa Bearer token).
 *
 * URL: GET /api/files/view?path=<path relatif penyimpanan>
 * Dilindungi middleware `signed` (berlaku 15 menit). Berkas dicari di SEMUA
 * disk (r2-private, r2, public, local) sehingga berkas lama maupun baru tetap
 * dapat ditampilkan, tanpa mengekspos URL storage langsung.
 */
class FileStreamController extends Controller
{
    public function __construct(private FileStorageService $files) {}

    public function show(Request $request): StreamedResponse
    {
        return $this->files->streamInlineAny((string) $request->query('path', ''));
    }
}
