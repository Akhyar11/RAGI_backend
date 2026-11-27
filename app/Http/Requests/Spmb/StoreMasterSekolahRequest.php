<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;

class StoreMasterSekolahRequest extends FormRequest
{
    /**
     * Admin SPMB atau pengguna yang berhak mengisi formulir pendaftaran (terproteksi auth:api).
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user && ($user->hasPermission('spmb.manage') || $user->hasPermission('spmb.pendaftaran.create'));
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'jenis' => ['nullable', 'string', 'max:50'],
        ];
    }
}
