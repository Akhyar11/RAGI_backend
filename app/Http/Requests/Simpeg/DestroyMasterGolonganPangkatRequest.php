<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class DestroyMasterGolonganPangkatRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && (
            $user->isAdmin() ||
            $user->hasRole('admin') ||
            $user->hasPermission('simpeg.delete') ||
            $user->hasPermission('simpeg.jabatan.manage') ||
            $user->hasPermission('simpeg.pegawai.manage')
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [];
    }
}
