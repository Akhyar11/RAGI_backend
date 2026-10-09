<?php

namespace App\Http\Requests\Siakad;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validasi penyimpanan Rumusan CPMK Program Studi.
 *
 * Otorisasi memakai prodi aktif user, bukan permission global, agar Tim
 * Kurikulum / Kaprodi yang terdaftar di `siakad_admin_prodi` tetap dapat
 * mengelola rumusan program studinya.
 */
class StoreCpmkProdiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) return false;

        if ($user->isSuperAdmin() || $user->hasPermission('siakad.kurikulum.manage') || $user->hasPermission('siakad.master.manage')) {
            return true;
        }

        $prodiId = $this->resolveProdiId();
        return $prodiId ? $user->canManageObeForProdi($prodiId) : false;
    }

    /**
     * Program studi pemilik: berasal dari kurikulum pada saat create, sedangkan
     * pada saat update memakai kurikulum yang tersimpan agar user tidak bisa
     * memindahkan rumusan ke prodi lain hanya dengan mengganti `kurikulum_id`.
     */
    private function resolveProdiId(): ?int
    {
        $id = $this->route('id');
        if ($id) {
            $existing = \App\Models\Siakad\CpmkProdi::find($id);
            $kurikulumId = $existing?->kurikulum_id ?? $this->input('kurikulum_id');
        } else {
            $kurikulumId = $this->input('kurikulum_id');
        }

        if (!$kurikulumId) {
            return null;
        }

        $kurikulum = \App\Models\Siakad\Kurikulum::find($kurikulumId);
        return $kurikulum?->program_studi_id !== null ? (int) $kurikulum->program_studi_id : null;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'kurikulum_id' => 'required|exists:siakad_kurikulum,id',
            'cpl_id' => 'required|exists:siakad_cpl,id',
            'kode_cpmk' => [
                'required',
                'string',
                'max:50',
                // Unik per kurikulum, mengikuti unique index tabel.
                \Illuminate\Validation\Rule::unique('siakad_cpmk_prodi', 'kode_cpmk')
                    ->where('kurikulum_id', $this->input('kurikulum_id'))
                    ->ignore($id),
            ],
            'deskripsi' => 'required|string|max:2000',
        ];
    }

    /**
     * Setelah validasi dasar, pastikan CPL berada pada program studi yang sama
     * dengan kurikulum. Tanpa ini, user bisa menautkan CPMK ke CPL prodi lain
     * hanya dengan mengirim `cpl_id` milik prodi tersebut.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $kurikulum = \App\Models\Siakad\Kurikulum::find($this->input('kurikulum_id'));
            $cpl = \App\Models\Siakad\Cpl::find($this->input('cpl_id'));

            if (!$kurikulum || !$cpl) {
                return;
            }

            if ((int) $kurikulum->program_studi_id !== (int) $cpl->program_studi_id) {
                $validator->errors()->add(
                    'cpl_id',
                    'CPL yang dipilih bukan milik program studi pada kurikulum tersebut.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'kurikulum_id.required' => 'Kurikulum wajib dipilih.',
            'kurikulum_id.exists' => 'Kurikulum yang dipilih tidak ditemukan.',
            'cpl_id.required' => 'CPL Prodi wajib dipilih.',
            'cpl_id.exists' => 'CPL yang dipilih tidak ditemukan.',
            'kode_cpmk.required' => 'Kode CPMK wajib diisi.',
            'kode_cpmk.unique' => 'Kode CPMK sudah digunakan pada kurikulum ini.',
            'kode_cpmk.max' => 'Kode CPMK maksimal 50 karakter.',
            'deskripsi.required' => 'Rumusan CPMK wajib diisi.',
            'deskripsi.max' => 'Rumusan CPMK maksimal 2000 karakter.',
        ];
    }
}