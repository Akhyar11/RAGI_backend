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
            || $user->hasPermission('spmb.manage')
            || (method_exists($user, 'isObeAdmin') && $user->isObeAdmin())
            || $user->hasPermission('siakad.kelas.manage')
            || $user->hasPermission('siakad.kurikulum.read')
            || $user->hasPermission('siakad.kurikulum.manage')
            || $user->hasPermission('siakad.krs.read')
            || $user->hasPermission('siakad.krs.approve')
            || $user->hasPermission('siakad.master.manage')
            || $user->hasPermission('siakad.konversi.manage')
            || $user->hasPermission('siakad.beasiswa.manage')
            || $user->hasPermission('siakad.dashboard.read')
            || $user->hasPermission('siakad.kelas.read')
            || $user->hasPermission('siakad.dosen.manage')
            || $user->hasPermission('siakad.mahasiswa.read')
            || $user->hasPermission('siakad.mahasiswa.manage');
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
