<?php

namespace App\Http\Requests\Siakad;

use App\Rules\MasterReferensiExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreCplRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) return false;

        if ($user->isSuperAdmin() || $user->hasRole('admin') || $user->hasRole('admin_siakad') || $user->hasPermission('siakad.master.manage')) {
            return true;
        }

        $prodiId = $this->input('program_studi_id');
        if ($prodiId && $user->canManageObeForProdi((int) $prodiId)) {
            return true;
        }

        $kurikulumId = $this->input('kurikulum_id');
        if ($kurikulumId) {
            $kur = \App\Models\Siakad\Kurikulum::find($kurikulumId);
            if ($kur && $kur->program_studi_id && $user->canManageObeForProdi((int) $kur->program_studi_id)) {
                return true;
            }
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'kurikulum_id' => 'nullable|exists:siakad_kurikulum,id',
            'jenis_cpl_id' => 'nullable|exists:siakad_jenis_cpl,id',
            'kode_cpl' => 'required|string|max:50',
            'kategori' => ['required', 'string', new MasterReferensiExists('kategori_cpl')],
            'jenis_list' => 'nullable|array',
            'jenis_list.*' => 'string|max:50',
            'deskripsi' => 'required|string',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'kategori.required' => 'Kategori CPL wajib dipilih.',
        ];
    }
}
