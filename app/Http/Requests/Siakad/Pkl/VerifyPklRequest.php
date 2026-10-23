<?php

namespace App\Http\Requests\Siakad\Pkl;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPklRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('siakad.pkl.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:disetujui,ditolak,sedang_berjalan,laporan_selesai',
            'dosen_pembimbing_id' => 'nullable|exists:siakad_dosen,id',
            'catatan_penolakan' => 'nullable|required_if:status,ditolak|string',
        ];
    }
}
