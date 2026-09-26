<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class StoreMasterGolonganPangkatRequest extends FormRequest
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
            $user->hasPermission('simpeg.create') ||
            $user->hasPermission('simpeg.jabatan.manage') ||
            $user->hasPermission('simpeg.pegawai.manage')
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'kode' => 'required|string|max:20|unique:simpeg_master_golongan_pangkat,kode',
            'nama' => 'required|string|max:100',
            'pangkat' => 'nullable|string|max:100',
            'ruang' => 'nullable|string|max:10',
            'urutan' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'kode' => 'Kode Golongan',
            'nama' => 'Nama Pangkat / Golongan',
            'pangkat' => 'Pangkat',
            'ruang' => 'Ruang',
            'urutan' => 'Urutan Tampilan',
            'is_active' => 'Status Aktif',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'kode.required' => 'Kode golongan (misal: III/a) wajib diisi.',
            'kode.unique' => 'Kode golongan sudah terdaftar.',
            'nama.required' => 'Nama jenjang pangkat/golongan wajib diisi.',
        ];
    }
}
