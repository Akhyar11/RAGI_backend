<?php

namespace App\Http\Requests\Arsip;

use Illuminate\Foundation\Http\FormRequest;

class GenerateNomorSuratRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => 'nullable|string|in:satuan,bulk',
            'tanggal_surat' => 'required|date',
            'kode_unit' => 'required|string|max:50',
            'kode_klasifikasi' => 'required|string|max:50',
            'perihal' => 'required|string|max:255',
            'tujuan' => 'nullable|string|max:200',
            'pegawai_id' => 'nullable|exists:simpeg_pegawai,id',
            'status' => 'nullable|string|in:terpakai,direservasi',
            'module_origin' => 'nullable|string|max:50',
            'catatan' => 'nullable|string',
            'alasan_backdate' => 'nullable|string|max:1000',
            'jumlah_nomor' => 'nullable|integer|min:1|max:500',
            'keterangan_item' => 'nullable|array',
            'keterangan_item.*' => 'nullable|string|max:255',
            'tujuan_item' => 'nullable|array',
            'tujuan_item.*' => 'nullable|string|max:200',
            'file_surat' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tanggalSurat = $this->input('tanggal_surat');
            if ($tanggalSurat) {
                try {
                    $date = \Carbon\Carbon::parse($tanggalSurat)->startOfDay();
                    $today = \Carbon\Carbon::today();
                    if ($date->lessThan($today)) {
                        $alasan = trim((string) $this->input('alasan_backdate'));
                        if (empty($alasan)) {
                            $validator->errors()->add(
                                'alasan_backdate',
                                'Penerbitan surat bertanggal mundur (backdate) wajib menyertakan alasan atau justifikasi.'
                            );
                        }
                    }
                } catch (\Throwable $e) {
                    // Biarkan validator tanggal bawaan yang menangani format invalid
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'tanggal_surat.required' => 'Tanggal surat wajib diisi.',
            'kode_unit.required' => 'Kode unit / jenjang wajib diisi.',
            'kode_klasifikasi.required' => 'Kode klasifikasi surat wajib diisi.',
            'perihal.required' => 'Perihal surat wajib diisi.',
            'pegawai_id.exists' => 'Pegawai pengaju/penanggung jawab tidak valid.',
            'jumlah_nomor.min' => 'Jumlah nomor surat minimal 1.',
            'jumlah_nomor.max' => 'Jumlah nomor surat maksimal 500 sekaligus.',
            'file_surat.max' => 'Ukuran berkas scan surat maksimal 10 MB.',
            'alasan_backdate.max' => 'Alasan backdate maksimal 1000 karakter.',
        ];
    }
}
