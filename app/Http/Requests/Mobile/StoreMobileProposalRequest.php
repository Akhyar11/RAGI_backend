<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class StoreMobileProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sippm.proposal.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'judul' => 'required|string|max:255',
            'abstrak' => 'required|string',
            'rumpun_ilmu' => 'required|string|max:150',
            'skema_id' => 'required|exists:sippm_skema_kegiatan,id',
            'periode_id' => 'nullable|exists:sippm_periode_hibah,id',
            'target_tkt' => 'nullable|integer|min:1|max:9',
            'anggaran_diajukan' => 'required|numeric|min:0',
            'file_proposal' => 'required|file|mimes:pdf|max:10240',
            'mata_kuliah_id' => 'nullable|exists:siakad_mata_kuliah,id',
        ];
    }

    public function messages(): array
    {
        return [
            'judul.required' => 'Judul proposal wajib diisi.',
            'abstrak.required' => 'Abstrak proposal wajib diisi.',
            'rumpun_ilmu.required' => 'Rumpun ilmu wajib diisi.',
            'skema_id.required' => 'Skema hibah penelitian/pengabdian wajib dipilih.',
            'skema_id.exists' => 'Skema hibah yang dipilih tidak valid.',
            'anggaran_diajukan.required' => 'Nominal anggaran yang diajukan wajib diisi.',
            'anggaran_diajukan.numeric' => 'Nominal anggaran harus berupa angka.',
            'anggaran_diajukan.min' => 'Nominal anggaran tidak boleh bernilai negatif.',
            'file_proposal.required' => 'Dokumen usulan proposal (PDF) wajib diunggah.',
            'file_proposal.mimes' => 'Dokumen usulan proposal harus berformat PDF.',
            'file_proposal.max' => 'Ukuran berkas dokumen proposal maksimal 10MB.',
        ];
    }
}
