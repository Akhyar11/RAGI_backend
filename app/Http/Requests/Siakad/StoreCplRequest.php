<?php

namespace App\Http\Requests\Siakad;

use App\Rules\MasterReferensiExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreCplRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return (bool) ($user && ($user->isSuperAdmin() || $user->hasRole('admin') || $user->hasRole('kaprodi') || $user->hasRole('wakil_prodi')));
    }

    public function rules(): array
    {
        return [
            'program_studi_id' => 'required|exists:siakad_program_studi,id',
            'kode_cpl' => 'required|string|max:50',
            'kategori' => ['required', 'string', new MasterReferensiExists('kategori_cpl')],
            'deskripsi' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'kategori.required' => 'Kategori CPL wajib dipilih.',
        ];
    }
}
