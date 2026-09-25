<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;

class ValidateReferralCodeRequest extends FormRequest
{
    /**
     * Endpoint validasi kode referral bersifat publik agar dapat dipakai
     * pada form registrasi akun.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50',
        ];
    }
}
