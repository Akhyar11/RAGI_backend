<?php

namespace App\Http\Requests\Spmb;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreBiodataRequest extends FormRequest
{
    /**
     * Biodata diisi oleh calon mahasiswa pemilik akun (terproteksi auth:api).
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $pendaftaranId = \App\Models\Spmb\PendaftaranCalonMhs::where('user_id', $this->user()?->id)->value('id');

        return [
            'gelombang_id' => 'sometimes|nullable|exists:spmb_gelombang_penerimaan,id',
            'program_studi_id' => 'sometimes|nullable|exists:siakad_program_studi,id',
            'program_studi_pilihan2_id' => 'sometimes|nullable|exists:siakad_program_studi,id',
            'master_tipe_jalur_id' => 'sometimes|nullable|exists:core_master_tipe_jalur,id',
            'nama_lengkap' => 'sometimes|nullable|string|max:255',
            'nik' => [
                'sometimes', 'nullable', 'string', 'max:20',
                Rule::unique('spmb_pendaftaran_calon_mhs', 'nik')->ignore($pendaftaranId),
            ],
            'kewarganegaraan_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('spmb_master_referensi', 'id')
                    ->where('tipe', 'kewarganegaraan')
                    ->where('is_active', true),
            ],
            'nomor_paspor' => [
                'sometimes', 'nullable', 'string', 'max:50',
                Rule::unique('spmb_pendaftaran_calon_mhs', 'nomor_paspor')->ignore($pendaftaranId),
            ],
            'negara_penerbit_paspor' => 'sometimes|nullable|string|max:100',
            'negara_kewarganegaraan' => 'sometimes|nullable|string|max:100',
            'kode_negara_kewarganegaraan' => 'sometimes|nullable|string|size:2',
            'tanggal_lahir' => 'sometimes|nullable|date',
            'tempat_lahir' => 'sometimes|nullable|string',
            'jenis_kelamin' => 'sometimes|nullable|string|max:10',
            'agama' => 'sometimes|nullable|string',
            'status_sipil' => 'sometimes|nullable|string|max:50',
            'kewarganegaraan' => 'sometimes|nullable|string',
            'no_hp' => 'sometimes|nullable|string',
            'alamat' => 'sometimes|nullable|string',
            'negara_domisili' => 'sometimes|nullable|string|max:100',
            'provinsi' => 'sometimes|nullable|string',
            'kota_kabupaten' => 'sometimes|nullable|string',
            'kecamatan' => 'sometimes|nullable|string',
            'kode_pos' => 'sometimes|nullable|string',
            'asal_sekolah' => 'sometimes|nullable|string',
            'alamat_sekolah' => 'sometimes|nullable|string',
            'npsn_sekolah' => 'sometimes|nullable|string',
            'jurusan_sekolah' => 'sometimes|nullable|string',
            'tahun_lulus' => 'sometimes|nullable|string',
            'nilai_rata_rapor' => 'sometimes|nullable|numeric',
            'nama_ayah' => 'sometimes|nullable|string',
            'pekerjaan_ayah' => 'sometimes|nullable|string',
            'nama_ibu' => 'sometimes|nullable|string',
            'pekerjaan_ibu' => 'sometimes|nullable|string',
            'penghasilan_ortu' => 'sometimes|nullable|string',
            'nama_ortu' => 'sometimes|nullable|string',
            'alamat_ortu' => 'sometimes|nullable|string',
            'telp_ortu' => 'sometimes|nullable|string',
            'nama_wali' => 'sometimes|nullable|string',
            'telepon_wali' => 'sometimes|nullable|string',
            'asal_lulusan' => 'sometimes|nullable|string|max:20',
            'asal_pt' => 'sometimes|nullable|string',
            'jenis_pt' => 'sometimes|nullable|string',
            'alamat_pt' => 'sometimes|nullable|string',
            'jenjang_pt' => 'sometimes|nullable|string',
            'progdi_pt' => 'sometimes|nullable|string',
            'ipk_pt' => 'sometimes|nullable|string',
            'nim_pt' => 'sometimes|nullable|string',
            'tahun_lulus_pt' => 'sometimes|nullable|string',
            'used_referral_code' => 'sometimes|nullable|string|max:50',
            'kode_diskon' => 'sometimes|nullable|string|max:50',
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if (!$this->filled('kewarganegaraan_id')) return;

            $reference = DB::table('spmb_master_referensi')
                ->where('tipe', 'kewarganegaraan')
                ->where('is_active', true)
                ->find($this->integer('kewarganegaraan_id'));

            if (!$reference) {
                $validator->errors()->add('kewarganegaraan_id', 'Kewarganegaraan tidak tersedia.');
                return;
            }

            $identityErrors = app(\App\Services\Spmb\SpmbApplicantIdentityValidator::class)
                ->errors($this->all(), (string) $reference->kode);
            foreach ($identityErrors as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        }];
    }
}
