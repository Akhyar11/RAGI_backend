<?php

namespace App\Http\Requests\Arsip;

use Illuminate\Foundation\Http\FormRequest;

class CheckNomorSuratRequest extends FormRequest
{
    /**
     * Menentukan apakah pengguna diizinkan melakukan request ini.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        return $user->isAdmin()
            || $user->hasPermission('arsip.nomor_surat.read')
            || $user->hasPermission('spmb.manage');
    }

    /**
     * Aturan validasi input.
     */
    public function rules(): array
    {
        return [
            'nomor_surat' => 'required_without:nomor_sk|nullable|string|max:200',
            'nomor_sk' => 'required_without:nomor_surat|nullable|string|max:200',
        ];
    }

    /**
     * Pesan validasi khusus.
     */
    public function messages(): array
    {
        return [
            'nomor_surat.required_without' => 'Parameter nomor surat atau nomor SK wajib diisi.',
            'nomor_sk.required_without' => 'Parameter nomor surat atau nomor SK wajib diisi.',
        ];
    }
}
