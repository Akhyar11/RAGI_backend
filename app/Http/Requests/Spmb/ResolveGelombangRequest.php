<?php

namespace App\Http\Requests\Spmb;

use App\Models\Role;
use App\Services\Spmb\SpmbGelombangResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveGelombangRequest extends FormRequest
{
    /** Admin SPMB (spmb.manage) atau calon mahasiswa yang sedang mendaftar. */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ($user->hasPermission('spmb.manage') || $user->hasRole(Role::SLUG_CALON_MAHASISWA));
    }

    public function rules(): array
    {
        return [
            'jalur_masuk_id' => ['required', 'integer', 'exists:spmb_jalur_masuk,id'],
            'konteks' => ['sometimes', Rule::in([SpmbGelombangResolver::KONTEKS_MANUAL, SpmbGelombangResolver::KONTEKS_ONLINE])],
        ];
    }

    public function messages(): array
    {
        return [
            'jalur_masuk_id.required' => 'Jalur pendaftaran wajib dipilih.',
            'jalur_masuk_id.exists' => 'Jalur pendaftaran tidak ditemukan.',
        ];
    }
}
