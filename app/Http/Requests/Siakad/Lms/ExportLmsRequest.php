<?php

namespace App\Http\Requests\Siakad\Lms;

use App\Services\Siakad\LmsExportService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi parameter export laporan LMS.
 *
 * Format export adalah closed-set domain (bukan entitas master), sehingga
 * daftar pilihan diambil dari konstanta `LmsExportService::FORMATS` —
 * bukan ditulis ulang sebagai literal di sini.
 */
class ExportLmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format' => ['nullable', 'string', 'in:' . implode(',', LmsExportService::FORMATS)],
        ];
    }

    public function messages(): array
    {
        return [
            'format.in'   => 'Format export harus salah satu dari: ' . implode(', ', LmsExportService::FORMATS) . '.',
            'format.string' => 'Format export harus berupa teks.',
        ];
    }

    /**
     * Format export terpilih (default CSV).
     *
     * Nama method memakai awalan `export` agar tidak bentrok dengan
     * `Illuminate\Http\Request::format()`.
     */
    public function exportFormat(): string
    {
        $format = $this->validated('format');

        return is_string($format) && $format !== '' ? $format : LmsExportService::FORMAT_CSV;
    }
}
