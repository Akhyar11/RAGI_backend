<?php

namespace App\Http\Requests\Siakad;

use Illuminate\Foundation\Http\FormRequest;

class StoreBahanKajianRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) return false;

        if ($user->isSuperAdmin() || $user->hasPermission('siakad.kurikulum.manage') || $user->hasPermission('siakad.master.manage')) {
            return true;
        }

        $prodiId = $this->input('program_studi_id');
        if (!$prodiId && $this->input('kurikulum_id')) {
            $kurikulum = \App\Models\Siakad\Kurikulum::find($this->input('kurikulum_id'));
            $prodiId = $kurikulum?->program_studi_id;
        }

        return $prodiId ? $user->canManageObeForProdi((int) $prodiId) : false;
    }

    public function rules(): array
    {
        $unique = 'unique:siakad_bahan_kajian,kode_bk';
        if ($this->route('id')) {
            $unique .= ',' . $this->route('id');
        }

        return [
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'kurikulum_id' => 'nullable|exists:siakad_kurikulum,id',
            'koordinator_id' => 'nullable|exists:siakad_dosen,id',
            'kode_bk' => ['required', 'string', 'max:50', $unique],
            'nama_bk' => 'required|string|max:255',
            'deskripsi' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'kode_bk.required' => 'Kode bahan kajian wajib diisi.',
            'kode_bk.unique' => 'Kode bahan kajian sudah digunakan pada program studi ini.',
            'nama_bk.required' => 'Rumusan bahan kajian wajib diisi.',
        ];
    }
}
