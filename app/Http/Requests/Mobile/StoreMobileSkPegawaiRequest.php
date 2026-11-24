<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class StoreMobileSkPegawaiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('simpeg.sk_pegawai.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'kategori_sk_id' => 'required|exists:simpeg_master_kategori_sk,id',
            'nomor_sk' => 'required|string|max:100',
            'judul_sk' => 'required|string|max:255',
            'tanggal_sk' => 'required|date',
            'tmt_sk' => 'required|date',
            'tmt_selesai' => 'nullable|date|after_or_equal:tmt_sk',
            'pejabat_penetap' => 'required|string|max:150',
            'file_sk' => 'required|file|mimes:pdf|max:10240',
            'keterangan' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'kategori_sk_id.required' => 'Kategori SK wajib dipilih.',
            'kategori_sk_id.exists' => 'Kategori SK yang dipilih tidak valid.',
            'nomor_sk.required' => 'Nomor SK wajib diisi.',
            'judul_sk.required' => 'Judul SK wajib diisi.',
            'tanggal_sk.required' => 'Tanggal SK wajib diisi.',
            'tmt_sk.required' => 'TMT SK wajib diisi.',
            'tmt_selesai.after_or_equal' => 'TMT selesai harus sama atau setelah TMT SK.',
            'pejabat_penetap.required' => 'Pejabat penetap wajib diisi.',
            'file_sk.required' => 'Berkas SK wajib diunggah.',
            'file_sk.mimes' => 'Berkas SK wajib berformat PDF.',
            'file_sk.max' => 'Ukuran berkas SK maksimal 10MB.',
        ];
    }
}
