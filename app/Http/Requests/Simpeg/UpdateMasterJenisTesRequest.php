<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMasterJenisTesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->hasPermission('simpeg.kompetensi.manage') || $user->isAdmin());
    }

    public function rules(): array
    {
        $id = $this->route('jenis_te') ?? $this->route('jenis_tes') ?? $this->route('id');

        return [
            'nama' => 'sometimes|required|string|max:150',
            'kode' => 'sometimes|required|string|max:50|unique:simpeg_master_jenis_tes,kode,' . $id,
            'kategori' => 'sometimes|required|string|max:50',
            'skor_min' => 'sometimes|required|numeric|min:0',
            'skor_max' => 'sometimes|required|numeric|gte:skor_min',
            'deskripsi' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama jenis tes wajib diisi.',
            'kode.required' => 'Kode jenis tes wajib diisi.',
            'kode.unique' => 'Kode jenis tes sudah digunakan.',
            'kategori.required' => 'Kategori tes wajib diisi.',
            'skor_min.required' => 'Skor minimal wajib diisi.',
            'skor_max.required' => 'Skor maksimal wajib diisi.',
            'skor_max.gte' => 'Skor maksimal harus lebih besar atau sama dengan skor minimal.',
        ];
    }
}
