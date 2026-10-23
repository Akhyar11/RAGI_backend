<?php

namespace App\Http\Requests\Siakad\TugasAkhir;

use Illuminate\Foundation\Http\FormRequest;

class ApproveProposalTaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('siakad.ta.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|in:disetujui_prodi,ditolak,sedang_bimbingan',
            'dosen_pembimbing_1_id' => 'required|exists:siakad_dosen,id',
            'dosen_pembimbing_2_id' => 'nullable|different:dosen_pembimbing_1_id|exists:siakad_dosen,id',
            'nomor_sk_pembimbing' => 'nullable|string|max:100',
            'tanggal_sk_pembimbing' => 'nullable|date',
            'catatan_prodi' => 'nullable|string',
        ];
    }
}
