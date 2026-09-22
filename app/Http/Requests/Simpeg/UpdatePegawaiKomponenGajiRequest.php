<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePegawaiKomponenGajiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && (
            $user->hasPermission('simpeg.payroll.manage')
            || $user->hasPermission('simpeg.pegawai.manage')
            || $user->hasPermission('simpeg.pegawai.create')
            || $user->hasPermission('simpeg.pegawai.update')
            || $user->hasRole('superadmin')
            || $user->hasRole('admin')
        );
    }

    public function rules(): array
    {
        return [
            'komponen' => 'required|array',
            'komponen.*.komponen_gaji_id' => 'required|exists:simpeg_master_komponen_gaji,id',
            'komponen.*.nominal_kustom' => 'nullable|numeric|min:0',
            'komponen.*.nilai_kustom' => 'nullable|numeric|min:0',
            'komponen.*.is_active' => 'boolean',
            'komponen.*.catatan' => 'nullable|string',
        ];
    }
}
