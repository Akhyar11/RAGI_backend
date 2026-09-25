<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;

class LabBhpTransaksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jenis_transaksi' => 'required|in:masuk,keluar',
            'jumlah' => 'required|numeric|min:0.01',
            'tanggal' => 'nullable|date',
            'keterangan' => 'nullable|string',
        ];
    }
}
