<?php

namespace App\Http\Requests\Sikeu;

use App\Services\Sikeu\SaldoAwalService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSaldoAwalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('sikeu.akuntansi.manage');
    }

    public function rules(): array
    {
        return [
            'tanggal' => 'required|date',
            'keterangan' => 'nullable|string|max:1000',
            'details' => 'required|array|min:1',
            'details.*.akun_id' => [
                'required',
                'integer',
                Rule::exists('sikeu_akun_keuangan', 'id')
                    ->whereIn('kelompok', SaldoAwalService::KELOMPOK_VALID)
                    ->where('is_active', true),
            ],
            'details.*.debet' => 'required|numeric|min:0',
            'details.*.kredit' => 'required|numeric|min:0',
            'details.*.keterangan' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal.required' => 'Tanggal saldo awal wajib diisi.',
            'tanggal.date' => 'Format tanggal saldo awal tidak valid.',
            'details.required' => 'Tambahkan minimal satu baris akun saldo awal.',
            'details.min' => 'Tambahkan minimal satu baris akun saldo awal.',
            'details.*.akun_id.required' => 'Akun wajib dipilih pada setiap baris.',
            'details.*.akun_id.exists' => 'Akun tidak valid, tidak aktif, atau bukan akun neraca (aset/liabilitas/ekuitas).',
            'details.*.debet.numeric' => 'Nominal debet harus berupa angka.',
            'details.*.debet.min' => 'Nominal debet tidak boleh negatif.',
            'details.*.kredit.numeric' => 'Nominal kredit harus berupa angka.',
            'details.*.kredit.min' => 'Nominal kredit tidak boleh negatif.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $details = $this->input('details', []);
            $akunDipakai = [];
            $adaNominal = false;

            foreach ($details as $index => $row) {
                $debet = (float) ($row['debet'] ?? 0);
                $kredit = (float) ($row['kredit'] ?? 0);

                if ($debet == 0.0 && $kredit == 0.0) {
                    $validator->errors()->add(
                        "details.{$index}.debet",
                        'Setiap baris harus memiliki nominal debet atau kredit lebih dari nol.'
                    );
                    continue;
                }

                $adaNominal = true;
                $akunId = (int) ($row['akun_id'] ?? 0);
                if ($akunId > 0 && isset($akunDipakai[$akunId])) {
                    $validator->errors()->add(
                        "details.{$index}.akun_id",
                        'Akun yang sama tidak boleh muncul lebih dari satu baris.'
                    );
                }
                if ($akunId > 0) {
                    $akunDipakai[$akunId] = true;
                }
            }

            if (! $adaNominal) {
                $validator->errors()->add('details', 'Total saldo awal harus lebih dari nol.');
            }
        });
    }
}
