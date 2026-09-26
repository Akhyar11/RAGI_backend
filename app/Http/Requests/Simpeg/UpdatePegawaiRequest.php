<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePegawaiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $pegawaiId = $this->route('id') ?? $this->route('pegawai');
        $pegawai = \App\Models\Simpeg\Pegawai::find($pegawaiId);

        if (!$this->user()->hasPermission('simpeg.pegawai.update') && !$this->user()->hasPermission('simpeg.pegawai.manage')) {
            if (!$pegawai || $pegawai->user_id !== $this->user()->id) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $id = $this->route('id') ?? $this->route('pegawai');

        return [
            'user_id' => 'nullable|exists:core_users,id|unique:simpeg_pegawai,user_id,' . $id,
            'unit_kerja_id' => 'nullable|exists:simpeg_unit_kerja,id',
            'nip' => 'nullable|string|unique:simpeg_pegawai,nip,' . $id,
            'nidn' => 'nullable|string|unique:simpeg_pegawai,nidn,' . $id,
            'nuptk' => 'nullable|string|unique:simpeg_pegawai,nuptk,' . $id,
            'nik' => 'nullable|string|unique:simpeg_pegawai,nik,' . $id,
            'nama_lengkap' => 'sometimes|string|max:255',
            'gelar_depan' => 'nullable|string|max:50',
            'gelar_belakang' => 'nullable|string|max:50',
            'tanggal_lahir' => 'nullable|date',
            'tempat_lahir' => 'nullable|string|max:100',
            'jenis_kelamin' => 'sometimes|string|max:10',
            'agama' => 'nullable|string|max:50',
            'role_ids' => 'sometimes|array',
            'role_ids.*' => 'exists:core_roles,id',
            'jenis_pegawai' => 'nullable|string',
            'status_kepegawaian' => 'sometimes|string|max:50',
            'tanggal_masuk' => 'nullable|date',
            'status' => 'sometimes|string|max:50',
            'telepon' => 'nullable|string|max:30',
            'nama_bank' => 'nullable|string|max:50',
            'nomor_rekening' => 'nullable|string|max:50',
            'nama_rekening' => 'nullable|string|max:100',
            'alamat' => 'nullable|string',
            'shift_template_id' => 'nullable|exists:simpeg_shift_templates,id',
            'office_location_id' => 'nullable|exists:simpeg_office_locations,id',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'nip' => 'NIP',
            'nidn' => 'NIDN',
            'nuptk' => 'NUPTK',
            'nama_lengkap' => 'Nama Lengkap',
            'shift_template_id' => 'Shift Kerja (Jadwal Presensi)',
        ];
    }
}
