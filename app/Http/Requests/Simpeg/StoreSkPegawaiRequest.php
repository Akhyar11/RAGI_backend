<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class StoreSkPegawaiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (!$this->filled('pegawai_id') && $this->user()?->pegawai?->id) {
            $this->merge([
                'pegawai_id' => $this->user()->pegawai->id,
            ]);
        }
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->route('id');

        return [
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'kategori_sk_id' => 'required|exists:simpeg_master_kategori_sk,id',
            'nomor_sk' => 'required|string|max:100',
            'judul_sk' => 'required|string|max:255',
            'tanggal_sk' => 'required|date',
            'tmt_sk' => 'required|date',
            'tmt_selesai' => 'nullable|date|after_or_equal:tmt_sk',
            'pejabat_penetap' => 'required|string|max:150',
            'file_sk' => ($isUpdate ? 'nullable' : 'required') . '|file|mimes:pdf|max:10240',
            'keterangan' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'pegawai_id.required' => 'Pegawai pemilik SK wajib dipilih.',
            'pegawai_id.exists' => 'Data pegawai tidak valid.',
            'kategori_sk_id.required' => 'Kategori SK wajib dipilih.',
            'kategori_sk_id.exists' => 'Kategori SK tidak valid.',
            'nomor_sk.required' => 'Nomor SK wajib diisi.',
            'judul_sk.required' => 'Judul / nama SK wajib diisi.',
            'tanggal_sk.required' => 'Tanggal penetapan SK wajib diisi.',
            'tmt_sk.required' => 'TMT (Terhitung Mulai Tanggal) wajib diisi.',
            'tmt_selesai.after_or_equal' => 'TMT selesai harus sama atau setelah TMT mulai.',
            'pejabat_penetap.required' => 'Pejabat penetap SK wajib diisi.',
            'file_sk.required' => 'Berkas pindaian SK (PDF) wajib diunggah.',
            'file_sk.mimes' => 'Berkas SK harus berformat PDF.',
            'file_sk.max' => 'Ukuran berkas SK maksimal 10MB.',
        ];
    }
}
