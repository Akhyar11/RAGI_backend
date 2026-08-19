<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;

class AsetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $asetId = $this->route('aset') ? $this->route('aset')->id : null;

        return [
            'kategori_id' => 'required|exists:kategori_aset,id',
            'ruangan_id' => 'nullable|exists:ruangan,id',
            'kode_aset' => 'required|string|max:100|unique:aset,kode_aset,' . $asetId,
            'nama' => 'required|string|max:150',
            'merk' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'tanggal_perolehan' => 'nullable|date',
            'harga_perolehan' => 'required|numeric|min:0',
            'nilai_buku' => 'nullable|numeric|min:0',
            'kondisi' => 'required|in:baik,rusak_ringan,rusak_berat',
            'status' => 'required|in:tersedia,dipinjam,maintenance,dihapuskan',
        ];
    }
}
