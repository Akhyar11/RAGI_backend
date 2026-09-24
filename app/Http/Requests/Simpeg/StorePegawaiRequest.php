<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class StorePegawaiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('simpeg.pegawai.create') || $this->user()->hasPermission('simpeg.pegawai.manage');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id' => 'nullable|exists:core_users,id|unique:simpeg_pegawai,user_id',
            'unit_kerja_id' => 'nullable|exists:simpeg_unit_kerja,id',
            'nip' => 'required|string|unique:simpeg_pegawai,nip',
            'nidn' => 'required|string|unique:simpeg_pegawai,nidn',
            'nuptk' => 'required|string|unique:simpeg_pegawai,nuptk',
            'nik' => 'nullable|string|unique:simpeg_pegawai,nik',
            'nama_lengkap' => 'required|string|max:255',
            'gelar_depan' => 'nullable|string|max:50',
            'gelar_belakang' => 'nullable|string|max:50',
            'email' => 'nullable|email|unique:core_users,email',
            'username' => 'nullable|string|unique:core_users,username',
            'tanggal_lahir' => 'nullable|date',
            'tempat_lahir' => 'nullable|string|max:100',
            'jenis_kelamin' => 'nullable|string|max:10',
            'agama' => 'nullable|string|max:50',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:core_roles,id',
            'jenis_pegawai' => 'nullable|string',
            'status_kepegawaian' => 'nullable|string|max:50',
            'tanggal_masuk' => 'required|date',
            'status' => 'nullable|string|max:50',
            'telepon' => 'nullable|string|max:30',
            'alamat' => 'nullable|string',
            'shift_template_id' => 'required|exists:simpeg_shift_templates,id',
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
            'tanggal_masuk' => 'Tanggal Masuk',
            'shift_template_id' => 'Shift Kerja (Jadwal Presensi)',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'nip.required' => 'NIP wajib diisi.',
            'nip.unique' => 'NIP sudah terdaftar pada sistem.',
            'nidn.required' => 'NIDN wajib diisi.',
            'nidn.unique' => 'NIDN sudah terdaftar pada sistem.',
            'nuptk.required' => 'NUPTK wajib diisi.',
            'nuptk.unique' => 'NUPTK sudah terdaftar pada sistem.',
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'tanggal_masuk.required' => 'Tanggal masuk wajib diisi.',
            'tanggal_masuk.date' => 'Format tanggal masuk tidak valid.',
            'shift_template_id.required' => 'Shift Kerja (Jadwal Presensi) wajib dipilih.',
            'shift_template_id.exists' => 'Shift Kerja yang dipilih tidak valid atau tidak ditemukan.',
        ];
    }
}
