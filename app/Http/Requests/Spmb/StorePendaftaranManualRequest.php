<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Validation\Rule;

class StorePendaftaranManualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('spmb.manage');
    }

    protected function prepareForValidation(): void
    {
        $thirdPartyIds = $this->input('pihak_ketiga_user_ids', []);
        $thirdPartyIds = is_array($thirdPartyIds) ? $thirdPartyIds : [];

        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'nik' => preg_replace('/\s+/', '', (string) $this->input('nik')) ?: null,
            'pihak_ketiga_user_ids' => array_values(array_unique(array_filter($thirdPartyIds))),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', 'unique:core_users,email'],
            'gelombang_id' => ['required', 'integer', 'exists:spmb_gelombang_penerimaan,id'],
            'master_tipe_jalur_id' => ['required', 'integer', 'exists:core_master_tipe_jalur,id'],
            'program_studi_id' => [
                'required',
                'integer',
                Rule::exists('siakad_program_studi', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'program_studi_pilihan2_id' => [
                'nullable',
                'integer',
                'different:program_studi_id',
                Rule::exists('siakad_program_studi', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:20', 'unique:spmb_pendaftaran_calon_mhs,nik'],
            'kewarganegaraan_id' => ['required', 'integer', Rule::exists('spmb_master_referensi', 'id')->where('tipe', 'kewarganegaraan')->where('is_active', true)],
            'nomor_paspor' => ['nullable', 'string', 'max:50', 'unique:spmb_pendaftaran_calon_mhs,nomor_paspor'],
            'negara_penerbit_paspor' => ['nullable', 'string', 'max:100'],
            'negara_kewarganegaraan' => ['nullable', 'string', 'max:100'],
            'kode_negara_kewarganegaraan' => ['nullable', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'tanggal_lahir' => ['nullable', 'date', 'before:today'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'jenis_kelamin' => ['nullable', Rule::in(['L', 'P'])],
            'agama' => ['nullable', 'string', 'max:50'],
            'status_sipil' => ['nullable', 'string', 'max:50'],
            'kewarganegaraan' => ['nullable', 'string', 'max:50'],
            'negara_domisili' => ['required', 'string', 'max:100'],
            'no_hp' => ['required', 'string', 'max:30'],
            'alamat' => ['required', 'string', 'max:2000'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kota_kabupaten' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'kode_pos' => ['nullable', 'string', 'max:20'],
            'asal_sekolah' => ['nullable', 'string', 'max:255'],
            'alamat_sekolah' => ['nullable', 'string', 'max:1000'],
            'jurusan_sekolah' => ['nullable', 'string', 'max:100'],
            'tahun_lulus' => ['nullable', 'string', 'max:4'],
            'npsn_sekolah' => ['nullable', 'string', 'max:20'],
            'nilai_rata_rapor' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'nama_ayah' => ['nullable', 'string', 'max:255'],
            'pekerjaan_ayah' => ['nullable', 'string', 'max:100'],
            'nama_ibu' => ['nullable', 'string', 'max:255'],
            'pekerjaan_ibu' => ['nullable', 'string', 'max:100'],
            'penghasilan_ortu' => ['nullable', 'string', 'max:100'],
            'nama_ortu' => ['nullable', 'string', 'max:255'],
            'alamat_ortu' => ['nullable', 'string', 'max:1000'],
            'telp_ortu' => ['nullable', 'string', 'max:30'],
            'nama_wali' => ['nullable', 'string', 'max:255'],
            'telepon_wali' => ['nullable', 'string', 'max:30'],
            'asal_lulusan' => ['nullable', Rule::in(['sekolah', 'pt'])],
            'asal_pt' => ['nullable', 'string', 'max:255'],
            'jenis_pt' => ['nullable', 'string', 'max:100'],
            'alamat_pt' => ['nullable', 'string', 'max:255'],
            'jenjang_pt' => ['nullable', 'string', 'max:50'],
            'progdi_pt' => ['nullable', 'string', 'max:255'],
            'ipk_pt' => ['nullable', 'numeric', 'min:0', 'max:4'],
            'nim_pt' => ['nullable', 'string', 'max:30'],
            'tahun_lulus_pt' => ['nullable', 'string', 'max:4'],
            'info_daftar' => ['nullable', 'string', 'max:100'],
            'ket_info_daftar' => ['nullable', 'string', 'max:255'],
            'referrer_user_id' => [
                'nullable',
                'integer',
                Rule::exists('core_users', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->whereNotNull('referral_code')
                    ->where('referral_code', '!=', ''),
            ],
            'pihak_ketiga_user_ids' => ['nullable', 'array', 'max:20'],
            'pihak_ketiga_user_ids.*' => [
                'required',
                'integer',
                'distinct',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $roleSlug = SystemSetting::get('third_party_registration_role');
                    $role = $roleSlug ? Role::query()->where('slug', $roleSlug)->where('is_active', true)->first() : null;
                    $eligible = $role && User::query()->whereKey($value)->where('is_active', true)->whereHas(
                        'roles', fn ($query) => $query->where('core_roles.id', $role->id),
                    )->exists();
                    if (! $eligible) {
                        $fail('Akun pihak ketiga tidak sesuai dengan role yang dikonfigurasi.');
                    }
                },
            ],
            'bukti_transfer' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $reference = \App\Models\System\MasterReferensi::query()
                ->where('tipe', 'kewarganegaraan')
                ->where('is_active', true)
                ->find($this->integer('kewarganegaraan_id'));

            if (!$reference) return;

            $identityErrors = app(\App\Services\Spmb\SpmbApplicantIdentityValidator::class)
                ->errors($this->all(), (string) $reference->kode);
            foreach ($identityErrors as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        }];
    }
}
