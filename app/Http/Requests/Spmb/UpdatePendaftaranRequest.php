<?php

namespace App\Http\Requests\Spmb;

use App\Models\Role;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\System\MasterReferensi;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePendaftaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('spmb.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('nik')) {
            $this->merge(['nik' => preg_replace('/\s+/', '', (string) $this->input('nik')) ?: null]);
        }
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
        if ($this->has('pihak_ketiga_user_ids')) {
            $ids = $this->input('pihak_ketiga_user_ids');
            $this->merge(['pihak_ketiga_user_ids' => array_values(array_unique(array_filter(is_array($ids) ? $ids : [])))]);
        }
    }

    public function rules(): array
    {
        $id = (int) $this->route('id');
        $userId = PendaftaranCalonMhs::withTrashed()->whereKey($id)->value('user_id');
        $activeProdi = fn () => Rule::exists('siakad_program_studi', 'id')->where('is_active', true)->whereNull('deleted_at');

        return [
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('core_users', 'email')->ignore($userId)],
            'nama_lengkap' => ['sometimes', 'required', 'string', 'max:255'],
            'kewarganegaraan_id' => ['sometimes', 'nullable', 'integer', Rule::exists('spmb_master_referensi', 'id')->where('tipe', 'kewarganegaraan')->where('is_active', true)],
            'nomor_paspor' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('spmb_pendaftaran_calon_mhs', 'nomor_paspor')->ignore($id)],
            'negara_penerbit_paspor' => ['sometimes', 'nullable', 'string', 'max:100'],
            'negara_kewarganegaraan' => ['sometimes', 'nullable', 'string', 'max:100'],
            'kode_negara_kewarganegaraan' => ['sometimes', 'nullable', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'status_sipil' => ['sometimes', 'nullable', Rule::in(['menikah', 'belum_menikah'])],
            'negara_domisili' => ['sometimes', 'nullable', 'string', 'max:100'],
            'npsn_sekolah' => ['sometimes', 'nullable', 'string', 'max:20'],
            'nama_ayah' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pekerjaan_ayah' => ['sometimes', 'nullable', 'string', 'max:100'],
            'telepon_ayah' => ['sometimes', 'nullable', 'string', 'max:30'],
            'penghasilan_ayah' => ['sometimes', 'nullable', 'string', 'max:100'],
            'nama_ibu' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pekerjaan_ibu' => ['sometimes', 'nullable', 'string', 'max:100'],
            'telepon_ibu' => ['sometimes', 'nullable', 'string', 'max:30'],
            'penghasilan_ibu' => ['sometimes', 'nullable', 'string', 'max:100'],
            'nama_wali' => ['sometimes', 'nullable', 'string', 'max:255'],
            'telepon_wali' => ['sometimes', 'nullable', 'string', 'max:30'],
            'pekerjaan_wali' => ['sometimes', 'nullable', 'string', 'max:100'],
            'penghasilan_wali' => ['sometimes', 'nullable', 'string', 'max:100'],
            'asal_lulusan' => ['sometimes', 'nullable', Rule::in(['sekolah', 'pt'])],
            'asal_pt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'jenis_pt' => ['sometimes', 'nullable', 'string', 'max:100'],
            'alamat_pt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'jenjang_pt' => ['sometimes', 'nullable', 'string', 'max:50'],
            'progdi_pt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ipk_pt' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:4'],
            'nim_pt' => ['sometimes', 'nullable', 'string', 'max:30'],
            'tahun_lulus_pt' => ['sometimes', 'nullable', 'digits:4'],
            'referrer_user_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('core_users', 'id')->where('is_active', true)->whereNull('deleted_at')
                    ->whereNotNull('referral_code')->where('referral_code', '!=', ''),
            ],
            'pihak_ketiga_user_ids' => ['sometimes', 'nullable', 'array', 'max:20'],
            'pihak_ketiga_user_ids.*' => [
                'required', 'integer', 'distinct',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $roleSlug = SystemSetting::get('third_party_registration_role');
                    $role = $roleSlug ? Role::query()->where('slug', $roleSlug)->where('is_active', true)->first() : null;
                    $eligible = $role && User::query()->whereKey($value)->where('is_active', true)
                        ->whereHas('roles', fn ($query) => $query->where('core_roles.id', $role->id))->exists();
                    if (! $eligible) {
                        $fail('Akun pihak ketiga tidak sesuai dengan role yang dikonfigurasi.');
                    }
                },
            ],
            'nik' => ['sometimes', 'nullable', 'digits:16', Rule::unique('spmb_pendaftaran_calon_mhs', 'nik')->ignore($id)],
            'tempat_lahir' => ['sometimes', 'nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['sometimes', 'nullable', 'date', 'before:today'],
            'jenis_kelamin' => ['sometimes', 'nullable', Rule::in(['L', 'P'])],
            'agama' => ['sometimes', 'nullable', 'string', 'max:50'],
            'no_hp' => ['sometimes', 'nullable', 'string', 'max:30'],
            'alamat' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'provinsi' => ['sometimes', 'nullable', 'string', 'max:100'],
            'kota_kabupaten' => ['sometimes', 'nullable', 'string', 'max:100'],
            'kecamatan' => ['sometimes', 'nullable', 'string', 'max:100'],
            'kode_pos' => ['sometimes', 'nullable', 'string', 'max:20'],
            'asal_sekolah' => ['sometimes', 'nullable', 'string', 'max:255'],
            'alamat_sekolah' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'jurusan_sekolah' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tahun_lulus' => ['sometimes', 'nullable', 'digits:4'],
            'nama_ortu' => ['sometimes', 'nullable', 'string', 'max:255'],
            'alamat_ortu' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'telp_ortu' => ['sometimes', 'nullable', 'string', 'max:30'],
            'pekerjaan_ortu' => ['sometimes', 'nullable', 'string', 'max:100'],
            'info_daftar' => ['sometimes', 'nullable', 'string', 'max:100'],
            'ket_info_daftar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'program_studi_id' => ['sometimes', 'required', 'integer', $activeProdi()],
            'program_studi_pilihan2_id' => ['sometimes', 'nullable', 'integer', 'different:program_studi_id', $activeProdi()],
            'gelombang_id' => ['sometimes', 'nullable', 'integer', 'exists:spmb_gelombang_penerimaan,id'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $referenceId = $this->input('kewarganegaraan_id');
            if (! $referenceId) {
                return;
            }
            $code = strtoupper((string) MasterReferensi::query()->whereKey($referenceId)->value('kode'));
            if ($code === 'WNA') {
                $required = [
                    'nomor_paspor' => 'Nomor paspor wajib diisi untuk WNA.',
                    'negara_penerbit_paspor' => 'Negara penerbit paspor wajib diisi untuk WNA.',
                    'negara_kewarganegaraan' => 'Negara kewarganegaraan wajib diisi untuk WNA.',
                    'kode_negara_kewarganegaraan' => 'Kode negara wajib 2 huruf untuk WNA.',
                ];
                foreach ($required as $field => $message) {
                    if (! $this->filled($field)) {
                        $validator->errors()->add($field, $message);
                    }
                }
                if ($this->filled('nik')) {
                    $validator->errors()->add('nik', 'NIK tidak berlaku untuk WNA; kosongkan NIK.');
                }
            }
        }];
    }
}
