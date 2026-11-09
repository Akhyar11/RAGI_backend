<?php

namespace App\Http\Requests\Siakad;

use App\Models\Siakad\KoreksiNilaiMahasiswa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class KoreksiNilaiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('siakad.nilai.koreksi.create') ?? false;
    }

    public function rules(): array
    {
        $type = $this->input('jenis_nilai');
        $isKonvensional = $type === KoreksiNilaiMahasiswa::JENIS_KONVENSIONAL;
        $isKomponenObe = $type === KoreksiNilaiMahasiswa::JENIS_KOMPONEN_OBE;
        $isCpmkObe = $type === KoreksiNilaiMahasiswa::JENIS_CPMK_OBE;

        return [
            'jenis_nilai' => [
                'required',
                Rule::in([
                    KoreksiNilaiMahasiswa::JENIS_KONVENSIONAL,
                    KoreksiNilaiMahasiswa::JENIS_KOMPONEN_OBE,
                    KoreksiNilaiMahasiswa::JENIS_CPMK_OBE,
                ]),
            ],
            'nilai_mahasiswa_id' => [
                Rule::requiredIf($isKonvensional),
                'nullable',
                'integer',
                'exists:siakad_nilai_mahasiswa,id',
            ],
            'nilai_komponen_mahasiswa_id' => [
                Rule::requiredIf($isKomponenObe),
                'nullable',
                'integer',
                'exists:siakad_nilai_komponen_mhs,id',
            ],
            'ketercapaian_cpmk_mahasiswa_id' => [
                Rule::requiredIf($isCpmkObe),
                'nullable',
                'integer',
                'exists:siakad_ketercapaian_cpmk_mhs,id',
            ],
            'nilai_baru' => ['required', 'array', 'min:1'],
            'nilai_baru.nilai_harian' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'nilai_baru.nilai_uts' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'nilai_baru.nilai_uas' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'nilai_baru.nilai_praktik' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'nilai_baru.nilai_angka' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'alasan_koreksi' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('jenis_nilai');
            $values = $this->input('nilai_baru', []);
            $targetFields = [
                'nilai_mahasiswa_id',
                'nilai_komponen_mahasiswa_id',
                'ketercapaian_cpmk_mahasiswa_id',
            ];
            $expectedTarget = match ($type) {
                KoreksiNilaiMahasiswa::JENIS_KONVENSIONAL => 'nilai_mahasiswa_id',
                KoreksiNilaiMahasiswa::JENIS_KOMPONEN_OBE => 'nilai_komponen_mahasiswa_id',
                KoreksiNilaiMahasiswa::JENIS_CPMK_OBE => 'ketercapaian_cpmk_mahasiswa_id',
                default => null,
            };
            foreach ($targetFields as $field) {
                if ($field !== $expectedTarget && $this->filled($field)) {
                    $validator->errors()->add($field, 'Pilih satu target nilai sesuai jenis koreksi.');
                }
            }

            $allowed = match ($type) {
                KoreksiNilaiMahasiswa::JENIS_KONVENSIONAL => ['nilai_harian', 'nilai_uts', 'nilai_uas', 'nilai_praktik'],
                KoreksiNilaiMahasiswa::JENIS_KOMPONEN_OBE,
                KoreksiNilaiMahasiswa::JENIS_CPMK_OBE => ['nilai_angka'],
                default => [],
            };

            if (!is_array($values) || array_diff(array_keys($values), $allowed) !== []) {
                $validator->errors()->add('nilai_baru', 'Field nilai baru tidak sesuai dengan jenis nilai yang dikoreksi.');
                return;
            }

            if ($type === KoreksiNilaiMahasiswa::JENIS_KONVENSIONAL && $values === []) {
                $validator->errors()->add('nilai_baru', 'Minimal satu komponen nilai harus dikoreksi.');
            }

            if (
                in_array($type, [KoreksiNilaiMahasiswa::JENIS_KOMPONEN_OBE, KoreksiNilaiMahasiswa::JENIS_CPMK_OBE], true)
                && !array_key_exists('nilai_angka', $values)
            ) {
                $validator->errors()->add('nilai_baru.nilai_angka', 'Nilai angka wajib diisi.');
            }
        });
    }
}